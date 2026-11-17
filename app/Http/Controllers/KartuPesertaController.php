<?php

namespace App\Http\Controllers;

use App\Models\PaketSoal;
use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Support\Pengguna;
use App\Support\Referensi;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Menu "Kartu Peserta" — cetak kartu peserta ujian.
 *
 * Satu kartu untuk satu siswa, memuat seluruh jadwal terpilih yang
 * diikutinya, bukan satu kartu per jadwal: nomor peserta adalah NIS siswa dan
 * akunnya sama untuk semua ujian, jadi pada pekan PAS dengan sepuluh mapel
 * cukup satu kartu per siswa.
 *
 * Kata sandi tidak dicetak. Aplikasi ini memang tidak pernah mengetahuinya:
 * siswa masuk memakai akun Data Center, yang kata sandinya tersimpan sebagai
 * hash.
 */
class KartuPesertaController extends Controller
{
    public function index(Request $r)
    {
        $r->validate([
            'ujian_id' => 'nullable|array',
            'ujian_id.*' => 'integer',
            'rombongan_belajar_id' => 'nullable|integer',
            'judul' => 'nullable|string|max:80',
            'saring' => 'nullable',
        ]);

        $daftarUjian = Ujian::query()
            ->when(Pengguna::guruId(), fn ($q, $guruId) => $q->where('guru_id', $guruId))
            ->with(['mataPelajaran', 'paketSoal'])
            ->orderBy('waktu_mulai')
            ->get();

        $terpilih = $this->jadwalTerpilih($r, $daftarUjian)->keyBy('id');
        $rombelId = $r->integer('rombongan_belajar_id') ?: null;

        // Kartu tanpa jadwal hanya ketika kolom jadwalnya memang dikosongkan.
        // Jadwal yang diminta tetapi tidak terjangkau — misalnya milik guru
        // lain — tidak boleh diam-diam berubah menjadi cetak satu sekolah.
        $tanpaJadwal = $r->has('saring') && ! $r->filled('ujian_id');

        return view('kartu-peserta.index', [
            'daftarUjian' => $daftarUjian,
            'terpilih' => $terpilih,
            'tanpaJadwal' => $tanpaJadwal,
            'kartuPerKelas' => $tanpaJadwal
                ? $this->kartuTanpaJadwal($rombelId)
                : $this->kartu($terpilih, $rombelId),
            'judul' => $r->filled('judul') ? $r->input('judul') : $this->judulBawaan($terpilih),
            'periode' => $this->periode($terpilih),
            'sekolah' => Sekolah::profil(),
        ]);
    }

    /**
     * Jadwal yang kartunya dicetak.
     *
     * Kolom jadwal boleh dikosongkan: sekolah kerap membagikan kartu sebelum
     * jadwalnya terbit, dan kartu tanpa tabel jadwal tetap berguna karena
     * nomor peserta serta username-nya sudah tercetak. Karena itu form ini
     * membawa penanda "saring": kiriman form dengan kolom jadwal kosong
     * memang berarti tanpa jadwal, sedangkan kunjungan pertama ke halaman
     * ini — yang tidak membawa penanda apa pun — tetap memakai bawaannya,
     * yaitu seluruh jadwal yang belum lewat.
     *
     * @param  Collection<int, Ujian>  $daftarUjian
     * @return Collection<int, Ujian>
     */
    protected function jadwalTerpilih(Request $r, Collection $daftarUjian): Collection
    {
        if ($r->filled('ujian_id')) {
            return $daftarUjian->whereIn('id', array_map('intval', $r->input('ujian_id')));
        }

        if ($r->has('saring')) {
            return $daftarUjian->take(0);
        }

        return $daftarUjian->filter(fn (Ujian $u) => $u->status !== Ujian::SELESAI && ! $u->sudah_lewat);
    }

