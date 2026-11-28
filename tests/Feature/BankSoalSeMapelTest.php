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
 * Bank soal satu mata pelajaran dibagi antar guru yang mengampunya.
 *
 * Satu mata pelajaran kerap diajar beberapa guru pada tingkat yang berbeda —
 * Matematika kelas 7 oleh seorang guru, kelas 8 oleh yang lain. Bank soalnya
 * tetap satu, jadi keduanya boleh saling melihat butir dan memilihnya untuk
 * paket yang diujikan, walau tingkat kelasnya tidak sama.
 *
 * Yang tidak ikut terbuka dan dijaga di sini: butir mata pelajaran lain,
 * butir yang mapelnya belum diisi, serta hak menghapus dan menonaktifkan yang
 * tetap milik pembuatnya. Menyunting boleh oleh sesama guru se-mapel.
 */
class BankSoalSeMapelTest extends TestCase
{
    use RefreshDatabase;

    protected Guru $guru;

    protected int $guruLain;

    protected int $mapelSaya;

    protected int $mapelLain;

    protected int $tingkatSaya;

    protected int $tingkatLain;

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

        $this->mapelSaya = $mapelSaya->id;
        $this->mapelLain = $mapelLain->id;
        $this->tingkatSaya = $tingkatSaya->id;
        $this->tingkatLain = $tingkatLain->id;

