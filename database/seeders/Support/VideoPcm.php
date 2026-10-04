<?php

namespace Database\Seeders\Support;

use GdImage;
use InvalidArgumentException;

/**
 * Penyusun video MP4 (H.264) sederhana untuk data contoh.
 *
 * Seeder tidak boleh bergantung pada ffmpeg atau berkas yang harus diunduh,
 * jadi videonya disusun langsung dari gambar GD. Kompresi sungguhan terlalu
 * rumit untuk keperluan ini; sebagai gantinya dipakai dua jenis makroblok
 * yang paling sederhana dalam standar H.264:
 *
 *  - I_PCM  — piksel 16×16 disimpan mentah, apa adanya;
 *  - P_Skip — salin dari bingkai sebelumnya, nyaris tanpa ongkos.
 *
 * Makroblok yang tidak berubah dari bingkai sebelumnya dilewati, sehingga
 * animasi yang hanya mengganti sebagian kecil layar tetap berukuran ratusan
 * KB. Hasilnya H.264 Constrained Baseline dalam wadah MP4 dengan kotak moov
 * di depan — format yang diputar semua peramban, termasuk WebView ExamBro,
 * dan bisa mulai diputar sebelum unduhannya selesai.
 *
 * Tanpa trek suara: suara dalam MP4 harus berupa AAC, dan penyandi AAC jauh
 * di luar keperluan data contoh.
 */
final class VideoPcm
{
    /** Satuan waktu MP4: milidetik. */
    private const SKALA_WAKTU = 1000;

    /** Nilai frame_num berputar di 0–15 (log2_max_frame_num = 4). */
    private const MAKS_FRAME_NUM = 16;

    /** @var list<array{mb: list<string>, durasi: int}> */
    private array $bingkai = [];

    private string $bitBuffer = '';

    private int $bitAkumulator = 0;

    private int $bitJumlah = 0;

    public function __construct(private readonly int $lebar, private readonly int $tinggi)
    {
        if ($lebar % 16 !== 0 || $tinggi % 16 !== 0) {
            throw new InvalidArgumentException('Lebar dan tinggi video harus kelipatan 16.');
        }
    }

    /**
     * Tambahkan satu bingkai yang tampil selama $detik. Bingkai yang sama
     * persis dengan sebelumnya cukup memperpanjang tampilnya bingkai itu.
     */
    public function tambah(GdImage $gambar, float $detik): void
    {
        if (imagesx($gambar) !== $this->lebar || imagesy($gambar) !== $this->tinggi) {
            throw new InvalidArgumentException("Ukuran bingkai harus {$this->lebar}×{$this->tinggi}.");
        }

        $mb = $this->makroblok($gambar);
        $durasi = max(1, (int) round($detik * self::SKALA_WAKTU));
        $akhir = array_key_last($this->bingkai);

        if ($akhir !== null && $this->bingkai[$akhir]['mb'] === $mb) {
            $this->bingkai[$akhir]['durasi'] += $durasi;

            return;
        }

        $this->bingkai[] = ['mb' => $mb, 'durasi' => $durasi];
    }

    /** Isi berkas .mp4 utuh. */
    public function mp4(): string
    {
        if ($this->bingkai === []) {
            throw new InvalidArgumentException('Video belum berisi bingkai.');
        }

        $sps = $this->nal(0x67, $this->sps());
        $pps = $this->nal(0x68, $this->pps());

        $sampel = [];

        foreach ($this->bingkai as $i => $b) {
            $nal = $i === 0
                ? $this->nal(0x65, $this->irisanIdr($b['mb']))
                : $this->nal(0x61, $this->irisanP($b['mb'], $this->bingkai[$i - 1]['mb'], $i));

            // Di dalam MP4, unit NAL diawali panjangnya (4 bita), bukan kode awal.
            $sampel[] = pack('N', strlen($nal)).$nal;
        }

        return $this->wadah($sps, $pps, $sampel, array_column($this->bingkai, 'durasi'));
    }

    // =====================================================================
    // Piksel → makroblok YUV 4:2:0
    // =====================================================================

