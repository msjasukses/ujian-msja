<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Sekolah;
use App\Models\TindakLanjut;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Support\ExcelExport;
use App\Support\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Induk bersama sub menu Remidial dan Pengayaan. Keduanya berjalan atas
 * data yang sama — hasil ujian dibandingkan dengan KKM — hanya berbeda sisi:
 * remidial mengambil yang di bawah KKM, pengayaan yang sudah mencapai KKM.
 */
abstract class TindakLanjutController extends Controller
{
    /** TindakLanjut::REMIDIAL atau TindakLanjut::PENGAYAAN. */
    abstract protected function jenis(): string;

    /** Nama route dasar, mis. "laporan.remidial". */
    abstract protected function routeDasar(): string;

    /** Folder view, mis. "laporan.remidial". */
    abstract protected function folderView(): string;

    /** Peserta yang masuk kelompok ini. */
    abstract protected function memenuhiSyarat(UjianPeserta $peserta, float $kkm): bool;

    /** @return array<string, string> */
    abstract protected function daftarBentuk(): array;

    public function index(Request $r)
    {
        $items = Ujian::query()
            ->when(Pengguna::guruId(), fn ($q, $g) => $q->where('guru_id', $g))
            ->with('mataPelajaran')
            ->withCount(['peserta as selesai_count' => fn ($q) => $q->where('status', UjianPeserta::SELESAI)])
            ->when($r->q, fn ($q, $v) => $q->where('nama_ujian', 'like', "%{$v}%"))
            ->when($r->mata_pelajaran_id, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
            ->when($r->rombongan_belajar_id, fn ($q, $v) => $q->whereHas('kelas',
                fn ($k) => $k->where('rombongan_belajar_id', $v)))
            ->orderByDesc('waktu_mulai')
            ->paginate(20)
            ->withQueryString();

        return view($this->folderView().'.index', [
            'items' => $items,
            'jenis' => $this->jenis(),
            'routeDasar' => $this->routeDasar(),
        ]);
    }

    public function show(Request $r, Ujian $ujian)
    {
        return view($this->folderView().'.show', [
            'ujian' => $ujian->load('mataPelajaran'),
            'daftar' => $this->kandidat($ujian, $r->get('rombongan_belajar_id')),
            'bentuk' => $this->daftarBentuk(),
            'jenis' => $this->jenis(),
            'routeDasar' => $this->routeDasar(),
        ]);
    }

    /** Simpan rencana tindak lanjut untuk siswa-siswa yang dicentang. */
    public function simpan(Request $r, Ujian $ujian)
    {
        $r->validate([
            'pilih' => 'required|array|min:1',
            'pilih.*' => 'integer',
            'bentuk' => 'required|string|max:100',
            'tanggal' => 'nullable|date',
            'keterangan' => 'nullable|string|max:1000',
        ], [], [
            'pilih' => 'Siswa',
            'bentuk' => 'Bentuk tindak lanjut',
        ]);

        $nilaiAwal = $ujian->peserta()->whereIn('siswa_id', $r->pilih)->pluck('nilai', 'siswa_id');
        $jumlah = 0;

        foreach ($r->pilih as $siswaId) {
            TindakLanjut::updateOrCreate(
                ['ujian_id' => $ujian->id, 'siswa_id' => $siswaId, 'jenis' => $this->jenis()],
                [
                    'nilai_awal' => $nilaiAwal[$siswaId] ?? null,
                    'tanggal' => $r->input('tanggal') ?: now()->toDateString(),
                    'bentuk' => $r->input('bentuk'),
                    'keterangan' => $r->input('keterangan'),
                    'status' => 'direncanakan',
                ]
            );
            $jumlah++;
        }

        return back()->with('success', "Rencana {$this->jenis()} tersimpan untuk {$jumlah} siswa.");
    }

    /** Catat hasil akhir setelah tindak lanjut dilaksanakan. */
    public function nilaiAkhir(Request $r, Ujian $ujian)
    {
        $r->validate([
            'nilai_akhir' => 'required|array',
            'nilai_akhir.*' => 'nullable|numeric|min:0|max:100',
        ]);

        $jumlah = 0;

        foreach ($r->input('nilai_akhir') as $tindakLanjutId => $nilai) {
            if ($nilai === null || $nilai === '') {
                continue;
            }

            $tl = TindakLanjut::where('ujian_id', $ujian->id)->find($tindakLanjutId);

            if ($tl) {
                $tl->update(['nilai_akhir' => (float) $nilai, 'status' => 'selesai']);
                $jumlah++;
            }
        }

        return back()->with('success', "Nilai akhir {$this->jenis()} tersimpan untuk {$jumlah} siswa.");
    }

    public function hapus(Ujian $ujian, TindakLanjut $tindak_lanjut)
    {
        abort_unless($tindak_lanjut->ujian_id === $ujian->id, 404);

        $tindak_lanjut->delete();

        return back()->with('success', 'Rencana tindak lanjut dihapus.');
    }

    public function export(Request $r, Ujian $ujian)
    {
        $daftar = $this->kandidat($ujian, $r->get('rombongan_belajar_id'));
        $sekolah = Sekolah::profil();
        $label = strtoupper($this->jenis());

        $rows = $daftar->values()->map(fn ($d, $i) => [
            $i + 1,
            $d->nisn,
            $d->nama,
            $d->kelas,
            $d->nilai,
            (float) $ujian->kkm,
            $d->selisih,
            $d->rencana?->bentuk_label ?? 'Belum direncanakan',
            $d->rencana?->tanggal?->format('d/m/Y') ?? '-',
            $d->rencana?->nilai_akhir ?? '-',
            $d->rencana?->status ?? '-',
            $d->rencana?->keterangan ?? '-',
        ]);

        return ExcelExport::make($label)
            ->judul(
                'DAFTAR '.$label,
                $sekolah?->nama_sekolah ?? '',
                $ujian->nama_ujian.' ('.$ujian->kode_ujian.')',
                'Mata Pelajaran: '.($ujian->mataPelajaran->nama_mapel ?? '-').'   |   KKM: '.(float) $ujian->kkm,
                'Jumlah siswa: '.$daftar->count()
            )
            ->header(['No', 'NISN', 'Nama Siswa', 'Kelas', 'Nilai', 'KKM', 'Selisih',
                'Bentuk '.ucfirst($this->jenis()), 'Tanggal', 'Nilai Akhir', 'Status', 'Keterangan'])
            ->rows($rows)
            ->unduh($this->jenis().'-'.Str::slug($ujian->kode_ujian).'.xlsx');
    }

    /**
     * Siswa yang masuk kelompok ini beserta rencana tindak lanjutnya (bila ada).
     *
     * @return Collection<int, object>
     */
    protected function kandidat(Ujian $ujian, int|string|null $rombelId = null): Collection
    {
        $kkm = (float) $ujian->kkm;

        $rencana = TindakLanjut::where('ujian_id', $ujian->id)
            ->where('jenis', $this->jenis())
            ->get()
            ->keyBy('siswa_id');

        return $ujian->peserta()
            ->with(['siswa', 'rombel'])
            ->where('status', UjianPeserta::SELESAI)
            ->when($rombelId, fn ($q, $v) => $q->where('rombongan_belajar_id', $v))
            ->get()
            ->filter(fn (UjianPeserta $p) => $this->memenuhiSyarat($p, $kkm))
            ->map(fn (UjianPeserta $p) => (object) [
                'peserta_id' => $p->id,
                'siswa_id' => $p->siswa_id,
                'nisn' => $p->siswa->nisn ?? '-',
                'nama' => $p->siswa->nama_siswa ?? '-',
                'kelas' => $p->rombel->nama_rombel ?? '-',
                'nilai' => (float) $p->nilai,
                'selisih' => round((float) $p->nilai - $kkm, 2),
                'rencana' => $rencana[$p->siswa_id] ?? null,
            ])
            ->sortBy('nilai')
            ->values();
    }
}
