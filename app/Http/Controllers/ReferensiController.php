<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\GuruMapel;
use App\Models\MataPelajaran;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\TahunAjaran;
use App\Models\TingkatKelas;
use App\Support\ExcelExport;
use Illuminate\Http\Request;

/**
 * Menu "Data Referensi": tampilan baca-saja atas data milik aplikasi Data
 * Center — siswa, guru, wali kelas, guru mata pelajaran, mata pelajaran,
 * tingkat kelas dan tahun ajaran.
 *
 * Aplikasi ujian tidak menyediakan tombol tambah/ubah/hapus di sini: satu
 * sumber kebenaran tetap ada di Data Center, halaman ini hanya memastikan
 * pengguna ujian bisa memeriksa data yang dipakainya.
 */
class ReferensiController extends Controller
{
    public function siswa(Request $r)
    {
        $tahunAjaranId = TahunAjaran::aktif()?->id;

        $items = Siswa::query()
            ->when($r->q, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('nama_siswa', 'like', "%{$v}%")
                ->orWhere('nisn', 'like', "%{$v}%")
                ->orWhere('nis', 'like', "%{$v}%")))
            ->when($r->jenis_kelamin, fn ($q, $v) => $q->where('jenis_kelamin', $v))
            ->when($r->rombongan_belajar_id, fn ($q, $v) => $q->whereIn('id',
                SiswaRombel::where('rombongan_belajar_id', $v)->pluck('siswa_id')))
            ->when($r->status ?: 'Aktif', fn ($q, $v) => $q->where('status_siswa', $v))
            ->orderBy('nama_siswa')
            ->paginate(25)
            ->withQueryString();

        // Nama kelas tiap siswa pada halaman ini saja, supaya tidak N+1.
        $kelas = $this->kelasSiswa($items->pluck('id')->all(), $tahunAjaranId);

        return view('referensi.siswa', compact('items', 'kelas'));
    }

    public function guru(Request $r)
    {
        $items = Guru::query()
            ->with('mataPelajaran')
            ->when($r->q, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('nama_ptk', 'like', "%{$v}%")
                ->orWhere('nip', 'like', "%{$v}%")
                ->orWhere('nuptk', 'like', "%{$v}%")))
            ->when($r->status_kepegawaian, fn ($q, $v) => $q->where('status_kepegawaian', $v))
            ->orderBy('nama_ptk')
            ->paginate(25)
            ->withQueryString();

        return view('referensi.guru', compact('items'));
    }

    public function waliKelas(Request $r)
    {
        $tahunAjaranId = TahunAjaran::aktif()?->id;

        $items = RombonganBelajar::query()
            ->with(['waliKelas', 'jurusan', 'tahunAjaran'])
            ->withCount('siswaRombel')
            ->when($tahunAjaranId && ! $r->tahun_ajaran_id, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->when($r->tahun_ajaran_id, fn ($q, $v) => $q->where('tahun_ajaran_id', $v))
            ->when($r->q, fn ($q, $v) => $q->where('nama_rombel', 'like', "%{$v}%"))
            ->orderBy('tingkat')->orderBy('nama_rombel')
            ->paginate(25)
            ->withQueryString();

        return view('referensi.wali-kelas', compact('items'));
    }

    public function guruMapel(Request $r)
    {
        $tahunAjaranId = TahunAjaran::aktif()?->id;

        $items = GuruMapel::query()
            ->with(['guru', 'mataPelajaran', 'rombel', 'tahunAjaran'])
            ->when($tahunAjaranId && ! $r->tahun_ajaran_id, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->when($r->tahun_ajaran_id, fn ($q, $v) => $q->where('tahun_ajaran_id', $v))
            ->when($r->guru_id, fn ($q, $v) => $q->where('guru_id', $v))
            ->when($r->mata_pelajaran_id, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
            ->orderBy('guru_id')
            ->paginate(25)
            ->withQueryString();

        return view('referensi.guru-mapel', compact('items'));
    }

    public function mapel(Request $r)
    {
        $items = MataPelajaran::query()
            ->with('jurusan')
            ->when($r->q, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('nama_mapel', 'like', "%{$v}%")
                ->orWhere('kode_mapel', 'like', "%{$v}%")))
            ->when($r->kelompok, fn ($q, $v) => $q->where('kelompok', $v))
            ->orderBy('nama_mapel')
            ->paginate(25)
            ->withQueryString();

        return view('referensi.mapel', compact('items'));
    }

    public function tingkatKelas()
    {
        return view('referensi.tingkat-kelas', [
            'items' => TingkatKelas::urut()->get(),
        ]);
    }

    public function tahunAjaran()
    {
        return view('referensi.tahun-ajaran', [
            'items' => TahunAjaran::orderByDesc('kode_tahun_ajaran')->get(),
        ]);
    }

    /** Export daftar siswa (dipakai untuk mencocokkan data peserta ujian). */
    public function exportSiswa(Request $r)
    {
        $tahunAjaranId = TahunAjaran::aktif()?->id;

        $items = Siswa::query()
            ->when($r->rombongan_belajar_id, fn ($q, $v) => $q->whereIn('id',
                SiswaRombel::where('rombongan_belajar_id', $v)->pluck('siswa_id')))
            ->where('status_siswa', $r->status ?: 'Aktif')
            ->orderBy('nama_siswa')
            ->get();

        $kelas = $this->kelasSiswa($items->pluck('id')->all(), $tahunAjaranId);

        $rows = $items->values()->map(fn (Siswa $s, $i) => [
            $i + 1, $s->nisn, $s->nis, $s->nama_siswa, $s->jenis_kelamin,
            $kelas[$s->id] ?? '-', $s->tempat_lahir,
            $s->tanggal_lahir?->format('d/m/Y'), $s->agama, $s->nomor_hp, $s->status_siswa,
        ]);

        return ExcelExport::make('Data Siswa')
            ->judul('DATA SISWA (sumber: Data Center)', 'Dicetak: '.now()->format('d/m/Y H:i'))
            ->header(['No', 'NISN', 'NIS', 'Nama Siswa', 'L/P', 'Kelas', 'Tempat Lahir',
                'Tanggal Lahir', 'Agama', 'No. HP', 'Status'])
            ->rows($rows)
            ->unduh('data-siswa-'.now()->format('Ymd').'.xlsx');
    }

    /**
     * Peta siswa_id => nama rombel.
     *
     * @param  array<int, int>  $siswaIds
     * @return array<int, string>
     */
    protected function kelasSiswa(array $siswaIds, ?int $tahunAjaranId): array
    {
        if ($siswaIds === []) {
            return [];
        }

        $penempatan = SiswaRombel::whereIn('siswa_id', $siswaIds)
            ->when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->pluck('rombongan_belajar_id', 'siswa_id');

        $nama = RombonganBelajar::whereIn('id', $penempatan->values()->unique())
            ->pluck('nama_rombel', 'id');

        return $penempatan->map(fn ($rombelId) => $nama[$rombelId] ?? '-')->all();
    }
}
