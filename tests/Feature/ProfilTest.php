<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\LoginAttempt;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Menu "Profil Saya" di pojok kanan atas: admin/operator mengubah nama, email,
 * dan kata sandinya sendiri; guru hanya melihat, karena datanya milik Data Center.
 */
class ProfilTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);
    }

    public function test_menu_profil_tampil_di_pojok_kanan_atas(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Profil Saya')
            ->assertSee(route('profil.index'), false);
    }

    public function test_admin_mengubah_nama_dan_email(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('profil.update'), ['name' => 'Admin Baru', 'email' => 'baru@ujian.test'])
            ->assertRedirect(route('profil.index'))
            ->assertSessionHas('success');

        $this->assertSame(['Admin Baru', 'baru@ujian.test'], [$this->admin->fresh()->name, $this->admin->fresh()->email]);
    }

    public function test_email_yang_sudah_dipakai_ditolak(): void
    {
        User::create([
            'name' => 'Operator', 'email' => 'op@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_OPERATOR, 'is_aktif' => true,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('profil.update'), ['name' => 'Admin', 'email' => 'op@ujian.test'])
            ->assertSessionHasErrors('email');
    }

    public function test_kata_sandi_diubah_setelah_sandi_lama_cocok(): void
    {
        $this->actingAs($this->admin)
            ->put(route('profil.sandi'), [
                'password_lama' => 'salah-sekali',
                'password' => 'sandibaru123',
                'password_confirmation' => 'sandibaru123',
            ])
            ->assertSessionHasErrors('password_lama');

        $this->assertTrue(Hash::check('rahasia123', $this->admin->fresh()->password));

        $this->actingAs($this->admin)
            ->put(route('profil.sandi'), [
                'password_lama' => 'rahasia123',
                'password' => 'sandibaru123',
                'password_confirmation' => 'sandibaru123',
            ])
            ->assertRedirect(route('profil.index'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('sandibaru123', $this->admin->fresh()->password));
    }

    public function test_sandi_baru_harus_diulang_dengan_benar(): void
    {
        $this->actingAs($this->admin)
            ->put(route('profil.sandi'), [
                'password_lama' => 'rahasia123',
                'password' => 'sandibaru123',
                'password_confirmation' => 'beda123456',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('rahasia123', $this->admin->fresh()->password));
    }

    public function test_riwayat_masuk_akun_sendiri_ditampilkan(): void
    {
        LoginAttempt::create([
            'username' => $this->admin->email, 'guard' => 'web', 'success' => true,
            'ip_address' => '192.168.10.20', 'browser' => 'Chrome', 'os' => 'Windows',
        ]);
        LoginAttempt::create([
            'username' => 'orang.lain@ujian.test', 'guard' => 'web', 'success' => true,
            'ip_address' => '192.168.10.99',
        ]);

        $this->actingAs($this->admin)
            ->get(route('profil.index'))
            ->assertOk()
            ->assertSee('192.168.10.20')
            ->assertDontSee('192.168.10.99');
    }

    public function test_siswa_melihat_profilnya_dari_ruang_ujian(): void
    {
        $siswa = Siswa::first();

        if (! $siswa) {
            $this->markTestSkipped('Database datacenter belum berisi data siswa.');
        }

        LoginAttempt::create([
            'username' => $siswa->nisn, 'guard' => 'siswa', 'success' => true,
            'ip_address' => '192.168.10.55', 'browser' => 'Chrome', 'os' => 'Android',
        ]);

        // Menu profil ada di bilah atas ruang ujian.
        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.ujian.index'))
            ->assertOk()
            ->assertSee('Profil Saya')
            ->assertSee(route('siswa.profil'), false);

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.profil'))
            ->assertOk()
            ->assertSee($siswa->nama_siswa)
            ->assertSee((string) $siswa->nisn)
            ->assertSee('192.168.10.55')
            ->assertSee('hubungi wali kelas atau operator sekolah');
    }

    public function test_profil_siswa_tertutup_untuk_yang_bukan_siswa(): void
    {
        // Sama seperti seluruh ruang ujian: yang bukan siswa dilempar ke login.
        $this->actingAs($this->admin)
            ->get(route('siswa.profil'))
            ->assertRedirect(route('login'));

        $this->get(route('siswa.profil'))->assertRedirect(route('login'));
    }

    public function test_guru_hanya_melihat_profilnya(): void
    {
        $guru = Guru::first();

        if (! $guru) {
            $this->markTestSkipped('Database datacenter belum berisi data guru.');
        }

        $this->actingAs($guru, 'guru')
            ->get(route('profil.index'))
            ->assertOk()
            ->assertSee($guru->nama_ptk)
            ->assertSee('Data Center')
            ->assertDontSee('Ubah Kata Sandi');

        // Tidak ada jalan mengubahnya dari sini, meski permintaannya dikirim langsung.
        $this->actingAs($guru, 'guru')
            ->patch(route('profil.update'), ['name' => 'Ganti', 'email' => 'ganti@ujian.test'])
            ->assertForbidden();

        $this->actingAs($guru, 'guru')
            ->put(route('profil.sandi'), [
                'password_lama' => 'apa saja', 'password' => 'sandibaru123', 'password_confirmation' => 'sandibaru123',
            ])
            ->assertForbidden();
    }
}
