<?php

namespace App\Services;

use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\Models\UjianPeserta;
use Illuminate\Support\Collection;

/**
 * Analisis butir soal (item analysis) untuk satu ujian.
 *
 * Tiga ukuran yang dihitung:
 *
 *  - Tingkat Kesukaran (P) = rata-rata skor butir dibagi skor maksimalnya.
 *    Makin besar berarti makin mudah. 0,00–0,30 sukar; 0,31–0,70 sedang;
 *    0,71–1,00 mudah.
 *
 *  - Daya Pembeda (D) = selisih rata-rata skor kelompok atas dan kelompok
 *    bawah, dibagi skor maksimal. Kelompok atas/bawah diambil 27% peserta
 *    dengan nilai tertinggi/terendah (pembagian baku analisis butir).
 *    < 0,20 jelek; 0,20–0,29 cukup; 0,30–0,39 baik; >= 0,40 sangat baik.
 *    Nilai negatif menandakan butir menyesatkan — kunci perlu diperiksa.
 *
 *  - Efektivitas pengecoh: sebaran pemilih tiap opsi pada soal pilihan
 *    ganda. Opsi pengecoh dianggap berfungsi bila dipilih minimal 5% peserta.
 */
class AnalisisButirService
{
    /** Proporsi peserta yang masuk kelompok atas maupun kelompok bawah. */
    protected const PROPORSI_KELOMPOK = 0.27;

    /**
     * @return array{
     *     butir: Collection<int, object>,
     *     ringkasan: array<string, mixed>,
     *     jumlah_peserta: int
     * }
     */
    public function untukUjian(Ujian $ujian): array
    {
        $peserta = $ujian->peserta()
            ->where('status', UjianPeserta::SELESAI)
            ->orderByDesc('nilai')
            ->get();

        $jumlah = $peserta->count();

        if ($jumlah === 0) {
            return [
                'butir' => collect(),
                'ringkasan' => $this->ringkasanKosong(),
                'jumlah_peserta' => 0,
            ];
        }

        // Kelompok atas & bawah minimal 1 orang supaya daya pembeda tetap
        // bisa dihitung walau pesertanya sedikit.
        $ukuran = max(1, (int) round($jumlah * self::PROPORSI_KELOMPOK));
        $idAtas = $peserta->take($ukuran)->pluck('id');
        $idBawah = $peserta->reverse()->take($ukuran)->pluck('id');

        $detail = $ujian->paketSoal->detail()->with('soal')->get();
        $pesertaIds = $peserta->pluck('id');

        $jawabanPerSoal = UjianJawaban::whereIn('ujian_peserta_id', $pesertaIds)
            ->whereIn('soal_id', $detail->pluck('soal_id'))
            ->get()
            ->groupBy('soal_id');

        $butir = $detail->map(function ($d) use ($jawabanPerSoal, $idAtas, $idBawah, $jumlah) {
            $jawaban = $jawabanPerSoal->get($d->soal_id, collect());
            $maks = max(0.01, (float) $d->bobot);

            $rata = $jawaban->avg('skor') ?? 0;
            $rataAtas = $jawaban->whereIn('ujian_peserta_id', $idAtas)->avg('skor') ?? 0;
            $rataBawah = $jawaban->whereIn('ujian_peserta_id', $idBawah)->avg('skor') ?? 0;

            $p = round($rata / $maks, 3);
            $dp = round(($rataAtas - $rataBawah) / $maks, 3);

            $benar = $jawaban->where('is_benar', true)->count();
            $terisi = $jawaban->filter(fn ($j) => $j->terisi)->count();

            return (object) [
                'nomor' => $d->nomor_urut,
                'soal_id' => $d->soal_id,
                'soal' => $d->soal,
                'jenis' => $d->soal?->jenis,
                'jenis_label' => $d->soal?->jenis_label ?? '-',
                'bobot' => (float) $d->bobot,
                'jumlah_menjawab' => $terisi,
                'jumlah_kosong' => $jumlah - $terisi,
                'jumlah_benar' => $benar,
                'persen_benar' => $jumlah > 0 ? round($benar / $jumlah * 100, 1) : 0,
                'tingkat_kesukaran' => $p,
                'kategori_kesukaran' => $this->kategoriKesukaran($p),
                'daya_pembeda' => $dp,
                'kategori_daya_pembeda' => $this->kategoriDayaPembeda($dp),
                'pengecoh' => $this->analisisPengecoh($d->soal, $jawaban, $jumlah),
                'keputusan' => $this->keputusan($p, $dp),
            ];
        })->values();

        return [
            'butir' => $butir,
            'ringkasan' => $this->ringkasan($butir),
            'jumlah_peserta' => $jumlah,
        ];
    }

