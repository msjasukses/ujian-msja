<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Soal;
use App\Models\Topik;
use App\Models\Ujian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hapus banyak data sekaligus dari kotak centang di halaman daftar. Aturan
 * pengamannya harus sama dengan hapus satuan: yang masih dipakai dilewati,
 * sisanya tetap terhapus.
 */
class HapusMassalTest extends TestCase
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

        $this->actingAs($this->admin);
    }

    public function test_halaman_daftar_menampilkan_kotak_centang(): void
    {
        $topik = Topik::create(['nama_topik' => 'Topik Centang', 'sumber' => Topik::SUMBER_MANUAL]);

        $this->get('/topik')
            ->assertOk()
            ->assertSee('id="hapusMassal"', false)
            ->assertSee('value="'.$topik->id.'"', false)
            ->assertSee(route('topik.hapus-massal'), false);

        foreach (['/soal', '/paket-soal', '/ujian', '/pengguna'] as $url) {
            $this->get($url)->assertOk()->assertSee('id="hapusMassal"', false);
        }
    }

    public function test_topik_yang_masih_dipakai_dilewati(): void
    {
        $bebas = Topik::create(['nama_topik' => 'Bebas', 'sumber' => Topik::SUMBER_MANUAL]);
        $bebas2 = Topik::create(['nama_topik' => 'Bebas 2', 'sumber' => Topik::SUMBER_MANUAL]);
        $dipakai = Topik::create(['nama_topik' => 'Dipakai', 'sumber' => Topik::SUMBER_MANUAL]);
        $this->soal(['topik_id' => $dipakai->id]);

        $this->from('/topik')
            ->delete(route('topik.hapus-massal'), ['ids' => [$bebas->id, $bebas2->id, $dipakai->id]])
            ->assertRedirect('/topik')
            ->assertSessionHas('success', '2 topik dihapus.')
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'Dipakai'));

        $this->assertModelMissing($bebas);
        $this->assertModelMissing($bebas2);
        $this->assertModelExists($dipakai);
    }

    public function test_soal_paket_dan_ujian_dihapus_massal(): void
    {
        $bebas = $this->soal();
        $diPaket = $this->soal();

        $paketDipakai = PaketSoal::create(['kode_paket' => 'P-1', 'nama_paket' => 'Dipakai', 'is_aktif' => true]);
        $paketBebas = PaketSoal::create(['kode_paket' => 'P-2', 'nama_paket' => 'Bebas', 'is_aktif' => true]);
        PaketSoalDetail::create([
            'paket_soal_id' => $paketDipakai->id, 'soal_id' => $diPaket->id, 'nomor_urut' => 1, 'bobot' => 1,
        ]);
        $ujian = Ujian::create([
            'kode_ujian' => 'U-1', 'nama_ujian' => 'Ujian', 'paket_soal_id' => $paketDipakai->id,
            'waktu_mulai' => now(), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::DRAFT,
        ]);

        $this->delete(route('soal.hapus-massal'), ['ids' => [$bebas->id, $diPaket->id]])
            ->assertSessionHas('success', '1 soal dihapus.');
        $this->assertModelMissing($bebas);
        $this->assertModelExists($diPaket);

        $this->delete(route('paket-soal.hapus-massal'), ['ids' => [$paketBebas->id, $paketDipakai->id]])
            ->assertSessionHas('success', '1 paket soal dihapus.');
        $this->assertModelMissing($paketBebas);
        $this->assertModelExists($paketDipakai);

        $this->delete(route('ujian.hapus-massal'), ['ids' => [$ujian->id]])
            ->assertSessionHas('success', '1 ujian dihapus.');
        $this->assertModelMissing($ujian);
    }

    public function test_pengguna_tidak_menghapus_diri_sendiri(): void
    {
        $operator = User::create([
            'name' => 'Operator', 'email' => 'op@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_OPERATOR, 'is_aktif' => true,
        ]);

        $this->delete(route('pengguna.hapus-massal'), ['ids' => [$operator->id, $this->admin->id]])
            ->assertSessionHas('success', '1 pengguna dihapus.')
            ->assertSessionHas('error');

        $this->assertModelMissing($operator);
        $this->assertModelExists($this->admin);
    }

    public function test_butir_dilepas_massal_dari_paket(): void
    {
        $paket = PaketSoal::create(['kode_paket' => 'K-1', 'nama_paket' => 'Kelola', 'is_aktif' => true]);
        $paketLain = PaketSoal::create(['kode_paket' => 'K-2', 'nama_paket' => 'Lain', 'is_aktif' => true]);
        $detail = collect(range(1, 3))->map(fn ($i) => PaketSoalDetail::create([
            'paket_soal_id' => $paket->id, 'soal_id' => $this->soal()->id, 'nomor_urut' => $i, 'bobot' => 1,
        ]));
        $milikLain = PaketSoalDetail::create([
            'paket_soal_id' => $paketLain->id, 'soal_id' => $this->soal()->id, 'nomor_urut' => 1, 'bobot' => 1,
        ]);

        $this->get(route('paket-soal.kelola', $paket))
            ->assertOk()
            ->assertSee(route('paket-soal.lepas-massal', $paket), false);

        $this->delete(route('paket-soal.lepas-massal', $paket), ['ids' => [$detail[0]->id, $detail[1]->id, $milikLain->id]])
            ->assertSessionHas('success', '2 butir soal dilepas dari paket.');

        // Butir yang tersisa dirapikan kembali menjadi nomor 1, butir paket
        // lain tidak tersentuh, dan soalnya sendiri tetap ada di bank soal.
        $this->assertSame([1], $paket->detail()->pluck('nomor_urut')->all());
        $this->assertModelExists($milikLain);
        $this->assertSame(4, Soal::count());
    }

    public function test_kunci_essay_ditampilkan_tanpa_html(): void
    {
        $essay = $this->soal([
            'jenis' => Soal::ESSAY, 'opsi' => null,
            'kunci' => ['jawaban' => '<p>Lafaz <span class="teks-arab">بسم الله</span> &amp; artinya</p>'],
        ]);

        $this->assertSame('Lafaz بسم الله & artinya', $essay->kunci_ringkas);
        $this->get('/soal')->assertOk()->assertDontSee('&lt;span class=&quot;teks-arab', false);
    }

    public function test_tanpa_pilihan_ditolak(): void
    {
        $this->from('/topik')
            ->delete(route('topik.hapus-massal'), [])
            ->assertRedirect('/topik')
            ->assertSessionHasErrors('ids');
    }

    protected function soal(array $isi = []): Soal
    {
        return Soal::create($isi + [
            'jenis' => Soal::PG, 'pertanyaan' => 'Uji?', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'ya'], ['key' => 'B', 'text' => 'tidak']], 'kunci' => ['A'],
        ]);
    }
}