    /**
     * Pecah gambar menjadi makroblok 16×16 berformat sampel I_PCM: 256 bita
     * luma, lalu 64 bita Cb dan 64 bita Cr. Konversinya BT.601 rentang
     * terbatas (Y 16–235, C 16–240), jadi tidak ada sampel bernilai nol.
     *
     * @return list<string>
     */
    private function makroblok(GdImage $gambar): array
    {
        $lebarMb = intdiv($this->lebar, 16);
        $tinggiMb = intdiv($this->tinggi, 16);
        $hasil = [];

        for ($my = 0; $my < $tinggiMb; $my++) {
            for ($mx = 0; $mx < $lebarMb; $mx++) {
                $luma = $cb = $cr = '';
                $jumlahCb = $jumlahCr = array_fill(0, 64, 0);

                for ($y = 0; $y < 16; $y++) {
                    for ($x = 0; $x < 16; $x++) {
                        $rgb = imagecolorat($gambar, $mx * 16 + $x, $my * 16 + $y);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;

                        $luma .= chr($this->batas((int) round(16 + (65.481 * $r + 128.553 * $g + 24.966 * $b) / 255), 16, 235));

                        $c = intdiv($y, 2) * 8 + intdiv($x, 2);
                        $jumlahCb[$c] += -37.797 * $r - 74.203 * $g + 112.0 * $b;
                        $jumlahCr[$c] += 112.0 * $r - 93.786 * $g - 18.214 * $b;
                    }
                }

                // Krominans: rata-rata tiap kotak 2×2 piksel.
                for ($c = 0; $c < 64; $c++) {
                    $cb .= chr($this->batas((int) round(128 + $jumlahCb[$c] / 4 / 255), 16, 240));
                    $cr .= chr($this->batas((int) round(128 + $jumlahCr[$c] / 4 / 255), 16, 240));
                }

                $hasil[] = $luma.$cb.$cr;
            }
        }

        return $hasil;
    }

    private function batas(int $nilai, int $bawah, int $atas): int
    {
        return max($bawah, min($atas, $nilai));
    }

    // =====================================================================
    // Bitstream H.264
    // =====================================================================

    /** Sequence parameter set: Constrained Baseline, level 3.0. */
    private function sps(): string
    {
        $this->mulaiBit();
        $this->u(8, 66);                                // profile_idc: Baseline
        $this->u(8, 0xC0);                              // constraint_set0 & set1: Constrained Baseline
        $this->u(8, 30);                                // level_idc 3.0
        $this->ue(0);                                   // seq_parameter_set_id
        $this->ue(0);                                   // log2_max_frame_num_minus4
        $this->ue(2);                                   // pic_order_cnt_type: diturunkan dari frame_num
        $this->ue(1);                                   // max_num_ref_frames
        $this->u(1, 0);                                 // gaps_in_frame_num_value_allowed_flag
        $this->ue(intdiv($this->lebar, 16) - 1);        // pic_width_in_mbs_minus1
        $this->ue(intdiv($this->tinggi, 16) - 1);       // pic_height_in_map_units_minus1
        $this->u(1, 1);                                 // frame_mbs_only_flag
        $this->u(1, 1);                                 // direct_8x8_inference_flag
        $this->u(1, 0);                                 // frame_cropping_flag
        $this->u(1, 0);                                 // vui_parameters_present_flag

        return $this->akhiriBit();
    }

    private function pps(): string
    {
        $this->mulaiBit();
        $this->ue(0);                                   // pic_parameter_set_id
        $this->ue(0);                                   // seq_parameter_set_id
        $this->u(1, 0);                                 // entropy_coding_mode_flag: CAVLC
        $this->u(1, 0);                                 // bottom_field_pic_order_in_frame_present_flag
        $this->ue(0);                                   // num_slice_groups_minus1
        $this->ue(0);                                   // num_ref_idx_l0_default_active_minus1
        $this->ue(0);                                   // num_ref_idx_l1_default_active_minus1
        $this->u(1, 0);                                 // weighted_pred_flag
        $this->u(2, 0);                                 // weighted_bipred_idc
        $this->se(0);                                   // pic_init_qp_minus26
        $this->se(0);                                   // pic_init_qs_minus26
        $this->se(0);                                   // chroma_qp_index_offset
        $this->u(1, 1);                                 // deblocking_filter_control_present_flag
        $this->u(1, 0);                                 // constrained_intra_pred_flag
        $this->u(1, 0);                                 // redundant_pic_cnt_present_flag

        return $this->akhiriBit();
    }

    /**
     * Bingkai pertama: irisan IDR yang seluruh makrobloknya I_PCM.
     *
     * @param  list<string>  $mb
     */
    private function irisanIdr(array $mb): string
    {
        $this->mulaiBit();
        $this->ue(0);                                   // first_mb_in_slice
        $this->ue(7);                                   // slice_type: I (seluruh irisan)
        $this->ue(0);                                   // pic_parameter_set_id
        $this->u(4, 0);                                 // frame_num
        $this->ue(0);                                   // idr_pic_id
        $this->u(1, 0);                                 // no_output_of_prior_pics_flag
        $this->u(1, 0);                                 // long_term_reference_flag
        $this->se(0);                                   // slice_qp_delta
        $this->ue(1);                                   // disable_deblocking_filter_idc: mati

        foreach ($mb as $sampel) {
            $this->ue(25);                              // mb_type: I_PCM pada irisan I
            $this->pcm($sampel);
        }

        return $this->akhiriBit();
    }

