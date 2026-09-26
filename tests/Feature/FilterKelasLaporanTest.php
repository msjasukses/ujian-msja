<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Models\UjianPeserta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penyaring kelas di menu Hasil & Laporan Ujian: menyaring daftar ujian, dan
 * pada halaman detail membuat seluruh angkanya dihitung dari kelas itu saja.
 */
class FilterKelasLaporanTest extends TestCase
{
    use RefreshDatabase;

    protected Ujian $ujian;

    protected RombonganBelajar $kelasA;

    protected RombonganBelajar $kelasB;

    /** @var array<string, UjianPeserta> */
    protected array $peserta = [];

    protected function setUp(): void
    {
        parent::setUp();

        $kelas = RombonganBelajar::orderBy('id')->take(2)->get();
        $siswa = Siswa::orderBy('id')->take(2)->get();

        if ($kelas->count() < 2 || $siswa->count() < 2) {
            $this->markTestSkipped('Database datacenter belum berisi dua kelas dan dua siswa.');
        }

        [$this->kelasA, $this->kelasB] = [$kelas[0], $kelas[1]];

        $this->actingAs(User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]));

        $paket = PaketSoal::create(['kode_paket' => 'FK-1', 'nama_paket' => 'Paket', 'is_aktif' => true]);
        $soal = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Ibu kota Jabar?', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'Bandung'], ['key' => 'B', 'text' => 'Serang']],
            'kunci' => ['A'],
        ]);
        PaketSoalDetail::create(['paket_soal_id' => $paket->id, 'soal_id' => $soal->id, 'nomor_urut' => 1, 'bobot' => 1]);

        $this->ujian = Ujian::create([
            'kode_ujian' => 'FK', 'nama_ujian' => 'Ujian Dua Kelas', 'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subDay(), 'waktu_selesai' => now()->subHours(20),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::SELESAI,
        ]);

        foreach ([$this->kelasA, $this->kelasB] as $k) {
            UjianKelas::create(['ujian_id' => $this->ujian->id, 'rombongan_belajar_id' => $k->id]);
        }

        // Kelas A tuntas (90), kelas B tidak (40) — supaya angka ringkasannya
        // berbeda jelas saat disaring.
        $this->peserta['a'] = $this->peserta($siswa[0]->id, $this->kelasA->id, 90);
        $this->peserta['b'] = $this->peserta($siswa[1]->id, $this->kelasB->id, 40);
    }

    public function test_daftar_ujian_tersaring_menurut_kelas(): void
    {
        // Ujian lain yang hanya diikuti kelas B.
        $paket = PaketSoal::create(['kode_paket' => 'FK-2', 'nama_paket' => 'Paket B', 'is_aktif' => true]);
        $lain = Ujian::create([
            'kode_ujian' => 'FKB', 'nama_ujian' => 'Ujian Kelas B Saja', 'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subDay(), 'waktu_selesai' => now()->subHours(20),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::SELESAI,
        ]);
        UjianKelas::create(['ujian_id' => $lain->id, 'rombongan_belajar_id' => $this->kelasB->id]);

        foreach (['/laporan/nilai', '/laporan/statistik', '/laporan/analisis-butir',
            '/laporan/remidial', '/laporan/pengayaan'] as $url) {
            // Tanpa penyaring, keduanya tampil.
            $this->get($url)->assertOk()->assertSee('Ujian Dua Kelas')->assertSee('Ujian Kelas B Saja');

            $this->get($url.'?rombongan_belajar_id='.$this->kelasA->id)
                ->assertOk()
                ->assertSee('Ujian Dua Kelas')
                ->assertDontSee('Ujian Kelas B Saja');
        }
    }

    public function test_daftar_nilai_hanya_memuat_peserta_kelas_terpilih(): void
    {
        $halaman = $this->get("/laporan/nilai/{$this->ujian->id}?rombongan_belajar_id={$this->kelasA->id}")->assertOk();

        $halaman->assertSee($this->peserta['a']->siswa->nama_siswa);
        $halaman->assertDontSee($this->peserta['b']->siswa->nama_siswa);
    }

    public function test_statistik_dihitung_ulang_per_kelas(): void
    {
        // Dua peserta: rata-rata gabungan 65.
        $this->get("/laporan/statistik/{$this->ujian->id}")
            ->assertOk()
            ->assertSee('65');

        // Kelas A saja: rata-rata 90 dan seluruhnya tuntas.
        $kelasA = $this->get("/laporan/statistik/{$this->ujian->id}?rombongan_belajar_id={$this->kelasA->id}")
            ->assertOk();

        $kelasA->assertSee('90');
        $kelasA->assertSee('Semua angka di halaman ini dihitung dari kelas yang dipilih saja.');
    }

    public function test_remidial_hanya_menampilkan_kelas_terpilih(): void
    {
        // Yang di bawah KKM hanya kelas B.
        $this->get("/laporan/remidial/{$this->ujian->id}")
            ->assertOk()
            ->assertSee($this->peserta['b']->siswa->nama_siswa);

        $this->get("/laporan/remidial/{$this->ujian->id}?rombongan_belajar_id={$this->kelasA->id}")
            ->assertOk()
            ->assertDontSee($this->peserta['b']->siswa->nama_siswa);
    }

    public function test_pilihan_kelas_hanya_yang_ikut_ujian(): void
    {
        $luar = RombonganBelajar::whereNotIn('id', [$this->kelasA->id, $this->kelasB->id])->first();

        $halaman = $this->get("/laporan/statistik/{$this->ujian->id}")->assertOk();

        $halaman->assertSee('value="'.$this->kelasA->id.'"', false);
        $halaman->assertSee('value="'.$this->kelasB->id.'"', false);

        if ($luar) {
            $halaman->assertDontSee('>'.$luar->nama_rombel.'</option>', false);
        }
    }

    public function test_export_ikut_tersaring(): void
    {
        $jawab = $this->get("/laporan/nilai/{$this->ujian->id}/export?rombongan_belajar_id={$this->kelasA->id}");

        $jawab->assertOk();
        $this->assertStringContainsString('spreadsheet', $jawab->headers->get('content-type'));
    }

    protected function peserta(int $siswaId, int $rombelId, float $nilai): UjianPeserta
    {
        return UjianPeserta::create([
            'ujian_id' => $this->ujian->id,
            'siswa_id' => $siswaId,
            'rombongan_belajar_id' => $rombelId,
            'nomor_peserta' => (string) $siswaId,
            'status' => UjianPeserta::SELESAI,
            'nilai' => $nilai,
            'essay_dinilai' => true,
            'waktu_mulai' => now()->subDay(),
            'waktu_selesai' => now()->subDay()->addMinutes(30),
        ]);
    }
}
