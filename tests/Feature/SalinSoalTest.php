<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Soal;
use App\Models\Topik;
use App\Models\User;
use App\Support\Referensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menyalin butir soal ke topik lain — satu butir dari tombol barisnya, atau
 * sekaligus banyak dari kotak centang halaman daftar.
 *
 * Yang dijaga di sini: salinannya benar-benar butir baru yang isinya utuh,
 * aslinya tidak tersentuh, penempatan salinan boleh ikut topik tujuannya, dan
 * pembatasan kepemilikan guru tetap berlaku — baik atas butir yang disalin
 * maupun atas topik tujuannya.
 */
class SalinSoalTest extends TestCase
{
    use RefreshDatabase;

    protected Topik $asal;

    protected Topik $tujuan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Admin Salin', 'email' => 'salin@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]));

        $this->asal = Topik::create([
            'nama_topik' => 'Topik Asal', 'sumber' => Topik::SUMBER_MANUAL, 'is_aktif' => true,
        ]);

        $this->tujuan = Topik::create([
            'nama_topik' => 'Topik Tujuan', 'sumber' => Topik::SUMBER_MANUAL, 'is_aktif' => true,
            'mata_pelajaran_id' => Referensi::mapel()->first()?->id,
            'tingkat_kelas_id' => Referensi::tingkat()->first()?->id,
        ]);
    }

    public function test_halaman_daftar_menyediakan_tombol_salin(): void
    {
        $soal = $this->soal();

        $this->get('/soal')
            ->assertOk()
            ->assertSee(route('soal.salin'), false)
            ->assertSee('data-salin-soal="'.$soal->id.'"', false)
            ->assertSee('data-salin-terpilih', false)
            ->assertSee('id="modalSalinSoal"', false)
            ->assertSee('Topik Tujuan');
    }

    public function test_satu_butir_disalin_utuh_ke_topik_tujuan(): void
    {
        $asli = $this->soal([
            'kode_soal' => 'MTK-01',
            'jenis' => Soal::PG_KOMPLEKS,
            'pertanyaan' => '<p>Mana yang bilangan prima?</p>',
            'opsi' => [['key' => 'A', 'text' => '2'], ['key' => 'B', 'text' => '4'], ['key' => 'C', 'text' => '5']],
            'kunci' => ['A', 'C'],
            'bobot' => 2.5,
            'level_kognitif' => 'C3',
            'tingkat_kesukaran' => 'sedang',
            'pembahasan' => '<p>Prima hanya habis dibagi 1 dan dirinya.</p>',
            'media_path' => 'soal-media/abc.mp3',
            'media_tipe' => 'audio',
        ]);

        $this->from('/soal')
            ->post(route('soal.salin'), ['ids' => [$asli->id], 'topik_id' => $this->tujuan->id])
            ->assertRedirect('/soal')
            ->assertSessionHas('success', '1 soal disalin ke topik "Topik Tujuan".');

        $this->assertSame(2, Soal::count());

        $salinan = Soal::where('topik_id', $this->tujuan->id)->sole();

        $this->assertNotSame($asli->id, $salinan->id);
        $this->assertSame($asli->pertanyaan, $salinan->pertanyaan);
        $this->assertSame($asli->opsi, $salinan->opsi);
        $this->assertSame(['A', 'C'], $salinan->kunci);
        $this->assertSame('2.50', (string) $salinan->bobot);
        $this->assertSame('C3', $salinan->level_kognitif);
        $this->assertSame('sedang', $salinan->tingkat_kesukaran);
        $this->assertSame($asli->pembahasan, $salinan->pembahasan);
        $this->assertSame('soal-media/abc.mp3', $salinan->media_path);
        $this->assertSame('audio', $salinan->media_tipe);

        // Kode soal sengaja tidak ikut: dua butir berkode sama menyulitkan
        // guru sendiri saat menyunting lalu mengexport bank soalnya.
        $this->assertNull($salinan->kode_soal);

        // Aslinya tidak tersentuh.
        $this->assertSame($this->asal->id, $asli->fresh()->topik_id);
        $this->assertSame('MTK-01', $asli->fresh()->kode_soal);
    }

    public function test_banyak_butir_disalin_sekaligus(): void
    {
        $daftar = collect(range(1, 3))->map(fn ($i) => $this->soal(['pertanyaan' => "Soal ke-{$i}?"]));

        $this->post(route('soal.salin'), [
            'ids' => $daftar->pluck('id')->all(),
            'topik_id' => $this->tujuan->id,
        ])->assertSessionHas('success', '3 soal disalin ke topik "Topik Tujuan".');

        $salinan = Soal::where('topik_id', $this->tujuan->id)->orderBy('id')->get();

        $this->assertCount(3, $salinan);
        $this->assertSame(['Soal ke-1?', 'Soal ke-2?', 'Soal ke-3?'], $salinan->pluck('pertanyaan')->all());
        $this->assertSame(3, Soal::where('topik_id', $this->asal->id)->count());
    }

    public function test_penempatan_salinan_boleh_ikut_topik_tujuan(): void
    {
        if (! $this->tujuan->mata_pelajaran_id || ! $this->tujuan->tingkat_kelas_id) {
            $this->markTestSkipped('Database datacenter belum berisi mata pelajaran dan tingkat kelas.');
        }

        $asli = $this->soal(['mata_pelajaran_id' => null, 'tingkat_kelas_id' => null]);

        $this->post(route('soal.salin'), [
            'ids' => [$asli->id], 'topik_id' => $this->tujuan->id, 'ikut_penempatan' => '1',
        ])->assertSessionHas('success');

        $salinan = Soal::where('topik_id', $this->tujuan->id)->sole();

        $this->assertSame($this->tujuan->mata_pelajaran_id, $salinan->mata_pelajaran_id);
        $this->assertSame($this->tujuan->tingkat_kelas_id, $salinan->tingkat_kelas_id);
    }

    public function test_tanpa_ikut_penempatan_mapel_asli_dipertahankan(): void
    {
        $mapelLain = Referensi::mapel()->last()?->id;
        $asli = $this->soal(['mata_pelajaran_id' => $mapelLain]);

        $this->post(route('soal.salin'), [
            'ids' => [$asli->id], 'topik_id' => $this->tujuan->id,
        ])->assertSessionHas('success');

        $salinan = Soal::where('topik_id', $this->tujuan->id)->sole();

        $this->assertSame($mapelLain, $salinan->mata_pelajaran_id);
        $this->assertSame($this->tujuan->id, $salinan->topik_id);
    }

    public function test_topik_tujuan_wajib_dipilih(): void
    {
        $asli = $this->soal();

        $this->from('/soal')->post(route('soal.salin'), ['ids' => [$asli->id]])
            ->assertRedirect('/soal')
            ->assertSessionHasErrors('topik_id');

        $this->from('/soal')->post(route('soal.salin'), ['topik_id' => $this->tujuan->id])
            ->assertSessionHasErrors('ids');

        $this->from('/soal')->post(route('soal.salin'), [
            'ids' => [$asli->id], 'topik_id' => $this->tujuan->id + 999999,
        ])->assertSessionHasErrors('topik_id');

        $this->assertSame(1, Soal::count());
    }

    public function test_guru_tidak_bisa_menyalin_soal_guru_lain(): void
    {
        $guru = Guru::first();

        if (! $guru) {
            $this->markTestSkipped('Database datacenter belum berisi data guru.');
        }

        $milikGuruLain = $this->soal(['guru_id' => $guru->id + 1000000]);
        $milikSendiri = $this->soal(['guru_id' => $guru->id, 'pertanyaan' => 'Milik saya sendiri?']);

        // Topik tanpa penempatan selalu ikut tampil di daftar guru mana pun,
        // jadi yang diuji di sini benar-benar penyaringan butirnya.
        $tujuan = Topik::create([
            'nama_topik' => 'Topik Guru', 'sumber' => Topik::SUMBER_MANUAL, 'is_aktif' => true,
        ]);

        // Sesi admin dari setUp() harus ditutup lebih dulu: bila dua sesi
        // terpegang sekaligus, yang berlaku sesi admin (lihat Pengguna::guruId).
        auth()->guard('web')->logout();
        $this->actingAs($guru, 'guru');

        $this->post(route('soal.salin'), [
            'ids' => [$milikGuruLain->id, $milikSendiri->id],
            'topik_id' => $tujuan->id,
        ])->assertSessionHas('success', '1 soal disalin ke topik "Topik Guru".');

        // Yang tersalin hanya butirnya sendiri, dan pemiliknya tetap dia.
        $salinan = Soal::where('topik_id', $tujuan->id)->sole();

        $this->assertSame('Milik saya sendiri?', $salinan->pertanyaan);
        $this->assertSame($guru->id, $salinan->guru_id);
    }

    protected function soal(array $isi = []): Soal
    {
        return Soal::create($isi + [
            'topik_id' => $this->asal->id,
            'jenis' => Soal::PG, 'pertanyaan' => 'Uji salin?', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'ya'], ['key' => 'B', 'text' => 'tidak']], 'kunci' => ['A'],
        ]);
    }
}
