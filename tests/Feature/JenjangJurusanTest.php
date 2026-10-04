<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Jurusan;
use App\Models\PaketSoal;
use App\Models\Pengaturan;
use App\Models\Soal;
use App\Models\User;
use App\Support\Referensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jenjang sekolah diatur di menu Pengaturan. Pada SMA dan SMK setiap isian
 * tingkat kelas disertai pilihan jurusan; pada SD dan SMP pilihan itu tidak
 * muncul dan isian jurusan diabaikan.
 */
class JenjangJurusanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]));
    }

    public function test_sebelum_disimpan_jenjang_mengikuti_data_center(): void
    {
        $this->assertNull(Pengaturan::ambil(Pengaturan::JENJANG));
        $this->assertSame(Referensi::jenjangDataCenter(), Referensi::jenjang());
    }

    public function test_tulisan_jenjang_diseragamkan(): void
    {
        $this->assertSame('SMK', Referensi::normalJenjang(' smk '));
        $this->assertSame('SMK', Referensi::normalJenjang('MAK'));
        $this->assertSame('SMA', Referensi::normalJenjang('SMA/MA'));
        $this->assertSame('SMA', Referensi::normalJenjang('MA'));
        $this->assertSame('SMP', Referensi::normalJenjang('MTs'));
        $this->assertSame('SD', Referensi::normalJenjang('MI'));
        $this->assertNull(Referensi::normalJenjang(''));
        $this->assertNull(Referensi::normalJenjang('PAUD'));
    }

    public function test_admin_menyimpan_jenjang(): void
    {
        $this->get('/pengaturan')->assertOk()->assertSee('Jenjang Sekolah');

        $this->put('/pengaturan', ['jenjang' => 'SMK'])
            ->assertRedirect('/pengaturan')
            ->assertSessionHasNoErrors();

        $this->assertSame('SMK', Referensi::jenjang());
        $this->assertTrue(Referensi::pakaiJurusan());

        $this->put('/pengaturan', ['jenjang' => 'SMP'])->assertSessionHasNoErrors();

        $this->assertSame('SMP', Referensi::jenjang());
        $this->assertFalse(Referensi::pakaiJurusan());
    }

    public function test_jenjang_tak_dikenal_ditolak(): void
    {
        $this->put('/pengaturan', ['jenjang' => 'PAUD'])->assertSessionHasErrors('jenjang');

        $this->assertNull(Pengaturan::ambil(Pengaturan::JENJANG));
    }

    public function test_guru_tidak_bisa_membuka_pengaturan(): void
    {
        $guru = Guru::aktif()->first();

        if (! $guru) {
            $this->markTestSkipped('Database datacenter belum berisi data guru.');
        }

        auth('web')->logout();
        $this->actingAs($guru, 'guru');

        $this->get('/pengaturan')->assertForbidden();
        $this->put('/pengaturan', ['jenjang' => 'SMK'])->assertForbidden();
    }

    public function test_pilihan_jurusan_hanya_muncul_pada_sma_dan_smk(): void
    {
        $halaman = ['/topik', '/topik/create', '/soal', '/soal/create', '/soal/import', '/paket-soal', '/paket-soal/create'];

        foreach (['SD' => false, 'SMP' => false, 'SMA' => true, 'SMK' => true] as $jenjang => $muncul) {
            Pengaturan::simpan(Pengaturan::JENJANG, $jenjang);

            foreach ($halaman as $url) {
                $isi = $this->get($url)->assertOk();

                $muncul
                    ? $isi->assertSee('name="jurusan_id"', false)
                    : $isi->assertDontSee('name="jurusan_id"', false);
            }
        }
    }

    public function test_jurusan_tersimpan_pada_smk_dan_diabaikan_pada_smp(): void
    {
        $jurusan = Jurusan::aktif()->first();

        if (! $jurusan) {
            $this->markTestSkipped('Database datacenter belum berisi jurusan aktif.');
        }

        Pengaturan::simpan(Pengaturan::JENJANG, 'SMK');
        $this->post('/soal', $this->isianSoal(['jurusan_id' => $jurusan->id]))->assertSessionHasNoErrors();
        $this->assertSame($jurusan->id, (int) Soal::latest('id')->value('jurusan_id'));

        Pengaturan::simpan(Pengaturan::JENJANG, 'SMP');
        $this->post('/soal', $this->isianSoal(['jurusan_id' => $jurusan->id]))->assertSessionHasNoErrors();
        $this->assertNull(Soal::latest('id')->value('jurusan_id'));
    }

    public function test_jurusan_tak_dikenal_ditolak_hanya_pada_sma_dan_smk(): void
    {
        Pengaturan::simpan(Pengaturan::JENJANG, 'SMK');
        $this->post('/soal', $this->isianSoal(['jurusan_id' => 999999]))->assertSessionHasErrors('jurusan_id');
        $this->post('/topik', ['nama_topik' => 'Topik uji', 'jurusan_id' => 999999])->assertSessionHasErrors('jurusan_id');
        $this->assertSame(0, Soal::count());

        // Pada SMP isian jurusan tidak dibaca sama sekali.
        Pengaturan::simpan(Pengaturan::JENJANG, 'SMP');
        $this->post('/soal', $this->isianSoal(['jurusan_id' => 999999]))->assertSessionHasNoErrors();
        $this->assertNull(Soal::sole()->jurusan_id);
    }

    /** Jurusan yang sudah tercatat tidak terhapus saat jenjang diganti ke SMP lalu soalnya disimpan ulang. */
    public function test_jurusan_lama_bertahan_saat_jenjang_tanpa_jurusan(): void
    {
        $soal = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Soal TKJ', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'Bandung'], ['key' => 'B', 'text' => 'Serang']],
            'kunci' => ['A'], 'jurusan_id' => 901,
        ]);

        Pengaturan::simpan(Pengaturan::JENJANG, 'SMP');
        $this->put("/soal/{$soal->id}", $this->isianSoal())->assertSessionHasNoErrors();

        $this->assertSame(901, (int) $soal->fresh()->jurusan_id);
    }

    /** Paket berjurusan menawarkan butir jurusannya dan butir umum, bukan butir jurusan lain. */
    public function test_bank_soal_paket_mengikuti_jurusan_paket(): void
    {
        foreach (['Butir jurusan satu' => 901, 'Butir jurusan dua' => 902, 'Butir umum semua jurusan' => null] as $teks => $jurusanId) {
            Soal::create([
                'jenis' => Soal::PG, 'pertanyaan' => $teks, 'bobot' => 1,
                'opsi' => [['key' => 'A', 'text' => 'Ya'], ['key' => 'B', 'text' => 'Tidak']],
                'kunci' => ['A'], 'jurusan_id' => $jurusanId, 'is_aktif' => true,
                'tingkat_kesukaran' => 'mudah',
            ]);
        }

        $paket = PaketSoal::create(['kode_paket' => 'PKT-UJI', 'nama_paket' => 'Paket uji', 'jurusan_id' => 901]);

        Pengaturan::simpan(Pengaturan::JENJANG, 'SMK');
        $this->get("/paket-soal/{$paket->id}/kelola")->assertOk()
            ->assertSee('Butir jurusan satu')
            ->assertSee('Butir umum semua jurusan')
            ->assertDontSee('Butir jurusan dua');

        // Ambil acak pun hanya mengambil dari butir jurusan paket dan butir umum.
        $this->post("/paket-soal/{$paket->id}/acak", ['jumlah_mudah' => 5])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(
            [901, null],
            $paket->soal()->pluck('jurusan_id')->map(fn ($id) => $id === null ? null : (int) $id)->all()
        );

        // Begitu sekolah bukan SMA/SMK lagi, jurusan paket tidak menyaring.
        Pengaturan::simpan(Pengaturan::JENJANG, 'SMP');
        $this->get("/paket-soal/{$paket->id}/kelola")->assertOk()
            ->assertSee('Butir jurusan dua');
    }

    /** @return array<string, mixed> */
    protected function isianSoal(array $tambahan = []): array
    {
        return $tambahan + [
            'jenis' => Soal::PG,
            'pertanyaan' => 'Ibu kota Jawa Barat?',
            'bobot' => 1,
            'opsi_text' => ['Bandung', 'Serang'],
            'kunci_pg' => ['A'],
        ];
    }
}
