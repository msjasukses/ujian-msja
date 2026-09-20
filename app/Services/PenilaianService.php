<?php

namespace App\Services;

use App\Models\PaketSoalDetail;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\Models\UjianPeserta;

/**
 * Penilaian hasil ujian.
 *
 * Alur nilainya: tiap butir diberi skor (otomatis untuk soal objektif,
 * manual oleh guru untuk essay), lalu nilai akhir = total skor dibagi total
 * bobot paket, dikali 100. Dengan begitu bobot butir yang berbeda-beda tetap
 * menghasilkan skala nilai 0–100 yang seragam.
 */
class PenilaianService
{
    /**
     * Koreksi seluruh jawaban objektif milik satu peserta, lalu hitung ulang
     * nilai akhirnya. Butir essay dilewati — skornya menunggu penilaian guru.
     */
    public function koreksi(UjianPeserta $peserta): UjianPeserta
    {
        $jawaban = $peserta->jawaban()->with('soal')->get();

        foreach ($jawaban as $j) {
            if (! $j->soal || $j->soal->jenis === Soal::ESSAY) {
                continue;
            }

            $hasil = $j->soal->koreksi($j->jawaban);
            // Bobot butir bisa di-override per paket soal, jadi skala ulang
            // hasil koreksi terhadap bobot yang berlaku di paket ini.
            $bobotPaket = $this->bobotDiPaket($peserta, $j->soal_id);
            $bobotSoal = max(0.01, (float) $j->soal->bobot);

            $j->update([
                'is_benar' => $hasil['is_benar'],
                'skor' => round($hasil['skor'] / $bobotSoal * $bobotPaket, 2),
            ]);
        }

        return $this->hitungNilai($peserta);
    }

    /**
     * Hitung ulang nilai akhir dari skor per butir yang tersimpan. Dipanggil
     * setelah koreksi otomatis maupun setelah guru menilai essay.
     */
    public function hitungNilai(UjianPeserta $peserta): UjianPeserta
    {
        $jawaban = $peserta->jawaban()->with('soal')->get();
        $totalBobot = (float) $peserta->ujian->total_bobot;

        $skorObjektif = 0.0;
        $skorEssay = 0.0;
        $benar = $salah = $kosong = 0;
        $adaEssayBelumDinilai = false;

        foreach ($jawaban as $j) {
            $essay = $j->soal?->jenis === Soal::ESSAY;

            if ($essay) {
                $skorEssay += (float) $j->skor;
                if ($j->is_benar === null) {
                    $adaEssayBelumDinilai = true;
                }
            } else {
                $skorObjektif += (float) $j->skor;
            }

            if (! $j->terisi) {
                $kosong++;
            } elseif ($j->is_benar === true) {
                $benar++;
            } elseif ($j->is_benar === false) {
                $salah++;
            }
        }

        $nilai = $totalBobot > 0
            ? round(($skorObjektif + $skorEssay) / $totalBobot * 100, 2)
            : 0.0;

        $peserta->update([
            'skor_objektif' => round($skorObjektif, 2),
            'skor_essay' => round($skorEssay, 2),
            'nilai' => min(100, $nilai),
            'jumlah_benar' => $benar,
            'jumlah_salah' => $salah,
            'jumlah_kosong' => $kosong,
            'essay_dinilai' => ! $adaEssayBelumDinilai,
        ]);

        return $peserta->refresh();
    }

    /**
     * Simpan penilaian guru untuk satu butir essay.
     *
     * @param  float  $skor  Skor mentah, dibatasi 0..bobot butir di paket.
     */
    public function nilaiEssay(UjianJawaban $jawaban, float $skor): UjianJawaban
    {
        $maks = $this->bobotDiPaket($jawaban->peserta, $jawaban->soal_id);
        $skor = max(0, min($maks, $skor));

        $jawaban->update([
            'skor' => round($skor, 2),
            // Butir essay ditandai "benar" bila mencapai minimal 75% bobot —
            // hanya dipakai untuk statistik benar/salah, bukan untuk nilai.
            'is_benar' => $maks > 0 ? ($skor >= $maks * 0.75) : false,
        ]);

        return $jawaban;
    }

    /** Bobot butir menurut paket soal yang dipakai ujian ini. */
    protected function bobotDiPaket(UjianPeserta $peserta, int $soalId): float
    {
        $bobot = PaketSoalDetail::where('paket_soal_id', $peserta->ujian->paket_soal_id)
            ->where('soal_id', $soalId)
            ->value('bobot');

        return (float) ($bobot ?? 1);
    }

    /** Koreksi ulang seluruh peserta sebuah ujian (mis. setelah kunci diperbaiki). */
    public function koreksiUlangUjian(Ujian $ujian): int
    {
        $jumlah = 0;

        $ujian->peserta()->where('status', UjianPeserta::SELESAI)->cursor()
            ->each(function (UjianPeserta $p) use (&$jumlah) {
                $this->koreksi($p);
                $jumlah++;
            });

        return $jumlah;
    }
}
