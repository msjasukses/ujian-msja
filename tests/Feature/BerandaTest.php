<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Beranda: halaman depan sebelum masuk, sekaligus tempat kembali dari halaman
 * login. Pengunjung yang sesinya masih hidup tidak perlu melewatinya.
 */
class BerandaTest extends TestCase
{
    use RefreshDatabase;

    public function test_akar_aplikasi_menampilkan_beranda(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(config('app.name'))
            ->assertSee('Masuk ke aplikasi')
            ->assertSee(route('login'), false);
    }

    public function test_halaman_login_punya_tombol_kembali_ke_beranda(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Beranda')
            ->assertSee('href="'.route('beranda').'"', false);
    }

    public function test_pengelola_yang_sudah_masuk_langsung_ke_dashboard(): void
    {
        $admin = User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $this->actingAs($admin, 'web')->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_siswa_yang_sudah_masuk_langsung_ke_ruang_ujian(): void
    {
        $siswa = Siswa::first();

        if (! $siswa) {
            $this->markTestSkipped('Database datacenter belum berisi data siswa.');
        }

        $this->actingAs($siswa, 'siswa')->get('/')->assertRedirect(route('siswa.ujian.index'));
    }
}
