<?php

namespace Tests\Feature;

use App\Support\BerkasDataCenter;
use Tests\TestCase;

/**
 * Pencarian berkas logo milik Data Center.
 *
 * Inti fiturnya: letak berkasnya tidak pernah diketahui pasti — nama folder
 * aplikasi Data Center bebas, kedalamannya berbeda-beda, dan berkasnya kadang
 * mendarat di public/storage. Yang diuji di sini adalah janji itu: ketemu
 * walau tidak ada satu pun calon yang benar, tanpa salah menunjuk berkas lain
 * yang kebetulan bernama sama.
 */
class BerkasDataCenterTest extends TestCase
{
    protected string $akar;

    protected string $logo = 'sekolah/uji-logo-datacenter.png';

    protected function setUp(): void
    {
        parent::setUp();

        // Penempatan yang tidak akan pernah ditebak siapa pun: folder
        // aplikasi bernama acak, unggahan satu tingkat lebih dalam.
        $this->akar = sys_get_temp_dir().DIRECTORY_SEPARATOR.'uji-logo-'.uniqid();
        $unggahan = $this->akar.'/aplikasi-entah-apa/berkas-unggahan';

        @mkdir($unggahan.'/sekolah', 0777, true);
        file_put_contents($unggahan.'/'.$this->logo, 'bukan-gambar-sungguhan');

        BerkasDataCenter::lupakan($this->logo);

        config([
            'datacenter.akar_unggahan' => [$this->akar.'/tidak-ada/storage/app/public'],
            'datacenter.akar_pencarian' => [$this->akar],
        ]);
    }

    protected function tearDown(): void
    {
        BerkasDataCenter::lupakan($this->logo);
        $this->hapus($this->akar);

        parent::tearDown();
    }

    public function test_logo_ketemu_walau_tak_satu_pun_calon_benar(): void
    {
        $berkas = BerkasDataCenter::path($this->logo);

        $this->assertNotNull($berkas, 'Berkas logo seharusnya ketemu lewat penyisiran.');
        $this->assertStringEndsWith('berkas-unggahan'.DIRECTORY_SEPARATOR
            .'sekolah'.DIRECTORY_SEPARATOR.'uji-logo-datacenter.png', $berkas);
    }

    /** Sekali ketemu, akarnya diingat supaya halaman berikutnya tidak menyisir lagi. */
    public function test_letak_yang_sudah_ketemu_diingat(): void
    {
        BerkasDataCenter::path($this->logo);

        $this->assertSame(
            $this->garisMiring($this->akar.'/aplikasi-entah-apa/berkas-unggahan'),
            $this->garisMiring((string) BerkasDataCenter::akarDiingat()),
        );

        // Tanpa direktori pencarian sama sekali, akar yang diingat sudah cukup.
        config(['datacenter.akar_pencarian' => []]);

        $this->assertNotNull(BerkasDataCenter::cari($this->logo));
    }

    /**
     * Server sekolah berisi banyak aplikasi. Berkas bernama sama di folder
     * lain tidak boleh dipungut sebagai logo sekolah.
     */
    public function test_berkas_bernama_sama_di_tempat_lain_diabaikan(): void
    {
        @mkdir($this->akar.'/aplikasi-lain/gambar', 0777, true);
        file_put_contents($this->akar.'/aplikasi-lain/gambar/uji-logo-datacenter.png', 'x');

        $berkas = BerkasDataCenter::cari($this->logo);

        $this->assertStringNotContainsString('aplikasi-lain', (string) $berkas);
    }

    public function test_logo_yang_tidak_ada_di_mana_pun_bernilai_null(): void
    {
        $this->assertNull(BerkasDataCenter::path('sekolah/tidak-pernah-ada.png'));
    }

    /** Samakan pemisah folder supaya perbandingannya tidak bergantung sistem. */
    protected function garisMiring(string $path): string
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', $path);
    }

    protected function hapus(string $folder): void
    {
        if (! is_dir($folder)) {
            return;
        }

        foreach (array_diff((array) scandir($folder), ['.', '..']) as $isi) {
            $path = $folder.DIRECTORY_SEPARATOR.$isi;

            is_dir($path) ? $this->hapus($path) : @unlink($path);
        }

        @rmdir($folder);
    }
}
