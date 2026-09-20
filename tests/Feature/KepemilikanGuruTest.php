<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Soal;
use App\Models\Ujian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guru hanya boleh membuka dan mengubah soal, paket, dan ujian miliknya
 * sendiri — bukan hanya di halaman daftar, tetapi juga bila alamat milik guru
 * lain diketik langsung atau id-nya diselipkan ke formulir.
 */
class KepemilikanGuruTest extends TestCase
{
    use RefreshDatabase;

    protected Guru $guru;

    protected int $guruLain;

    protected function setUp(): void
    {
        parent::setUp();

        $guru = Guru::first();

        if (! $guru) {
            $this->markTestSkipped('Database datacenter belum berisi data guru.');
        }

        $this->guru = $guru;
        $this->guruLain = $guru->id + 1000000;
        $this->actingAs($guru, 'guru');
    }

    public function test_alamat_milik_guru_lain_menjawab_404(): void
    {
        [$soal, $paket, $ujian] = $this->dataMilik($this->guruLain);

        foreach ([
            "/soal/{$soal->id}", "/soal/{$soal->id}/edit",
            "/paket-soal/{$paket->id}/edit", "/paket-soal/{$paket->id}/kelola",
            "/ujian/{$ujian->id}/edit", "/ujian/{$ujian->id}/peserta",
            "/monitoring/{$ujian->id}", "/laporan/nilai/{$ujian->id}",
        ] as $url) {
            $this->get($url)->assertNotFound();
        }

        $this->delete("/soal/{$soal->id}")->assertNotFound();
        $this->delete("/paket-soal/{$paket->id}")->assertNotFound();
        $this->delete("/ujian/{$ujian->id}")->assertNotFound();

        $this->assertModelExists($soal);
        $this->assertModelExists($paket);
        $this->assertModelExists($ujian);
    }

    public function test_milik_sendiri_tetap_bisa_dibuka(): void
    {
        [$soal, $paket, $ujian] = $this->dataMilik($this->guru->id);

        $this->get("/soal/{$soal->id}/edit")->assertOk();
        $this->get("/paket-soal/{$paket->id}/kelola")->assertOk();
        $this->get("/ujian/{$ujian->id}/edit")->assertOk();
    }

    public function test_hapus_massal_mengabaikan_id_milik_guru_lain(): void
    {
        // Soal lepas (tidak di paket), jadi yang menahannya hanya kepemilikan.
        $soalLain = $this->soal($this->guruLain);
        $soalSendiri = $this->soal($this->guru->id);

        $this->delete(route('soal.hapus-massal'), ['ids' => [$soalLain->id, $soalSendiri->id]])
            ->assertSessionHas('success', '1 soal dihapus.');

        $this->assertModelExists($soalLain);
        $this->assertModelMissing($soalSendiri);
    }

    public function test_soal_guru_lain_tidak_bisa_dimasukkan_ke_paket(): void
    {
        $paket = PaketSoal::create(['kode_paket' => 'S-1', 'nama_paket' => 'Saya', 'is_aktif' => true, 'guru_id' => $this->guru->id]);
        $soalLain = $this->soal($this->guruLain);
        $soalSendiri = $this->soal($this->guru->id);

        $this->post(route('paket-soal.tambah-soal', $paket), ['soal_id' => [$soalLain->id, $soalSendiri->id]])
            ->assertSessionHas('success', '1 butir soal ditambahkan ke paket.');

        $this->assertSame([$soalSendiri->id], $paket->detail()->pluck('soal_id')->all());
    }

    public function test_paket_guru_lain_tidak_bisa_dijadwalkan(): void
    {
        $paketLain = PaketSoal::create(['kode_paket' => 'L-9', 'nama_paket' => 'Lain', 'is_aktif' => true, 'guru_id' => $this->guruLain]);

        $this->post(route('ujian.store'), [
            'kode_ujian' => 'U-9', 'nama_ujian' => 'Coba', 'paket_soal_id' => $paketLain->id,
            'waktu_mulai' => now()->toDateTimeString(), 'waktu_selesai' => now()->addHour()->toDateTimeString(),
            'durasi_menit' => 60, 'kkm' => 75,
        ])->assertSessionHasErrors('paket_soal_id');

        $this->assertDatabaseMissing('ujian', ['kode_ujian' => 'U-9']);
    }

    /** @return array{0: Soal, 1: PaketSoal, 2: Ujian} */
    protected function dataMilik(int $guruId, string $akhiran = ''): array
    {
        $soal = $this->soal($guruId);
        $paket = PaketSoal::create([
            'kode_paket' => "P-{$guruId}{$akhiran}", 'nama_paket' => 'Paket', 'is_aktif' => true, 'guru_id' => $guruId,
        ]);
        PaketSoalDetail::create(['paket_soal_id' => $paket->id, 'soal_id' => $soal->id, 'nomor_urut' => 1, 'bobot' => 1]);
        $ujian = Ujian::create([
            'kode_ujian' => "U-{$guruId}{$akhiran}", 'nama_ujian' => 'Ujian', 'paket_soal_id' => $paket->id,
            'waktu_mulai' => now(), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::DRAFT, 'guru_id' => $guruId,
        ]);

        return [$soal, $paket, $ujian];
    }

    protected function soal(int $guruId): Soal
    {
        return Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Uji?', 'bobot' => 1, 'guru_id' => $guruId,
            'opsi' => [['key' => 'A', 'text' => 'ya'], ['key' => 'B', 'text' => 'tidak']], 'kunci' => ['A'],
        ]);
    }
}
