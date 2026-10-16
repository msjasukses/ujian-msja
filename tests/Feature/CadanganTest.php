<?php

namespace Tests\Feature;

use App\Models\Soal;
use App\Models\Topik;
use App\Models\User;
use App\Services\CadanganService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Cadangan & pemulihan: isi cadangan lengkap, pemulihan mengembalikan data
 * persis seperti saat dicadangkan, dan pemulihan yang gagal tidak mengubah
 * apa pun.
 */
class CadanganTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected CadanganService $cadangan;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('Ekstensi zip tidak tersedia.');
        }

        Storage::fake('local');
        Storage::fake('public');

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);
        $this->actingAs($this->admin);

        $this->cadangan = app(CadanganService::class);
    }

    /** Tabel baru dari migrasi mendatang tidak boleh diam-diam luput dari cadangan. */
    public function test_setiap_tabel_aplikasi_tercakup(): void
    {
        $tabel = Schema::getTableListing(DB::connection()->getDatabaseName(), false);

        $this->assertSame(
            [],
            array_values(array_diff($tabel, CadanganService::TABEL, CadanganService::TIDAK_DICADANGKAN)),
            'Tabel ini belum masuk CadanganService::TABEL maupun TIDAK_DICADANGKAN.'
        );
    }

    public function test_cadangan_memuat_data_dan_media(): void
    {
        $soal = $this->soal('Soal bergambar <img src="/storage/soal/ab/grafik.png">');
        Storage::disk('public')->put('soal/ab/grafik.png', 'isi-gambar');
        Storage::disk('public')->put('soal-media/rekaman.mp3', 'isi-audio');

        $this->post('/cadangan', ['sertakan_berkas' => 1])
            ->assertRedirect()
            ->assertSessionHas('success');

        $nama = $this->cadangan->daftar()->sole()['nama'];
        $zip = $this->buka($nama);

        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame(CadanganService::PENANDA, $manifest['penanda']);
        $this->assertSame('manual', $manifest['jenis']);
        $this->assertSame(1, $manifest['tabel']['soal']);
        $this->assertSame(['berkas' => 2], ['berkas' => $manifest['berkas']['jumlah']]);

        foreach (CadanganService::TABEL as $tabel) {
            $this->assertNotFalse($zip->locateName("data/{$tabel}.jsonl"), "Tabel {$tabel} tidak ikut tercadang.");
        }

        $baris = json_decode(trim($zip->getFromName('data/soal.jsonl')), true);
        $this->assertSame($soal->id, $baris['id']);
        $this->assertSame($soal->pertanyaan, $baris['pertanyaan']);

        $this->assertSame('isi-gambar', $zip->getFromName('berkas/soal/ab/grafik.png'));
        $this->assertSame('isi-audio', $zip->getFromName('berkas/soal-media/rekaman.mp3'));
        $zip->close();
    }

    public function test_cadangan_tanpa_media(): void
    {
        Storage::disk('public')->put('soal/ab/grafik.png', 'isi-gambar');

        $this->post('/cadangan')->assertSessionHas('success');

        $zip = $this->buka($this->cadangan->daftar()->sole()['nama']);
        $this->assertFalse($zip->locateName('berkas/soal/ab/grafik.png'));
        $zip->close();
    }

    public function test_pemulihan_mengembalikan_data_seperti_saat_dicadangkan(): void
    {
        $topik = Topik::create(['nama_topik' => 'Bilangan Bulat', 'sumber' => Topik::SUMBER_MANUAL]);
        $lama = $this->soal('Soal yang dicadangkan', $topik->id);
        Storage::disk('public')->put('soal/ab/grafik.png', 'isi-gambar');

        $nama = $this->cadangan->buat('manual', true);

        // Sesudah dicadangkan: soal lama dihapus, ada soal baru, gambar hilang,
        // dan nama admin berubah.
        $lama->delete();
        $baru = $this->soal('Soal sesudah cadangan');
        Storage::disk('public')->delete('soal/ab/grafik.png');
        $this->admin->update(['name' => 'Nama Diganti']);

        $this->post("/cadangan/{$nama}/pulihkan", ['konfirmasi' => 'PULIHKAN'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertSame('Soal yang dicadangkan', Soal::findOrFail($lama->id)->pertanyaan);
        $this->assertSame($topik->id, Soal::findOrFail($lama->id)->topik_id);
        $this->assertNull(Soal::find($baru->id));
        $this->assertSame('Admin Uji', $this->admin->fresh()->name);
        Storage::disk('public')->assertExists('soal/ab/grafik.png');

        // Data sebelum pemulihan disimpan sebagai cadangan pengaman.
        $pengaman = $this->cadangan->daftar()->firstWhere('jenis', 'pengaman');
        $this->assertNotNull($pengaman);
        $this->assertSame(1, $pengaman['manifest']['tabel']['soal']);

        // Sesi admin diakhiri karena akun pengelola ikut dipulihkan.
        $this->assertGuest('web');
    }

    /** Cadangan rusak di tengah pemulihan: tidak satu baris pun berubah. */
    public function test_pemulihan_yang_gagal_tidak_mengubah_data(): void
    {
        $this->soal('Soal yang dicadangkan');
        $nama = $this->cadangan->buat('manual', false);

        $zip = $this->buka($nama);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $manifest['tabel']['soal'] = 99;
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->close();

        Soal::query()->delete();
        $baru = $this->soal('Soal sesudah cadangan');

        $this->post("/cadangan/{$nama}/pulihkan", ['konfirmasi' => 'PULIHKAN'])
            ->assertRedirect()
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'Pemulihan dibatalkan'));

        $this->assertSame([$baru->id], Soal::pluck('id')->all());
        $this->assertAuthenticatedAs($this->admin, 'web');
    }

    public function test_pemulihan_wajib_dikonfirmasi(): void
    {
        $this->soal('Soal yang dicadangkan');
        $nama = $this->cadangan->buat('manual', false);
        Soal::query()->delete();

        $this->post("/cadangan/{$nama}/pulihkan", ['konfirmasi' => 'pulihkan'])
            ->assertSessionHasErrors('konfirmasi');

        $this->assertSame(0, Soal::count());
    }

    /** Berkas di luar jenis media, atau yang mencoba keluar dari folder, tidak ditulis. */
    public function test_berkas_berbahaya_di_dalam_cadangan_dilewati(): void
    {
        $nama = $this->cadangan->buat('manual', false);

        $zip = $this->buka($nama);
        $zip->addFromString('berkas/soal/pintu-belakang.php', '<?php echo 1;');
        $zip->addFromString('berkas/../../luar.png', 'x');
        $zip->addFromString('berkas/soal/aman.png', 'gambar');
        $zip->close();

        $hasil = $this->cadangan->pulihkan($this->cadangan->jalur($nama), false);

        Storage::disk('public')->assertExists('soal/aman.png');
        Storage::disk('public')->assertMissing('soal/pintu-belakang.php');
        $this->assertSame(1, $hasil['berkas']);
        $this->assertStringContainsString('2 berkas', implode(' ', $hasil['catatan']));
    }

    public function test_unggahan_diperiksa_dan_belum_dipulihkan(): void
    {
        $sembarang = UploadedFile::fake()->createWithContent('bukan.zip', $this->zipSembarang());

        $this->post('/cadangan/unggah', ['berkas' => $sembarang])
            ->assertSessionHas('error', 'Berkas ini bukan cadangan Aplikasi Ujian.');
        $this->assertCount(0, $this->cadangan->daftar());

        $this->soal('Soal yang dicadangkan');
        $isi = file_get_contents($this->cadangan->jalur($this->cadangan->buat('manual', false)));
        Storage::disk('local')->deleteDirectory(CadanganService::FOLDER);
        Soal::query()->delete();

        $this->post('/cadangan/unggah', ['berkas' => UploadedFile::fake()->createWithContent('dari-laptop.zip', $isi)])
            ->assertSessionHas('success');

        $this->assertSame('unggahan', $this->cadangan->daftar()->sole()['jenis']);
        $this->assertSame(0, Soal::count(), 'Unggahan tidak boleh langsung memulihkan data.');
    }

    public function test_nama_berkas_tidak_bisa_keluar_dari_folder_cadangan(): void
    {
        $this->expectExceptionMessage('Nama berkas cadangan tidak sah.');

        $this->cadangan->jalur('../../.env.zip');
    }

    public function test_hanya_peran_admin_yang_boleh(): void
    {
        $operator = User::create([
            'name' => 'Operator', 'email' => 'op@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_OPERATOR, 'is_aktif' => true,
        ]);

        $this->get('/dashboard')->assertSee(route('cadangan.index'));

        $this->actingAs($operator);
        $this->get('/cadangan')->assertForbidden();
        $this->post('/cadangan')->assertForbidden();
        $this->get('/dashboard')->assertDontSee(route('cadangan.index'));
    }

    public function test_perintah_artisan_dan_cadangan_otomatis(): void
    {
        $this->soal('Soal yang dicadangkan');

        $this->artisan('cadangan:buat --otomatis --tanpa-berkas --simpan=2')->assertSuccessful();
        $this->travel(1)->seconds();
        $this->artisan('cadangan:buat --otomatis --tanpa-berkas --simpan=2')->assertSuccessful();
        $this->travel(1)->seconds();
        $this->artisan('cadangan:buat --otomatis --tanpa-berkas --simpan=2')->assertSuccessful();

        $otomatis = $this->cadangan->daftar()->where('jenis', 'otomatis');
        $this->assertCount(2, $otomatis, 'Hanya dua cadangan otomatis terbaru yang disisakan.');

        Soal::query()->delete();
        $this->artisan('cadangan:pulihkan', ['berkas' => $otomatis->first()['nama'], '--force' => true])
            ->assertSuccessful();
        $this->assertSame(1, Soal::count());

        $terjadwal = collect(app(Schedule::class)->events())
            ->contains(fn ($e) => str_contains($e->command, 'cadangan:buat --otomatis'));
        $this->assertTrue($terjadwal, 'Cadangan otomatis belum dijadwalkan.');
    }

    // =====================================================================

    protected function soal(string $pertanyaan, ?int $topikId = null): Soal
    {
        return Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => $pertanyaan, 'bobot' => 1, 'topik_id' => $topikId,
            'opsi' => [['key' => 'A', 'text' => 'Ya'], ['key' => 'B', 'text' => 'Tidak']],
            'kunci' => ['A'],
        ]);
    }

    protected function buka(string $nama): ZipArchive
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->cadangan->jalur($nama)));

        return $zip;
    }

    protected function zipSembarang(): string
    {
        $jalur = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($jalur, ZipArchive::OVERWRITE);
        $zip->addFromString('foto.txt', 'bukan cadangan');
        $zip->close();

        return file_get_contents($jalur);
    }
}
