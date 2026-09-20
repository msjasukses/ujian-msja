<?php

namespace Tests\Feature;

use App\Support\RumusLatex;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pengurai rumus LaTeX untuk naskah yang diimpor.
 *
 * Banyak naskah soal menuliskan rumusnya sebagai LaTeX — itulah bentuk yang
 * dihasilkan penulis rumus di web maupun keluaran asisten AI. Tanpa
 * penguraian, kode mentahnya yang masuk ke bank soal dan terbaca siswa.
 */
class RumusLatexTest extends TestCase
{
    #[DataProvider('rumus')]
    public function test_rumus_diurai_menjadi_penanda_tampil(string $masukan, string $harapan): void
    {
        $this->assertSame($harapan, RumusLatex::keHtml($masukan));
    }

    /** @return array<string, array{string, string}> */
    public static function rumus(): array
    {
        $pecahan = fn ($a, $b) => '<span class="pecahan"><span class="pembilang">'.$a
            .'</span><span class="penyebut">'.$b.'</span></span>';
        $akar = fn ($x) => '<span class="akar"><span class="radikan">'.$x.'</span></span>';

        return [
            'akar dalam kalimat' => [
                'Berapakah hasil dari \(4\sqrt{3} - 2\sqrt{3}\)?',
                'Berapakah hasil dari 4'.$akar('3').' - 2'.$akar('3').'?',
            ],
            'pecahan biasa' => [
                'Bentuk pecahan \(\frac{11}{4}\) jika diubah ...',
                'Bentuk pecahan '.$pecahan('11', '4').' jika diubah ...',
            ],
            'pecahan campuran' => [
                'Jawabannya \(2 \frac{3}{4}\).',
                'Jawabannya 2 '.$pecahan('3', '4').'.',
            ],
            'pangkat & indeks' => [
                'Rumus \(x^{2} + H_{2}O\) benar.',
                'Rumus x<sup>2</sup> + H<sub>2</sub>O benar.',
            ],
            'pangkat satu huruf tanpa kurung' => [
                'Nilai \(a^2 + b_1\) adalah ...',
                'Nilai a<sup>2</sup> + b<sub>1</sub> adalah ...',
            ],
            'akar berderajat' => [
                'Hitung \(\sqrt[3]{27}\).',
                'Hitung <sup>3</sup>'.$akar('27').'.',
            ],
            'pembatas blok' => [
                'Rumusnya \[\frac{a}{b}\] selesai.',
                'Rumusnya '.$pecahan('a', 'b').' selesai.',
            ],
            'pembatas dolar ganda' => [
                'Nilai $$\frac{a}{b} \le 5$$ terpenuhi.',
                'Nilai '.$pecahan('a', 'b').' ≤ 5 terpenuhi.',
            ],
            'lambang' => [
                'Operasi \(3 \times 4 \div 2 \pm 1 \ne 5\) benar.',
                'Operasi 3 × 4 ÷ 2 ± 1 ≠ 5 benar.',
            ],
            'huruf yunani' => [
                'Luas \(\pi r^{2}\) dengan \(\theta\) tertentu.',
                'Luas πr<sup>2</sup> dengan θ tertentu.',
            ],
            'kurung berukuran' => [
                'Nilai \(\left(\frac{1}{2}\right)\) sama.',
                'Nilai ('.$pecahan('1', '2').') sama.',
            ],
            'pecahan bersarang' => [
                'Hitung \(\frac{\frac{1}{2}}{3}\).',
                'Hitung '.$pecahan($pecahan('1', '2'), '3').'.',
            ],
            'teks di dalam rumus' => [
                'Satuan \(5 \text{ cm}\) saja.',
                'Satuan 5 cm saja.',
            ],
        ];
    }

    // =====================================================================

    /** Teks tanpa rumus dikembalikan apa adanya, tidak ikut di-escape. */
    public function test_teks_tanpa_rumus_tidak_diubah(): void
    {
        $polos = 'Ibu kota Jawa Barat adalah ... (lihat peta & tabel <lampiran>)';

        $this->assertSame($polos, RumusLatex::keHtml($polos));
        $this->assertSame('', RumusLatex::keHtml(null));
    }

    /**
     * Pembatas $...$ hanya berlaku bila isinya memang rumus.
     *
     * Tanpa syarat itu, penulisan harga pada soal cerita akan tertelan menjadi
     * rumus — dan soalnya berubah arti.
     */
    public function test_penulisan_harga_bukan_rumus(): void
    {
        $harga = 'Harga barang $5 dan $10 saja.';

        $this->assertSame($harga, RumusLatex::keHtml($harga));
    }

    /** Bagian kalimat di luar rumus tetap aman dari HTML. */
    public function test_teks_di_luar_rumus_di_escape(): void
    {
        $hasil = RumusLatex::keHtml('Perhatikan <b>ini</b> lalu \(x^2\) selesai.');

        $this->assertStringContainsString('&lt;b&gt;', $hasil);
        $this->assertStringContainsString('x<sup>2</sup>', $hasil);
    }

    /** Perintah tak dikenal ditulis apa adanya, bukan hilang tanpa jejak. */
    public function test_perintah_tak_dikenal_tetap_terlihat(): void
    {
        $hasil = RumusLatex::keHtml('Rumus \(\binom{n}{k}\) itu.');

        $this->assertStringContainsString('binom', $hasil);
    }

    /** Kurung yang tidak lengkap tidak boleh membuat penguraian berputar. */
    public function test_rumus_cacat_tidak_menggantung(): void
    {
        $hasil = RumusLatex::keHtml('Rumus \(\frac{1}{2\) rusak.');

        $this->assertIsString($hasil);
        $this->assertStringContainsString('rusak', $hasil);
    }
}