    /**
     * Bingkai berikutnya: irisan P. Makroblok yang sama dengan bingkai
     * sebelumnya dilewati (P_Skip, vektor geraknya nol), sisanya I_PCM.
     *
     * @param  list<string>  $mb
     * @param  list<string>  $sebelumnya
     */
    private function irisanP(array $mb, array $sebelumnya, int $urutan): string
    {
        $this->mulaiBit();
        $this->ue(0);                                   // first_mb_in_slice
        $this->ue(5);                                   // slice_type: P (seluruh irisan)
        $this->ue(0);                                   // pic_parameter_set_id
        $this->u(4, $urutan % self::MAKS_FRAME_NUM);    // frame_num
        $this->u(1, 0);                                 // num_ref_idx_active_override_flag
        $this->u(1, 0);                                 // ref_pic_list_modification_flag_l0
        $this->u(1, 0);                                 // adaptive_ref_pic_marking_mode_flag
        $this->se(0);                                   // slice_qp_delta
        $this->ue(1);                                   // disable_deblocking_filter_idc: mati

        $lewati = 0;

        foreach ($mb as $i => $sampel) {
            if ($sampel === $sebelumnya[$i]) {
                $lewati++;

                continue;
            }

            $this->ue($lewati);                         // mb_skip_run
            $lewati = 0;
            $this->ue(30);                              // mb_type: I_PCM pada irisan P (5 + 25)
            $this->pcm($sampel);
        }

        if ($lewati > 0) {
            $this->ue($lewati);
        }

        return $this->akhiriBit();
    }

    private function pcm(string $sampel): void
    {
        $this->ratakanBit();                            // pcm_alignment_zero_bit
        $this->bitBuffer .= $sampel;                    // 256 luma + 64 Cb + 64 Cr
    }

    /**
     * Bungkus RBSP menjadi unit NAL: bita kepala, lalu bita pencegah
     * emulasi (0x03) di depan setiap 00 00 0x yang bisa terbaca sebagai kode
     * awal.
     */
    private function nal(int $kepala, string $rbsp): string
    {
        return chr($kepala).preg_replace('/\x00\x00(?=[\x00-\x03])/', "\x00\x00\x03", $rbsp);
    }

    // ---- penulis bit ----------------------------------------------------

    private function mulaiBit(): void
    {
        $this->bitBuffer = '';
        $this->bitAkumulator = 0;
        $this->bitJumlah = 0;
    }

    private function u(int $panjang, int $nilai): void
    {
        for ($i = $panjang - 1; $i >= 0; $i--) {
            $this->bitAkumulator = ($this->bitAkumulator << 1) | (($nilai >> $i) & 1);

            if (++$this->bitJumlah === 8) {
                $this->bitBuffer .= chr($this->bitAkumulator);
                $this->bitAkumulator = 0;
                $this->bitJumlah = 0;
            }
        }
    }

    /** Exp-Golomb tak bertanda. */
    private function ue(int $nilai): void
    {
        $panjang = strlen(decbin($nilai + 1));
        $this->u($panjang - 1, 0);
        $this->u($panjang, $nilai + 1);
    }

    /** Exp-Golomb bertanda. */
    private function se(int $nilai): void
    {
        $this->ue($nilai <= 0 ? -2 * $nilai : 2 * $nilai - 1);
    }

    private function ratakanBit(): void
    {
        while ($this->bitJumlah !== 0) {
            $this->u(1, 0);
        }
    }

    /** rbsp_trailing_bits: satu bit 1, lalu nol sampai genap sebita. */
    private function akhiriBit(): string
    {
        $this->u(1, 1);
        $this->ratakanBit();

        return $this->bitBuffer;
    }

    // =====================================================================
    // Wadah MP4
    // =====================================================================

    /**
     * @param  list<string>  $sampel
     * @param  list<int>  $durasi
     */
    private function wadah(string $sps, string $pps, array $sampel, array $durasi): string
    {
        $total = array_sum($durasi);
        $ftyp = $this->kotak('ftyp', 'isom'.pack('N', 0x200).'isomiso2avc1mp41');

        // Ukuran moov tidak bergantung pada letak mdat, jadi dihitung dulu
        // dengan offset sembarang untuk mengetahui di mana mdat dimulai.
        $moov = $this->moov($sps, $pps, $sampel, $durasi, $total, 0);
        $offset = strlen($ftyp) + strlen($moov) + 8;
        $moov = $this->moov($sps, $pps, $sampel, $durasi, $total, $offset);

        return $ftyp.$moov.$this->kotak('mdat', implode('', $sampel));
    }

