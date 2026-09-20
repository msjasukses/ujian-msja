<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Sekolah;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Services\StatistikUjianService;
use App\Support\ExcelExport;
use App\Support\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Sub menu "Statistik Ujian" — sebaran dan ringkasan nilai per ujian & per kelas. */
class StatistikController extends Controller
{
    public function __construct(protected StatistikUjianService $statistik) {}

    public function index(Request $r)
    {
        $items = Ujian::query()
            ->when(Pengguna::guruId(), fn ($q, $g) => $q->where('guru_id', $g))
            ->with('mataPelajaran')
            ->withCount(['peserta as selesai_count' => fn ($q) => $q->where('status', UjianPeserta::SELESAI)])
            ->withAvg(['peserta as rata_nilai' => fn ($q) => $q->where('status', UjianPeserta::SELESAI)], 'nilai')
            ->when($r->q, fn ($q, $v) => $q->where('nama_ujian', 'like', "%{$v}%"))
            ->when($r->mata_pelajaran_id, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
            ->orderByDesc('waktu_mulai')
            ->paginate(20)
            ->withQueryString();

        return view('laporan.statistik.index', compact('items'));
    }

    public function show(Ujian $ujian)
    {
        return view('laporan.statistik.show', [
            'ujian' => $ujian->load('mataPelajaran', 'paketSoal'),
        ] + $this->statistik->untukUjian($ujian));
    }

    public function export(Ujian $ujian)
    {
        $data = $this->statistik->untukUjian($ujian);
        $r = $data['ringkasan'];
        $sekolah = Sekolah::profil();

        $export = ExcelExport::make('Statistik Ujian')
            ->judul(
                'STATISTIK HASIL UJIAN',
                $sekolah?->nama_sekolah ?? '',
                $ujian->nama_ujian.' ('.$ujian->kode_ujian.')',
                'Mata Pelajaran: '.($ujian->mataPelajaran->nama_mapel ?? '-').'   |   KKM: '.$r['kkm']
            )
            ->header(['Ukuran Statistik', 'Nilai'])
            ->rows([
                ['Peserta terdaftar', $r['terdaftar']],
                ['Peserta menyelesaikan', $r['selesai']],
                ['Belum mengerjakan', $r['belum']],
                ['Nilai rata-rata', $r['rata_rata']],
                ['Median', $r['median']],
                ['Modus', $r['modus'] ?? 'Tidak ada'],
                ['Nilai tertinggi', $r['tertinggi']],
                ['Nilai terendah', $r['terendah']],
                ['Jangkauan', $r['jangkauan']],
                ['Simpangan baku', $r['simpangan_baku']],
                ['Jumlah tuntas', $r['tuntas']],
                ['Jumlah belum tuntas', $r['belum_tuntas']],
                ['Persentase ketuntasan (%)', $r['persen_tuntas']],
            ])
            ->spasi(2)
            ->header(['Interval Nilai', 'Jumlah Siswa', 'Persentase (%)'])
            ->rows(array_map(
                fn ($d) => [$d['label'], $d['jumlah'], $d['persen']],
                $data['distribusi']
            ))
            ->spasi(2)
            ->header(['Kelas', 'Terdaftar', 'Selesai', 'Rata-rata', 'Tertinggi', 'Terendah', 'Tuntas', 'Belum Tuntas', 'Ketuntasan (%)'])
            ->rows($data['per_kelas']->map(fn ($k) => [
                $k->nama_kelas, $k->terdaftar, $k->selesai, $k->rata_rata,
                $k->tertinggi, $k->terendah, $k->tuntas, $k->belum_tuntas, $k->persen_tuntas,
            ]));

        return $export->unduh('statistik-'.Str::slug($ujian->kode_ujian).'.xlsx');
    }
}
