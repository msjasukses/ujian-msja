<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\SiswaApiToken;
use App\Models\SiswaRombel;
use App\Models\Soal;
use App\Models\TahunAjaran;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Models\UjianPeserta;
use App\Models\User;
use App\Services\RegistrasiPesertaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ujian kelas moving: pesertanya campuran siswa dari beberapa kelas yang
 * mengambil mata pelajaran yang sama, jadi dipilih per siswa — bukan dengan
 * menarik seluruh isi rombel.
 */
class KelasMovingTest extends TestCase
{
    use RefreshDatabase;

    protected PaketSoal $paket;

    protected RombonganBelajar $kelasA;

    protected RombonganBelajar $kelasB;

    protected Siswa $siswaA;

    protected Siswa $siswaB;

    protected function setUp(): void
    {
        parent::setUp();

        $tahunAjaranId = TahunAjaran::aktif()?->id;

        // Dua rombel yang masing-masing punya siswa aktif pada tahun ajaran
        // aktif — kandidat peserta diambil dari penempatan itu.
        $penempatan = SiswaRombel::when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->get()
            ->groupBy('rombongan_belajar_id')
            ->map(fn ($baris) => Siswa::aktif()->whereIn('id', $baris->pluck('siswa_id'))->orderBy('id')->first())
            ->filter();

        if ($penempatan->count() < 2) {
            $this->markTestSkipped('Database datacenter belum berisi dua kelas dengan siswa aktif.');
        }

        $rombelIds = $penempatan->keys()->take(2)->all();
        $this->kelasA = RombonganBelajar::findOrFail($rombelIds[0]);
        $this->kelasB = RombonganBelajar::findOrFail($rombelIds[1]);
        $this->siswaA = $penempatan[$rombelIds[0]];
        $this->siswaB = $penempatan[$rombelIds[1]];

        $this->actingAs(User::create([
            'name' => 'Admin Moving', 'email' => 'moving@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]));

        $this->paket = PaketSoal::create(['kode_paket' => 'KM-1', 'nama_paket' => 'Paket Moving', 'is_aktif' => true]);
        $soal = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Ibu kota Indonesia?', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'Jakarta'], ['key' => 'B', 'text' => 'Bandung']],
            'kunci' => ['A'],
        ]);
        PaketSoalDetail::create([
            'paket_soal_id' => $this->paket->id, 'soal_id' => $soal->id, 'nomor_urut' => 1, 'bobot' => 1,
        ]);
    }

    public function test_ujian_kelas_moving_bisa_dibuat_tanpa_memilih_kelas(): void
    {
        $this->post('/ujian', $this->formUjian(['mode_peserta' => Ujian::MODE_MANUAL]))
            ->assertSessionHasNoErrors();

        $ujian = Ujian::where('kode_ujian', 'KM-UJI')->firstOrFail();

        $this->assertTrue($ujian->kelas_moving);
        $this->assertSame(0, $ujian->peserta()->count());
        $this->assertSame(0, $ujian->kelas()->count());
    }

    public function test_mode_kelas_tetap_wajib_memilih_kelas(): void
    {
        $this->post('/ujian', $this->formUjian(['mode_peserta' => Ujian::MODE_KELAS]))
            ->assertSessionHasErrors('rombongan_belajar_id');

        $this->assertNull(Ujian::where('kode_ujian', 'KM-UJI')->first());
    }

    public function test_formulir_tanpa_mode_peserta_tetap_mode_kelas(): void
    {
        $this->post('/ujian', $this->formUjian(['rombongan_belajar_id' => [$this->kelasA->id]]))
            ->assertSessionHasNoErrors();

        $ujian = Ujian::where('kode_ujian', 'KM-UJI')->firstOrFail();

        $this->assertSame(Ujian::MODE_KELAS, $ujian->mode_peserta);
        $this->assertFalse($ujian->kelas_moving);
        // Peserta tetap tertarik otomatis dari kelas yang dipilih.
        $this->assertGreaterThan(0, $ujian->peserta()->count());
    }

    public function test_peserta_dipilih_per_siswa_lintas_kelas(): void
    {
        $ujian = $this->ujianMoving();

        $this->post(route('ujian.peserta.tambah', $ujian), [
            'siswa_id' => [$this->siswaA->id, $this->siswaB->id],
        ])->assertSessionHasNoErrors();

        $peserta = $ujian->peserta()->get()->keyBy('siswa_id');

        $this->assertCount(2, $peserta);
        $this->assertSame($this->kelasA->id, (int) $peserta[$this->siswaA->id]->rombongan_belajar_id);
        $this->assertSame($this->kelasB->id, (int) $peserta[$this->siswaB->id]->rombongan_belajar_id);
        // Ditandai manual supaya tidak ikut tercabut saat sinkron dari kelas.
        $this->assertSame(UjianPeserta::SUMBER_MANUAL, $peserta[$this->siswaA->id]->sumber);
    }

    public function test_halaman_pilih_peserta_menyaring_per_kelas(): void
    {
        $ujian = $this->ujianMoving();

        // Begitu dibuka, rombongan kelas movingnya langsung tampil: siswa dari
        // semua kelas pengambil mapel ujian, tanpa perlu memilih kelas dulu.
        $awal = $this->get(route('ujian.peserta.pilih', $ujian))->assertOk();
        $awal->assertSee($this->siswaA->nama_siswa);
        $awal->assertSee($this->siswaB->nama_siswa);

        // Penyaring kelas hanya mempersempit daftar yang sudah ada.
        $halaman = $this->get(route('ujian.peserta.pilih', $ujian).'?rombongan_belajar_id='.$this->kelasA->id)
            ->assertOk();

        $halaman->assertSee($this->siswaA->nama_siswa);
        $halaman->assertDontSee($this->siswaB->nama_siswa);
    }

    public function test_sinkron_kelas_tidak_mencabut_peserta_pilihan_guru(): void
    {
        // Ujian biasa untuk kelas A, lalu seorang siswa kelas B ditambahkan
        // manual — kondisi khas kelas moving yang menumpang ujian reguler.
        $ujian = Ujian::create($this->dataUjian(['kode_ujian' => 'KM-REG']));
        UjianKelas::create(['ujian_id' => $ujian->id, 'rombongan_belajar_id' => $this->kelasA->id]);

        $this->post(route('ujian.peserta.tambah', $ujian), ['siswa_id' => [$this->siswaB->id]])
            ->assertSessionHasNoErrors();

        $this->post(route('ujian.peserta.sinkron', $ujian))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ujian_peserta', [
            'ujian_id' => $ujian->id,
            'siswa_id' => $this->siswaB->id,
            'sumber' => UjianPeserta::SUMBER_MANUAL,
        ]);
        // Siswa kelas A tetap ikut tertarik otomatis seperti sebelumnya.
        $this->assertDatabaseHas('ujian_peserta', [
            'ujian_id' => $ujian->id,
            'siswa_id' => $this->siswaA->id,
            'sumber' => UjianPeserta::SUMBER_KELAS,
        ]);
    }

    public function test_sinkron_tidak_menarik_isi_kelas_pada_ujian_moving(): void
    {
        $ujian = $this->ujianMoving();
        // Kelas sempat terpasang, tapi mode moving tidak boleh menariknya.
        UjianKelas::create(['ujian_id' => $ujian->id, 'rombongan_belajar_id' => $this->kelasA->id]);

        $this->post(route('ujian.peserta.sinkron', $ujian))->assertSessionHas('error');

        $this->assertSame(0, $ujian->peserta()->count());
        $this->assertSame(
            ['ditambah' => 0, 'dicabut' => 0, 'dipertahankan' => 0, 'total' => 0],
            app(RegistrasiPesertaService::class)->sinkronkan($ujian)
        );
    }

    public function test_peserta_bisa_dicabut_kecuali_yang_sudah_mengerjakan(): void
    {
        $ujian = $this->ujianMoving();
        $this->post(route('ujian.peserta.tambah', $ujian), [
            'siswa_id' => [$this->siswaA->id, $this->siswaB->id],
        ]);

        $a = $ujian->peserta()->where('siswa_id', $this->siswaA->id)->firstOrFail();
        $b = $ujian->peserta()->where('siswa_id', $this->siswaB->id)->firstOrFail();

        $this->delete(route('ujian.peserta.hapus', [$ujian, $a]))->assertSessionHas('success');
        $this->assertDatabaseMissing('ujian_peserta', ['id' => $a->id]);

        $b->update(['status' => UjianPeserta::MULAI, 'waktu_mulai' => now()]);
        $this->delete(route('ujian.peserta.hapus', [$ujian, $b]))->assertSessionHas('error');
        $this->assertDatabaseHas('ujian_peserta', ['id' => $b->id]);
    }

    public function test_peserta_moving_melihat_ujiannya_di_ruang_ujian_siswa(): void
    {
        $ujian = $this->ujianMoving();
        $this->post(route('ujian.peserta.tambah', $ujian), ['siswa_id' => [$this->siswaB->id]]);

        // Siswa kelas B ikut ujian ini walau kelasnya tidak terdaftar sama
        // sekali pada ujian — inti dari kelas moving.
        $this->actingAs($this->siswaB, 'siswa')
            ->get('/siswa/ujian')
            ->assertOk()
            ->assertSee($ujian->nama_ujian);

    }

    public function test_siswa_yang_tidak_dipilih_tidak_melihat_ujian_moving(): void
    {
        $ujian = $this->ujianMoving();
        $this->post(route('ujian.peserta.tambah', $ujian), ['siswa_id' => [$this->siswaB->id]]);

        $this->actingAs($this->siswaA, 'siswa')
            ->get('/siswa/ujian')
            ->assertOk()
            ->assertDontSee($ujian->nama_ujian);
    }

    public function test_peserta_moving_terbaca_lewat_api_android(): void
    {
        $ujian = $this->ujianMoving();
        $this->post(route('ujian.peserta.tambah', $ujian), ['siswa_id' => [$this->siswaB->id]]);

        $mentah = SiswaApiToken::acak();
        SiswaApiToken::create([
            'siswa_id' => $this->siswaB->id,
            'token' => SiswaApiToken::sidik($mentah),
            'perangkat' => 'Uji Android',
            'terakhir_dipakai_at' => now(),
            'kedaluwarsa_at' => now()->addDays(SiswaApiToken::UMUR_HARI),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$mentah)
            ->getJson('/api/v1/ujian')
            ->assertOk()
            ->assertJsonFragment(['nama' => $ujian->nama_ujian]);
    }

    public function test_rekap_nilai_tetap_bisa_disaring_per_kelas_asal(): void
    {
        $ujian = $this->ujianMoving(['status' => Ujian::SELESAI]);
        $this->post(route('ujian.peserta.tambah', $ujian), [
            'siswa_id' => [$this->siswaA->id, $this->siswaB->id],
        ]);

        $ujian->peserta()->where('siswa_id', $this->siswaA->id)->update([
            'status' => UjianPeserta::SELESAI, 'nilai' => 90, 'essay_dinilai' => true,
        ]);
        $ujian->peserta()->where('siswa_id', $this->siswaB->id)->update([
            'status' => UjianPeserta::SELESAI, 'nilai' => 40, 'essay_dinilai' => true,
        ]);

        // Kedua kelas asal peserta muncul sebagai pilihan penyaring walau
        // tidak satu pun terdaftar di ujian_kelas.
        $this->assertEqualsCanonicalizing(
            [$this->kelasA->id, $this->kelasB->id],
            $ujian->rombelDipakai()->pluck('id')->map('intval')->all()
        );

        $halaman = $this->get("/laporan/nilai/{$ujian->id}?rombongan_belajar_id={$this->kelasA->id}")->assertOk();
        $halaman->assertSee($this->siswaA->nama_siswa);
        $halaman->assertDontSee($this->siswaB->nama_siswa);
    }

    /** @param array<string, mixed> $ganti */
    protected function ujianMoving(array $ganti = []): Ujian
    {
        return Ujian::create($this->dataUjian($ganti + ['mode_peserta' => Ujian::MODE_MANUAL]));
    }

    /**
     * @param  array<string, mixed>  $ganti
     * @return array<string, mixed>
     */
    protected function dataUjian(array $ganti = []): array
    {
        return $ganti + [
            'kode_ujian' => 'KM-MOV',
            'nama_ujian' => 'Ujian Moving Biologi',
            'paket_soal_id' => $this->paket->id,
            'waktu_mulai' => now()->subHour(),
            'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60,
            'kkm' => 75,
            'status' => Ujian::AKTIF,
            'mode_peserta' => Ujian::MODE_KELAS,
        ];
    }

    /**
     * @param  array<string, mixed>  $ganti
     * @return array<string, mixed>
     */
    protected function formUjian(array $ganti = []): array
    {
        return $ganti + [
            'kode_ujian' => 'KM-UJI',
            'nama_ujian' => 'Ujian Kelas Moving',
            'paket_soal_id' => $this->paket->id,
            'tahun_ajaran' => TahunAjaran::aktif()?->nama_tahun_ajaran,
            'semester' => 'Ganjil',
            'waktu_mulai' => now()->addDay()->format('Y-m-d H:i'),
            'waktu_selesai' => now()->addDay()->addHours(2)->format('Y-m-d H:i'),
            'durasi_menit' => 60,
            'kkm' => 75,
            'maks_pelanggaran' => 3,
            'status' => Ujian::DRAFT,
        ];
    }
}