    /**
     * @param  list<string>  $sampel
     * @param  list<int>  $durasi
     */
    private function moov(string $sps, string $pps, array $sampel, array $durasi, int $total, int $offset): string
    {
        $matriks = pack('N9', 0x00010000, 0, 0, 0, 0x00010000, 0, 0, 0, 0x40000000);

        $mvhd = $this->kotakPenuh('mvhd', 0, 0,
            pack('NNNN', 0, 0, self::SKALA_WAKTU, $total)
            .pack('Nn', 0x00010000, 0x0100).str_repeat("\0", 10)
            .$matriks.str_repeat("\0", 24).pack('N', 2));

        $tkhd = $this->kotakPenuh('tkhd', 0, 0x000003,
            pack('NNNNN', 0, 0, 1, 0, $total).str_repeat("\0", 8)
            .pack('nnnn', 0, 0, 0, 0).$matriks
            .pack('NN', $this->lebar << 16, $this->tinggi << 16));

        $mdhd = $this->kotakPenuh('mdhd', 0, 0,
            pack('NNNN', 0, 0, self::SKALA_WAKTU, $total).pack('nn', 0x55C4, 0)); // bahasa "und"

        $hdlr = $this->kotakPenuh('hdlr', 0, 0,
            pack('N', 0).'vide'.str_repeat("\0", 12)."VideoHandler\0");

        $vmhd = $this->kotakPenuh('vmhd', 0, 1, pack('nnnn', 0, 0, 0, 0));
        $dinf = $this->kotak('dinf', $this->kotakPenuh('dref', 0, 0,
            pack('N', 1).$this->kotakPenuh('url ', 0, 1, '')));

        $avcC = $this->kotak('avcC',
            pack('CCCCCC', 1, 66, 0xC0, 30, 0xFF, 0xE1)
            .pack('n', strlen($sps)).$sps
            .pack('C', 1).pack('n', strlen($pps)).$pps);

        $avc1 = $this->kotak('avc1',
            str_repeat("\0", 6).pack('n', 1)            // data_reference_index
            .pack('nn', 0, 0).str_repeat("\0", 12)
            .pack('nn', $this->lebar, $this->tinggi)
            .pack('NN', 0x00480000, 0x00480000)         // 72 dpi
            .pack('N', 0).pack('n', 1)                  // frame_count
            .str_repeat("\0", 32)                       // compressorname
            .pack('n', 0x0018).pack('n', 0xFFFF)
            .$avcC);

        $stsd = $this->kotakPenuh('stsd', 0, 0, pack('N', 1).$avc1);

        // Durasi beruntun yang sama dirangkum menjadi satu entri.
        $entri = [];
        foreach ($durasi as $d) {
            $akhir = array_key_last($entri);
            if ($akhir !== null && $entri[$akhir][1] === $d) {
                $entri[$akhir][0]++;
            } else {
                $entri[] = [1, $d];
            }
        }
        $stts = $this->kotakPenuh('stts', 0, 0, pack('N', count($entri))
            .implode('', array_map(fn ($e) => pack('NN', $e[0], $e[1]), $entri)));

        $stss = $this->kotakPenuh('stss', 0, 0, pack('NN', 1, 1));   // hanya bingkai pertama yang IDR
        $stsc = $this->kotakPenuh('stsc', 0, 0, pack('NNNN', 1, 1, count($sampel), 1));
        $stsz = $this->kotakPenuh('stsz', 0, 0, pack('NN', 0, count($sampel))
            .implode('', array_map(fn ($s) => pack('N', strlen($s)), $sampel)));
        $stco = $this->kotakPenuh('stco', 0, 0, pack('NN', 1, $offset));

        $stbl = $this->kotak('stbl', $stsd.$stts.$stss.$stsc.$stsz.$stco);
        $minf = $this->kotak('minf', $vmhd.$dinf.$stbl);
        $mdia = $this->kotak('mdia', $mdhd.$hdlr.$minf);
        $trak = $this->kotak('trak', $tkhd.$mdia);

        return $this->kotak('moov', $mvhd.$trak);
    }

    private function kotak(string $jenis, string $isi): string
    {
        return pack('N', 8 + strlen($isi)).$jenis.$isi;
    }

    private function kotakPenuh(string $jenis, int $versi, int $bendera, string $isi): string
    {
        return $this->kotak($jenis, pack('N', ($versi << 24) | $bendera).$isi);
    }
}
