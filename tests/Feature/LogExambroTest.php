<?php

namespace Tests\Feature;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Penandaan sesi yang dibuka lewat ExamBro pada menu Log Login.
 *
 * Sekolah mewajibkan ujian dikerjakan lewat peramban ujian. Yang dicari
 * pengawas justru kebalikannya: sesi siswa yang berhasil masuk dari peramban
 * biasa. Karena itu penandaannya harus tepat di kedua arah — salah menandai
 * peramban biasa sebagai ExamBro sama merugikannya dengan sebaliknya.
 */
class LogExambroTest extends TestCase
{
    use RefreshDatabase;

    /** User agent sungguhan dari perangkat yang dipakai sekolah. */
    private const UA_EXAMBRO = 'Mozilla/5.0 (Linux; Android 11; V2043 Build/RP1A.200720.012; wv) '
        .'AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/151.0.7922.199 Mobile Safari/537.36';

    private const UA_CHROME_ANDROID = 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/152.0.0.0 Mobile Safari/537.36';

    /**
     * User agent sungguhan yang terekam dari perangkat sekolah, 10 September 2026.
     *
     * Ini user agent Chrome yang sudah direduksi — "Android 10; K" adalah
     * pengganti tetap yang dikirim Chrome modern, bukan versi Android
     * sebenarnya. Tidak ada satu pun bagian di sini yang membedakannya dari
     * Chrome Android biasa.
     */
    private const UA_EXAMBRO_TEREDUKSI = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/152.0.0.0 Mobile Safari/537.36';

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $this->actingAs($this->admin);
    }

    // =====================================================================
    // Pengenalan
    // =====================================================================

    #[DataProvider('userAgent')]
    public function test_pengenalan_peramban_ujian(string $ua, bool $harapan, string $keterangan): void
    {
        $this->assertSame($harapan, LoginAttempt::webview($ua), $keterangan);
    }

    /** @return array<string, array{string, bool, string}> */
    public static function userAgent(): array
    {
        return [
            'ExamBro di Android' => [self::UA_EXAMBRO, true, 'Penanda wv seharusnya dikenali.'],

            // Kasus terpenting: Android juga, Chrome juga, tetapi bukan WebView.
            'Chrome Android biasa' => [self::UA_CHROME_ANDROID, false,
                'Chrome Android biasa tidak boleh tertandai sebagai peramban ujian.'],

            'Chrome Windows' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36',
                false, 'Peramban komputer meja bukan peramban ujian.',
            ],

            // Safari memakai token Version/ juga, tetapi versinya bukan 4.0
            // dan tidak berdampingan dengan Chrome/.
            'Safari iPhone' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 '
                .'(KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
                false, 'Token Version/ pada Safari tidak boleh disalahartikan.',
            ],

            'kosong' => ['', false, 'User agent kosong bukan peramban ujian.'],

            // Tanpa penanda yang disetel sekolah, build ini memang tidak dapat
            // dikenali — dan menebaknya akan salah menandai setiap siswa yang
            // memakai Chrome sungguhan.
            'ExamBro ber-UA tereduksi' => [self::UA_EXAMBRO_TEREDUKSI, false,
                'UA Chrome tereduksi tidak boleh ditebak sebagai peramban ujian.'],
        ];
    }

    // =====================================================================
    // Penanda tambahan yang disetel sekolah
    // =====================================================================

    /**
     * Sekolah dapat mendaftarkan penanda khas peramban ujiannya.
     *
     * Jalan keluar untuk build yang user agent-nya tidak dapat dibedakan:
     * banyak peramban ujian bisa disetel menambahkan potongan teks sendiri.
     */
    public function test_penanda_yang_disetel_sekolah_dikenali(): void
    {
        config(['ujian.penanda_exambro' => ['ExamBro']]);

        $ua = self::UA_EXAMBRO_TEREDUKSI.' ExamBro/2.1';

        $this->assertTrue(LoginAttempt::webview($ua));

        // Chrome biasa tetap tidak ikut tertandai.
        $this->assertFalse(LoginAttempt::webview(self::UA_CHROME_ANDROID));
    }

    /** Penyaring dan lencana tidak boleh berbeda pendapat tentang baris yang sama. */
    public function test_penyaring_mengikuti_penanda_yang_disetel(): void
    {
        config(['ujian.penanda_exambro' => ['ExamBro']]);

        $this->catat('3201000101', self::UA_EXAMBRO_TEREDUKSI.' ExamBro/2.1');
        $this->catat('3201000102', self::UA_CHROME_ANDROID);

        $this->assertSame(1, LoginAttempt::exambro()->count());
        $this->assertSame(1, LoginAttempt::exambro(false)->count());

        $this->assertSame(
            LoginAttempt::count(),
            LoginAttempt::exambro()->count() + LoginAttempt::exambro(false)->count()
        );
    }

    /** User agent apa adanya ditampilkan, supaya penandanya bisa ditemukan. */
    public function test_halaman_menampilkan_user_agent_apa_adanya(): void
    {
        $this->catat('3201000103', self::UA_EXAMBRO_TEREDUKSI);

        $this->get(route('log-login.index'))
            ->assertOk()
            ->assertSee('Android 10; K', false);
    }

    // =====================================================================
    // Penyaringan
    // =====================================================================

    public function test_penyaringan_memisahkan_kedua_kelompok(): void
    {
        $this->catat('3201000001', self::UA_EXAMBRO);
        $this->catat('3201000002', self::UA_EXAMBRO);
        $this->catat('3201000003', self::UA_CHROME_ANDROID);

        $this->assertSame(2, LoginAttempt::exambro()->count());
        $this->assertSame(1, LoginAttempt::exambro(false)->count());

        // Keduanya harus menjumlah tepat ke seluruh baris — tidak ada yang
        // luput dari kedua saringan.
        $this->assertSame(
            LoginAttempt::count(),
            LoginAttempt::exambro()->count() + LoginAttempt::exambro(false)->count()
        );
    }

    /** Baris lama ikut tertandai tanpa migrasi maupun pengisian ulang kolom. */
    public function test_baris_lama_ikut_tertandai(): void
    {
        $lama = $this->catat('3201000004', self::UA_EXAMBRO);

        $this->assertTrue($lama->fresh()->lewat_exambro);
        $this->assertSame(1, LoginAttempt::exambro()->count());
    }

    // =====================================================================
    // Halaman & export
    // =====================================================================

    public function test_halaman_menampilkan_lencana_dan_penyaring(): void
    {
        $this->catat('3201000005', self::UA_EXAMBRO);
        $this->catat('3201000006', self::UA_CHROME_ANDROID);

        $halaman = $this->get(route('log-login.index'));

        $halaman->assertOk();
        $halaman->assertSee('ExamBro');
        $halaman->assertSee('Peramban biasa');
        $halaman->assertSee('Lewat ExamBro');
        $halaman->assertSee('Bukan ExamBro');

        // Penyaring benar-benar mempersempit daftarnya.
        $this->get(route('log-login.index', ['exambro' => 'ya']))
            ->assertOk()
            ->assertSee('3201000005')
            ->assertDontSee('3201000006');

        $this->get(route('log-login.index', ['exambro' => 'tidak']))
            ->assertOk()
            ->assertSee('3201000006')
            ->assertDontSee('3201000005');
    }

    /** Angka ringkas menghitung sesi siswa hari ini yang lolos tanpa ExamBro. */
    public function test_angka_siswa_tanpa_exambro(): void
    {
        $this->catat('3201000007', self::UA_EXAMBRO);
        $this->catat('3201000008', self::UA_CHROME_ANDROID);
        $this->catat('3201000009', self::UA_CHROME_ANDROID);
        // Percobaan yang gagal tidak dihitung — belum tentu benar-benar masuk.
        $this->catat('3201000010', self::UA_CHROME_ANDROID, sukses: false);
        // Begitu pula sesi admin, yang memang tidak memakai peramban ujian.
        $this->catat('admin@ujian.test', self::UA_CHROME_ANDROID, guard: 'web');

        $this->get(route('log-login.index'))
            ->assertOk()
            ->assertSee('Siswa tanpa ExamBro hari ini');

        $this->assertSame(2, LoginAttempt::where('guard', 'siswa')
            ->where('success', true)
            ->whereDate('created_at', today())
            ->exambro(false)
            ->count());
    }

    public function test_export_memuat_kolom_exambro(): void
    {
        $this->catat('3201000011', self::UA_EXAMBRO);

        $respons = $this->get(route('log-login.export'));
        $respons->assertOk();

        ob_start();
        $respons->baseResponse->sendContent();
        $isi = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'uji').'.xlsx';
        file_put_contents($path, $isi);

        $teks = collect(
            IOFactory::load($path)->getActiveSheet()->toArray()
        )->flatten()->filter()->implode(' | ');

        @unlink($path);

        $this->assertStringContainsString('Lewat ExamBro', $teks);
        $this->assertStringContainsString('3201000011', $teks);
    }

    // =====================================================================

    private function catat(string $username, string $ua, bool $sukses = true, string $guard = 'siswa'): LoginAttempt
    {
        $parsed = LoginAttempt::parseUserAgent($ua);

        return LoginAttempt::create([
            'username' => $username,
            'guard' => $guard,
            'success' => $sukses,
            'ip_address' => '192.168.10.72',
            'user_agent' => $ua,
            'device_type' => $parsed['device'],
            'browser' => $parsed['browser'],
            'os' => $parsed['os'],
            'attempt_no' => 1,
        ]);
    }
}
