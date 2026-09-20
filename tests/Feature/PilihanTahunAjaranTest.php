<?php

namespace Tests\Feature;

use App\Models\Topik;
use App\Models\User;
use App\Support\Referensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tahun ajaran di formulir topik, soal, paket, dan ujian dipilih dari daftar
 * tahun ajaran Data Center — bukan diketik bebas.
 */
class PilihanTahunAjaranTest extends TestCase
{
    use RefreshDatabase;

    protected string $aktif;

    protected function setUp(): void
    {
        parent::setUp();

        $aktif = Referensi::namaTahunAjaranAktif();

        if (! $aktif) {
            $this->markTestSkipped('Database datacenter belum berisi tahun ajaran.');
        }

        $this->aktif = $aktif;

        $this->actingAs(User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]));
    }

    public function test_formulir_menampilkan_dropdown_tahun_ajaran(): void
    {
        foreach (['/topik/create', '/soal/create', '/paket-soal/create', '/ujian/create'] as $url) {
            $halaman = $this->get($url)->assertOk();

            $halaman->assertSee('<select name="tahun_ajaran"', false);
            $halaman->assertDontSee('<input name="tahun_ajaran"', false);

            // Tahun ajaran aktif terpilih sebagai bawaan.
            $halaman->assertSee('<option value="'.$this->aktif.'" selected', false);
        }
    }

    public function test_tahun_ajaran_di_luar_data_center_ditolak(): void
    {
        $this->post('/topik', ['nama_topik' => 'Coba', 'tahun_ajaran' => '1999/2000'])
            ->assertSessionHasErrors('tahun_ajaran');

        $this->post('/topik', ['nama_topik' => 'Coba', 'tahun_ajaran' => $this->aktif])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('topik', ['nama_topik' => 'Coba', 'tahun_ajaran' => $this->aktif]);
    }

    public function test_nilai_lama_tetap_bisa_disimpan_ulang(): void
    {
        $topik = Topik::create(['nama_topik' => 'Lama', 'sumber' => Topik::SUMBER_MANUAL, 'tahun_ajaran' => '2019/2020']);

        $this->get("/topik/{$topik->id}/edit")
            ->assertOk()
            ->assertSee('2019/2020 (tidak ada di Data Center)');

        $this->put("/topik/{$topik->id}", ['nama_topik' => 'Lama diubah', 'tahun_ajaran' => '2019/2020'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2019/2020', $topik->fresh()->tahun_ajaran);
    }
}
