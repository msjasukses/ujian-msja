<?php

namespace Tests\Feature;

use App\Models\Soal;
use App\Models\User;
use App\Services\GambarSoalService;
use App\Support\AlamatPublik;
use App\Support\TeksSoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Aplikasi yang dipasang di subfolder, mis. https://sekolah.sch.id/cbt.
 *
 * Gambar soal dulu dirujuk sebagai "/storage/soal/..." — alamat yang dihitung
 * dari akar domain. Di subfolder, peramban memintanya dari luar aplikasi dan
 * gambar yang baru ditempel ke kolom pertanyaan tampil rusak. Kini alamat
 * tersimpan netral dan diberi awalan subfolder saat ditampilkan.
 */
class GambarSubfolderTest extends TestCase
{
    use RefreshDatabase;

    /** Variabel server yang dikirim PHP-FPM bila aplikasi dipasang di /cbt. */
    protected const SERVER_SUBFOLDER = ['SCRIPT_NAME' => '/cbt/index.php'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->actingAs(User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]));
    }

    public function test_unggahan_mengembalikan_alamat_bersubfolder(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Ekstensi GD tidak tersedia.');
        }

        $url = $this->withServerVariables($this->server())
            ->post($this->alamat('/cbt/soal/gambar'), ['gambar' => UploadedFile::fake()->image('grafik.png', 60, 40)])
            ->assertOk()
            ->json('url');

        $this->assertStringStartsWith('/cbt/storage/soal/', $url);
        Storage::disk('public')->assertExists(str_replace('/cbt/storage/', '', $url));
    }

    /** Alamat bersubfolder dari editor disimpan dalam bentuk netral, di semua kolom. */
    public function test_soal_tersimpan_dengan_alamat_netral(): void
    {
        $this->withServerVariables($this->server())->post($this->alamat('/cbt/soal'), [
            'jenis' => Soal::PG,
            'pertanyaan' => 'Perhatikan grafik <img src="/cbt/storage/soal/ab/grafik.png" alt="Gambar soal">',
            'opsi_text' => ['<img src="/cbt/storage/soal/cd/opsi.png" alt="Gambar soal">', 'Tidak ada'],
            'kunci_pg' => ['A'],
            'bobot' => 1,
            'pembahasan' => 'Lihat <img src="/cbt/storage/soal/ef/bahas.png">',
        ])->assertSessionHasNoErrors();

        $soal = Soal::sole();

        $this->assertStringContainsString('src="/storage/soal/ab/grafik.png"', $soal->pertanyaan);
        $this->assertStringContainsString('src="/storage/soal/cd/opsi.png"', $soal->opsi[0]['text']);
        $this->assertStringContainsString('src="/storage/soal/ef/bahas.png"', $soal->pembahasan);
        $this->assertStringNotContainsString('/cbt/', json_encode($soal->only(['pertanyaan', 'opsi', 'pembahasan'])));
    }

    /** Soal lama — termasuk yang dibuat di Laragon lalu dipindah ke VPS — tampil benar. */
    public function test_gambar_tampil_berawalan_subfolder_di_halaman_soal(): void
    {
        $soal = $this->soal('<p>Grafik <img src="/storage/soal/ab/grafik.png" alt="Grafik"></p>');

        $this->withServerVariables($this->server())
            ->get($this->alamat("/cbt/soal/{$soal->id}"))
            ->assertOk()
            ->assertSee('src="/cbt/storage/soal/ab/grafik.png"', false)
            ->assertDontSee('src="/storage/soal/ab/grafik.png"', false);

        // Editor pada formulir ubah diberi tahu awalannya.
        $this->withServerVariables($this->server())
            ->get($this->alamat("/cbt/soal/{$soal->id}/edit"))
            ->assertOk()
            ->assertSee('const DASAR = "\/cbt";', false);
    }

    public function test_media_dan_teks_soal_mengikuti_subfolder(): void
    {
        $this->app->instance('request', Request::create('https://sekolah.test/cbt/soal', 'GET', [], [], [], $this->server()));

        $this->assertSame('/cbt', AlamatPublik::dasar());
        $this->assertSame('/cbt/storage/soal-media/a.mp3', (new Soal(['media_path' => 'soal-media/a.mp3']))->media_url);
        $this->assertStringContainsString(
            'src="/cbt/storage/soal/x.png"',
            TeksSoal::html('<img src="/storage/soal/x.png" alt="x">')->toHtml()
        );
    }

    public function test_di_akar_domain_alamat_tidak_berubah(): void
    {
        $this->assertSame('', AlamatPublik::dasar());
        $this->assertStringContainsString(
            'src="/storage/soal/x.png"',
            TeksSoal::html('<img src="/storage/soal/x.png" alt="x">')->toHtml()
        );
    }

    /** Pembersih gambar tak terpakai tidak boleh menghapus gambar yang masih dirujuk. */
    public function test_rujukan_bersubfolder_tetap_dikenali_pembersih(): void
    {
        $this->assertSame(
            ['/storage/soal/ab/x.png', '/storage/soal/cd/y.png'],
            app(GambarSoalService::class)->rujukanDalam(
                '<img src="/cbt/storage/soal/ab/x.png"> <img src="/storage/soal/cd/y.png">'
            )
        );
    }

    /**
     * Alamat lengkap. Alamat relatif disusun alat uji dari akar permintaan
     * sebelumnya, yang di sini sudah berawalan /cbt, sehingga permintaan
     * kedua dalam satu tes menjadi /cbt/cbt/...
     */
    protected function alamat(string $jalur): string
    {
        return 'https://sekolah.test'.$jalur;
    }

    /** @return array<string, string> */
    protected function server(): array
    {
        return self::SERVER_SUBFOLDER + ['SCRIPT_FILENAME' => public_path('index.php')];
    }

    protected function soal(string $pertanyaan): Soal
    {
        return Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => $pertanyaan, 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'Ya'], ['key' => 'B', 'text' => 'Tidak']],
            'kunci' => ['A'],
        ]);
    }
}
