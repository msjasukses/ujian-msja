<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Models\PaketSoalDetail;
use App\Models\Sekolah;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\Models\UjianPeserta;
use App\Services\PenilaianService;
use App\Support\ExcelExport;
use App\Support\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Sub menu "Daftar Nilai Ujian" — rekap nilai per ujian, sekaligus tempat
 * guru menilai butir essay yang tidak bisa dikoreksi otomatis.
 */
class NilaiController extends Controller
{
    public function __construct(protected PenilaianService $penilaian) {}

    /** Pilih ujian yang mau dilihat nilainya. */
    public function index(Request $r)
    {
        $items = Ujian::query()
            ->when(Pengguna::guruId(), fn ($q, $g) => $q->where('guru_id', $g))
            ->with(['mataPelajaran'])
            ->withCount([
                'peserta',
                'peserta as selesai_count' => fn ($q) => $q->where('status', UjianPeserta::SELESAI),
                'peserta as belum_dinilai_count' => fn ($q) => $q->where('status', UjianPeserta::SELESAI)
                    ->where('essay_dinilai', false),
            ])
            ->when($r->q, fn ($q, $v) => $q->where('nama_ujian', 'like', "%{$v}%"))
            ->when($r->mata_pelajaran_id, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
            ->when($r->rombongan_belajar_id, fn ($q, $v) => $q->whereHas('kelas',
                fn ($k) => $k->where('rombongan_belajar_id', $v)))
            ->orderByDesc('waktu_mulai')
            ->paginate(20)
            ->withQueryString();

        return view('laporan.nilai.index', compact('items'));
    }

    /** Daftar nilai peserta satu ujian. */
    public function show(Request $r, Ujian $ujian)
    {
        $peserta = $this->pesertaTerurut($ujian, $r->get('rombongan_belajar_id'));
        $kkm = (float) $ujian->kkm;
        $nilai = $peserta->where('status', UjianPeserta::SELESAI)->pluck('nilai')->map(fn ($n) => (float) $n);

        return view('laporan.nilai.show', [
            'ujian' => $ujian->load('mataPelajaran', 'paketSoal'),
            'peserta' => $peserta,
            'ringkasan' => [
                'jumlah' => $nilai->count(),
                'rata_rata' => $nilai->count() ? round($nilai->avg(), 2) : 0,
                'tertinggi' => $nilai->count() ? round($nilai->max(), 2) : 0,
                'terendah' => $nilai->count() ? round($nilai->min(), 2) : 0,
                'tuntas' => $nilai->filter(fn ($v) => $v >= $kkm)->count(),
                'belum_tuntas' => $nilai->filter(fn ($v) => $v < $kkm)->count(),
                'menunggu_koreksi' => $peserta->where('status', UjianPeserta::SELESAI)
                    ->where('essay_dinilai', false)->count(),
            ],
        ]);
    }

    /** Lembar jawaban seorang peserta + form penilaian essay. */
    public function detail(Ujian $ujian, UjianPeserta $peserta)
    {
        abort_unless($peserta->ujian_id === $ujian->id, 404);

        return view('laporan.nilai.detail', [
            'ujian' => $ujian,
            'peserta' => $peserta->load('siswa', 'rombel'),
            'jawaban' => $peserta->jawaban()->with('soal')->get(),
            'bobot' => PaketSoalDetail::where('paket_soal_id', $ujian->paket_soal_id)
                ->pluck('bobot', 'soal_id'),
        ]);
    }

    /** Simpan skor essay yang diberikan guru, lalu hitung ulang nilai akhir. */
    public function nilaiEssay(Request $r, Ujian $ujian, UjianPeserta $peserta)
    {
        abort_unless($peserta->ujian_id === $ujian->id, 404);

        $r->validate([
            'skor' => 'required|array',
            'skor.*' => 'nullable|numeric|min:0',
        ]);

        foreach ($r->input('skor') as $jawabanId => $skor) {
            if ($skor === null || $skor === '') {
                continue;
            }

            $jawaban = UjianJawaban::where('ujian_peserta_id', $peserta->id)->find($jawabanId);

            if ($jawaban && $jawaban->soal?->jenis === Soal::ESSAY) {
                $this->penilaian->nilaiEssay($jawaban, (float) $skor);
            }
        }

        $this->penilaian->hitungNilai($peserta);

        return back()->with('success', 'Penilaian essay disimpan dan nilai akhir diperbarui.');
    }

    /** Koreksi ulang seluruh peserta — dipakai setelah kunci jawaban diperbaiki. */
    public function koreksiUlang(Ujian $ujian)
    {
        $jumlah = $this->penilaian->koreksiUlangUjian($ujian);

        return back()->with('success', "{$jumlah} lembar jawaban dikoreksi ulang.");
    }

    public function export(Request $r, Ujian $ujian)
    {
        $peserta = $this->pesertaTerurut($ujian, $r->get('rombongan_belajar_id'));
        $kkm = (float) $ujian->kkm;
        $sekolah = Sekolah::profil();

        $rows = $peserta->values()->map(function (UjianPeserta $p, $i) use ($kkm) {
            $selesai = $p->status === UjianPeserta::SELESAI;

            return [
                $i + 1,
                $p->siswa->nisn ?? '-',
                $p->siswa->nama_siswa ?? '-',
                $p->siswa->jenis_kelamin ?? '-',
                $p->rombel->nama_rombel ?? '-',
                $p->status_label,
                $selesai ? $p->jumlah_benar : '-',
                $selesai ? $p->jumlah_salah : '-',
                $selesai ? $p->jumlah_kosong : '-',
                $selesai ? (float) $p->skor_objektif : '-',
                $selesai ? (float) $p->skor_essay : '-',
                $selesai ? (float) $p->nilai : '-',
                $selesai ? ((float) $p->nilai >= $kkm ? 'Tuntas' : 'Belum Tuntas') : '-',
                $p->waktu_mulai?->format('d/m/Y H:i') ?? '-',
                $p->waktu_selesai?->format('d/m/Y H:i') ?? '-',
            ];
        });

        $nilai = $peserta->where('status', UjianPeserta::SELESAI)->pluck('nilai')->map(fn ($n) => (float) $n);

        return ExcelExport::make('Daftar Nilai')
            ->judul(
                'DAFTAR NILAI UJIAN',
                $sekolah?->nama_sekolah ?? '',
                $ujian->nama_ujian.' ('.$ujian->kode_ujian.')',
                'Mata Pelajaran: '.($ujian->mataPelajaran->nama_mapel ?? '-').'   |   KKM: '.$kkm,
                'Pelaksanaan: '.$ujian->waktu_mulai->format('d/m/Y H:i')
            )
            ->header(['No', 'NISN', 'Nama Siswa', 'L/P', 'Kelas', 'Status', 'Benar', 'Salah', 'Kosong',
                'Skor Objektif', 'Skor Essay', 'Nilai', 'Ketuntasan', 'Mulai', 'Selesai'])
            ->rows($rows)
            ->spasi()
            ->ringkasan(['', '', 'RATA-RATA', '', '', '', '', '', '', '', '',
                $nilai->count() ? round($nilai->avg(), 2) : 0, '', '', ''])
            ->ringkasan(['', '', 'TERTINGGI', '', '', '', '', '', '', '', '',
                $nilai->count() ? round($nilai->max(), 2) : 0, '', '', ''])
            ->ringkasan(['', '', 'TERENDAH', '', '', '', '', '', '', '', '',
                $nilai->count() ? round($nilai->min(), 2) : 0, '', '', ''])
            ->ringkasan(['', '', 'TUNTAS / BELUM', '', '', '', '', '', '', '', '',
                $nilai->filter(fn ($v) => $v >= $kkm)->count().' / '.$nilai->filter(fn ($v) => $v < $kkm)->count(),
                '', '', ''])
            ->unduh('daftar-nilai-'.Str::slug($ujian->kode_ujian).'.xlsx');
    }

    /**
     * Peserta diurutkan kelas lalu nama. Nama siswa berada di database
     * datacenter sehingga pengurutan dikerjakan setelah data dimuat.
     *
     * @return Collection<int, UjianPeserta>
     */
    protected function pesertaTerurut(Ujian $ujian, ?string $rombelId)
    {
        return $ujian->peserta()
            ->with(['siswa', 'rombel'])
            ->when($rombelId, fn ($q, $v) => $q->where('rombongan_belajar_id', $v))
            ->get()
            ->sortBy([
                fn ($a, $b) => strcmp($a->rombel->nama_rombel ?? '', $b->rombel->nama_rombel ?? ''),
                fn ($a, $b) => strcmp($a->siswa->nama_siswa ?? '', $b->siswa->nama_siswa ?? ''),
            ])
            ->values();
    }
}
