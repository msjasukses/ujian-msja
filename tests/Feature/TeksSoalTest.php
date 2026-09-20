<?php

namespace Tests\Feature;

use App\Support\TeksSoal;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Teks soal harus meloloskan penanda yang dipakai guru — pangkat, teks Arab,
 * pecahan bertingkat — sambil menahan apa pun yang bisa berjalan di peramban
 * pembacanya.
 */
class TeksSoalTest extends TestCase
{
    // =====================================================================
    // Yang harus lolos apa adanya
    // =====================================================================

    public function test_huruf_arab_dan_harakat_tidak_berubah(): void
    {
        $arab = 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ';

        $this->assertSame($arab, TeksSoal::polos($arab));
        $this->assertStringContainsString($arab, TeksSoal::html($arab)->toHtml());
    }

    public function test_blok_teks_arab_mempertahankan_arah_kanan_ke_kiri(): void
    {
        $hasil = TeksSoal::html(
            'Lafaz <span class="teks-arab" lang="ar" dir="rtl">الْحَمْدُ لِلَّهِ</span> artinya segala puji bagi Allah.'
        )->toHtml();

        $this->assertStringContainsString('dir="rtl"', $hasil);
        $this->assertStringContainsString('lang="ar"', $hasil);
        $this->assertStringContainsString('class="teks-arab"', $hasil);
        $this->assertStringContainsString('الْحَمْدُ لِلَّهِ', $hasil);
    }

    #[DataProvider('simbolMatematika')]
    public function test_simbol_matematika_tersimpan_utuh(string $simbol): void
    {
        $teks = "Hitunglah nilai dari {$simbol} pada soal berikut.";

        $this->assertStringContainsString($simbol, TeksSoal::html($teks)->toHtml());
        $this->assertStringContainsString($simbol, TeksSoal::polos($teks));
    }

    /** @return array<string, array{string}> */
    public static function simbolMatematika(): array
    {
        return collect([
            '× ÷ ± ∓', '≤ ≥ ≠ ≈ ≡', '∈ ∉ ⊂ ⊆ ∪ ∩ ∅',
            '√ ∛ ∑ ∏ ∫ ∂ ∆ ∇ ∞', 'α β γ θ λ π σ ω Δ Σ Ω',
            'x² y³ a⁴ n⁰', 'H₂O CO₂ aₙ', '½ ⅓ ¾ ⅞',
            '→ ⇒ ⇔ ↔', '90° ∠ABC ⊥ ∥ △', '٠١٢٣٤٥٦٧٨٩',
        ])->mapWithKeys(fn ($s) => [$s => [$s]])->all();
    }

    public function test_pangkat_dan_indeks_dipertahankan(): void
    {
        $hasil = TeksSoal::html('Luas = s<sup>2</sup> dan rumus air adalah H<sub>2</sub>O.')->toHtml();

        $this->assertStringContainsString('<sup>2</sup>', $hasil);
        $this->assertStringContainsString('<sub>2</sub>', $hasil);
    }

    public function test_pecahan_bertingkat_dan_akar_dipertahankan(): void
    {
        $pecahan = '<span class="pecahan"><span class="pembilang">2x + 1</span><span class="penyebut">3</span></span>';
        $akar = '<span class="akar"><span class="radikan">x² + 1</span></span>';

        $hasil = TeksSoal::html("Sederhanakan {$pecahan} lalu tentukan {$akar}.")->toHtml();

        $this->assertStringContainsString('class="pecahan"', $hasil);
        $this->assertStringContainsString('class="pembilang"', $hasil);
        $this->assertStringContainsString('class="penyebut"', $hasil);
        $this->assertStringContainsString('class="akar"', $hasil);
        $this->assertStringContainsString('class="radikan"', $hasil);
    }

    public function test_tabel_sederhana_dipertahankan(): void
    {
        $hasil = TeksSoal::html('<table><tr><td colspan="2">x</td><td>y</td></tr></table>')->toHtml();

        $this->assertStringContainsString('<table>', $hasil);
        $this->assertStringContainsString('colspan="2"', $hasil);
    }

    // =====================================================================
    // Yang harus ditahan
    // =====================================================================

    public function test_script_dibuang_berikut_isinya(): void
    {
        $hasil = TeksSoal::html('Soal biasa <script>fetch("/kunci-jawaban")</script> lanjutan.')->toHtml();

        $this->assertStringNotContainsString('<script', $hasil);
        $this->assertStringNotContainsString('fetch(', $hasil);
        // Kalimat gurunya sendiri tetap utuh.
        $this->assertStringContainsString('Soal biasa', $hasil);
        $this->assertStringContainsString('lanjutan.', $hasil);
    }