    /**
     * Sebaran pemilih tiap opsi pada soal pilihan ganda / pilihan ganda
     * kompleks. Jenis soal lain tidak punya pengecoh untuk dianalisis.
     *
     * @param  Collection<int, UjianJawaban>  $jawaban
     * @return array<int, array<string, mixed>>
     */
    protected function analisisPengecoh(?Soal $soal, Collection $jawaban, int $jumlahPeserta): array
    {
        if (! $soal || ! in_array($soal->jenis, [Soal::PG, Soal::PG_KOMPLEKS], true)) {
            return [];
        }

        $kunci = array_map('strtoupper', array_map('strval', $soal->kunci ?? []));
        $hitung = [];

        foreach ($jawaban as $j) {
            foreach ((array) $j->jawaban as $pilih) {
                $key = strtoupper((string) $pilih);
                $hitung[$key] = ($hitung[$key] ?? 0) + 1;
            }
        }

        return collect($soal->opsiPilihan())->map(function ($opsi) use ($hitung, $kunci, $jumlahPeserta) {
            $key = strtoupper((string) $opsi['key']);
            $dipilih = $hitung[$key] ?? 0;
            $persen = $jumlahPeserta > 0 ? round($dipilih / $jumlahPeserta * 100, 1) : 0;
            $adalahKunci = in_array($key, $kunci, true);

            return [
                'key' => $opsi['key'],
                'text' => $opsi['text'],
                'dipilih' => $dipilih,
                'persen' => $persen,
                'kunci' => $adalahKunci,
                // Pengecoh berfungsi bila menarik minimal 5% peserta.
                'status' => $adalahKunci
                    ? 'Kunci'
                    : ($persen >= 5 ? 'Berfungsi' : 'Tidak berfungsi'),
            ];
        })->all();
    }

    protected function kategoriKesukaran(float $p): string
    {
        return match (true) {
            $p <= 0.30 => 'Sukar',
            $p <= 0.70 => 'Sedang',
            default => 'Mudah',
        };
    }

    protected function kategoriDayaPembeda(float $d): string
    {
        return match (true) {
            $d < 0 => 'Negatif (periksa kunci)',
            $d < 0.20 => 'Jelek',
            $d < 0.30 => 'Cukup',
            $d < 0.40 => 'Baik',
            default => 'Sangat Baik',
        };
    }

    /** Rekomendasi tindakan atas butir berdasarkan P dan D. */
    protected function keputusan(float $p, float $d): string
    {
        if ($d < 0) {
            return 'Buang / perbaiki kunci';
        }
        if ($d < 0.20) {
            return 'Revisi';
        }
        if ($p <= 0.10 || $p >= 0.95) {
            return 'Revisi (terlalu '.($p >= 0.95 ? 'mudah' : 'sukar').')';
        }

        return 'Diterima';
    }

    /** @param Collection<int, object> $butir */
    protected function ringkasan(Collection $butir): array
    {
        return [
            'jumlah_butir' => $butir->count(),
            'mudah' => $butir->where('kategori_kesukaran', 'Mudah')->count(),
            'sedang' => $butir->where('kategori_kesukaran', 'Sedang')->count(),
            'sukar' => $butir->where('kategori_kesukaran', 'Sukar')->count(),
            'diterima' => $butir->where('keputusan', 'Diterima')->count(),
            'revisi' => $butir->filter(fn ($b) => str_starts_with($b->keputusan, 'Revisi'))->count(),
            'dibuang' => $butir->where('keputusan', 'Buang / perbaiki kunci')->count(),
            'rata_kesukaran' => round((float) $butir->avg('tingkat_kesukaran'), 3),
            'rata_daya_pembeda' => round((float) $butir->avg('daya_pembeda'), 3),
        ];
    }

    protected function ringkasanKosong(): array
    {
        return [
            'jumlah_butir' => 0, 'mudah' => 0, 'sedang' => 0, 'sukar' => 0,
            'diterima' => 0, 'revisi' => 0, 'dibuang' => 0,
            'rata_kesukaran' => 0, 'rata_daya_pembeda' => 0,
        ];
    }
}
