<?php

namespace Tests\Unit;

use App\Models\Soal;
use App\Models\UjianJawaban;
use Tests\TestCase;

/**
 * Pengacakan opsi hanya memindahkan isi opsinya; abjad di kolom huruf tetap
 * urut a, b, c, d dari atas ke bawah.
 *
 * Yang dijaga uji ini ada dua: huruf yang dibaca siswa selalu urut, dan nilai
 * yang dikirim ke server tetap huruf asli opsi — tanpa itu, koreksi akan
 * menilai opsi yang salah.
 */
class AcakOpsiHurufTest extends TestCase
{
    private function soalPg(): Soal
    {
        return new Soal([
            'jenis' => Soal::PG,
            'pertanyaan' => 'Ibu kota Indonesia adalah ...',
            'opsi' => [
                ['key' => 'A', 'text' => 'Jakarta'],
                ['key' => 'B', 'text' => 'Bandung'],
                ['key' => 'C', 'text' => 'Surabaya'],
                ['key' => 'D', 'text' => 'Medan'],
            ],
            'kunci' => ['A'],
            'bobot' => 1,
        ]);
    }

    private function butir(Soal $soal, ?array $urutan): UjianJawaban
    {
        $j = new UjianJawaban(['urutan_opsi' => $urutan]);
        $j->setRelation('soal', $soal);

        return $j;
    }

    public function test_abjad_tetap_urut_meski_isi_opsi_berpindah(): void
    {
        $tampil = $this->butir($this->soalPg(), ['C', 'A', 'D', 'B'])->opsiTampil();

        // Huruf yang dibaca siswa: tetap A, B, C, D.
        $this->assertSame(['A', 'B', 'C', 'D'], array_column($tampil, 'label'));

        // Isi opsinya yang berpindah tempat.
        $this->assertSame(['Surabaya', 'Jakarta', 'Medan', 'Bandung'], array_column($tampil, 'text'));

        // Nilai yang dikirim ke server tetap huruf asli tiap opsi.
        $this->assertSame(['C', 'A', 'D', 'B'], array_column($tampil, 'key'));
    }

    public function test_jawaban_dinilai_menurut_huruf_asli_bukan_huruf_tampil(): void
    {
        $soal = $this->soalPg();
        $tampil = $this->butir($soal, ['C', 'A', 'D', 'B'])->opsiTampil();

        // Siswa memilih baris berhuruf B, yang isinya "Jakarta" — jawaban benar,
        // walaupun kuncinya tersimpan sebagai "A".
        $barisB = collect($tampil)->firstWhere('label', 'B');

        $this->assertSame('Jakarta', $barisB['text']);
        $this->assertTrue($soal->koreksi([$barisB['key']])['is_benar']);

        // Sebaliknya, mengirim huruf tampil apa adanya akan dinilai salah.
        $this->assertFalse($soal->koreksi(['B'])['is_benar']);
    }

    public function test_tanpa_pengacakan_huruf_dan_nilai_sama(): void
    {
        $tampil = $this->butir($this->soalPg(), null)->opsiTampil();

        $this->assertSame(['A', 'B', 'C', 'D'], array_column($tampil, 'key'));
        $this->assertSame(['A', 'B', 'C', 'D'], array_column($tampil, 'label'));
        $this->assertSame(['Jakarta', 'Bandung', 'Surabaya', 'Medan'], array_column($tampil, 'text'));
    }

    public function test_penjodohan_hanya_kolom_kanan_yang_isinya_diacak(): void
    {
        $soal = new Soal([
            'jenis' => Soal::PENJODOHAN,
            'pertanyaan' => 'Jodohkan negara dengan ibu kotanya.',
            'opsi' => [
                'kiri' => [
                    ['key' => '1', 'text' => 'Indonesia'],
                    ['key' => '2', 'text' => 'Jepang'],
                    ['key' => '3', 'text' => 'Mesir'],
                ],
                'kanan' => [
                    ['key' => 'A', 'text' => 'Jakarta'],
                    ['key' => 'B', 'text' => 'Tokyo'],
                    ['key' => 'C', 'text' => 'Kairo'],
                ],
            ],
            'kunci' => ['1' => 'A', '2' => 'B', '3' => 'C'],
            'bobot' => 3,
        ]);

        $butir = $this->butir($soal, ['B', 'C', 'A']);

        // Kolom kiri tidak ikut diacak: pernyataan tetap urut seperti aslinya.
        $this->assertSame(['1', '2', '3'], array_column($butir->opsiTampil('kiri'), 'label'));
        $this->assertSame(['Indonesia', 'Jepang', 'Mesir'], array_column($butir->opsiTampil('kiri'), 'text'));

        $kanan = $butir->opsiTampil('kanan');

        $this->assertSame(['A', 'B', 'C'], array_column($kanan, 'label'));
        $this->assertSame(['Tokyo', 'Kairo', 'Jakarta'], array_column($kanan, 'text'));
        $this->assertSame(['B', 'C', 'A'], array_column($kanan, 'key'));
    }

    public function test_benar_salah_kompleks_memakai_huruf_urut(): void
    {
        $soal = new Soal([
            'jenis' => Soal::BENAR_SALAH_KOMPLEKS,
            'pertanyaan' => 'Nilai tiap pernyataan berikut.',
            'opsi' => [
                ['key' => 'A', 'text' => 'Air mendidih pada 100 C'],
                ['key' => 'B', 'text' => 'Bumi berbentuk kubus'],
                ['key' => 'C', 'text' => 'Matahari adalah bintang'],
            ],
            'kunci' => ['A' => 'benar', 'B' => 'salah', 'C' => 'benar'],
            'bobot' => 3,
        ]);

        $tampil = $this->butir($soal, ['C', 'B', 'A'])->opsiTampil();

        $this->assertSame(['A', 'B', 'C'], array_column($tampil, 'label'));
        $this->assertSame(['C', 'B', 'A'], array_column($tampil, 'key'));

        // Kunci tetap menempel pada huruf asli pernyataan, jadi menilai tiap
        // pernyataan menurut 'key' tetap dihitung benar seluruhnya.
        $jawaban = [];

        foreach ($tampil as $p) {
            $jawaban[$p['key']] = $soal->kunci[$p['key']];
        }

        $this->assertTrue($soal->koreksi($jawaban)['is_benar']);
    }

    public function test_opsi_yang_hilang_tidak_membuat_abjad_bolong(): void
    {
        // Bila satu opsi dihapus setelah urutan dikunci, barisnya ikut hilang
        // tetapi huruf yang tersisa tetap urut tanpa celah.
        $tampil = $this->butir($this->soalPg(), ['C', 'Z', 'A', 'D'])->opsiTampil();

        $this->assertSame(['A', 'B', 'C'], array_column($tampil, 'label'));
        $this->assertSame(['Surabaya', 'Jakarta', 'Medan'], array_column($tampil, 'text'));
    }
}
