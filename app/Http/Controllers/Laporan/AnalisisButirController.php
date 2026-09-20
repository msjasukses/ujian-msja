<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Sekolah;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Services\AnalisisButirService;
use App\Support\ExcelExport;
use App\Support\Pengguna;
use App\Support\TeksSoal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Sub menu "Analisis Butir Soal" — tingkat kesukaran, daya pembeda, pengecoh. */
class AnalisisButirController extends Controller
{
    public function __construct(protected AnalisisButirService $analisis) {}

    public function index(Request $r)
    {
        $items = Ujian::query()
            ->when(Pengguna::guruId(), fn ($q, $g) => $q->where('guru_id', $g))
            ->with(['mataPelajaran', 'paketSoal'])
            ->withCount(['peserta as selesai_count' => fn ($q) => $q->where('status', UjianPeserta::SELESAI)])
            ->when($r->q, fn ($q, $v) => $q->where('nama_ujian', 'like', "%{$v}%"))
            ->when($r->mata_pelajaran_id, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
            ->orderByDesc('waktu_mulai')
            ->paginate(20)
            ->withQueryString();

        return view('laporan.analisis.index', compact('items'));
    }

    public function show(Ujian $ujian)
    {
        return view('laporan.analisis.show', [
            'ujian' => $ujian->load('mataPelajaran', 'paketSoal'),
        ] + $this->analisis->untukUjian($ujian));
    }

    public function export(Ujian $ujian)
    {
        $data = $this->analisis->untukUjian($ujian);
        $sekolah = Sekolah::profil();

        $rows = $data['butir']->map(fn ($b) => [
            $b->nomor,
            $b->jenis_label,
            Str::limit(TeksSoal::polos($b->soal?->pertanyaan), 120),
            $b->bobot,
            $b->jumlah_menjawab,
            $b->jumlah_kosong,
            $b->jumlah_benar,
            $b->persen_benar,
            $b->tingkat_kesukaran,
            $b->kategori_kesukaran,
            $b->daya_pembeda,
            $b->kategori_daya_pembeda,
            $b->keputusan,
        ]);

        $export = ExcelExport::make('Analisis Butir')
            ->judul(
                'ANALISIS BUTIR SOAL',
                $sekolah?->nama_sekolah ?? '',
                $ujian->nama_ujian.' ('.$ujian->kode_ujian.')',
                'Mata Pelajaran: '.($ujian->mataPelajaran->nama_mapel ?? '-'),
                'Jumlah peserta dianalisis: '.$data['jumlah_peserta']
            )
            ->header(['No', 'Jenis', 'Butir Soal', 'Bobot', 'Menjawab', 'Kosong', 'Benar', '% Benar',
                'Tingkat Kesukaran (P)', 'Kategori', 'Daya Pembeda (D)', 'Kategori', 'Keputusan'])
            ->rows($rows)
            ->spasi(2)
            ->header(['Ringkasan', 'Jumlah Butir'])
            ->rows([
                ['Kategori mudah', $data['ringkasan']['mudah']],
                ['Kategori sedang', $data['ringkasan']['sedang']],
                ['Kategori sukar', $data['ringkasan']['sukar']],
                ['Butir diterima', $data['ringkasan']['diterima']],
                ['Butir perlu revisi', $data['ringkasan']['revisi']],
                ['Butir dibuang / kunci diperiksa', $data['ringkasan']['dibuang']],
                ['Rata-rata tingkat kesukaran', $data['ringkasan']['rata_kesukaran']],
                ['Rata-rata daya pembeda', $data['ringkasan']['rata_daya_pembeda']],
            ]);

        // Lembar kedua: sebaran pengecoh tiap opsi pilihan ganda.
        $pengecoh = [];
        foreach ($data['butir'] as $b) {
            foreach ($b->pengecoh as $o) {
                $pengecoh[] = [$b->nomor, $o['key'], Str::limit($o['text'], 80),
                    $o['dipilih'], $o['persen'], $o['status']];
            }
        }

        if ($pengecoh !== []) {
            $export->spasi(2)
                ->header(['No Butir', 'Opsi', 'Teks Opsi', 'Dipilih', 'Persentase (%)', 'Status'])
                ->rows($pengecoh);
        }

        return $export->unduh('analisis-butir-'.Str::slug($ujian->kode_ujian).'.xlsx');
    }
}
