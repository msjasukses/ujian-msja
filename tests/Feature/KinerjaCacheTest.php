<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Models\User;
use App\Services\RedisCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Menu "Kinerja & Cache": memindahkan tembolok dan sesi login ke Redis dari
 * halaman admin.
 *
 * Yang dijaga pengujian ini bukan kecepatannya, melainkan bahwa setelan yang
 * salah tidak pernah sempat tertulis. Menu ini menulis .env — berkas yang
 * dibaca sebelum aplikasi hidup — sehingga satu baris keliru di sana membuat
 * seluruh aplikasi tidak bisa dibuka siapa pun, dan memperbaikinya butuh SSH.
 *
 * .env yang dipakai di sini berkas sementara, bukan milik proyek: pengujian
 * tidak boleh mengubah setelan mesin yang menjalankannya.
 */
class KinerjaCacheTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected string $folderEnv;

    protected string $berkasEnv;

    /** Isi .env awal: sengaja memuat baris lain yang tidak boleh ikut berubah. */
    protected const ENV_AWAL = <<<'ENV'
        APP_NAME="Aplikasi Ujian Sekolah"
        APP_KEY=base64:contoh

        DB_DATABASE=ujian
        SESSION_DRIVER=database
        CACHE_STORE=database

        # CACHE_STORE=redis
        MAIL_MAILER=log

        ENV;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Kinerja', 'email' => 'kinerja@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $this->folderEnv = storage_path('framework/testing/kinerja-'.uniqid());
        File::ensureDirectoryExists($this->folderEnv);

        $this->berkasEnv = $this->folderEnv.DIRECTORY_SEPARATOR.'.env';
        File::put($this->berkasEnv, static::ENV_AWAL);

        $this->app->useEnvironmentPath($this->folderEnv);

        // Pengujian berjalan dengan sesi "array"; yang ingin ditiru di sini
        // keadaan server sungguhan, yang bawaannya "database".
        config(['session.driver' => 'database']);

        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folderEnv);

        parent::tearDown();
    }

    protected function layanan(): RedisCacheService
    {
        return new RedisCacheService($this->app);
    }

    /** Isian formulir yang sah; Redis-nya sengaja di porta yang pasti tertutup. */
    protected function isian(array $ganti = []): array
    {
        return array_merge([
            'host' => '127.0.0.1',
            'port' => 1,
            'password' => '',
            'database' => 0,
            'cache_database' => 1,
            'prefix' => 'uji_',
            'tembolok' => 'database',
            'sesi' => 'database',
        ], $ganti);
    }

    // =====================================================================
    // Siapa yang boleh masuk
    // =====================================================================

    public function test_operator_tidak_boleh_membuka(): void
    {
        $operator = User::create([
            'name' => 'Operator', 'email' => 'operator@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_OPERATOR, 'is_aktif' => true,
        ]);

        $this->actingAs($operator)->get(route('kinerja.index'))->assertForbidden();
    }

    public function test_admin_melihat_keadaan_sekarang(): void
    {
        $this->get(route('kinerja.index'))
            ->assertOk()
            ->assertSee('Kinerja &amp; Cache', false)
            ->assertSee('Penyimpan');
    }

    public function test_halaman_menuntun_pemasangan_saat_klien_redis_belum_ada(): void
    {
        if ($this->layanan()->klien()) {
            $this->markTestSkipped('Klien Redis tersedia di mesin ini.');
        }

        // Tanpa petunjuk ini, admin sekolah hanya melihat pilihan Redis yang
        // mati tanpa tahu apa yang kurang.
        $this->get(route('kinerja.index'))
            ->assertOk()
            ->assertSee('belum punya klien Redis')
            ->assertSee('php8.2-redis');
    }

    public function test_keadaan_melaporkan_penyimpan_yang_sedang_dipakai(): void
    {
        config(['cache.default' => 'redis', 'session.driver' => 'redis']);

        $keadaan = $this->layanan()->keadaan();

        $this->assertSame('redis', $keadaan['tembolok']);
        $this->assertSame('redis', $keadaan['sesi']);
        $this->assertSame($this->berkasEnv, $keadaan['env_path']);
        $this->assertTrue($keadaan['env_bisa_ditulis']);
    }

    // =====================================================================
    // Menulis .env
    // =====================================================================

    public function test_menyimpan_mengubah_baris_penyimpan_tanpa_menyentuh_sisanya(): void
    {
        $this->layanan()->terapkan($this->isian(), 'database', 'database');

        $isi = File::get($this->berkasEnv);

        $this->assertStringContainsString('CACHE_STORE=database', $isi);
        $this->assertStringContainsString('SESSION_DRIVER=database', $isi);

        // Baris milik bagian lain tidak boleh ikut tergeser atau hilang.
        $this->assertStringContainsString('APP_NAME="Aplikasi Ujian Sekolah"', $isi);
        $this->assertStringContainsString('DB_DATABASE=ujian', $isi);
        $this->assertStringContainsString('MAIL_MAILER=log', $isi);
    }

    public function test_baris_yang_dikomentari_tidak_ikut_diubah(): void
    {
        $this->layanan()->terapkan($this->isian(), 'database', 'database');

        // Catatan admin tetap catatan: hanya baris yang benar-benar berlaku
        // yang boleh ditulis ulang.
        $this->assertStringContainsString('# CACHE_STORE=redis', File::get($this->berkasEnv));
        $this->assertSame(1, preg_match_all('/^CACHE_STORE=/m', File::get($this->berkasEnv)));
    }

    public function test_parameter_redis_ikut_tersimpan_walau_masih_memakai_database(): void
    {
        $this->layanan()->terapkan(
            $this->isian(['host' => '10.0.0.9', 'port' => 6380, 'prefix' => 'smpn264_']),
            'database',
            'database'
        );

        $isi = File::get($this->berkasEnv);

        // Admin yang mencoba Redis, mundur sebentar, lalu mencoba lagi tidak
        // perlu mengetik ulang alamatnya.
        $this->assertStringContainsString('REDIS_HOST=10.0.0.9', $isi);
        $this->assertStringContainsString('REDIS_PORT=6380', $isi);
        $this->assertStringContainsString('REDIS_PREFIX=smpn264_', $isi);
    }

    public function test_kata_sandi_berkarakter_khusus_ditulis_dalam_kutip(): void
    {
        $this->layanan()->terapkan(
            $this->isian(['password' => 'rahasia #1 $pagi']),
            'database',
            'database'
        );

        // Tanpa kutip, Dotenv memotong nilainya di spasi pertama dan sisanya
        // terbaca sebagai komentar.
        $this->assertStringContainsString('REDIS_PASSWORD="rahasia #1 $pagi"', File::get($this->berkasEnv));
    }

    public function test_kata_sandi_kosong_tidak_menimpa_yang_sudah_tersimpan(): void
    {
        $this->layanan()->terapkan($this->isian(['password' => 'sandi-lama']), 'database', 'database');
        $this->layanan()->terapkan($this->isian(['password' => '']), 'database', 'database');

        $this->assertStringContainsString('REDIS_PASSWORD=sandi-lama', File::get($this->berkasEnv));
    }

    // =====================================================================
    // Yang tidak boleh sempat tertulis
    // =====================================================================

    public function test_redis_yang_tidak_bisa_disambung_tidak_mengubah_apa_pun(): void
    {
        $sebelum = File::get($this->berkasEnv);

        $this->post(route('kinerja.simpan'), $this->isian(['tembolok' => 'redis']))
            ->assertRedirect()
            ->assertSessionHas('error');

        // Inilah syarat terpentingnya: .env yang menunjuk Redis mati akan
        // mematikan seluruh aplikasi pada permintaan berikutnya.
        $this->assertSame($sebelum, File::get($this->berkasEnv));
    }

    public function test_sesi_tidak_boleh_berganti_saat_ada_peserta_mengerjakan(): void
    {
        $this->pesertaSedangUjian();

        $sebelum = File::get($this->berkasEnv);

        $this->post(route('kinerja.simpan'), $this->isian(['sesi' => 'redis']))
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $pesan) => str_contains($pesan, 'sedang mengerjakan ujian'));

        $this->assertSame($sebelum, File::get($this->berkasEnv));
        $this->assertSame(1, $this->layanan()->pesertaSedangUjian());
    }

    public function test_tembolok_tetap_boleh_diubah_saat_ujian_berlangsung(): void
    {
        $this->pesertaSedangUjian();

        // Tembolok tidak memutus sesi siapa pun, jadi penjaganya tidak ikut
        // menghalangi yang satu ini.
        $this->layanan()->terapkan($this->isian(), 'database', 'database');

        $this->assertStringContainsString('CACHE_STORE=database', File::get($this->berkasEnv));
    }

    public function test_database_tembolok_harus_berbeda_dari_database_sesi(): void
    {
        $this->post(route('kinerja.simpan'), $this->isian(['database' => 2, 'cache_database' => 2]))
            ->assertSessionHasErrors('cache_database');

        $this->assertStringNotContainsString('REDIS_DB=2', File::get($this->berkasEnv));
    }

    public function test_awalan_kunci_yang_disarankan_berbeda_antar_pemasangan(): void
    {
        // Satu server sekolah kerap menampung beberapa domain aplikasi ini.
        // Awalan yang sama membuat sesi siswa sekolah lain ikut terbaca.
        $this->assertStringContainsString(
            substr(sha1($this->app->basePath()), 0, 6),
            $this->layanan()->prefixSaran()
        );
    }

    // =====================================================================

    protected function pesertaSedangUjian(): UjianPeserta
    {
        $paket = PaketSoal::create(['kode_paket' => 'KIN-P', 'nama_paket' => 'Paket', 'is_aktif' => true]);

        $ujian = Ujian::create([
            'kode_ujian' => 'KIN-UJN', 'nama_ujian' => 'Ujian Kinerja', 'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subMinutes(10), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
        ]);

        return UjianPeserta::create([
            'ujian_id' => $ujian->id, 'siswa_id' => 999001,
            'status' => UjianPeserta::MULAI, 'waktu_mulai' => now()->subMinutes(5),
        ]);
    }
}
