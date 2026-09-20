<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\LoginAttempt;
use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Siswa;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianLog;
use App\Models\UjianPeserta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ruang ujian peserta, pembatasan antar guard, dan pencatatan log login. */
class RuangUjianSiswaTest extends TestCase
{
    use RefreshDatabase;

    protected Siswa $siswa;

    protected Ujian $ujian;

    protected UjianPeserta $peserta;

    protected Soal $pg;

    protected Soal $essay;

    protected function setUp(): void
    {
        parent::setUp();

        $siswa = Siswa::first();

        if (! $siswa) {
            $this->markTestSkipped('Database datacenter belum berisi data siswa.');
        }

        $this->siswa = $siswa;

        $paket = PaketSoal::create(['kode_paket' => 'RUJ-1', 'nama_paket' => 'Paket', 'is_aktif' => true]);

        $this->pg = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Ibu kota Jabar?', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'Bandung'], ['key' => 'B', 'text' => 'Serang']],
            'kunci' => ['A'],
        ]);
        $this->essay = Soal::create([
            'jenis' => Soal::ESSAY, 'pertanyaan' => 'Jelaskan!', 'bobot' => 3,
            'kunci' => ['jawaban' => 'Uraian', 'kata_kunci' => []],
        ]);

        foreach ([$this->pg, $this->essay] as $i => $soal) {
            PaketSoalDetail::create([
                'paket_soal_id' => $paket->id, 'soal_id' => $soal->id,
                'nomor_urut' => $i + 1, 'bobot' => $soal->bobot,
            ]);
        }

        $this->ujian = Ujian::create([
            'kode_ujian' => 'RUJ-UJN', 'nama_ujian' => 'Ujian Ruang',
            'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subMinutes(10), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'token' => 'ABC123',
            'tampilkan_hasil' => true, 'status' => Ujian::AKTIF,
        ]);

        $this->peserta = UjianPeserta::create([
            'ujian_id' => $this->ujian->id, 'siswa_id' => $this->siswa->id,
            'status' => UjianPeserta::TERDAFTAR,
        ]);
    }

    public function test_siswa_melihat_daftar_dan_konfirmasi_ujian(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->get('/siswa/ujian')->assertOk()->assertSee('Ujian Ruang');
        $this->get("/siswa/ujian/{$this->peserta->id}")->assertOk()->assertSee('Token ujian');
    }

    /**
     * Jam berjalan dan tombol segarkan pada ruang ujian.
     *
     * Di ExamBro tidak ada bilah alamat, jadi tanpa tombol ini satu-satunya
     * cara peserta memuat ulang daftarnya ketika pengawas baru membuka ujian
     * adalah keluar dari peramban ujian — yang justru terhitung pelanggaran.
     */
    public function test_ruang_ujian_menyediakan_jam_dan_tombol_segarkan(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->get('/siswa/ujian')
            ->assertOk()
            ->assertSee('jamSekarang', false)
            ->assertSee('tombolSegarkan', false)
            ->assertSee('Segarkan daftar ujian', false)
            ->assertSee(now()->format('H:i'), false);
    }

    /**
     * Satu peramban dapat memegang sesi admin dan sesi siswa sekaligus —
     * operator sekolah kerap masuk sebagai siswa untuk memeriksa tampilannya.
     * Ruang ujian harus tetap menampilkan pesertanya, bukan si admin.
     */
    public function test_ruang_ujian_menampilkan_peserta_meski_ada_sesi_admin(): void
    {
        $admin = User::create([
            'name' => 'Operator Sekolah', 'email' => 'operator@ujian.test',
            'password' => 'rahasia123', 'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $this->actingAs($admin, 'web');
        $this->actingAs($this->siswa, 'siswa');

        $this->get('/siswa/ujian')
            ->assertOk()
            ->assertSee($this->siswa->nama_siswa, false)
            ->assertDontSee('Operator Sekolah', false);
    }

    public function test_token_salah_ditolak_dan_tercatat(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->post("/siswa/ujian/{$this->peserta->id}/mulai", ['token' => 'SALAH'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(UjianPeserta::TERDAFTAR, $this->peserta->fresh()->status);
        $this->assertTrue(UjianLog::where('event', 'token_salah')->exists());
    }

    public function test_siswa_mengerjakan_dan_mengumpulkan(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->post("/siswa/ujian/{$this->peserta->id}/mulai", ['token' => 'abc123'])
            ->assertRedirect("/siswa/ujian/{$this->peserta->id}/kerjakan");

        $this->get("/siswa/ujian/{$this->peserta->id}/kerjakan")
            ->assertOk()
            ->assertSee('Ibu kota Jabar?')
            ->assertSee('Navigasi Soal')
            // Selama mengerjakan, tidak boleh ada jalan keluar dari lembar
            // ujian — termasuk menu profil dan tombol keluar di bilah atas.
            ->assertDontSee('Profil Saya')
            ->assertDontSee(route('siswa.profil'), false);

        // Menyimpan jawaban pilihan ganda lewat endpoint yang dipakai lembar ujian.
        $this->postJson("/siswa/ujian/{$this->peserta->id}/simpan", [
            'soal_id' => $this->pg->id,
            'jawaban' => ['A'],
        ])->assertOk()->assertJson(['ok' => true, 'terjawab' => 1]);

        $this->postJson("/siswa/ujian/{$this->peserta->id}/simpan", [
            'soal_id' => $this->essay->id,
            'jawaban' => ['teks' => 'Jawaban essay saya'],
        ])->assertOk()->assertJson(['terjawab' => 2]);

        $this->post("/siswa/ujian/{$this->peserta->id}/selesai")
            ->assertRedirect("/siswa/ujian/{$this->peserta->id}/hasil");

        $peserta = $this->peserta->fresh();
        $this->assertSame(UjianPeserta::SELESAI, $peserta->status);
        $this->assertEquals(25, (float) $peserta->nilai);   // 1 dari total bobot 4

        $this->get("/siswa/ujian/{$this->peserta->id}/hasil")->assertOk()->assertSee('25');
    }

    /** Setelah dikumpulkan, jawaban tidak boleh diubah lagi. */
    public function test_jawaban_ditolak_setelah_dikumpulkan(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->post("/siswa/ujian/{$this->peserta->id}/mulai", ['token' => 'ABC123']);
        $this->post("/siswa/ujian/{$this->peserta->id}/selesai");

        $this->postJson("/siswa/ujian/{$this->peserta->id}/simpan", [
            'soal_id' => $this->pg->id,
            'jawaban' => ['A'],
        ])->assertStatus(409);
    }

    /** Lembar ujian milik siswa lain tidak bisa dibuka. */
    public function test_siswa_tidak_bisa_membuka_lembar_milik_orang_lain(): void
    {
        $siswaLain = Siswa::where('id', '!=', $this->siswa->id)->first();

        if (! $siswaLain) {
            $this->markTestSkipped('Butuh minimal dua siswa di datacenter.');
        }

        $this->actingAs($siswaLain, 'siswa');

        $this->get("/siswa/ujian/{$this->peserta->id}")->assertForbidden();
        $this->post("/siswa/ujian/{$this->peserta->id}/selesai")->assertForbidden();
    }

    /** Ujian berstatus draft belum boleh dikerjakan. */
    public function test_ujian_draft_belum_bisa_dimulai(): void
    {
        $this->ujian->update(['status' => Ujian::DRAFT]);
        $this->actingAs($this->siswa, 'siswa');

        $this->post("/siswa/ujian/{$this->peserta->id}/mulai", ['token' => 'ABC123'])
            ->assertSessionHas('error');

        $this->assertSame(UjianPeserta::TERDAFTAR, $this->peserta->fresh()->status);
    }

    /** Pembahasan baru terbuka setelah jendela waktu ujian berakhir. */
    public function test_pembahasan_tertutup_selama_ujian_masih_berjalan(): void
    {
        $this->actingAs($this->siswa, 'siswa');
        $this->post("/siswa/ujian/{$this->peserta->id}/mulai", ['token' => 'ABC123']);
        $this->post("/siswa/ujian/{$this->peserta->id}/selesai");

        $this->get("/siswa/ujian/{$this->peserta->id}/hasil")
            ->assertOk()
            ->assertDontSee('Pembahasan Jawaban')
            ->assertSee('dibuka setelah seluruh jendela waktu ujian berakhir');

        $this->ujian->update(['waktu_selesai' => now()->subMinute()]);

        $this->get("/siswa/ujian/{$this->peserta->id}/hasil")
            ->assertOk()
            ->assertSee('Pembahasan Jawaban');
    }

    /** Pengawas bisa mereset pengerjaan; jawaban lama terhapus. */
    public function test_pengawas_dapat_mereset_pengerjaan(): void
    {
        $this->actingAs($this->siswa, 'siswa');
        $this->post("/siswa/ujian/{$this->peserta->id}/mulai", ['token' => 'ABC123']);
        $this->postJson("/siswa/ujian/{$this->peserta->id}/simpan", ['soal_id' => $this->pg->id, 'jawaban' => ['A']]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'a@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        // Guard disebut eksplisit: actingAs sebelumnya sudah menggeser guard
        // bawaan ke "siswa", sehingga tanpa ini admin ikut masuk ke guard itu.
        $this->actingAs($admin, 'web')
            ->post("/monitoring/{$this->ujian->id}/peserta/{$this->peserta->id}/reset", ['alasan' => 'koneksi putus'])
            ->assertRedirect();

        $peserta = $this->peserta->fresh();
        $this->assertSame(UjianPeserta::TERDAFTAR, $peserta->status);
        $this->assertSame(0, $peserta->jawaban()->count());
        $this->assertSame(1, $peserta->reset_count);
        $this->assertTrue(UjianLog::where('event', 'reset')->exists());
    }

    /** Siswa yang tersasar ke area pengelola diarahkan kembali ke ruang ujiannya. */
    public function test_siswa_tidak_bisa_masuk_area_pengelola(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->get('/dashboard')->assertRedirect('/siswa/ujian');
        $this->get('/soal')->assertRedirect('/siswa/ujian');
    }

    /** Guru boleh mengelola, tetapi menu sistem tetap khusus admin. */
    public function test_guru_tidak_boleh_membuka_menu_admin(): void
    {
        $guru = Guru::first();

        if (! $guru) {
            $this->markTestSkipped('Database datacenter belum berisi data guru.');
        }

        $this->actingAs($guru, 'guru');

        $this->get('/dashboard')->assertOk();
        $this->get('/soal')->assertOk();
        $this->get('/log-login')->assertForbidden();
        $this->get('/pengguna')->assertForbidden();

        // Menu Data Center berisi data seluruh sekolah, jadi tertutup untuk guru
        // — termasuk bila alamatnya diketik langsung.
        foreach ([
            '/referensi/siswa', '/referensi/guru', '/referensi/wali-kelas',
            '/referensi/guru-mapel', '/referensi/mata-pelajaran',
            '/referensi/tingkat-kelas', '/referensi/tahun-ajaran',
            '/referensi/siswa/export',
        ] as $url) {
            $this->get($url)->assertForbidden();
        }

        // Menunya pun tidak muncul di sidebar guru. Yang diperiksa tautannya,
        // bukan teks "Data Center" — kata itu masih wajar muncul di footer.
        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('/referensi/', false)
            ->assertDontSee('grpReferensi', false);
    }

    /** Percobaan login dicatat lengkap ke tabel log login. */
    public function test_percobaan_login_tercatat(): void
    {
        User::create([
            'name' => 'Admin', 'email' => 'admin@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $this->post('/login', [
            'peran' => 'admin', 'username' => 'admin@ujian.test', 'password' => 'salah',
        ])->assertSessionHasErrors('username');

        $this->post('/login', [
            'peran' => 'admin', 'username' => 'admin@ujian.test', 'password' => 'rahasia123',
        ])->assertRedirect('/dashboard');

        $this->assertSame(2, LoginAttempt::count());
        $this->assertSame(1, LoginAttempt::where('success', false)->count());
        $this->assertSame(1, LoginAttempt::where('success', true)->count());
        $this->assertSame('web', LoginAttempt::first()->guard);
        $this->assertSame(2, LoginAttempt::latest('id')->first()->attempt_no);
    }

    /** Form login hanya punya satu kolom pengenal; peran ditebak dari isiannya. */
    public function test_login_tanpa_kolom_peran_menebak_peran_dari_pengenal(): void
    {
        User::create([
            'name' => 'Admin', 'email' => 'admin@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $this->post('/login', [
            'username' => 'admin@ujian.test', 'password' => 'rahasia123',
        ])->assertRedirect('/dashboard');

        $this->assertSame('web', LoginAttempt::latest('id')->first()->guard);

        $this->post('/logout');

        $this->post('/login', [
            'username' => $this->siswa->nisn, 'password' => 'pasti-salah',
        ])->assertSessionHasErrors('username');

        $this->assertSame('siswa', LoginAttempt::latest('id')->first()->guard);
    }

    /** Peserta ujian masuk memakai NISN, bukan email. */
    public function test_login_siswa_memakai_nisn_sebagai_username(): void
    {
        $this->post('/login', [
            'peran' => 'siswa', 'username' => $this->siswa->nisn, 'password' => 'pasti-salah',
        ])->assertSessionHasErrors('username');

        $percobaan = LoginAttempt::first();
        $this->assertSame('siswa', $percobaan->guard);
        $this->assertSame($this->siswa->nisn, $percobaan->username);
    }
}
