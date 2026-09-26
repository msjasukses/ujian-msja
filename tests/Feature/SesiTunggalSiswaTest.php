<?php

namespace Tests\Feature;

use App\Models\LoginAttempt;
use App\Models\PaketSoal;
use App\Models\SesiSiswa;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Models\User;
use App\Services\SesiSiswaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Satu akun siswa hanya boleh masuk di satu perangkat pada satu waktu.
 *
 * Aturannya login terbaru yang menang. Setiap "perangkat" di sini memulai dari
 * sesi kosong dan masuk lewat formulir login sungguhan, sehingga kaitan di
 * AuthController ikut teruji — bukan hanya layanannya.
 */
class SesiTunggalSiswaTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI = SesiSiswaService::KUNCI_SESI;

    protected Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $siswa = Siswa::first();

        if (! $siswa) {
            $this->markTestSkipped('Database datacenter belum berisi data siswa.');
        }

        if (! Hash::check('password', $siswa->password)) {
            $this->markTestSkipped('Siswa contoh tidak memakai kata sandi "password".');
        }

        $this->siswa = $siswa;
    }

    // =====================================================================

    public function test_login_terbaru_mengambil_alih_akun(): void
    {
        $tokenA = $this->masukDari('192.168.10.21');
        $tokenB = $this->masukDari('192.168.10.22');

        $this->assertNotSame($tokenA, $tokenB);

        // Perangkat yang terakhir masuk tetap berjalan.
        $this->withSession([self::KUNCI => $tokenB])
            ->get(route('siswa.ujian.index'))
            ->assertOk();

        // Perangkat lama dikeluarkan, dan diberi tahu dari mana akunnya diambil.
        $this->withSession([self::KUNCI => $tokenA])
            ->get(route('siswa.ujian.index'))
            ->assertRedirect(route('login'));

        $this->assertStringContainsString('perangkat lain', (string) session('error'));
        $this->assertStringContainsString('192.168.10.22', (string) session('error'));
        $this->assertFalse(Auth::guard('siswa')->check());
    }

    /**
     * Lembar ujian menyimpan jawaban lewat fetch. Pengalihan ke halaman login
     * akan terbaca di sana sebagai "gagal menyimpan, periksa koneksi" — jadi
     * permintaan JSON menerima jawaban yang menyebut keadaan sebenarnya.
     */
    public function test_permintaan_json_dari_perangkat_lama_menerima_401(): void
    {
        $tokenA = $this->masukDari('192.168.10.21');
        $this->masukDari('192.168.10.22');

        $this->withSession([self::KUNCI => $tokenA])
            ->getJson(route('siswa.ujian.index'))
            ->assertStatus(401)
            ->assertJson([
                'ok' => false,
                'sesi_berakhir' => true,
                'alihkan' => route('login'),
            ]);
    }

    /** Login ganda di tengah ujian dicatat untuk pengawas: bisa berarti joki. */
    public function test_login_ganda_di_tengah_ujian_tercatat_untuk_pengawas(): void
    {
        $peserta = $this->pesertaSedangMengerjakan();

        $this->masukDari('192.168.10.21');
        $this->masukDari('192.168.10.22');

        $this->assertDatabaseHas('ujian_log', [
            'ujian_peserta_id' => $peserta->id,
            'event' => 'sesi_ganda',
            'keterangan' => 'Masuk dari 192.168.10.22; sesi di 192.168.10.21 diakhiri.',
        ]);
    }

    /** Masuk pertama kali, atau masuk ulang tanpa ujian berjalan, tidak mengganggu pengawas. */
    public function test_tanpa_ujian_berjalan_tidak_ada_catatan(): void
    {
        $this->masukDari('192.168.10.21');
        $this->masukDari('192.168.10.22');

        $this->assertDatabaseMissing('ujian_log', ['event' => 'sesi_ganda']);
    }

    /**
     * Keluar dari perangkat lama tidak boleh ikut mengakhiri sesi di perangkat
     * baru — hanya baris milik sesi itu sendiri yang dihapus.
     */
    public function test_keluar_hanya_mengakhiri_sesi_milik_sendiri(): void
    {
        $tokenA = $this->masukDari('192.168.10.21');
        $tokenB = $this->masukDari('192.168.10.22');

        $this->actingAs($this->siswa, 'siswa')
            ->withSession([self::KUNCI => $tokenA])
            ->post(route('logout'));

        $this->assertDatabaseHas('sesi_siswa', ['siswa_id' => $this->siswa->id, 'token' => $tokenB]);

        $this->actingAs($this->siswa, 'siswa')
            ->withSession([self::KUNCI => $tokenB])
            ->post(route('logout'));

        $this->assertDatabaseMissing('sesi_siswa', ['siswa_id' => $this->siswa->id]);
    }

    /**
     * Sesi yang lahir sebelum fitur ini dipasang tidak membawa token. Bila
     * aplikasi diperbarui di tengah ujian, mengeluarkan semuanya sekaligus
     * justru mengacaukan ujian — sesi itu diakui dan diberi token.
     */
    public function test_sesi_lama_tanpa_token_diakui_bila_belum_ada_sesi_resmi(): void
    {
        $this->actingAs($this->siswa, 'siswa')
            ->get(route('siswa.ujian.index'))
            ->assertOk();

        $this->assertDatabaseHas('sesi_siswa', ['siswa_id' => $this->siswa->id]);
    }

    /** ...tetapi bila akun itu sudah punya sesi resmi di perangkat lain, sesi tanpa token yang mengalah. */
    public function test_sesi_tanpa_token_mengalah_pada_sesi_resmi(): void
    {
        SesiSiswa::create([
            'siswa_id' => $this->siswa->id,
            'token' => str_repeat('b', 64),
            'ip_address' => '192.168.10.22',
            'login_at' => now(),
        ]);

        $this->actingAs($this->siswa, 'siswa')
            ->get(route('siswa.ujian.index'))
            ->assertRedirect(route('login'));
    }

    /**
     * Sesi yang sudah mati tetap dijawab 401 pada permintaan JSON, bukan
     * pengalihan — dan alasannya tidak hilang di tengah jalan.
     *
     * Ketahuan saat pengujian dua perangkat: jawaban "masuk dari perangkat
     * lain" hanya dikirim sekali, pada permintaan pertama sesudah kejadian.
     * Bila yang pertama itu bukan yang membawa siswa ke halaman login, setiap
     * denyut berikutnya menerima pengalihan yang tidak dikenalinya dan soalnya
     * tetap tampil — lalu pesan titipannya kedaluwarsa sebelum terbaca.
     */
    public function test_sesi_yang_sudah_mati_tetap_dijawab_401_dengan_alasannya(): void
    {
        $tokenA = $this->masukDari('192.168.10.21');
        $this->masukDari('192.168.10.22');

        // Permintaan pertama perangkat lama: sesinya diakhiri di sini.
        $this->withSession([self::KUNCI => $tokenA])
            ->getJson(route('siswa.ujian.index'))
            ->assertStatus(401);

        // Permintaan berikutnya dari sesi yang sudah mati — tetap 401.
        $this->getJson(route('siswa.ujian.index'))
            ->assertStatus(401)
            ->assertJson(['sesi_berakhir' => true]);

        // Alasan aslinya masih tersedia untuk halaman login.
        $this->assertStringContainsString('192.168.10.22', (string) session('error'));
    }

    // =====================================================================
    // Penandaan di menu Log Login
    // =====================================================================

    /** Login dari perangkat lain selagi sesi lama masih dipakai: tertandai, beserta perangkat yang tergeser. */
    public function test_login_ganda_tertandai_pada_log_login(): void
    {
        $this->masukDari('192.168.10.21');
        $this->masukDari('192.168.10.22');

        $baris = LoginAttempt::latest('id')->first();

        $this->assertTrue($baris->login_ganda);
        $this->assertSame('192.168.10.21', $baris->login_ganda_ip);
        $this->assertNotEmpty($baris->login_ganda_ua);

        // Login pertama tidak menggeser siapa pun.
        $this->assertSame(1, LoginAttempt::where('login_ganda', true)->count());
    }

    /**
     * ExamBro yang ditutup lalu dibuka lagi di HP yang sama kehilangan sesinya
     * dan masuk ulang dengan IP dan peramban yang persis sama. Itu bukan dua
     * perangkat, dan tidak boleh mengisi daftar pengawas dengan alarm palsu.
     */
    public function test_masuk_ulang_dari_perangkat_yang_sama_bukan_login_ganda(): void
    {
        $this->masukDari('192.168.10.21');
        $this->masukDari('192.168.10.21');

        $this->assertSame(0, LoginAttempt::where('login_ganda', true)->count());
    }

    /**
     * Penanda sesi lama tidak terhapus bila siswa sekadar menutup peramban.
     * Sesi yang sudah lama tidak dipakai bukan "bersamaan" — tanpa syarat ini
     * hampir setiap login pagi tertandai ganda karena sisa sesi kemarin.
     */
    public function test_sesi_lama_yang_sudah_tidak_dipakai_bukan_login_ganda(): void
    {
        $this->masukDari('192.168.10.21');

        $this->travel(SesiSiswaService::MENIT_AKTIF + 1)->minutes();

        $this->masukDari('192.168.10.22');

        $this->assertSame(0, LoginAttempt::where('login_ganda', true)->count());
    }

    /** Sesi yang tetap dipakai terus terbarui "terakhir dipakai"-nya, jadi tetap terhitung bersamaan. */
    public function test_sesi_yang_terus_dipakai_tetap_terhitung_bersamaan(): void
    {
        $tokenA = $this->masukDari('192.168.10.21');

        // Perangkat A terus mengerjakan: sebelas menit, tetapi aktif setiap menit.
        for ($i = 0; $i < 11; $i++) {
            $this->travel(1)->minutes();
            $this->withSession([self::KUNCI => $tokenA])
                ->get(route('siswa.ujian.index'))
                ->assertOk();
        }

        $this->masukDari('192.168.10.22');

        $this->assertSame(1, LoginAttempt::where('login_ganda', true)->count());
    }

    public function test_menu_log_login_menampilkan_siswa_login_ganda(): void
    {
        $this->masukDari('192.168.10.21');
        $this->masukDari('192.168.10.22');

        $admin = User::firstOrCreate(
            ['email' => 'pengawas@ujian.test'],
            ['name' => 'Pengawas', 'password' => 'rahasia123', 'role' => User::ROLE_ADMIN, 'is_aktif' => true]
        );

        $html = $this->actingAs($admin)
            ->get(route('log-login.index'))
            ->assertOk()
            ->assertSee('Siswa login ganda hari ini')
            ->assertSee('Login ganda')
            ->assertSee('menggeser 192.168.10.21')
            // Nama siswa ikut tampil, bukan hanya NISN.
            ->assertSee($this->siswa->nama_siswa)
            ->getContent();

        $barisSiswa = 'font-monospace">'.$this->siswa->nisn;
        $this->assertSame(2, substr_count($html, $barisSiswa), 'Tanpa penyaring, kedua login tampil.');

        $tersaring = $this->actingAs($admin)
            ->get(route('log-login.index', ['ganda' => 'ya']))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($tersaring, $barisSiswa), 'Penyaring hanya menyisakan login ganda.');
    }

    public function test_export_log_login_memuat_kolom_login_ganda(): void
    {
        $this->masukDari('192.168.10.21');
        $this->masukDari('192.168.10.22');

        $admin = User::firstOrCreate(
            ['email' => 'pengawas@ujian.test'],
            ['name' => 'Pengawas', 'password' => 'rahasia123', 'role' => User::ROLE_ADMIN, 'is_aktif' => true]
        );

        $respons = $this->actingAs($admin)->get(route('log-login.export'));
        $respons->assertOk();

        ob_start();
        $respons->baseResponse->sendContent();
        $isi = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'uji').'.xlsx';
        file_put_contents($path, $isi);
        $teks = collect(IOFactory::load($path)->getActiveSheet()->toArray())->flatten()->filter()->implode(' | ');
        @unlink($path);

        $this->assertStringContainsString('Login Ganda', $teks);
        $this->assertStringContainsString('Menggeser Sesi di IP', $teks);
        $this->assertStringContainsString('192.168.10.21', $teks);
    }

    // =====================================================================
    // Penandaan di menu Monitoring Ujian
    // =====================================================================

    public function test_monitoring_menampilkan_login_ganda_peserta(): void
    {
        $peserta = $this->pesertaSedangMengerjakan();

        $this->masukDari('192.168.10.21');
        $this->masukDari('192.168.10.22');

        $this->actingAs($this->pengawas())
            ->get(route('monitoring.show', $peserta->ujian_id))
            ->assertOk()
            // Bilah peringatan menyebut nama, supaya pengawas tahu harus mendatangi siapa.
            ->assertSee('1 peserta login ganda')
            ->assertSee($this->siswa->nama_siswa)
            // Lencana pada baris pesertanya.
            ->assertSee('Login ganda 1x')
            // Rincian perangkat untuk tooltip.
            ->assertSee('Masuk dari 192.168.10.22; sesi di 192.168.10.21 diakhiri.');

        // Jejaknya sendiri ada di halaman Jejak Aktivitas, dan tetap disorot
        // supaya tidak tenggelam di antara jejak biasa.
        $this->actingAs($this->pengawas())
            ->get(route('monitoring.jejak', $peserta->ujian_id))
            ->assertOk()
            ->assertSee('jejak-ganda', false)
            ->assertSee('Masuk dari 192.168.10.22; sesi di 192.168.10.21 diakhiri.');
    }

    /** Tabel monitoring menyegarkan diri tiap 15 detik; datanya harus ikut membawa login ganda. */
    public function test_data_penyegar_monitoring_membawa_login_ganda(): void
    {
        $peserta = $this->pesertaSedangMengerjakan();

        $this->masukDari('192.168.10.21');
        $this->masukDari('192.168.10.22');
        $this->masukDari('192.168.10.21');

        $data = $this->actingAs($this->pengawas())
            ->getJson(route('monitoring.data', $peserta->ujian_id))
            ->assertOk()
            ->json();

        // Dua kali saling menggeser, tetapi satu peserta.
        $this->assertSame(1, $data['ringkasan']['login_ganda']);

        $baris = collect($data['peserta'])->firstWhere('id', $peserta->id);
        $this->assertSame(2, $baris['login_ganda']);
        $this->assertStringContainsString('Masuk dari 192.168.10.21', $baris['login_ganda_ket']);
    }

    public function test_tanpa_login_ganda_monitoring_tetap_bersih(): void
    {
        $peserta = $this->pesertaSedangMengerjakan();

        $this->masukDari('192.168.10.21');

        $this->actingAs($this->pengawas())
            ->get(route('monitoring.show', $peserta->ujian_id))
            ->assertOk()
            ->assertDontSee('peserta login ganda')
            ->assertDontSee('Login ganda 1x');
    }

    public function test_export_monitoring_memuat_jumlah_login_ganda(): void
    {
        $peserta = $this->pesertaSedangMengerjakan();

        $this->masukDari('192.168.10.21');
        $this->masukDari('192.168.10.22');

        $respons = $this->actingAs($this->pengawas())->get(route('monitoring.export', $peserta->ujian_id));
        $respons->assertOk();

        ob_start();
        $respons->baseResponse->sendContent();
        $isi = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'uji').'.xlsx';
        file_put_contents($path, $isi);
        $baris = IOFactory::load($path)->getActiveSheet()->toArray();
        @unlink($path);

        $kepala = collect($baris)->first(fn ($r) => in_array('Login Ganda (kali)', $r, true));
        $this->assertNotNull($kepala, 'Kolom Login Ganda (kali) harus ada.');

        $kolom = array_search('Login Ganda (kali)', $kepala, true);
        $milikSiswa = collect($baris)->first(fn ($r) => in_array($this->siswa->nama_siswa, $r, true));
        $this->assertEquals(1, $milikSiswa[$kolom]);
    }

    /** Lembar ujian memeriksa sesinya secara berkala, tidak menunggu siswa menjawab. */
    public function test_lembar_ujian_memeriksa_sesi_secara_berkala(): void
    {
        $peserta = $this->pesertaSedangMengerjakan();

        $isi = $this->actingAs($this->siswa, 'siswa')
            ->get(route('siswa.ujian.kerjakan', $peserta))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('urlDenyut', $isi);
        $this->assertStringContainsString('sesiBerakhir(data)', $isi);
    }

    // =====================================================================

    /** Satu perangkat: sesi kosong, lalu masuk lewat formulir login sungguhan. */
    private function masukDari(string $ip): string
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/login', ['username' => $this->siswa->nisn, 'password' => 'password'])
            ->assertRedirect();

        $token = session(self::KUNCI);
        $this->assertNotEmpty($token, 'Login siswa harus menitipkan token sesi.');

        return $token;
    }

    private function pengawas(): User
    {
        return User::firstOrCreate(
            ['email' => 'pengawas@ujian.test'],
            ['name' => 'Pengawas', 'password' => 'rahasia123', 'role' => User::ROLE_ADMIN, 'is_aktif' => true]
        );
    }

    private function pesertaSedangMengerjakan(): UjianPeserta
    {
        $paket = PaketSoal::create(['kode_paket' => 'SES-1', 'nama_paket' => 'Paket', 'is_aktif' => true]);

        $ujian = Ujian::create([
            'kode_ujian' => 'SES-UJN', 'nama_ujian' => 'Ujian Sesi',
            'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subMinutes(10), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
        ]);

        return UjianPeserta::create([
            'ujian_id' => $ujian->id, 'siswa_id' => $this->siswa->id,
            'status' => UjianPeserta::MULAI, 'waktu_mulai' => now()->subMinute(),
        ]);
    }
}
