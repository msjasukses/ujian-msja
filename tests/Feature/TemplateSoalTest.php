<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Template\Daftar;
use App\Support\Template\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Template soal lintas mata pelajaran.
 *
 * Yang diperiksa di sini bukan tampilannya melainkan kebenaran butirnya:
 * kunci harus sama dengan hitungan, gambar harus memuat angka yang dipakai
 * rumus, dan pengecoh harus berupa kekeliruan yang masuk akal — bukan angka
 * sembarang yang membuat siswa menebak.
 */
class TemplateSoalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Admin Uji', 'email' => 'template@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]));
    }

    /**
     * Isian yang tidak disebut memakai nilai awal template, supaya tiap tes
     * hanya menuliskan angka yang memang sedang diujinya.
     */
    protected function rakit(string $kode, array $isi = [], string $jenis = 'pg', ?int $benar = null): TestResponse
    {
        return $this->postJson(route('soal.template.rakit'), [
            'kode' => $kode,
            'isi' => $isi + Daftar::kelas($kode)::awal(),
            'jenis' => $jenis,
            'benar' => $benar ?? 1,
        ]);
    }

    // =====================================================================
    // Bangun datar
    // =====================================================================

    public function test_pilihan_ganda_luas_persegi_panjang(): void
    {
        $isi = $this->rakit('bangun-datar')->assertOk()->json();

        $this->assertSame('40 cm²', $isi['jawaban']);
        $this->assertStringContainsString('luas persegi panjang tersebut', $isi['teks']);

        // Opsi urut menaik, dan hanya satu yang bertanda kunci.
        $this->assertSame(['13 cm²', '26 cm²', '40 cm²', '80 cm²'], array_column($isi['opsi'], 'teks'));
        $this->assertSame(['C'], $isi['kunci']);
        $this->assertSame([false, false, true, false], array_column($isi['opsi'], 'benar'));

        $this->assertStringContainsString('Luas persegi panjang = p × l = 8 × 5 = 40 cm²', $isi['pembahasan']);
    }

    public function test_gambar_memuat_ukuran_yang_dipakai_rumus(): void
    {
        $svg = $this->rakit('bangun-datar')->json('svg');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('>8 cm<', $svg);
        $this->assertStringContainsString('>5 cm<', $svg);
    }

    public function test_pengecoh_berasal_dari_kekeliruan_yang_lazim(): void
    {
        // Keliling 2 × (8 + 5) = 26 dan luas 40 saling menjadi pengecoh,
        // begitu pula 8 + 5 = 13 serta 2 × 26 = 52.
        $opsi = array_column($this->rakit('bangun-datar', ['tanya' => 'keliling'])->json('opsi'), 'teks');

        $this->assertSame(['13 cm', '26 cm', '40 cm', '52 cm'], $opsi);
    }

    public function test_keliling_segitiga_siku_menghitung_sisi_miring(): void
    {
        $isi = $this->rakit('bangun-datar', [
            'bentuk' => 'segitiga_siku',
            'alas' => 8,
            'tinggi' => 6,
            'tanya' => 'keliling',
        ])->assertOk()->json();

        $this->assertSame('24 cm', $isi['jawaban']);
        $this->assertStringContainsString('Sisi miring = 10 cm', $isi['pembahasan']);
        // Pada soal keliling sisi miringnya ikut tertulis di gambar; pada
        // soal luas tidak, karena di sana angka itu tidak dipakai.
        $this->assertStringContainsString('>10 cm<', $isi['svg']);
    }

    public function test_sisi_turunan_tidak_ditulis_pada_soal_luas(): void
    {
        $svg = $this->rakit('bangun-datar', [
            'bentuk' => 'segitiga_siku',
            'alas' => 8,
            'tinggi' => 6,
        ])->json('svg');

        $this->assertStringNotContainsString('>10 cm<', $svg);
    }

    public function test_lingkaran_memakai_pi_yang_dipilih(): void
    {
        $isi = $this->rakit('bangun-datar', [
            'bentuk' => 'lingkaran',
            'jari_jari' => 7,
            'pi' => '22/7',
        ])->assertOk()->json();

        $this->assertSame('154 cm²', $isi['jawaban']);
        $this->assertStringContainsString('22/7 × 7 × 7', $isi['pembahasan']);
    }

    public function test_isian_singkat_mengisi_kunci_dan_kata_kunci(): void
    {
        $isi = $this->rakit('bangun-datar', [], 'essay')->assertOk()->json();

        $this->assertSame('40 cm²', $isi['kunci_essay']);
        $this->assertSame('40', $isi['kata_kunci']);
    }

    public function test_pernyataan_benar_salah_memakai_angka_pengecoh(): void
    {
        $benar = $this->rakit('bangun-datar', [], 'benar_salah', 1)->assertOk()->json();
        $salah = $this->rakit('bangun-datar', [], 'benar_salah', 0)->assertOk()->json();

        $this->assertSame('benar', $benar['kunci_bs']);
        $this->assertStringContainsString('adalah 40 cm²', $benar['teks']);

        $this->assertSame('salah', $salah['kunci_bs']);
        $this->assertStringContainsString('adalah 26 cm²', $salah['teks']);
    }

    // =====================================================================
    // Bangun ruang
    // =====================================================================

    public function test_volume_balok(): void
    {
        $isi = $this->rakit('bangun-ruang', ['panjang' => 6, 'lebar' => 3, 'tinggi' => 4])
            ->assertOk()->json();

        $this->assertSame('72 cm³', $isi['jawaban']);
        // Luas permukaan 108 dan luas satu sisi 18 jadi pengecoh: keduanya
        // hasil antara yang benar-benar muncul saat mengerjakan.
        $this->assertContains('108 cm³', array_column($isi['opsi'], 'teks'));
        $this->assertContains('18 cm³', array_column($isi['opsi'], 'teks'));
    }

    public function test_luas_permukaan_kubus(): void
    {
        $isi = $this->rakit('bangun-ruang', [
            'bentuk' => 'kubus', 'rusuk' => 5, 'tanya' => 'luas_permukaan',
        ])->assertOk()->json();

        $this->assertSame('150 cm²', $isi['jawaban']);
    }

    public function test_volume_tabung_memakai_pi(): void
    {
        $isi = $this->rakit('bangun-ruang', [
            'bentuk' => 'tabung', 'jari_jari' => 7, 'tinggi_tabung' => 10, 'pi' => '22/7',
        ])->assertOk()->json();

        $this->assertSame('1.540 cm³', $isi['jawaban']);
    }

    // =====================================================================
    // Pecahan
    // =====================================================================

    public function test_pecahan_bagian_yang_diarsir(): void
    {
        // Pecahannya disebut tegas, tidak bergantung nilai bawaan: nilai
        // bawaan harus sah untuk kesembilan jenis pertanyaan sekaligus
        // sehingga sewaktu-waktu dapat berubah.
        $isi = $this->rakit('pecahan', ['penyebut' => 4, 'pembilang' => 3])->assertOk()->json();

        $this->assertSame('3/4', $isi['jawaban']);
        $this->assertSame(['1/4', '2/4', '3/4', '4/3'], array_column($isi['opsi'], 'teks'));
        $this->assertStringContainsString('bagian yang diarsir', $isi['teks']);
    }

    public function test_pecahan_bagian_yang_tidak_diarsir(): void
    {
        $isi = $this->rakit('pecahan', [
            'tanya' => 'tidak_arsir', 'penyebut' => 4, 'pembilang' => 3,
        ])->assertOk()->json();

        $this->assertSame('1/4', $isi['jawaban']);
    }

    public function test_pecahan_menolak_arsiran_melebihi_bagiannya(): void
    {
        $this->rakit('pecahan', ['penyebut' => 4, 'pembilang' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('isi.pembilang');
    }

    // =====================================================================
    // Jam & waktu
    // =====================================================================

    public function test_membaca_jam_analog(): void
    {
        $isi = $this->rakit('jam-waktu', ['jam' => 7, 'menit' => 45])->assertOk()->json();

        $this->assertSame('07.45', $isi['jawaban']);
        // Membaca jarum pendek sebagai angka berikutnya adalah kekeliruan
        // khas soal ini, jadi 08.45 memang disediakan.
        $this->assertContains('08.45', array_column($isi['opsi'], 'teks'));
    }

    public function test_menghitung_waktu_selesai(): void
    {
        $isi = $this->rakit('jam-waktu', [
            'tanya' => 'selesai', 'jam' => 7, 'menit' => 45, 'durasi_jam' => 1, 'durasi_menit' => 30,
        ])->assertOk()->json();

        $this->assertSame('09.15', $isi['jawaban']);
        $this->assertStringContainsString('1 jam 30 menit', $isi['teks']);
    }

    // =====================================================================
    // Diagram batang
    // =====================================================================

    public function test_diagram_batang_nilai_tertinggi(): void
    {
        $isi = $this->rakit('diagram-data')->assertOk()->json();

        $this->assertSame('Kamis', $isi['jawaban']);
        // Pengecohnya nama kategori lain yang memang ada di diagram itu.
        $this->assertContains('Senin', array_column($isi['opsi'], 'teks'));
    }

    public function test_diagram_batang_selisih(): void
    {
        $isi = $this->rakit('diagram-data', ['tanya' => 'selisih'])->assertOk()->json();

        $this->assertSame('5 buku', $isi['jawaban']);
    }

    public function test_diagram_batang_menolak_data_yang_tidak_sepadan(): void
    {
        $this->rakit('diagram-data', ['nilai' => '4, 7'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('isi.nilai');
    }

    // =====================================================================
    // IPA & IPS
    // =====================================================================

    public function test_konversi_suhu_ke_reamur(): void
    {
        $isi = $this->rakit('suhu-termometer', ['suhu' => 60, 'skala' => 'reamur'])->assertOk()->json();

        $this->assertSame('48 °R', $isi['jawaban']);
    }

    public function test_konversi_suhu_ke_fahrenheit_menyediakan_pengecoh_lupa_tambah_32(): void
    {
        $isi = $this->rakit('suhu-termometer', ['suhu' => 60, 'skala' => 'fahrenheit'])->assertOk()->json();

        $this->assertSame('140 °F', $isi['jawaban']);
        $this->assertContains('108 °F', array_column($isi['opsi'], 'teks'));
    }

    public function test_hambatan_pengganti_rangkaian_seri(): void
    {
        $isi = $this->rakit('rangkaian-listrik')->assertOk()->json();

        $this->assertSame('9 Ω', $isi['jawaban']);
        // Hasil rumus susunan yang satunya ikut jadi pilihan, jadi siswa yang
        // salah membaca skema menemukan jawabannya di daftar.
        $this->assertContains('2 Ω', array_column($isi['opsi'], 'teks'));
    }

    public function test_kuat_arus_rangkaian_paralel(): void
    {
        $isi = $this->rakit('rangkaian-listrik', [
            'susunan' => 'paralel', 'r1' => 6, 'r2' => 3, 'tegangan' => 12, 'tanya' => 'arus',
        ])->assertOk()->json();

        $this->assertSame('6 A', $isi['jawaban']);
    }

    public function test_gaya_kuasa_pada_tuas(): void
    {
        $isi = $this->rakit('pesawat-sederhana')->assertOk()->json();

        $this->assertSame('200 N', $isi['jawaban']);
        $this->assertStringContainsString('KM = lengan kuasa ÷ lengan beban', $isi['pembahasan']);
    }

    public function test_keuntungan_mekanis_bidang_miring(): void
    {
        $isi = $this->rakit('pesawat-sederhana', [
            'jenis_alat' => 'bidang_miring', 'tinggi' => 2, 'panjang' => 6, 'tanya' => 'keuntungan',
        ])->assertOk()->json();

        $this->assertSame('3', $isi['jawaban']);
    }

    public function test_bidang_miring_menolak_panjang_lebih_pendek_dari_tinggi(): void
    {
        $this->rakit('pesawat-sederhana', [
            'jenis_alat' => 'bidang_miring', 'tinggi' => 6, 'panjang' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('isi.panjang');
    }

    public function test_jarak_sebenarnya_dari_skala_peta(): void
    {
        $isi = $this->rakit('skala-peta', ['skala' => 1500000, 'jarak_peta' => 4])->assertOk()->json();

        $this->assertSame('60 km', $isi['jawaban']);
        // Lupa mengubah sentimeter ke kilometer berselisih kelipatan sepuluh.
        $this->assertContains('600 km', array_column($isi['opsi'], 'teks'));
    }

    public function test_jarak_pada_peta_dari_jarak_sebenarnya(): void
    {
        $isi = $this->rakit('skala-peta', [
            'tanya' => 'peta', 'skala' => 1500000, 'jarak_nyata' => 60,
        ])->assertOk()->json();

        $this->assertSame('4 cm', $isi['jawaban']);
    }

    // =====================================================================
    // IPA & Fisika
    // =====================================================================

    public function test_membaca_jangka_sorong(): void
    {
        $isi = $this->rakit('alat-ukur-besaran', ['alat' => 'jangka_sorong', 'sorong' => 3.47])
            ->assertOk()->json();

        $this->assertSame('3,47 cm', $isi['jawaban']);
        // Skala utamanya saja — tanpa nonius — memang disediakan sebagai
        // pengecoh karena itu kekeliruan pokoknya.
        $this->assertContains('3,40 cm', array_column($isi['opsi'], 'teks'));
    }

    public function test_tekanan_hidrostatis(): void
    {
        $isi = $this->rakit('tekanan-fluida', [
            'tanya' => 'hidrostatis', 'massa_jenis' => 1000, 'kedalaman' => 2.5,
        ])->assertOk()->json();

        $this->assertSame('25.000 N/m²', $isi['jawaban']);
    }

    public function test_gaya_pada_dongkrak_hidrolik(): void
    {
        $isi = $this->rakit('tekanan-fluida', [
            'tanya' => 'hidrolik', 'luas_1' => 20, 'luas_2' => 500, 'gaya_1' => 40,
        ])->assertOk()->json();

        $this->assertSame('1.000 N', $isi['jawaban']);
    }

    public function test_resultan_gaya_berlawanan_arah(): void
    {
        $isi = $this->rakit('gaya-gerak', [
            'gaya_kiri' => 15, 'gaya_kanan' => 40, 'gaya_kanan_2' => 0,
        ])->assertOk()->json();

        $this->assertSame('25 N', $isi['jawaban']);
        // Menjumlahkan tanpa memperhatikan arah menghasilkan 55 N.
        $this->assertContains('55 N', array_column($isi['opsi'], 'teks'));
    }

    public function test_energi_kinetik(): void
    {
        $isi = $this->rakit('bentuk-energi', [
            'tanya' => 'kinetik', 'massa' => 4, 'kecepatan' => 6,
        ])->assertOk()->json();

        $this->assertSame('72 joule', $isi['jawaban']);
    }

    public function test_kalor_untuk_menaikkan_suhu(): void
    {
        $isi = $this->rakit('suhu-termometer', [
            'tanya' => 'kalor', 'massa' => 2, 'kalor_jenis' => 4200, 'kenaikan' => 30,
        ])->assertOk()->json();

        $this->assertSame('252.000 joule', $isi['jawaban']);
    }

    public function test_panjang_gelombang_dari_gambar(): void
    {
        $isi = $this->rakit('getaran-gelombang', [
            'panjang_tali' => 6, 'jumlah_gelombang' => 3,
        ])->assertOk()->json();

        $this->assertSame('2 m', $isi['jawaban']);
    }

    public function test_sudut_pantul_sama_dengan_sudut_datang(): void
    {
        $isi = $this->rakit('getaran-gelombang', [
            'tanya' => 'pemantulan', 'sudut_datang' => 35,
        ])->assertOk()->json();

        $this->assertSame('35°', $isi['jawaban']);
    }

    public function test_urutan_planet(): void
    {
        $isi = $this->rakit('bumi-tata-surya', ['urutan' => 5])->assertOk()->json();

        $this->assertSame('Yupiter', $isi['jawaban']);
    }

    public function test_kutub_magnet_yang_tarik_menarik(): void
    {
        $isi = $this->rakit('rangkaian-listrik', [
            'tanya' => 'magnet', 'sifat' => 'tarik', 'kutub_kiri' => 'U',
        ])->assertOk()->json();

        $this->assertSame('Selatan', $isi['jawaban']);
    }

    public function test_keuntungan_mekanis_roda_berporos(): void
    {
        $isi = $this->rakit('pesawat-sederhana', [
            'jenis_alat' => 'roda_berporos', 'jari_roda' => 40, 'jari_poros' => 8,
            'tanya' => 'keuntungan',
        ])->assertOk()->json();

        $this->assertSame('5', $isi['jawaban']);
    }

    // =====================================================================
    // Berlaku untuk seluruh template
    // =====================================================================
    // Pecahan: menyenilaikan, membandingkan, dan menghitung
    // =====================================================================

    public function test_pecahan_setengah_tetap_berpilihan_empat(): void
    {
        // 1/2 adalah pecahan terpendek yang mungkin. Pengecoh "kurang satu
        // bagian" dan "lebih satu bagian" sama-sama jatuh ke bilangan tak
        // sah di sini, sehingga dahulu pilihannya hanya tiga.
        $isi = $this->rakit('pecahan', ['penyebut' => 2, 'pembilang' => 1])->assertOk()->json();

        $this->assertCount(4, $isi['opsi']);
        $this->assertSame('1/2', $isi['jawaban']);
    }

    public function test_pecahan_senilai_bukan_menambah_kedua_bilangan(): void
    {
        $isi = $this->rakit('pecahan', [
            'tanya' => 'senilai', 'penyebut' => 4, 'pembilang' => 3,
        ])->assertOk()->json();

        $this->assertSame('6/8', $isi['jawaban']);
        $this->assertContains('4/5', array_column($isi['opsi'], 'teks'));
    }

    public function test_pecahan_menolak_menyederhanakan_yang_sudah_sederhana(): void
    {
        $this->rakit('pecahan', ['tanya' => 'sederhana', 'penyebut' => 4, 'pembilang' => 3])
            ->assertStatus(422)
            ->assertJsonValidationErrors('isi.pembilang');
    }

    public function test_pecahan_menolak_desimal_yang_tak_berhenti(): void
    {
        $this->rakit('pecahan', ['tanya' => 'desimal', 'penyebut' => 3, 'pembilang' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('isi.penyebut');
    }

    public function test_pecahan_menjumlah_menyamakan_penyebut_dahulu(): void
    {
        $isi = $this->rakit('pecahan', [
            'tanya' => 'jumlah',
            'penyebut' => 4, 'pembilang' => 1,
            'penyebut_dua' => 6, 'pembilang_dua' => 1,
        ])->assertOk()->json();

        $this->assertSame('5/12', $isi['jawaban']);
        // Kekeliruan paling sering: pembilang dengan pembilang dan penyebut
        // dengan penyebut. Harus ada di daftar supaya butirnya mengukur.
        $this->assertContains('2/10', array_column($isi['opsi'], 'teks'));
    }

    public function test_pecahan_hasil_satu_utuh_tidak_ditulis_satu_per_satu(): void
    {
        $isi = $this->rakit('pecahan', [
            'tanya' => 'jumlah',
            'penyebut' => 2, 'pembilang' => 1,
            'penyebut_dua' => 2, 'pembilang_dua' => 1,
        ])->assertOk()->json();

        $this->assertSame('1', $isi['jawaban']);
    }

    public function test_pecahan_menolak_membandingkan_dua_pecahan_kembar(): void
    {
        $this->rakit('pecahan', [
            'tanya' => 'banding',
            'penyebut' => 4, 'pembilang' => 1,
            'penyebut_dua' => 4, 'pembilang_dua' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('isi.pembilang_dua');
    }

    public function test_pecahan_senilai_yang_tertulis_berbeda_tetap_dibandingkan(): void
    {
        $isi = $this->rakit('pecahan', [
            'tanya' => 'banding',
            'penyebut' => 4, 'pembilang' => 2,
            'penyebut_dua' => 2, 'pembilang_dua' => 1,
        ])->assertOk()->json();

        $this->assertStringContainsString('sama besar', $isi['jawaban']);
    }

    // =====================================================================
    // Pewarisan golongan darah
    // =====================================================================

    public function test_golongan_darah_ab_dengan_o_hanya_melahirkan_a_dan_b(): void
    {
        $isi = $this->rakit('golongan-darah', ['ayah' => 'AB', 'ibu' => 'OO'])
            ->assertOk()->json();

        $this->assertSame('A dan B', $isi['jawaban']);
    }

    public function test_golongan_darah_peluangnya_dihitung_dari_empat_kotak(): void
    {
        $isi = $this->rakit('golongan-darah', [
            'tanya' => 'persen', 'ayah' => 'AO', 'ibu' => 'AO', 'sasaran' => 'A',
        ])->assertOk()->json();

        $this->assertSame('75%', $isi['jawaban']);
    }

    public function test_golongan_darah_menolak_pasangan_tanpa_golongan_mustahil(): void
    {
        // Soal "yang tidak mungkin" tidak punya jawaban kalau keempat
        // golongan dapat muncul.
        $this->rakit('golongan-darah', [
            'tanya' => 'tidak_mungkin', 'ayah' => 'AO', 'ibu' => 'BO',
        ])->assertStatus(422)->assertJsonValidationErrors('isi.ibu');
    }

    public function test_golongan_darah_menuliskan_alel_sebagai_pangkat(): void
    {
        $isi = $this->rakit('golongan-darah', ['ayah' => 'AO', 'ibu' => 'BO'])
            ->assertOk()->json();

        // Lambang alel harus naik, baik pada teks butir maupun gambarnya —
        // "I^A" yang bocor apa adanya berarti penandanya tidak terolah.
        $this->assertStringContainsString('I<sup>A</sup>', $isi['teks']);
        $this->assertStringNotContainsString('I^', strip_tags($isi['teks']));
        $this->assertStringNotContainsString('I^', $isi['pembahasan']);
    }

    // =====================================================================
    // Simbiosis
    // =====================================================================

    public function test_simbiosis_benalu_termasuk_parasitisme(): void
    {
        $isi = $this->rakit('simbiosis', ['pasangan' => 'benalu_inang'])->assertOk()->json();

        $this->assertSame('Parasitisme', $isi['jawaban']);
    }

    public function test_simbiosis_gambar_tidak_membocorkan_untung_ruginya(): void
    {
        $isi = $this->rakit('simbiosis', ['pasangan' => 'lebah_bunga'])->assertOk()->json();

        foreach (['diuntungkan', 'dirugikan', 'mutualisme'] as $bocor) {
            $this->assertStringNotContainsString($bocor, $isi['svg']);
        }
    }

    // =====================================================================
    // Listrik: daya, energi, dan biaya
    // =====================================================================

    public function test_listrik_daya_dihitung_dari_tegangan_dan_arus(): void
    {
        $isi = $this->rakit('rangkaian-listrik', [
            'tanya' => 'daya', 'susunan' => 'seri', 'r1' => 6, 'r2' => 3, 'tegangan' => 12,
        ])->assertOk()->json();

        // Rt = 9 Ω, I = 12/9 A, P = 12 × 12/9 = 16 watt.
        $this->assertSame('16 watt', $isi['jawaban']);
    }

    public function test_listrik_energi_dibagi_seribu_menjadi_kwh(): void
    {
        $isi = $this->rakit('rangkaian-listrik', [
            'tanya' => 'energi', 'alat' => 'setrika', 'banyak_alat' => 1,
            'jam' => 2, 'hari' => 30,
        ])->assertOk()->json();

        // 300 watt = 0,3 kW; 0,3 × 2 × 30 = 18 kWh.
        $this->assertSame('18 kWh', $isi['jawaban']);
    }

    public function test_listrik_biaya_dibulatkan_ke_rupiah_penuh(): void
    {
        $isi = $this->rakit('rangkaian-listrik', [
            'tanya' => 'biaya', 'alat' => 'kulkas', 'banyak_alat' => 1,
            'jam' => 24, 'hari' => 30, 'tarif' => 1444,
        ])->assertOk()->json();

        // 86,4 kWh × Rp1.444 = Rp124.761,60 → dibulatkan ke rupiah penuh.
        $this->assertSame('Rp124.762', $isi['jawaban']);
    }

    // =====================================================================
    // Informatika
    // =====================================================================

    public function test_sistem_bilangan_biner_ke_desimal(): void
    {
        $isi = $this->rakit('sistem-bilangan', [
            'tanya' => 'ke_desimal', 'basis' => 'biner', 'nilai' => 45,
        ])->assertOk()->json();

        $this->assertSame('45', $isi['jawaban']);
        $this->assertStringContainsString('101101', $isi['teks']);
    }

    public function test_sistem_bilangan_menolak_nilai_tempat_berangka_nol(): void
    {
        // 45 biner = 101101; angka ke-2 dari kanan adalah 0, sehingga
        // jawabannya pasti 0 dan seluruh pengecohnya ikut nol.
        $this->rakit('sistem-bilangan', [
            'tanya' => 'nilai_tempat', 'basis' => 'biner', 'nilai' => 45, 'tempat' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('isi.tempat');
    }

    public function test_gerbang_logika_menanyakan_kolom_keluaran_bukan_satu_nilai(): void
    {
        $isi = $this->rakit('gerbang-logika', ['tanya' => 'keluaran', 'gerbang' => 'and'])
            ->assertOk()->json();

        $this->assertSame('0, 0, 0, 1', $isi['jawaban']);

        // Keluaran satu pasang masukan hanya 0 atau 1, sehingga dua dari
        // empat pilihan pasti bernilai sama. Pilihannya harus benar-benar
        // berbeda satu sama lain.
        $teks = array_column($isi['opsi'], 'teks');
        $this->assertSame($teks, array_unique($teks));
    }

    public function test_gerbang_logika_rangkaian_menyebut_nilai_antaranya(): void
    {
        $isi = $this->rakit('gerbang-logika', [
            'tanya' => 'rangkaian', 'gerbang' => 'and', 'gerbang_dua' => 'or',
            'a' => '1', 'b' => '0', 'c' => '1',
        ])->assertOk()->json();

        // AND(1,0) = 0, lalu OR(0,1) = 1.
        $this->assertStringContainsString('= 0', $isi['jawaban']);
        $this->assertStringContainsString('Y = 1', $isi['jawaban']);
    }

    public function test_lembar_kerja_menghitung_rentang_sel(): void
    {
        $isi = $this->rakit('lembar-kerja', [
            'tanya' => 'hasil', 'fungsi' => 'SUM', 'lajur' => 'B',
        ])->assertOk()->json();

        // Lajur B berisi 80, 92, 70, dan 85.
        $this->assertSame('327', $isi['jawaban']);
    }

    public function test_lembar_kerja_alamat_sel_ditulis_lajur_dahulu(): void
    {
        $isi = $this->rakit('lembar-kerja', [
            'tanya' => 'alamat', 'lajur' => 'C', 'baris' => 5,
        ])->assertOk()->json();

        $this->assertSame('C5', $isi['jawaban']);
        $this->assertContains('5C', array_column($isi['opsi'], 'teks'));
    }

    // =====================================================================
    // IPS
    // =====================================================================

    public function test_zona_waktu_dihitung_dari_bujurnya(): void
    {
        $isi = $this->rakit('zona-waktu', ['tanya' => 'zona', 'kota' => 'Banda Aceh'])
            ->assertOk()->json();

        $this->assertStringContainsString('WIB', $isi['jawaban']);
    }

    public function test_garis_waktu_selisih_dihitung_dari_tahunnya(): void
    {
        $isi = $this->rakit('garis-waktu', [
            'tanya' => 'selisih', 'peristiwa' => 'proklamasi', 'peristiwa_dua' => 'reformasi',
        ])->assertOk()->json();

        $this->assertSame('53 tahun', $isi['jawaban']);
    }

    public function test_garis_waktu_menolak_dua_peristiwa_yang_sama(): void
    {
        $this->rakit('garis-waktu', [
            'tanya' => 'selisih', 'peristiwa' => 'proklamasi', 'peristiwa_dua' => 'proklamasi',
        ])->assertStatus(422)->assertJsonValidationErrors('isi.peristiwa_dua');
    }

    public function test_piramida_penduduk_membaca_kelompok_terbanyak(): void
    {
        $isi = $this->rakit('piramida-penduduk', [
            'tanya' => 'kelompok', 'bentuk' => 'ekspansif',
        ])->assertOk()->json();

        $this->assertSame('0–14 tahun', $isi['jawaban']);
    }

    public function test_arus_ekonomi_arah_panah_menentukan_pelakunya(): void
    {
        $isi = $this->rakit('arus-ekonomi', ['tanya' => 'pelaku', 'nomor' => 2])
            ->assertOk()->json();

        $this->assertSame('Rumah Tangga Produsen', $isi['jawaban']);
    }

    /**
     * Nilai bawaan tiap template harus sah untuk setiap jenis pertanyaannya.
     *
     * Guru yang membuka sebuah template lalu berganti jenis pertanyaan tanpa
     * mengubah isian lain harus tetap mendapat butir, bukan pesan kesalahan.
     */
    public function test_nilai_bawaan_sah_untuk_tiap_jenis_pertanyaan(): void
    {
        $ditolak = [];

        foreach (Daftar::TEMPLATE as $kelas) {
            $awal = $kelas::awal();
            $kolomTanya = null;

            foreach ($kelas::kolom() as $kolom) {
                if ($kolom['nama'] === 'tanya') {
                    $kolomTanya = $kolom;
                }
            }

            foreach ($kolomTanya ? array_keys($kolomTanya['pilihan']) : [null] as $tanya) {
                $isi = $tanya === null ? $awal : ['tanya' => $tanya] + $awal;
                $galat = $kelas::periksa($isi);

                if ($galat !== []) {
                    $ditolak[] = $kelas::kode().' tanya='.$tanya.': '.implode(' ', $galat);
                }
            }
        }

        $this->assertSame([], $ditolak);
    }

    // =====================================================================

    public function test_ukuran_wajib_lengkap(): void
    {
        $this->postJson(route('soal.template.rakit'), [
            'kode' => 'bangun-datar',
            'isi' => ['bentuk' => 'persegi_panjang', 'panjang' => 8, 'satuan' => 'cm', 'tanya' => 'luas', 'warna' => 'biru'],
            'jenis' => 'pg',
        ])->assertStatus(422)->assertJsonValidationErrors('isi.lebar');
    }

    public function test_ukuran_yang_tidak_mungkin_digambar_ditolak(): void
    {
        $this->rakit('bangun-datar', [
            'bentuk' => 'jajar_genjang', 'alas' => 10, 'tinggi' => 6, 'sisi_miring' => 4,
        ])->assertStatus(422)->assertJsonValidationErrors('isi.sisi_miring');
    }

    public function test_template_tak_dikenal_ditolak(): void
    {
        $this->postJson(route('soal.template.rakit'), [
            'kode' => 'tidak-ada', 'isi' => [], 'jenis' => 'pg',
        ])->assertStatus(422)->assertJsonValidationErrors('kode');
    }

    /**
     * Tiap template, pada nilai awalnya dan tiap pilihan "yang ditanyakan",
     * harus menghasilkan butir yang utuh: empat opsi, satu kunci, gambar yang
     * berupa SVG, dan pembahasan yang terisi.
     */
    public function test_semua_template_menghasilkan_butir_yang_utuh(): void
    {
        foreach (Daftar::semua() as $kode => $kelas) {
            $tanya = collect($kelas::kolom())->firstWhere('nama', 'tanya');

            foreach (array_keys($tanya['pilihan'] ?? ['' => '']) as $pilihan) {
                $isi = $this->rakit($kode, $pilihan === '' ? [] : ['tanya' => $pilihan])
                    ->assertOk()
                    ->json();

                $konteks = "{$kode}.{$pilihan}";

                $this->assertCount(4, $isi['opsi'], "{$konteks}: opsi tidak empat");
                $this->assertCount(1, $isi['kunci'], "{$konteks}: kunci tidak tunggal");
                $this->assertSame(
                    1,
                    count(array_filter(array_column($isi['opsi'], 'benar'))),
                    "{$konteks}: opsi benar tidak tepat satu",
                );
                $this->assertStringStartsWith('<svg', (string) $isi['svg'], "{$konteks}: tanpa gambar");
                $this->assertNotEmpty($isi['pembahasan'], "{$konteks}: tanpa pembahasan");
                $this->assertNotEmpty($isi['teks'], "{$konteks}: tanpa teks soal");
            }
        }
    }

    /**
     * Sapuan menyeluruh: tiap template dicoba pada seluruh gabungan pilihan
     * yang mungkin — jenis bangun × yang ditanyakan × satuan dan seterusnya,
     * termasuk tiap nilai kolom angka yang jangkauannya pendek seperti
     * "planet urutan ke-" atau "organ ke-" — lalu diperiksa apakah butirnya
     * tetap utuh.
     *
     * Tes bersampel di atas pernah meloloskan empat kekeliruan yang hanya
     * muncul pada gabungan tertentu: konversi ml ke cm³ yang pengalinya satu
     * sehingga pengecohnya berimpit dengan kunci, katrol tetap yang gaya
     * kuasanya sama dengan berat bebannya, jendela pengecoh planet yang
     * kehabisan tetangga di urutan terluar, dan peluang satu uang logam yang
     * ketiga pengecohnya saling berimpit. Sapuan inilah yang menangkapnya.
     */
    public function test_setiap_gabungan_pilihan_menghasilkan_butir_yang_utuh(): void
    {
        $diuji = 0;

        foreach (Daftar::semua() as $kode => $kelas) {
            $template = Daftar::buat($kode);

            foreach ($this->gabungan($kelas) as $pilihan) {
                $isi = $pilihan + $kelas::awal();

                // Gabungan yang memang ditolak template bukan kekeliruan.
                if ($kelas::periksa($isi) !== []) {
                    continue;
                }

                $diuji++;
                $konteks = $kode.' '.json_encode($pilihan);
                $butir = $template->rakit($isi, 'pg');

                $this->assertCount(4, $butir['opsi'], "{$konteks}: opsi tidak empat");
                $this->assertCount(1, $butir['kunci'], "{$konteks}: kunci tidak tunggal");
                $this->assertSame(
                    1,
                    count(array_filter(array_column($butir['opsi'], 'benar'))),
                    "{$konteks}: opsi benar tidak tepat satu",
                );

                $svg = (string) $butir['svg'];

                $this->assertStringStartsWith('<svg', $svg, "{$konteks}: tanpa gambar");
                $this->assertGreaterThan(260, strlen($svg), "{$konteks}: gambarnya nyaris kosong");
                $this->assertDoesNotMatchRegularExpression(
                    '/="-?(NAN|INF)"/',
                    $svg,
                    "{$konteks}: gambar memuat angka tak hingga",
                );
                $this->assertNotEmpty($butir['pembahasan'], "{$konteks}: tanpa pembahasan");
            }
        }

        $this->assertGreaterThan(6000, $diuji, 'Sapuannya terlalu sedikit; ada kolom pilihan yang terlewat.');
    }

    /**
     * Seluruh gabungan nilai dari kolom bertipe pilihan pada sebuah template,
     * beserta tiap nilai kolom angka yang jangkauannya pendek.
     *
     * Kolom angka berjangkauan panjang — panjang sisi, banyak keturunan,
     * kadar larutan — sengaja tidak disapu: nilainya bersinambung, dan
     * menyapunya hanya akan mengulang jalur kode yang sama ribuan kali.
     * Yang berjangkauan pendek justru memilih cabang, misalnya planet
     * keberapa atau organ keberapa yang ditanyakan.
     *
     * @param  class-string<Template>  $kelas
     * @return array<int, array<string, int|string>>
     */
    protected function gabungan(string $kelas): array
    {
        $gabungan = [[]];

        foreach ($kelas::kolom() as $kolom) {
            $nilai = match (true) {
                in_array($kolom['jenis'], ['pilihan', 'pilihan-ikon'], true) => array_keys($kolom['pilihan']),
                $kolom['jenis'] === 'angka' && ($kolom['langkah'] ?? null) === 1
                    && ($kolom['max'] ?? PHP_INT_MAX) - ($kolom['min'] ?? 0) <= 7 => range((int) $kolom['min'], (int) $kolom['max']),
                default => null,
            };

            if ($nilai === null) {
                continue;
            }

            $baru = [];

            foreach ($gabungan as $sebelum) {
                foreach ($nilai as $satu) {
                    $baru[] = $sebelum + [$kolom['nama'] => $satu];
                }
            }

            // Dibatasi supaya template berkolom pilihan banyak tidak meledak
            // jumlah gabungannya; yang penting tiap nilai pernah tercoba.
            $gabungan = array_slice($baru, 0, 2000);
        }

        return $gabungan;
    }

    /** Tiap bentuk pada template yang punya pilihan bentuk ikut tergambar. */
    public function test_semua_bentuk_tergambar(): void
    {
        foreach (Daftar::semua() as $kode => $kelas) {
            $bentuk = collect($kelas::kolom())
                ->first(fn ($k) => $k['jenis'] === 'pilihan-ikon');

            foreach (array_keys($bentuk['pilihan'] ?? []) as $pilihan) {
                $svg = $this->rakit($kode, [$bentuk['nama'] => $pilihan])
                    ->assertOk()
                    ->json('svg');

                $this->assertStringStartsWith('<svg', (string) $svg, "{$kode}.{$pilihan} tidak tergambar");
            }
        }
    }

    /**
     * Ikon tiap template harus benar-benar ada di berkas ikonnya.
     *
     * Nama ikon yang keliru tidak menggagalkan apa pun — kartunya hanya
     * tampil tanpa gambar di galeri, dan itu baru ketahuan saat guru
     * membukanya. Ikon juga tidak boleh kembar supaya kartu-kartu pada
     * daftar tetap dapat dibedakan sekilas.
     */
    public function test_ikon_tiap_template_ada_dan_tidak_kembar(): void
    {
        $berkas = public_path('vendor/bootstrap-icons/bootstrap-icons.min.css');

        $this->assertFileExists($berkas, 'Berkas ikon tidak ditemukan; jalurnya berubah?');

        $tersedia = file_get_contents($berkas);
        $dipakai = [];

        foreach (Daftar::semua() as $kode => $kelas) {
            $ikon = $kelas::ikon();

            $this->assertStringContainsString(
                '.'.$ikon.'::before',
                $tersedia,
                "{$kode}: ikon {$ikon} tidak ada di Bootstrap Icons",
            );

            $this->assertArrayNotHasKey(
                $ikon,
                $dipakai,
                "{$kode}: ikon {$ikon} sudah dipakai ".($dipakai[$ikon] ?? ''),
            );

            $dipakai[$ikon] = $kode;
        }
    }

    /**
     * Teks yang diketik guru tidak boleh berubah menjadi markah.
     *
     * Teks butir, opsi, dan pembahasan sengaja berisi HTML — rumus kimia
     * memakai <sub>, pembahasan memakai <br> — dan formulir menyisipkannya
     * apa adanya ke editor. Karena itu isian bebas harus diloloskan lebih
     * dahulu oleh templatenya; kalau tidak, yang diketik guru ikut berjalan
     * di layar siswa saat soalnya dikerjakan.
     */
    public function test_isian_bebas_tidak_menjadi_markah(): void
    {
        $jahat = '<script>alert(1)</script>';
        $diuji = 0;

        foreach (Daftar::semua() as $kode => $kelas) {
            $template = Daftar::buat($kode);

            foreach ($kelas::kolom() as $kolom) {
                if ($kolom['jenis'] !== 'teks') {
                    continue;
                }

                foreach ($this->gabungan($kelas) as $pilihan) {
                    $isi = [$kolom['nama'] => $this->racuni((string) ($kolom['awal'] ?? ''), $jahat)]
                        + $pilihan + $kelas::awal();

                    if ($kelas::periksa($isi) !== []) {
                        continue;
                    }

                    $diuji++;
                    $butir = $template->rakit($isi, 'pg');
                    $konteks = $kode.'.'.$kolom['nama'].' '.json_encode($pilihan);

                    $terbaca = $butir['teks'].$butir['pembahasan']
                        .implode('', array_column($butir['opsi'], 'teks'));

                    $this->assertStringNotContainsString('<script', $terbaca, "{$konteks}: ketikan guru jadi markah");
                }
            }
        }

        $this->assertGreaterThan(20, $diuji, 'Tidak ada kolom isian bebas yang teruji.');
    }

    /**
     * Sisipkan teks berbahaya ke dalam isian bawaan sambil mempertahankan
     * bentuknya: isian berupa daftar berkoma hanya diganti bagian pertamanya,
     * supaya banyak anggotanya tetap lolos pemeriksaan template.
     */
    protected function racuni(string $awal, string $jahat): string
    {
        $bagian = explode(',', $awal);

        if (count($bagian) < 2) {
            return $jahat;
        }

        $bagian[0] = $jahat;

        return implode(',', $bagian);
    }
}
