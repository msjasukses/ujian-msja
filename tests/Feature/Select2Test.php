<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Select2 untuk seluruh <select>.
 *
 * Perilakunya sendiri berjalan di peramban dan diperiksa di sana; yang dijaga
 * di sini adalah hal-hal yang, bila diam-diam kembali, merusak aplikasi tanpa
 * ada yang menyadarinya.
 */
class Select2Test extends TestCase
{
    use RefreshDatabase;

    private const ASET = [
        'vendor/select2/jquery.min.js' => 'jQuery v3.7.1',
        'vendor/select2/select2.min.js' => 'Select2 4.1.0-rc.0',
        'vendor/select2/i18n/id.js' => 'Select2 4.1.0-rc.0',
        'vendor/select2/select2.min.css' => '.select2-container',
        'vendor/select2/select2-bootstrap-5-theme.min.css' => 'Bootstrap 5 theme v1.3.0',
    ];

    /** Asetnya ada di server sendiri, bukan halaman galat yang terunduh. */
    public function test_aset_select2_tersimpan_lokal(): void
    {
        foreach (self::ASET as $berkas => $penanda) {
            $jalur = public_path($berkas);

            $this->assertFileExists($jalur);
            $this->assertStringContainsString($penanda, File::get($jalur), "{$berkas} bukan berkas yang diharapkan.");
        }
    }

    public function test_halaman_pengelola_memuat_select2_dari_server_sendiri(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-s2@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $html = $this->actingAs($admin)->get(route('soal.create'))->assertOk()->getContent();

        foreach (array_keys(self::ASET) as $berkas) {
            $this->assertStringContainsString(asset($berkas), $html);
        }

        // Skrip Select2 dimuat sebelum skrip halaman, supaya halaman yang ingin
        // memakai Select2Aplikasi sudah menemukannya.
        $this->assertLessThan(
            strrpos($html, '</body>'),
            strpos($html, 'vendor/select2/select2.min.js')
        );
    }

    /** Lembar ujian siswa juga — soal penjodohan memakai select. */
    public function test_tata_letak_siswa_memuat_select2(): void
    {
        $tataLetak = File::get(resource_path('views/layouts/siswa.blade.php'));

        $this->assertStringContainsString("@include('partials.select2')", $tataLetak);
    }

    /**
     * Tidak boleh ada lagi onchange langsung pada <select>.
     *
     * Atribut itu ikut terpanggil oleh jQuery saat Select2 mengubah nilainya,
     * lalu sekali lagi oleh jembatan kejadian change: formulir terkirim dua
     * kali. Penggantinya data-kirim-otomatis.
     */
    public function test_tidak_ada_onchange_langsung_pada_select(): void
    {
        foreach (File::allFiles(resource_path('views')) as $berkas) {
            $isi = $berkas->getContents();

            $this->assertDoesNotMatchRegularExpression('/<select[^>]*\sonchange=/i', $isi,
                $berkas->getRelativePathname().' masih memasang onchange langsung pada <select>.');
        }
    }

    public function test_jembatan_change_dan_tanpa_placeholder(): void
    {
        $partial = File::get(resource_path('views/partials/select2.blade.php'));

        // Tanpa jembatan, jawaban soal penjodohan diam-diam berhenti tersimpan:
        // kabar perubahan Select2 tidak sampai ke addEventListener('change').
        $this->assertStringContainsString("el.dispatchEvent(new Event('change', { bubbles: true }))", $partial);

        // Dengan placeholder, pilihan "Semua kelas" dan sejenisnya hilang dari
        // daftar dan tidak bisa dipilih kembali.
        $kode = preg_replace('#/\*.*?\*/#s', '', $partial);
        $this->assertStringNotContainsString('placeholder:', $kode);
    }
}
