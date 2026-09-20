<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\Soal;
use App\Models\TahunAjaran;
use App\Models\Topik;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Models\UjianPeserta;
use App\Models\User;
use App\Services\PengerjaanUjianService;
use App\Services\RegistrasiPesertaService;
use App\Services\SinkronCpTpAtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji jalur utama aplikasi: menyusun soal, menjadwalkan ujian, siswa
 * mengerjakan, lalu hasilnya dikoreksi dan dilaporkan.
 *
 * Data siswa/guru/kelas dibaca dari database datacenter yang asli (read-only),
 * jadi tes ini dilewati bila database itu belum tersedia.
 */
class AlurUjianTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji',
            'email' => 'uji@ujian.test',
            'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN,
            'is_aktif' => true,
        ]);
    }

    /** Semua halaman pengelola bisa dibuka tanpa error. */
    public function test_halaman_pengelola_dapat_diakses(): void
    {
        $this->actingAs($this->admin);

        $halaman = [
            '/dashboard',
            '/topik', '/topik/create', '/topik/sinkron',
            '/soal', '/soal/create', '/soal/import',
            '/paket-soal', '/paket-soal/create',
            '/ujian', '/ujian/create',
            '/laporan/nilai', '/laporan/statistik', '/laporan/analisis-butir',
            '/laporan/remidial', '/laporan/pengayaan',
            '/monitoring',
            '/referensi/siswa', '/referensi/guru', '/referensi/wali-kelas',
            '/referensi/guru-mapel', '/referensi/mata-pelajaran',
            '/referensi/tingkat-kelas', '/referensi/tahun-ajaran',
            '/log-login', '/pengguna', '/pengguna/create',
        ];

        foreach ($halaman as $url) {
            $this->get($url)->assertOk();
        }
    }

    /** Halaman detail yang butuh data (kelola paket, edit soal/ujian) ikut dibuka. */
    public function test_halaman_detail_dapat_diakses(): void
    {
        $this->actingAs($this->admin);

        $topik = Topik::create(['nama_topik' => 'Topik Uji', 'sumber' => Topik::SUMBER_MANUAL]);
        $soal = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Uji?', 'bobot' => 1, 'topik_id' => $topik->id,
            'opsi' => [['key' => 'A', 'text' => 'ya'], ['key' => 'B', 'text' => 'tidak']], 'kunci' => ['A'],
        ]);
        $paket = PaketSoal::create(['kode_paket' => 'DTL-1', 'nama_paket' => 'Paket', 'is_aktif' => true]);
        PaketSoalDetail::create([
            'paket_soal_id' => $paket->id, 'soal_id' => $soal->id, 'nomor_urut' => 1, 'bobot' => 1,
        ]);
        $ujian = Ujian::create([
            'kode_ujian' => 'DTL-UJN', 'nama_ujian' => 'Ujian Detail', 'paket_soal_id' => $paket->id,
            'waktu_mulai' => now(), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::DRAFT,
        ]);

        foreach ([
            "/topik/{$topik->id}/edit",
            "/soal/{$soal->id}",
            "/soal/{$soal->id}/edit",
            "/paket-soal/{$paket->id}/edit",
            "/paket-soal/{$paket->id}/kelola",
            "/ujian/{$ujian->id}/edit",
            "/ujian/{$ujian->id}/peserta",
            "/monitoring/{$ujian->id}",
            "/monitoring/{$ujian->id}/data",
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    /** Butir bisa dipilih ke paket lalu urutan & bobotnya disunting. */
    public function test_pemilihan_soal_ke_paket(): void
    {
        $this->actingAs($this->admin);

        $paket = PaketSoal::create(['kode_paket' => 'PLH-1', 'nama_paket' => 'Paket', 'is_aktif' => true]);

        $soal = collect(range(1, 3))->map(fn ($i) => Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => "Soal {$i}", 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'a'], ['key' => 'B', 'text' => 'b']], 'kunci' => ['A'],
        ]));

        $this->post("/paket-soal/{$paket->id}/soal", ['soal_id' => $soal->pluck('id')->all()])
            ->assertRedirect();

        $this->assertSame(3, $paket->detail()->count());
        $this->assertSame([1, 2, 3], $paket->detail()->pluck('nomor_urut')->all());

        // Butir yang sama tidak ditambahkan dua kali.
        $this->post("/paket-soal/{$paket->id}/soal", ['soal_id' => [$soal[0]->id]]);
        $this->assertSame(3, $paket->detail()->count());

        // Ubah bobot & urutan.
        $detail = $paket->detail()->get();
        $this->put("/paket-soal/{$paket->id}/urutan", [
            'urut' => [$detail[0]->id => 3, $detail[1]->id => 1, $detail[2]->id => 2],
            'bobot' => [$detail[0]->id => 5, $detail[1]->id => 2, $detail[2]->id => 1],
        ])->assertRedirect();

        $this->assertEquals(5, $detail[0]->fresh()->bobot);
        $this->assertSame(8.0, $paket->fresh()->total_bobot);

        // Melepas butir merapikan penomoran menjadi 1..n lagi.
        $this->delete("/paket-soal/{$paket->id}/soal/{$detail[0]->id}")->assertRedirect();
        $this->assertSame([1, 2], $paket->detail()->pluck('nomor_urut')->all());
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/siswa/ujian')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    /** Kelima jenis soal bisa disimpan lewat form input soal. */
    public function test_semua_jenis_soal_bisa_disimpan(): void
    {
        $this->actingAs($this->admin);

        $dasar = ['pertanyaan' => 'Pertanyaan uji', 'bobot' => 1, 'is_aktif' => 1];

        $this->post('/soal', $dasar + [
            'jenis' => Soal::PG,
            'opsi_text' => ['Bandung', 'Semarang', 'Serang'],
            'kunci_pg' => ['A'],
        ])->assertRedirect();

        $this->post('/soal', $dasar + [
            'jenis' => Soal::PG_KOMPLEKS,
            'opsi_text' => ['2', '4', '7'],
            'kunci_pg' => ['A', 'C'],
        ])->assertRedirect();

        $this->post('/soal', $dasar + ['jenis' => Soal::BENAR_SALAH, 'kunci_bs' => 'benar'])->assertRedirect();

        $this->post('/soal', $dasar + [
            'jenis' => Soal::ESSAY,
            'kunci_essay' => 'Jawaban model',
            'kata_kunci' => 'satu, dua',
        ])->assertRedirect();

        $this->post('/soal', $dasar + [
            'jenis' => Soal::PENJODOHAN,
            'jodoh_kiri' => ['Jepang', 'Korea'],
            'jodoh_kanan' => ['Tokyo', 'Seoul'],
        ])->assertRedirect();

        $this->assertSame(5, Soal::count());
        $this->assertSame(['A', 'C'], Soal::where('jenis', Soal::PG_KOMPLEKS)->first()->kunci);
        $this->assertSame(['1' => 'A', '2' => 'B'], Soal::where('jenis', Soal::PENJODOHAN)->first()->kunci);
    }

    /** Pilihan ganda biasa menolak kunci lebih dari satu. */
    public function test_pg_biasa_menolak_kunci_ganda(): void
    {
        $this->actingAs($this->admin);

        $this->post('/soal', [
            'jenis' => Soal::PG,
            'pertanyaan' => 'Uji',
            'bobot' => 1,
            'opsi_text' => ['a', 'b', 'c'],
            'kunci_pg' => ['A', 'B'],
        ])->assertSessionHasErrors('kunci_pg');
    }

    /** Koreksi otomatis tiap jenis soal menghasilkan skor yang diharapkan. */
    public function test_koreksi_otomatis_per_jenis_soal(): void
    {
        $pg = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'x', 'bobot' => 2,
            'opsi' => [['key' => 'A', 'text' => 'a'], ['key' => 'B', 'text' => 'b']],
            'kunci' => ['A'],
        ]);
        $this->assertSame(2.0, $pg->koreksi(['A'])['skor']);
        $this->assertSame(0.0, $pg->koreksi(['B'])['skor']);
        $this->assertSame(0.0, $pg->koreksi(null)['skor']);

        // PG kompleks memakai skor parsial: satu benar dari dua kunci = separuh.
        $pgk = Soal::create([
            'jenis' => Soal::PG_KOMPLEKS, 'pertanyaan' => 'x', 'bobot' => 4,
            'opsi' => [['key' => 'A', 'text' => 'a'], ['key' => 'B', 'text' => 'b'], ['key' => 'C', 'text' => 'c']],
            'kunci' => ['A', 'C'],
        ]);
        $this->assertSame(4.0, $pgk->koreksi(['A', 'C'])['skor']);
        $this->assertSame(2.0, $pgk->koreksi(['A'])['skor']);
        // Satu benar + satu salah saling meniadakan.
        $this->assertSame(0.0, $pgk->koreksi(['A', 'B'])['skor']);

        $jodoh = Soal::create([
            'jenis' => Soal::PENJODOHAN, 'pertanyaan' => 'x', 'bobot' => 4,
            'opsi' => ['kiri' => [['key' => '1', 'text' => 'a'], ['key' => '2', 'text' => 'b']],
                'kanan' => [['key' => 'A', 'text' => 'x'], ['key' => 'B', 'text' => 'y']]],
            'kunci' => ['1' => 'A', '2' => 'B'],
        ]);
        $this->assertSame(4.0, $jodoh->koreksi(['1' => 'A', '2' => 'B'])['skor']);
        $this->assertSame(2.0, $jodoh->koreksi(['1' => 'A', '2' => 'A'])['skor']);

        $essay = Soal::create(['jenis' => Soal::ESSAY, 'pertanyaan' => 'x', 'bobot' => 5,
            'kunci' => ['jawaban' => 'y', 'kata_kunci' => []]]);
        // Essay selalu menunggu penilaian guru.
        $this->assertNull($essay->koreksi(['teks' => 'apa pun'])['is_benar']);
    }

    /** Alur penuh: jadwal ujian, siswa mengerjakan, nilai terhitung. */
    public function test_alur_pengerjaan_ujian_sampai_nilai(): void
    {
        $siswa = Siswa::first();

        if (! $siswa) {
            $this->markTestSkipped('Database datacenter belum berisi data siswa.');
        }

        $paket = PaketSoal::create([
            'kode_paket' => 'UJI-1', 'nama_paket' => 'Paket Uji', 'is_aktif' => true,
        ]);

        $pg = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Ibu kota Jabar?', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'Bandung'], ['key' => 'B', 'text' => 'Serang']],
            'kunci' => ['A'],
        ]);
        $essay = Soal::create([
            'jenis' => Soal::ESSAY, 'pertanyaan' => 'Jelaskan!', 'bobot' => 3,
            'kunci' => ['jawaban' => 'Penjelasan', 'kata_kunci' => []],
        ]);

        foreach ([$pg, $essay] as $i => $soal) {
            PaketSoalDetail::create([
                'paket_soal_id' => $paket->id, 'soal_id' => $soal->id,
                'nomor_urut' => $i + 1, 'bobot' => $soal->bobot,
            ]);
        }

        $ujian = Ujian::create([
            'kode_ujian' => 'UJI-UJN-1', 'nama_ujian' => 'Ujian Uji',
            'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subMinutes(5), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
        ]);

        $peserta = UjianPeserta::create([
            'ujian_id' => $ujian->id, 'siswa_id' => $siswa->id, 'status' => UjianPeserta::TERDAFTAR,
        ]);

        $pengerjaan = app(PengerjaanUjianService::class);
        $pengerjaan->mulai($peserta);

        $this->assertSame(2, $peserta->jawaban()->count());
        $this->assertSame(UjianPeserta::MULAI, $peserta->fresh()->status);

        $pengerjaan->simpanJawaban($peserta, $pg->id, ['A']);
        $pengerjaan->simpanJawaban($peserta, $essay->id, ['teks' => 'Jawaban saya']);

        $peserta = $pengerjaan->selesaikan($peserta);

        // Bagian objektif langsung terkoreksi (1 dari total bobot 4 = 25).
        $this->assertSame(UjianPeserta::SELESAI, $peserta->status);
        $this->assertEquals(1, (float) $peserta->skor_objektif);
        $this->assertEquals(25, (float) $peserta->nilai);
        $this->assertFalse($peserta->essay_dinilai);

        // Guru menilai essay penuh -> nilai naik menjadi 100.
        $this->actingAs($this->admin);
        $jawabanEssay = $peserta->jawaban()->where('soal_id', $essay->id)->first();

        $this->post("/laporan/nilai/{$ujian->id}/peserta/{$peserta->id}/essay", [
            'skor' => [$jawabanEssay->id => 3],
        ])->assertRedirect();

        $peserta->refresh();
        $this->assertEquals(100, (float) $peserta->nilai);
        $this->assertTrue($peserta->essay_dinilai);

        // Halaman laporan untuk ujian ini bisa dibuka semuanya.
        foreach ([
            "/laporan/nilai/{$ujian->id}",
            "/laporan/nilai/{$ujian->id}/peserta/{$peserta->id}",
            "/laporan/statistik/{$ujian->id}",
            "/laporan/analisis-butir/{$ujian->id}",
            "/laporan/remidial/{$ujian->id}",
            "/laporan/pengayaan/{$ujian->id}",
            "/monitoring/{$ujian->id}",
            "/ujian/{$ujian->id}/peserta",
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    /** Peserta ujian ditarik dari penempatan kelas di datacenter. */
    public function test_registrasi_peserta_mengambil_siswa_dari_kelas(): void
    {
        // Peserta hanya ditarik untuk tahun ajaran yang aktif, jadi kelas yang
        // diuji harus punya penempatan siswa pada tahun ajaran itu.
        $tahunAjaranId = TahunAjaran::aktif()?->id;
        $penempatan = SiswaRombel::where('tahun_ajaran_id', $tahunAjaranId)->first();

        if (! $penempatan) {
            $this->markTestSkipped('Database datacenter belum berisi penempatan siswa pada tahun ajaran aktif.');
        }

        $paket = PaketSoal::create(['kode_paket' => 'UJI-2', 'nama_paket' => 'Paket', 'is_aktif' => true]);
        $ujian = Ujian::create([
            'kode_ujian' => 'UJI-UJN-2', 'nama_ujian' => 'Ujian Kelas',
            'paket_soal_id' => $paket->id,
            'waktu_mulai' => now(), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::DRAFT,
        ]);
        UjianKelas::create(['ujian_id' => $ujian->id, 'rombongan_belajar_id' => $penempatan->rombongan_belajar_id]);

        $hasil = app(RegistrasiPesertaService::class)->sinkronkan($ujian);

        $this->assertGreaterThan(0, $hasil['ditambah']);
        $this->assertSame($hasil['total'], $ujian->peserta()->count());

        // Sinkron kedua kalinya tidak boleh menggandakan peserta.
        $ulang = app(RegistrasiPesertaService::class)->sinkronkan($ujian);
        $this->assertSame(0, $ulang['ditambah']);
        $this->assertSame($hasil['total'], $ulang['total']);
    }

    /** Sinkron CP-TP-ATP bersifat idempoten. */
    public function test_sinkron_cp_tp_atp_tidak_menggandakan_topik(): void
    {
        $service = app(SinkronCpTpAtpService::class);

        if (! $service->koneksiTersedia()) {
            $this->markTestSkipped('Database kurikulum tidak tersedia.');
        }

        $pertama = $service->jalankan();

        if ($pertama['total'] === 0) {
            $this->markTestSkipped('Tabel pemetaan_cp_tp_atp masih kosong.');
        }

        $jumlahTopik = Topik::count();

        $kedua = $service->jalankan();

        $this->assertSame(0, $kedua['baru']);
        $this->assertSame($jumlahTopik, Topik::count());
        $this->assertSame($pertama['baru'], $kedua['diperbarui']);
    }
}
