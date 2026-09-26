<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\GuruMapel;
use App\Models\MataPelajaran;
use App\Models\PaketSoal;
use App\Models\Soal;
use App\Models\TingkatKelas;
use App\Models\Topik;
use App\Models\User;
use App\Support\Referensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daftar topik mengikuti mapel yang diampu dan tingkat kelas yang diajar guru
 * — di menu Topik, Daftar Soal, Input Soal, Import, dan Pemilihan Soal.
 */
class PilihanTopikGuruTest extends TestCase
{
    use RefreshDatabase;

    protected Guru $guru;

    protected Topik $milikSaya;

    protected Topik $mapelLain;

    protected Topik $tingkatLain;

    protected Topik $tanpaPenempatan;

    protected function setUp(): void
    {
        parent::setUp();

        $tahunAjaranId = Referensi::tahunAjaranAktif()?->id;
        $guruId = GuruMapel::where('tahun_ajaran_id', $tahunAjaranId)->value('guru_id');
        $guru = $guruId ? Guru::find($guruId) : null;

        if (! $guru) {
            $this->markTestSkipped('Database datacenter belum berisi penugasan guru pada tahun ajaran aktif.');
        }

        $this->guru = $guru;
        $this->actingAs($guru, 'guru');

        $mapelSaya = Referensi::mapel()->first();
        $tingkatSaya = Referensi::tingkat()->first();
        $mapelLain = MataPelajaran::aktif()->whereNotIn('id', Referensi::mapel()->pluck('id'))->first();
        $tingkatLain = TingkatKelas::aktif()->whereNotIn('id', Referensi::tingkat()->pluck('id'))->first();

        if (! $mapelSaya || ! $tingkatSaya || ! $mapelLain || ! $tingkatLain) {
            $this->markTestSkipped('Butuh mapel & tingkat di dalam dan di luar pengampuan guru.');
        }

        $this->milikSaya = $this->topik('Topik Saya', $mapelSaya->id, $tingkatSaya->id);
        $this->mapelLain = $this->topik('Topik Mapel Lain', $mapelLain->id, $tingkatSaya->id);
        $this->tingkatLain = $this->topik('Topik Tingkat Lain', $mapelSaya->id, $tingkatLain->id);
        $this->tanpaPenempatan = $this->topik('Topik Tanpa Penempatan', null, null);
    }

    public function test_menu_topik_hanya_memuat_topik_yang_relevan(): void
    {
        $halaman = $this->get('/topik')->assertOk();

        $halaman->assertSee('Topik Saya');
        // Topik tanpa mapel/tingkat tetap tampil — bila tidak, topik yang baru
        // dibuat guru tanpa mengisi keduanya akan hilang dari daftarnya sendiri.
        $halaman->assertSee('Topik Tanpa Penempatan');
        $halaman->assertDontSee('Topik Mapel Lain');
        $halaman->assertDontSee('Topik Tingkat Lain');
    }

    public function test_dropdown_topik_di_menu_soal_ikut_tersaring(): void
    {
        $paket = PaketSoal::create([
            'kode_paket' => 'TP-1', 'nama_paket' => 'Paket', 'is_aktif' => true,
            'guru_id' => $this->guru->id,
        ]);

        foreach (['/soal', '/soal/create', '/soal/import', "/paket-soal/{$paket->id}/kelola"] as $url) {
            $halaman = $this->get($url)->assertOk();

            $halaman->assertSee('Topik Saya');
            $halaman->assertDontSee('Topik Mapel Lain');
            $halaman->assertDontSee('Topik Tingkat Lain');
        }
    }

    public function test_admin_tetap_melihat_seluruh_topik(): void
    {
        $this->actingAs($this->admin(), 'web');

        $this->get('/topik')
            ->assertOk()
            ->assertSee('Topik Mapel Lain')
            ->assertSee('Topik Tingkat Lain');
    }

    /**
     * Satu peramban kerap memegang sesi admin dan guru sekaligus — operator
     * masuk sebagai guru untuk memeriksa tampilannya. Selama sesi adminnya
     * masih ada, halaman pengelola tetap tampil sebagai admin, sesuai peran
     * yang tertulis di pojok kanan atas.
     */
    public function test_sesi_admin_menang_atas_sesi_guru_yang_ikut_terbuka(): void
    {
        $this->actingAs($this->guru, 'guru');
        $this->actingAs($this->admin(), 'web');

        $this->get('/topik')
            ->assertOk()
            ->assertSee('Topik Mapel Lain');
    }

    protected function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'uji@ujian.test'],
            ['name' => 'Admin Uji', 'password' => 'rahasia123', 'role' => User::ROLE_ADMIN, 'is_aktif' => true],
        );
    }

    public function test_soal_tidak_bisa_ditempel_ke_topik_di_luar_pengampuan(): void
    {
        $this->post('/soal', $this->isianSoal(['topik_id' => $this->mapelLain->id]))
            ->assertSessionHasErrors('topik_id');

        $this->post('/soal', $this->isianSoal(['topik_id' => $this->milikSaya->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->milikSaya->id, Soal::firstOrFail()->topik_id);
    }

    /** Butir lama yang topiknya di luar pengampuan tetap bisa disimpan ulang. */
    public function test_topik_lama_tetap_diterima(): void
    {
        $soal = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Soal admin', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'a'], ['key' => 'B', 'text' => 'b']], 'kunci' => ['A'],
            'topik_id' => $this->mapelLain->id, 'guru_id' => $this->guru->id,
        ]);

        $this->put("/soal/{$soal->id}", $this->isianSoal(['topik_id' => $this->mapelLain->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->mapelLain->id, $soal->fresh()->topik_id);
    }

    protected function topik(string $nama, ?int $mapelId, ?int $tingkatId): Topik
    {
        return Topik::create([
            'nama_topik' => $nama,
            'mata_pelajaran_id' => $mapelId,
            'tingkat_kelas_id' => $tingkatId,
            'sumber' => Topik::SUMBER_MANUAL,
            'is_aktif' => true,
        ]);
    }

    /** @return array<string, mixed> */
    protected function isianSoal(array $tambahan = []): array
    {
        return $tambahan + [
            'jenis' => Soal::PG,
            'pertanyaan' => 'Pertanyaan uji',
            'bobot' => 1,
            'opsi_text' => ['A', 'B'],
            'kunci_pg' => ['A'],
        ];
    }
}