        // Guru lain yang sungguhan bila ada — namanya ikut ditampilkan di
        // daftar; bila datacenter hanya punya satu guru, id semu sudah cukup
        // karena yang menentukan keterlihatan adalah mapel butirnya.
        $this->guruLain = Guru::where('id', '!=', $guru->id)->value('id') ?? $guru->id + 1000000;
    }

    public function test_butir_semapel_tingkat_lain_tampil_di_bank_soal(): void
    {
        $seMapel = $this->soal($this->guruLain, $this->mapelSaya, $this->tingkatLain, 'Soal kelas sebelah?');
        $mapelLain = $this->soal($this->guruLain, $this->mapelLain, $this->tingkatSaya, 'Soal mapel lain?');
        $tanpaMapel = $this->soal($this->guruLain, null, null, 'Soal tanpa mapel?');
        $punyaSaya = $this->soal($this->guru->id, $this->mapelSaya, $this->tingkatSaya, 'Soal saya sendiri?');

        $halaman = $this->get('/soal')->assertOk();

        $halaman->assertSee('Soal kelas sebelah?');
        $halaman->assertSee('Soal saya sendiri?');

        // Mapel lain dan butir yang mapelnya belum diisi tetap tertutup:
        // tidak ada dasar untuk menyebut guru ini berhak melihatnya.
        $halaman->assertDontSee('Soal mapel lain?');
        $halaman->assertDontSee('Soal tanpa mapel?');

        // Kartu hitungan di atas daftar ikut memakai cakupan yang sama.
        $halaman->assertSee('>2<', false);

        $this->assertModelExists($mapelLain);
        $this->assertModelExists($tanpaMapel);
        $this->assertModelExists($seMapel);
        $this->assertModelExists($punyaSaya);
    }

    public function test_butir_guru_lain_semapel_bisa_disunting_tetapi_tetap_milik_pembuatnya(): void
    {
        $seMapel = $this->soal($this->guruLain, $this->mapelSaya, $this->tingkatLain);

        // Dibaca dan disunting: boleh.
        $this->get("/soal/{$seMapel->id}")->assertOk()->assertSee(route('soal.edit', $seMapel), false);
        $this->get("/soal/{$seMapel->id}/edit")->assertOk();

        $this->put(route('soal.update', $seMapel), [
            'jenis' => Soal::PG, 'pertanyaan' => 'Sudah dibetulkan rekan?', 'bobot' => 2,
            'mata_pelajaran_id' => $this->mapelSaya, 'tingkat_kelas_id' => $this->tingkatLain,
            'opsi_text' => ['ya', 'tidak'], 'kunci_pg' => ['B'],
        ])->assertSessionHasNoErrors()->assertRedirect(route('soal.index'));

        $segar = $seMapel->fresh();
        $this->assertSame('Sudah dibetulkan rekan?', $segar->pertanyaan);
        $this->assertSame(['B'], $segar->kunci);
        $this->assertSame($this->guruLain, $segar->guru_id);
    }

    public function test_butir_guru_lain_tidak_bisa_dihapus_atau_dinonaktifkan(): void
    {
        $seMapel = $this->soal($this->guruLain, $this->mapelSaya, $this->tingkatLain);

        // Dinonaktifkan, dihapus: tidak — dan jawabannya 404, sama seperti
        // data milik orang lain yang lain pula.
        $this->patch(route('soal.toggle', $seMapel))->assertNotFound();
        $this->delete(route('soal.destroy', $seMapel))->assertNotFound();

        $this->assertModelExists($seMapel);
        $this->assertTrue($seMapel->fresh()->is_aktif);
    }

    public function test_hapus_massal_menyebut_butir_milik_guru_lain(): void
    {
        $seMapel = $this->soal($this->guruLain, $this->mapelSaya, $this->tingkatLain, 'Punya tetangga?');
        $punyaSaya = $this->soal($this->guru->id, $this->mapelSaya, $this->tingkatSaya);

        $this->delete(route('soal.hapus-massal'), ['ids' => [$seMapel->id, $punyaSaya->id]])
            ->assertSessionHas('success', '1 soal dihapus.')
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'milik guru lain'));

        $this->assertModelExists($seMapel);
        $this->assertModelMissing($punyaSaya);
    }

    public function test_penyaring_buatan_saya_mengembalikan_daftar_ke_milik_sendiri(): void
    {
        $this->soal($this->guruLain, $this->mapelSaya, $this->tingkatLain, 'Soal kelas sebelah?');
        $this->soal($this->guru->id, $this->mapelSaya, $this->tingkatSaya, 'Soal saya sendiri?');

        $this->get('/soal?pemilik=saya')
            ->assertOk()
            ->assertSee('Soal saya sendiri?')
            ->assertDontSee('Soal kelas sebelah?');
    }

    public function test_butir_semapel_bisa_dipilih_untuk_diujikan(): void
    {
        $seMapel = $this->soal($this->guruLain, $this->mapelSaya, $this->tingkatLain, 'Soal kelas sebelah?');
        $mapelLain = $this->soal($this->guruLain, $this->mapelLain, $this->tingkatSaya, 'Soal mapel lain?');

        // Paket dibuat tanpa tingkat supaya butir tingkat lain ikut tampil;
        // itulah gunanya — guru kelas 7 memakai butir kelas 8 yang sepadan.
        $paket = PaketSoal::create([
            'kode_paket' => 'SM-1', 'nama_paket' => 'Paket Bersama', 'is_aktif' => true,
            'guru_id' => $this->guru->id, 'mata_pelajaran_id' => $this->mapelSaya,
        ]);

        $this->get(route('paket-soal.kelola', $paket))
            ->assertOk()
            ->assertSee('Soal kelas sebelah?')
            ->assertDontSee('Soal mapel lain?');

        $this->post(route('paket-soal.tambah-soal', $paket), ['soal_id' => [$seMapel->id, $mapelLain->id]])
            ->assertSessionHas('success', '1 butir soal ditambahkan ke paket.');

        $this->assertSame([$seMapel->id], $paket->detail()->pluck('soal_id')->all());
    }

    public function test_ambil_acak_ikut_memakai_butir_semapel(): void
    {
        $seMapel = $this->soal($this->guruLain, $this->mapelSaya, $this->tingkatLain);
        $seMapel->update(['tingkat_kesukaran' => 'sedang']);

        $mapelLain = $this->soal($this->guruLain, $this->mapelLain, $this->tingkatSaya);
        $mapelLain->update(['tingkat_kesukaran' => 'sedang']);

        $paket = PaketSoal::create([
            'kode_paket' => 'SM-2', 'nama_paket' => 'Paket Acak', 'is_aktif' => true,
            'guru_id' => $this->guru->id,
        ]);

        $this->post(route('paket-soal.acak', $paket), ['jumlah_sedang' => 5]);

        $this->assertSame([$seMapel->id], $paket->detail()->pluck('soal_id')->all());
    }

    /**
     * Menyalin butir guru lain boleh, dan salinannya menjadi milik yang
     * menyalin — dialah yang akan menyuntingnya, sedangkan butir asalnya
     * tetap utuh di tangan pembuatnya.
     */
    public function test_salinan_butir_guru_lain_menjadi_milik_penyalin(): void
    {
        $seMapel = $this->soal($this->guruLain, $this->mapelSaya, $this->tingkatLain, 'Soal kelas sebelah?');

        $tujuan = Topik::create([
            'nama_topik' => 'Topik Saya', 'sumber' => Topik::SUMBER_MANUAL, 'is_aktif' => true,
            'mata_pelajaran_id' => $this->mapelSaya, 'tingkat_kelas_id' => $this->tingkatSaya,
        ]);

        $this->post(route('soal.salin'), ['ids' => [$seMapel->id], 'topik_id' => $tujuan->id])
            ->assertSessionHas('success', '1 soal disalin ke topik "Topik Saya".');

        $salinan = Soal::where('topik_id', $tujuan->id)->sole();

        $this->assertSame($this->guru->id, $salinan->guru_id);
        $this->assertSame('Soal kelas sebelah?', $salinan->pertanyaan);
        $this->assertSame($this->guruLain, $seMapel->fresh()->guru_id);
    }

    public function test_admin_tetap_melihat_seluruh_bank_soal(): void
    {
        $this->soal($this->guruLain, $this->mapelLain, $this->tingkatSaya, 'Soal mapel lain?');
        $this->soal($this->guruLain, null, null, 'Soal tanpa mapel?');

        // Guard harus disebut: actingAs() tanpa nama guard memakai guard yang
        // terakhir dipasang — 'guru' dari setUp() — sehingga admin justru
        // terbaca sebagai guru dan daftarnya ikut tersaring.
        auth()->guard('guru')->logout();
        $this->actingAs(User::create([
            'name' => 'Admin Bank', 'email' => 'bank@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]), 'web');

        $this->get('/soal')
            ->assertOk()
            ->assertSee('Soal mapel lain?')
            ->assertSee('Soal tanpa mapel?');
    }

    protected function soal(int $guruId, ?int $mapelId, ?int $tingkatId, string $pertanyaan = 'Uji semapel?'): Soal
    {
        return Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => $pertanyaan, 'bobot' => 1, 'is_aktif' => true,
            'guru_id' => $guruId, 'mata_pelajaran_id' => $mapelId, 'tingkat_kelas_id' => $tingkatId,
            'opsi' => [['key' => 'A', 'text' => 'ya'], ['key' => 'B', 'text' => 'tidak']], 'kunci' => ['A'],
        ]);
    }
}
