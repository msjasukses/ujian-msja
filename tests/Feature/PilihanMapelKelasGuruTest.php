<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\GuruMapel;
use App\Models\MataPelajaran;
use App\Models\Soal;
use App\Models\TingkatKelas;
use App\Models\User;
use App\Support\Referensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dropdown mata pelajaran dan tingkat kelas mengikuti penugasan guru di Data
 * Center: guru hanya melihat mapel yang diampu dan kelas yang diajarnya,
 * sedangkan admin/operator tetap melihat semuanya.
 */
class PilihanMapelKelasGuruTest extends TestCase
{
    use RefreshDatabase;

    protected Guru $guru;

    protected function setUp(): void
    {
        parent::setUp();

        // Guru yang penugasannya terisi pada tahun ajaran aktif.
        $tahunAjaranId = Referensi::tahunAjaranAktif()?->id;
        $guruId = GuruMapel::where('tahun_ajaran_id', $tahunAjaranId)->value('guru_id');
        $guru = $guruId ? Guru::find($guruId) : null;

        if (! $guru) {
            $this->markTestSkipped('Database datacenter belum berisi penugasan guru pada tahun ajaran aktif.');
        }

        $this->guru = $guru;
    }

    public function test_guru_hanya_melihat_mapel_dan_kelas_yang_diampu(): void
    {
        $this->actingAs($this->guru, 'guru');

        $mapel = Referensi::mapel();
        $tingkat = Referensi::tingkat();

        $this->assertNotEmpty($mapel, 'Daftar mapel guru tidak boleh kosong.');
        $this->assertLessThan(MataPelajaran::aktif()->count(), $mapel->count(),
            'Daftar mapel guru seharusnya lebih sedikit daripada seluruh mapel.');
        $this->assertSame($this->guru->mapelIds(Referensi::tahunAjaranAktif()?->id), $mapel->pluck('id')->all());

        // Tingkat diturunkan dari rombel yang diajar guru pada tahun ajaran aktif.
        $nomorDiajar = Referensi::rombel($this->guru->id)->pluck('tingkat')->map(fn ($t) => (int) $t)->unique()->sort()->values();
        $this->assertSame($nomorDiajar->all(), $tingkat->pluck('nomor')->map(fn ($n) => (int) $n)->sort()->values()->all());
    }

    public function test_formulir_guru_hanya_memuat_pilihannya(): void
    {
        $lain = MataPelajaran::aktif()->whereNotIn('id', Referensi::mapel()->pluck('id'))->first();

        $this->actingAs($this->guru, 'guru');

        foreach (['/soal/create', '/topik/create', '/paket-soal/create', '/soal/import'] as $url) {
            $halaman = $this->get($url)->assertOk();

            foreach (Referensi::mapel() as $m) {
                $halaman->assertSee($m->nama_mapel);
            }

            if ($lain) {
                $halaman->assertDontSee($lain->nama_mapel);
            }
        }
    }

    public function test_admin_tetap_melihat_seluruh_mapel_dan_kelas(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]));

        $this->assertSame(MataPelajaran::aktif()->count(), Referensi::mapel()->count());
        $this->assertSame(TingkatKelas::aktif()->count(), Referensi::tingkat()->count());
    }

    public function test_mapel_di_luar_pengampuan_ditolak_saat_disimpan(): void
    {
        $this->actingAs($this->guru, 'guru');

        $lain = MataPelajaran::aktif()->whereNotIn('id', Referensi::mapel()->pluck('id'))->first();

        if (! $lain) {
            $this->markTestSkipped('Guru ini mengampu seluruh mapel yang ada.');
        }

        $this->post('/soal', $this->isianSoal(['mata_pelajaran_id' => $lain->id]))
            ->assertSessionHasErrors('mata_pelajaran_id');

        $this->post('/soal', $this->isianSoal(['mata_pelajaran_id' => Referensi::mapel()->first()->id]))
            ->assertSessionHasNoErrors();
    }

    /** Soal buatan admin dengan mapel lain tetap bisa disimpan ulang oleh guru. */
    public function test_nilai_lama_di_luar_pengampuan_tetap_diterima(): void
    {
        $this->actingAs($this->guru, 'guru');

        $lain = MataPelajaran::aktif()->whereNotIn('id', Referensi::mapel()->pluck('id'))->first();

        if (! $lain) {
            $this->markTestSkipped('Guru ini mengampu seluruh mapel yang ada.');
        }

        $soal = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Soal admin', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'Bandung'], ['key' => 'B', 'text' => 'Serang']],
            'kunci' => ['A'],
            'mata_pelajaran_id' => $lain->id,
            'guru_id' => $this->guru->id,
        ]);

        $this->get("/soal/{$soal->id}/edit")
            ->assertOk()
            ->assertSee('di luar mapel yang Anda ampu');

        $this->put("/soal/{$soal->id}", $this->isianSoal(['mata_pelajaran_id' => $lain->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($lain->id, $soal->fresh()->mata_pelajaran_id);
    }

    /** @return array<string, mixed> */
    protected function isianSoal(array $tambahan = []): array
    {
        // Bentuk isiannya mengikuti formulir input soal, bukan kolom database.
        return $tambahan + [
            'jenis' => Soal::PG,
            'pertanyaan' => 'Ibu kota Jawa Barat?',
            'bobot' => 1,
            'opsi_text' => ['Bandung', 'Serang'],
            'kunci_pg' => ['A'],
        ];
    }
}
