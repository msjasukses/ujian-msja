<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Siswa;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianLog;
use App\Models\UjianPeserta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jejak aktivitas ujian punya halamannya sendiri: layar monitoring dipakai
 * memantau keadaan terkini, sedangkan penelusuran kejadian butuh seluruh
 * catatan beserta penyaringnya.
 */
class JejakAktivitasTest extends TestCase
{
    use RefreshDatabase;

    protected Ujian $ujian;

    protected UjianPeserta $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $siswa = Siswa::first();

        if (! $siswa) {
            $this->markTestSkipped('Database datacenter belum berisi data siswa.');
        }

        $this->actingAs(User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]), 'web');

        $paket = PaketSoal::create(['kode_paket' => 'JJK-1', 'nama_paket' => 'Paket', 'is_aktif' => true]);
        $soal = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Uji?', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'a'], ['key' => 'B', 'text' => 'b']], 'kunci' => ['A'],
        ]);
        PaketSoalDetail::create(['paket_soal_id' => $paket->id, 'soal_id' => $soal->id, 'nomor_urut' => 1, 'bobot' => 1]);

        $this->ujian = Ujian::create([
            'kode_ujian' => 'JJK', 'nama_ujian' => 'Ujian Jejak', 'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subHour(), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
        ]);

        $this->peserta = UjianPeserta::create([
            'ujian_id' => $this->ujian->id, 'siswa_id' => $siswa->id,
            'rombongan_belajar_id' => $siswa->rombelPada()?->id,
            'nomor_peserta' => '001', 'status' => UjianPeserta::MULAI, 'waktu_mulai' => now()->subMinutes(10),
        ]);

        UjianLog::catat($this->ujian->id, $this->peserta->id, 'mulai', 'Awal pengerjaan');
        UjianLog::catat($this->ujian->id, $this->peserta->id, 'tab_baru', 'Membuka tab lain');
        UjianLog::catat($this->ujian->id, $this->peserta->id, 'sesi_ganda', 'Masuk dari perangkat lain');
    }

    public function test_layar_monitoring_tidak_lagi_memuat_panel_jejak(): void
    {
        $this->get(route('monitoring.show', $this->ujian))
            ->assertOk()
            ->assertDontSee('Jejak Aktivitas Terakhir')
            // Tombol menuju halaman tersendiri tetap ada di samping penyaring.
            ->assertSee(route('monitoring.jejak', $this->ujian), false)
            ->assertSee('Jejak Aktivitas');
    }

    public function test_halaman_jejak_menampilkan_seluruh_kejadian(): void
    {
        $halaman = $this->get(route('monitoring.jejak', $this->ujian))->assertOk();

        $halaman->assertSee('Awal pengerjaan');
        $halaman->assertSee('Membuka tab lain');
        $halaman->assertSee('Masuk dari perangkat lain');
        $halaman->assertSee($this->peserta->siswa->nama_siswa);
    }

    public function test_penyaring_jenis_kejadian(): void
    {
        // Yang diperiksa keterangan barisnya, bukan nama kejadian — nama itu
        // juga muncul sebagai pilihan pada dropdown penyaring.
        $halaman = $this->get(route('monitoring.jejak', [$this->ujian, 'event' => 'pelanggaran']))->assertOk();

        $halaman->assertSee('Membuka tab lain');
        $halaman->assertDontSee('Awal pengerjaan');

        $satuJenis = $this->get(route('monitoring.jejak', [$this->ujian, 'event' => 'sesi_ganda']))->assertOk();
        $satuJenis->assertSee('Masuk dari perangkat lain');
        $satuJenis->assertDontSee('Membuka tab lain');
    }

    public function test_penyaring_kelas_dan_pencarian_siswa(): void
    {
        $rombelId = $this->peserta->rombongan_belajar_id;

        if ($rombelId) {
            $this->get(route('monitoring.jejak', [$this->ujian, 'rombongan_belajar_id' => $rombelId]))
                ->assertOk()
                ->assertSee('Awal pengerjaan');

            $this->get(route('monitoring.jejak', [$this->ujian, 'rombongan_belajar_id' => $rombelId + 9999]))
                ->assertOk()
                ->assertSee('Tidak ada kejadian yang cocok');
        }

        $this->get(route('monitoring.jejak', [$this->ujian, 'q' => 'nama-yang-tidak-ada']))
            ->assertOk()
            ->assertSee('Tidak ada kejadian yang cocok');
    }

    public function test_ringkasan_menghitung_kejadian_penting(): void
    {
        UjianLog::catat($this->ujian->id, $this->peserta->id, 'tolak_non_exambro', 'Chrome');

        $this->get(route('monitoring.jejak', $this->ujian))
            ->assertOk()
            ->assertSee('Total kejadian')
            ->assertSee('Ditolak masuk');

        $this->assertSame(1, UjianLog::where('ujian_id', $this->ujian->id)->pelanggaran()->count());
    }

    /** Guru tidak boleh menelusuri jejak ujian milik guru lain. */
    public function test_jejak_ujian_guru_lain_tertutup(): void
    {
        $guru = \App\Models\Guru::first();

        if (! $guru) {
            $this->markTestSkipped('Database datacenter belum berisi data guru.');
        }

        $this->ujian->update(['guru_id' => $guru->id + 1000000]);

        // Sesi admin dari setUp diakhiri dulu: selama sesi itu masih ada,
        // yang berlaku peran admin dan seluruh ujian memang boleh dibuka.
        auth()->guard('web')->logout();

        $this->actingAs($guru, 'guru')
            ->get(route('monitoring.jejak', $this->ujian))
            ->assertNotFound();
    }
}
