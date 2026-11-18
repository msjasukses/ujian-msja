<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Topik;
use App\Models\User;
use App\Support\Referensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Topik wajib punya mata pelajaran.
 *
 * Topik tanpa mapel tidak menuntun apa pun: ia tidak terjaring penyaring
 * mapel di daftar topik, tidak bisa mengisi penempatan soal yang memakainya,
 * dan tampil sebagai "-" di mana-mana. Satu-satunya gunanya dulu hanyalah
 * menunda pengisian — dan yang ditunda begitu nyaris tidak pernah kembali
 * diisi, sampai seseorang menyusun paket soal dan topiknya tidak ketemu.
 */
class MapelWajibTopikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Admin Topik', 'email' => 'mapel-wajib@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]));
    }

    protected function mapel(): MataPelajaran
    {
        $mapel = MataPelajaran::aktif()->first();

        if (! $mapel) {
            $this->markTestSkipped('Database datacenter belum berisi mata pelajaran.');
        }

        return $mapel;
    }

    public function test_topik_tanpa_mata_pelajaran_ditolak(): void
    {
        $this->post(route('topik.store'), ['nama_topik' => 'Topik Tanpa Mapel'])
            ->assertSessionHasErrors('mata_pelajaran_id');

        $this->assertDatabaseCount('topik', 0);
    }

    public function test_pesannya_memakai_bahasa_layar_ini(): void
    {
        // Pesan bawaan Laravel akan berbunyi "The mata pelajaran id field is
        // required" — nama kolom database, dalam bahasa yang bukan bahasa
        // formulirnya.
        $this->post(route('topik.store'), ['nama_topik' => 'Topik Tanpa Mapel'])
            ->assertSessionHasErrors(['mata_pelajaran_id' => 'Mata pelajaran wajib dipilih.']);
    }

    public function test_topik_dengan_mata_pelajaran_tersimpan(): void
    {
        $mapel = $this->mapel();

        $this->post(route('topik.store'), [
            'nama_topik' => 'Topik Berpenempatan',
            'mata_pelajaran_id' => $mapel->id,
        ])
            ->assertRedirect(route('topik.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('topik', [
            'nama_topik' => 'Topik Berpenempatan',
            'mata_pelajaran_id' => $mapel->id,
        ]);
    }

    public function test_topik_lama_yang_belum_bermapel_harus_diisi_saat_diubah(): void
    {
        // Baris warisan dari sebelum aturan ini ada. Menyimpannya ulang
        // adalah satu-satunya saat yang pas untuk melengkapinya.
        $topik = Topik::create([
            'nama_topik' => 'Topik Lama',
            'sumber' => Topik::SUMBER_MANUAL,
            'is_aktif' => true,
        ]);

        $this->put(route('topik.update', $topik), ['nama_topik' => 'Topik Lama'])
            ->assertSessionHasErrors('mata_pelajaran_id');

        $this->assertNull($topik->fresh()->mata_pelajaran_id);
    }

    public function test_formulirnya_menandai_kolom_itu_wajib(): void
    {
        $this->get(route('topik.create'))
            ->assertOk()
            ->assertSee('Mata pelajaran <span class="text-danger">*</span>', false);
    }

    public function test_formulir_lain_tidak_ikut_diwajibkan(): void
    {
        // Aturan yang sama dipakai formulir soal, paket soal, dan import —
        // ketiganya memang boleh disimpan tanpa penempatan, lalu dilengkapi
        // belakangan. Wajibnya hanya diminta di menu Topik.
        $this->assertContains('nullable', Referensi::aturanMapel());
        $this->assertContains('required', Referensi::aturanMapel(wajib: true));
    }
}