    public function test_penangan_kejadian_dibuang_tetapi_tulisannya_tetap(): void
    {
        $hasil = TeksSoal::html('<b onclick="curiJawaban()" onmouseover="x()">Perhatikan</b> gambar berikut.')->toHtml();

        $this->assertStringNotContainsString('onclick', $hasil);
        $this->assertStringNotContainsString('onmouseover', $hasil);
        $this->assertStringContainsString('<b>Perhatikan</b>', $hasil);
    }

    public function test_tag_di_luar_daftar_diganti_isinya(): void
    {
        $hasil = TeksSoal::html('<iframe src="https://situs-lain.test">Bacalah</iframe> teks ini.')->toHtml();

        $this->assertStringNotContainsString('<iframe', $hasil);
        $this->assertStringContainsString('Bacalah', $hasil);
    }

    public function test_kelas_di_luar_daftar_dibuang(): void
    {
        $hasil = TeksSoal::html('<span class="position-fixed top-0 teks-arab">ا</span>')->toHtml();

        $this->assertStringNotContainsString('position-fixed', $hasil);
        $this->assertStringContainsString('teks-arab', $hasil);
    }

    public function test_gambar_hanya_boleh_dari_server_sendiri(): void
    {
        $luar = TeksSoal::html('<img src="https://pelacak.test/1.png" alt="a">')->toHtml();
        $skema = TeksSoal::html('<img src="javascript:alert(1)" alt="a">')->toHtml();
        $lokal = TeksSoal::html('<img src="/storage/soal/grafik.png" alt="Grafik">')->toHtml();

        $this->assertStringNotContainsString('<img', $luar);
        $this->assertStringNotContainsString('<img', $skema);
        $this->assertStringContainsString('/storage/soal/grafik.png', $lokal);
    }

    public function test_teks_biasa_tetap_di_escape(): void
    {
        $hasil = TeksSoal::html('Jika 5 < 8 dan 9 > 2, maka pernyataan "A & B" benar.')->toHtml();

        $this->assertStringContainsString('&lt;', $hasil);
        $this->assertStringContainsString('&amp;', $hasil);
    }

    /**
     * Penanda komentar HTML tidak boleh ikut tampil maupun terhitung isi.
     *
     * Google Dokumen menyelipkan <!--StartFragment-->, <!--EndFragment-->,
     * dan penanda internalnya sendiri pada setiap salinan. Penanda itu tidak
     * kelihatan di layar, tetapi ikut terbawa saat guru menempel ke formulir.
     */
    public function test_komentar_html_dibuang(): void
    {
        $hasil = TeksSoal::html(
            '<!--StartFragment-->Perhatikan gambar<!--TgQPHd|||[]--> berikut.<!--EndFragment-->'
        )->toHtml();

        $this->assertStringNotContainsString('<!--', $hasil);
        $this->assertStringNotContainsString('StartFragment', $hasil);
        $this->assertStringContainsString('Perhatikan gambar', $hasil);
        $this->assertStringContainsString('berikut.', $hasil);
    }

    /** Isi yang hanya berupa komentar terhitung kosong. */
    public function test_isi_hanya_komentar_terhitung_kosong(): void
    {
        $this->assertTrue(TeksSoal::kosong('<!--StartFragment--><!--EndFragment-->'));
        $this->assertSame('', TeksSoal::polos('<!--StartFragment--><!--EndFragment-->'));
    }

    // =====================================================================
    // Perilaku pergantian baris
    // =====================================================================

    public function test_pergantian_baris_menjadi_br(): void
    {
        $this->assertStringContainsString('<br>', TeksSoal::html("Baris satu\nBaris dua")->toHtml());
    }

    public function test_isi_bertag_blok_tidak_ditambahi_br(): void
    {
        $hasil = TeksSoal::html("<table>\n<tr>\n<td>a</td>\n</tr>\n</table>")->toHtml();

        $this->assertStringNotContainsString('<br', $hasil);
    }

    public function test_polos_membuang_penanda_dan_memulihkan_entitas(): void
    {
        $polos = TeksSoal::polos('<b>Hitung</b> nilai 5 &lt; 8 dan <sup>2</sup> pangkat.');

        $this->assertSame('Hitung nilai 5 < 8 dan 2 pangkat.', $polos);
    }

    public function test_isi_kosong_aman(): void
    {
        $this->assertSame('', TeksSoal::html(null)->toHtml());
        $this->assertSame('', TeksSoal::html('   ')->toHtml());
        $this->assertSame('', TeksSoal::polos(null));
    }
}
