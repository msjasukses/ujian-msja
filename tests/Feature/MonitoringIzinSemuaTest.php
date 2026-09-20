<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianLog;
use App\Models\UjianPeserta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Izin serentak: seluruh peserta yang lembarnya terkunci dibolehkan
 * melanjutkan ujian dengan satu tombol.
 */
class MonitoringIzinSemuaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Ujian $ujian;

    /** @var array<string, UjianPeserta> */
    protected array $p = [];

    protected int $kelas1;

    protected int $kelas2;

    protected function setUp(): void
    {
        parent::setUp();

        $siswa = Siswa::orderBy('id')->take(4)->pluck('id');
        $rombel = RombonganBelajar::orderBy('id')->take(2)->pluck('id');

        if ($siswa->count() < 4 || $rombel->count() < 2) {
            $this->markTestSkipped('Database datacenter belum berisi cukup siswa dan rombel.');
        }

        [$this->kelas1, $this->kelas2] = [$rombel[0], $rombel[1]];

        $this->admin = User::create([
            'name' => 'Pengawas', 'email' => 'pengawas@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $paket = PaketSoal::create(['kode_paket' => 'IZN-P', 'nama_paket' => 'Paket', 'is_aktif' => true]);

        $this->ujian = Ujian::create([
            'kode_ujian' => 'IZN-UJN', 'nama_ujian' => 'Ujian Izin', 'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subMinutes(10), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
        ]);

        $buat = fn (int $i, int $kelas, int $pelanggaran, bool $terkunci) => UjianPeserta::create([
            'ujian_id' => $this->ujian->id, 'siswa_id' => $siswa[$i], 'rombongan_belajar_id' => $kelas,
            'status' => UjianPeserta::MULAI, 'waktu_mulai' => now()->subMinutes(5),
            'pelanggaran' => $pelanggaran, 'dikunci_at' => $terkunci ? now() : null,
        ]);

        $this->p['kunci1a'] = $buat(0, $this->kelas1, 3, true);
        $this->p['kunci1b'] = $buat(1, $this->kelas1, 3, true);
        $this->p['kunci2'] = $buat(2, $this->kelas2, 3, true);
        $this->p['belumTerkunci'] = $buat(3, $this->kelas1, 2, false);

        foreach ($this->p as $p) {
            UjianLog::create(['ujian_id' => $this->ujian->id, 'ujian_peserta_id' => $p->id, 'event' => 'hilang_fokus']);
        }
    }

    public function test_semua_yang_terkunci_diizinkan_lanjut(): void
    {
        $this->actingAs($this->admin)
            ->post(route('monitoring.buka-kunci-semua', $this->ujian))
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach (['kunci1a', 'kunci1b', 'kunci2'] as $k) {
            $segar = $this->p[$k]->fresh();
            $this->assertNull($segar->dikunci_at, "{$k} seharusnya sudah tidak terkunci.");
            $this->assertSame(0, $segar->pelanggaran);

            $this->assertDatabaseHas('ujian_log', [
                'ujian_peserta_id' => $this->p[$k]->id,
                'event' => 'dibuka_pengawas',
                'keterangan' => 'Diizinkan serentak bersama 3 peserta.',
            ]);
        }
    }

    /**
     * Yang belum terkunci tidak disentuh: ia memang sudah boleh melanjutkan,
     * dan mengampuni pelanggarannya bukan maksud tombol ini.
     */
    public function test_yang_belum_terkunci_tidak_dinolkan(): void
    {
        $this->actingAs($this->admin)->post(route('monitoring.buka-kunci-semua', $this->ujian));

        $this->assertSame(2, $this->p['belumTerkunci']->fresh()->pelanggaran);
        $this->assertDatabaseMissing('ujian_log', [
            'ujian_peserta_id' => $this->p['belumTerkunci']->id,
            'event' => 'dibuka_pengawas',
        ]);
    }

    /** Rincian pelanggaran tetap tercatat — izin tidak menghapus jejak. */
    public function test_jejak_pelanggaran_tetap_ada(): void
    {
        $this->actingAs($this->admin)->post(route('monitoring.buka-kunci-semua', $this->ujian));

        $data = $this->actingAs($this->admin)
            ->getJson(route('monitoring.data', ['ujian' => $this->ujian, 'status' => 'melanggar']))
            ->json();

        $this->assertSame(4, $data['ringkasan']['melanggar']);
        $this->assertSame(0, $data['ringkasan']['terkunci']);
    }

    /** Disaring per kelas: izinnya hanya untuk kelas itu. */
    public function test_saringan_kelas_membatasi_izin(): void
    {
        $this->actingAs($this->admin)
            ->post(route('monitoring.buka-kunci-semua', $this->ujian), ['rombongan_belajar_id' => $this->kelas1]);

        $this->assertNull($this->p['kunci1a']->fresh()->dikunci_at);
        $this->assertNull($this->p['kunci1b']->fresh()->dikunci_at);
        $this->assertNotNull($this->p['kunci2']->fresh()->dikunci_at, 'Kelas lain tidak boleh ikut diizinkan.');
    }

    public function test_tombol_menyebut_jumlah_dan_nama(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('monitoring.show', $this->ujian))
            ->assertOk()
            ->assertSee('Izinkan semua lanjut (3)')
            ->getContent();

        foreach (['kunci1a', 'kunci1b', 'kunci2'] as $k) {
            $this->assertStringContainsString(e($this->p[$k]->siswa->nama_siswa), $html);
        }

        // Disaring ke kelas 1: tombolnya menyebut dua, dan membawa kelasnya.
        $this->actingAs($this->admin)
            ->get(route('monitoring.show', ['ujian' => $this->ujian, 'rombongan_belajar_id' => $this->kelas1]))
            ->assertSee('Izinkan semua lanjut (2)')
            ->assertSee('name="rombongan_belajar_id" value="'.$this->kelas1.'"', false);
    }

    public function test_tanpa_lembar_terkunci_tombol_tidak_ada(): void
    {
        UjianPeserta::where('ujian_id', $this->ujian->id)->update(['dikunci_at' => null]);

        $this->actingAs($this->admin)
            ->get(route('monitoring.show', $this->ujian))
            ->assertOk()
            ->assertDontSee('Izinkan semua lanjut');

        $this->actingAs($this->admin)
            ->post(route('monitoring.buka-kunci-semua', $this->ujian))
            ->assertSessionHas('error');
    }
}
