<?php

namespace App\Http\Controllers;

use App\Models\PaketSoal;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Models\UjianPeserta;
use App\Services\RegistrasiPesertaService;
use App\Support\ExcelExport;
use App\Support\Pengguna;
use App\Support\Referensi;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menu "Registrasi Ujian": menjadwalkan pelaksanaan sebuah paket soal untuk
 * kelas-kelas tertentu, lalu mendaftarkan siswanya sebagai peserta.
 */
class UjianController extends Controller
{
    public function __construct(protected RegistrasiPesertaService $registrasi) {}

    public function index(Request $r)
    {
        $items = $this->kueriDasar()
            ->with(['paketSoal', 'mataPelajaran'])
            ->withCount([
                'peserta',
                'peserta as peserta_selesai_count' => fn ($q) => $q->where('status', UjianPeserta::SELESAI),
            ])
            ->when($r->q, fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('nama_ujian', 'like', "%{$v}%")->orWhere('kode_ujian', 'like', "%{$v}%");
            }))
            ->when($r->status, fn ($q, $v) => $q->where('status', $v))
            ->when($r->mata_pelajaran_id, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
            ->orderByDesc('waktu_mulai')
            ->paginate(20)
            ->withQueryString();

        $stat = [
            'total' => $this->kueriDasar()->count(),
            'draft' => $this->kueriDasar()->where('status', Ujian::DRAFT)->count(),
            'aktif' => $this->kueriDasar()->where('status', Ujian::AKTIF)->count(),
            'selesai' => $this->kueriDasar()->where('status', Ujian::SELESAI)->count(),
        ];

        return view('ujian.index', compact('items', 'stat'));
    }

    public function create()
    {
        $item = new Ujian([
            'kode_ujian' => $this->kodeBaru(),
            'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
            'waktu_mulai' => now()->addDay()->setTime(7, 30),
            'waktu_selesai' => now()->addDay()->setTime(9, 30),
            'durasi_menit' => 90,
            'kkm' => 75,
            'token' => strtoupper(Str::random(6)),
            'tampilkan_hasil' => true,
            'status' => Ujian::DRAFT,
        ]);

        return view('ujian.form', [
            'item' => $item,
            'kelasTerpilih' => [],
            'daftarPaket' => $this->daftarPaket(),
        ]);
    }

    public function store(Request $r)
    {
        $ujian = DB::transaction(function () use ($r) {
            $ujian = Ujian::create($this->validasi($r) + ['guru_id' => Pengguna::guruId()]);
            $this->simpanKelas($ujian, $r->input('rombongan_belajar_id', []));

            return $ujian;
        });

        $hasil = $this->registrasi->sinkronkan($ujian);

        return redirect()->route('ujian.peserta', $ujian)
            ->with('success', "Ujian dibuat dan {$hasil['ditambah']} peserta terdaftar otomatis dari kelas yang dipilih.");
    }

    public function edit(Ujian $ujian)
    {
        return view('ujian.form', [
            'item' => $ujian,
            'kelasTerpilih' => $ujian->rombelIds(),
            'daftarPaket' => $this->daftarPaket(),
        ]);
    }

    public function update(Request $r, Ujian $ujian)
    {
        $data = $this->validasi($r, $ujian->id);

        // Mengganti paket soal setelah ada yang mengerjakan akan membuat
        // lembar jawaban peserta tidak lagi cocok dengan naskahnya.
        if ((int) $data['paket_soal_id'] !== $ujian->paket_soal_id && $this->sudahAdaYangMengerjakan($ujian)) {
            return back()->withInput()
                ->with('error', 'Paket soal tidak bisa diganti karena sudah ada peserta yang mengerjakan ujian ini.');
        }

        DB::transaction(function () use ($ujian, $data, $r) {
            $ujian->update($data);
            $this->simpanKelas($ujian, $r->input('rombongan_belajar_id', []));
        });

        $this->registrasi->sinkronkan($ujian);

        return redirect()->route('ujian.index')->with('success', 'Registrasi ujian diperbarui.');
    }

    public function destroy(Ujian $ujian)
    {
        if ($this->sudahAdaYangMengerjakan($ujian)) {
            return back()->with('error', 'Ujian tidak bisa dihapus karena sudah ada peserta yang mengerjakan.');
        }

        $ujian->delete();

        return back()->with('success', 'Registrasi ujian dihapus.');
    }

    public function hapusMassal(Request $r)
    {
        return $this->hapusBanyak($r, $this->kueriDasar(), fn (Ujian $u) => $this->sudahAdaYangMengerjakan($u)
            ? "Ujian \"{$u->nama_ujian}\" sudah ada peserta yang mengerjakan."
            : null, 'ujian');
    }

    /** Daftar peserta sebuah ujian + tombol sinkron ulang. */
    public function peserta(Request $r, Ujian $ujian)
    {
        $items = $ujian->peserta()
            ->with(['siswa', 'rombel'])
            ->when($r->q, fn ($q, $v) => $q->whereIn('siswa_id',
                Siswa::where('nama_siswa', 'like', "%{$v}%")
                    ->orWhere('nisn', 'like', "%{$v}%")->pluck('id')))
            ->when($r->rombongan_belajar_id, fn ($q, $v) => $q->where('rombongan_belajar_id', $v))
            ->when($r->status, fn ($q, $v) => $q->where('status', $v))
            // Nama siswa ada di database lain sehingga tidak bisa dipakai
            // ORDER BY; urutkan pakai kelas lalu nomor peserta yang tersimpan lokal.
            ->orderBy('rombongan_belajar_id')
            ->orderBy('nomor_peserta')
            ->paginate(30)
            ->withQueryString();

        return view('ujian.peserta', compact('ujian', 'items'));
    }

    public function sinkronPeserta(Ujian $ujian)
    {
        if ($ujian->rombelIds() === []) {
            return back()->with('error', 'Belum ada kelas peserta yang dipilih pada ujian ini.');
        }

        $hasil = $this->registrasi->sinkronkan($ujian);

        return back()->with('success',
            "Sinkron peserta selesai: {$hasil['ditambah']} ditambahkan, {$hasil['dicabut']} dicabut, total {$hasil['total']} peserta.");
    }

    /** Batalkan / aktifkan kembali keikutsertaan seorang peserta. */
    public function togglePeserta(Ujian $ujian, UjianPeserta $peserta)
    {
        abort_unless($peserta->ujian_id === $ujian->id, 404);

        $peserta->update([
            'status' => $peserta->status === UjianPeserta::DIBATALKAN
                ? UjianPeserta::TERDAFTAR
                : UjianPeserta::DIBATALKAN,
        ]);

        return back()->with('success', 'Status peserta diperbarui.');
    }

    /** Ubah status ujian: draft -> aktif -> selesai. */
    public function ubahStatus(Request $r, Ujian $ujian)
    {
        $r->validate(['status' => 'required|in:'.implode(',', array_keys(Ujian::STATUS))]);

        if ($r->status === Ujian::AKTIF && $ujian->paketSoal->detail()->count() === 0) {
            return back()->with('error', 'Paket soal ujian ini masih kosong. Isi butir soalnya terlebih dahulu.');
        }

        $ujian->update(['status' => $r->status]);

        return back()->with('success', 'Status ujian diubah menjadi '.Ujian::STATUS[$r->status].'.');
    }

    /** Buat token baru (mis. bocor sebelum ujian dimulai). */
    public function tokenBaru(Ujian $ujian)
    {
        $ujian->update(['token' => strtoupper(Str::random(6))]);

        return back()->with('success', 'Token ujian diperbarui menjadi '.$ujian->token.'.');
    }

    /** Kartu peserta / daftar hadir ke Excel. */
    public function exportPeserta(Ujian $ujian)
    {
        $peserta = $ujian->peserta()->with(['siswa', 'rombel'])->get()
            ->sortBy(fn ($p) => [$p->rombel->nama_rombel ?? '', $p->siswa->nama_siswa ?? '']);

        $rows = $peserta->values()->map(fn ($p, $i) => [
            $i + 1,
            $p->siswa->nisn ?? '-',
            $p->nomor_peserta ?: '-',
            $p->siswa->nama_siswa ?? '-',
            $p->siswa->jenis_kelamin ?? '-',
            $p->rombel->nama_rombel ?? '-',
            $p->status_label,
            '', // kolom tanda tangan diisi manual saat ujian
        ]);

        return ExcelExport::make('Daftar Peserta')
            ->judul(
                'DAFTAR HADIR PESERTA UJIAN',
                $ujian->nama_ujian.' ('.$ujian->kode_ujian.')',
                'Waktu: '.$ujian->waktu_mulai->format('d/m/Y H:i').' — '.$ujian->waktu_selesai->format('H:i'),
                'Token: '.($ujian->token ?: 'tanpa token')
            )
            ->header(['No', 'NISN', 'No. Peserta', 'Nama Siswa', 'L/P', 'Kelas', 'Status', 'Tanda Tangan'])
            ->rows($rows)
            ->unduh('peserta-'.Str::slug($ujian->kode_ujian).'.xlsx');
    }

    /** @return Collection<int, PaketSoal> */
    protected function daftarPaket()
    {
        return PaketSoal::aktif()
            ->when(Pengguna::guruId(), fn ($q, $g) => $q->where('guru_id', $g))
            ->withCount('detail')
            ->orderBy('nama_paket')
            ->get();
    }

    protected function kueriDasar()
    {
        return Ujian::query()
            ->when(Pengguna::guruId(), fn ($q, $guruId) => $q->where('guru_id', $guruId));
    }

    protected function sudahAdaYangMengerjakan(Ujian $ujian): bool
    {
        return $ujian->peserta()
            ->whereIn('status', [UjianPeserta::MULAI, UjianPeserta::SELESAI])
            ->exists();
    }

    /** @param array<int, int|string> $rombelIds */
    protected function simpanKelas(Ujian $ujian, array $rombelIds): void
    {
        $rombelIds = array_filter(array_map('intval', $rombelIds));

        UjianKelas::where('ujian_id', $ujian->id)->whereNotIn('rombongan_belajar_id', $rombelIds ?: [0])->delete();

        foreach ($rombelIds as $id) {
            UjianKelas::firstOrCreate(['ujian_id' => $ujian->id, 'rombongan_belajar_id' => $id]);
        }
    }

    protected function kodeBaru(): string
    {
        return 'UJN-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
    }

    protected function validasi(Request $r, ?int $abaikanId = null): array
    {
        $data = $r->validate([
            'kode_ujian' => 'required|string|max:40|unique:ujian,kode_ujian'.($abaikanId ? ",{$abaikanId}" : ''),
            'nama_ujian' => 'required|string|max:255',
            'paket_soal_id' => ['required', 'integer', function ($atribut, $nilai, $gagal) {
                if (! PaketSoal::milikPengguna()->whereKey($nilai)->exists()) {
                    $gagal('Paket soal yang dipilih tidak ditemukan.');
                }
            }],
            'tahun_ajaran' => Referensi::aturanTahunAjaran($r->route('ujian')?->tahun_ajaran),
            'semester' => 'nullable|string|max:20',
            'waktu_mulai' => 'required|date',
            'waktu_selesai' => 'required|date|after:waktu_mulai',
            'durasi_menit' => 'required|integer|min:5|max:600',
            'token' => 'nullable|string|max:10',
            'kkm' => 'required|numeric|min:0|max:100',
            'maks_pelanggaran' => 'required|integer|min:0|max:255',
            'status' => 'required|in:'.implode(',', array_keys(Ujian::STATUS)),
            'rombongan_belajar_id' => 'required|array|min:1',
            'rombongan_belajar_id.*' => 'integer',
        ], [], [
            'paket_soal_id' => 'Paket soal',
            'rombongan_belajar_id' => 'Kelas peserta',
            'waktu_selesai' => 'Waktu selesai',
        ]);

        // Mapel ujian selalu mengikuti paket soalnya agar laporan per mapel konsisten.
        $data['mata_pelajaran_id'] = PaketSoal::find($data['paket_soal_id'])?->mata_pelajaran_id;

        unset($data['rombongan_belajar_id']);

        return $data + [
            'tampilkan_hasil' => $r->boolean('tampilkan_hasil'),
            'acak_soal' => $r->boolean('acak_soal'),
            'acak_opsi' => $r->boolean('acak_opsi'),
            'proteksi_ketat' => $r->boolean('proteksi_ketat'),
            'wajib_exambro' => $r->boolean('wajib_exambro'),
        ];
    }
}