    /**
     * Kartu tanpa jadwal, mis. dicetak sebelum jadwal ujian terbit.
     *
     * Pesertanya tidak bisa datang dari tabel peserta ujian — belum ada
     * ujiannya — jadi daftarnya diambil langsung dari penempatan kelas di
     * Data Center, disaring sama seperti Sinkron Peserta: hanya siswa aktif
     * pada tahun ajaran aktif. Guru hanya memperoleh kelas yang diampunya,
     * mengikuti daftar kelas yang sama dengan yang tampil di penyaring.
     *
     * @return Collection<string, Collection<int, object>>
     */
    protected function kartuTanpaJadwal(?int $rombelId): Collection
    {
        $rombel = Referensi::rombel(Pengguna::guruId());

        if ($rombelId) {
            $rombel = $rombel->where('id', $rombelId);
        }

        if ($rombel->isEmpty()) {
            return collect();
        }

        $penempatan = SiswaRombel::whereIn('rombongan_belajar_id', $rombel->pluck('id')->all())
            ->when(Referensi::tahunAjaranAktif()?->id, fn ($q, $tahunId) => $q->where('tahun_ajaran_id', $tahunId))
            ->pluck('rombongan_belajar_id', 'siswa_id')
            ->map('intval');

        $siswa = Siswa::aktif()->whereIn('id', $penempatan->keys()->all() ?: [0])->get()->keyBy('id');
        $namaKelas = $rombel->pluck('nama_rombel', 'id');

        return $this->kelompokkan(
            $penempatan
                ->filter(fn ($idRombel, $idSiswa) => $siswa->has($idSiswa))
                ->map(fn ($idRombel, $idSiswa) => (object) [
                    'siswa' => $siswa[$idSiswa],
                    'kelas' => $namaKelas[$idRombel] ?? null,
                    'nomor' => $siswa[$idSiswa]->nis,
                    'jadwal' => collect(),
                ])
        );
    }

    /**
     * Kartu dikelompokkan per kelas — tiap kelas dicetak mulai halaman baru,
     * supaya tumpukannya bisa langsung diserahkan ke wali kelas.
     *
     * @param  Collection<int, Ujian>  $ujian
     * @return Collection<string, Collection<int, object>>
     */
    protected function kartu(Collection $ujian, ?int $rombelId): Collection
    {
        if ($ujian->isEmpty()) {
            return collect();
        }

        return UjianPeserta::with(['siswa', 'rombel'])
            ->whereIn('ujian_id', $ujian->keys())
            ->when($rombelId, fn ($q) => $q->where('rombongan_belajar_id', $rombelId))
            ->get()
            ->filter(fn (UjianPeserta $p) => $p->siswa)
            ->groupBy('siswa_id')
            ->map(function (Collection $baris) use ($ujian) {
                $p = $baris->first();

                return (object) [
                    'siswa' => $p->siswa,
                    'kelas' => $p->rombel?->nama_rombel,
                    'nomor' => $p->nomor_peserta ?: $p->siswa->nis,
                    'jadwal' => $baris->map(fn (UjianPeserta $b) => $ujian[$b->ujian_id])
                        ->sortBy('waktu_mulai')->values(),
                ];
            })
            ->pipe(fn (Collection $kartu) => $this->kelompokkan($kartu));
    }

    /**
     * Kartu diurutkan lalu dikelompokkan per kelas. Nama kelas diurutkan
     * alami: 7-2 sebelum 7-10.
     *
     * @param  Collection<int, object>  $kartu
     * @return Collection<string, Collection<int, object>>
     */
    protected function kelompokkan(Collection $kartu): Collection
    {
        return $kartu
            ->sortBy([
                fn ($a, $b) => strnatcasecmp((string) $a->kelas, (string) $b->kelas),
                fn ($a, $b) => strcasecmp($a->siswa->nama_siswa, $b->siswa->nama_siswa),
            ])
            ->groupBy(fn ($k) => $k->kelas ?? 'Tanpa kelas');
    }

    /**
     * Nama kegiatan di bawah judul kartu: nama ujiannya bila hanya satu,
     * jenis ujiannya (mis. Penilaian Akhir Semester) bila semua jadwal
     * terpilih sejenis, selain itu kosong.
     *
     * @param  Collection<int, Ujian>  $ujian
     */
    protected function judulBawaan(Collection $ujian): ?string
    {
        if ($ujian->count() === 1) {
            return $ujian->first()->nama_ujian;
        }

        $jenis = $ujian->map(fn (Ujian $u) => $u->paketSoal?->jenis_ujian)->unique();

        return $jenis->count() === 1 && $jenis->first()
            ? PaketSoal::JENIS_UJIAN[$jenis->first()] ?? $jenis->first()
            : null;
    }

    /** @param  Collection<int, Ujian>  $ujian */
    protected function periode(Collection $ujian): string
    {
        $semester = $ujian->pluck('semester')->filter()->unique();
        $tahun = $ujian->pluck('tahun_ajaran')->filter()->unique();

        return collect([
            $semester->count() === 1 ? 'Semester '.$semester->first() : null,
            $tahun->count() === 1 ? $tahun->first() : Referensi::namaTahunAjaranAktif(),
        ])->filter()->implode(' · ');
    }
}
