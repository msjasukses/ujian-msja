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
 * Ujian yang ditandai "Wajib lewat ExamBro" hanya boleh dikerjakan dari
 * peramban ujian. Peserta yang memakai peramban biasa ditolak sejak tombol
 * Mulai, dan lembar yang sudah berjalan pun berhenti bila alamatnya dibuka
 * dari luar ExamBro.
 */
class WajibExambroTest extends TestCase
{
    use RefreshDatabase;

    /** User agent WebView Android — bentuk yang dikirim ExamBro. */
    protected const UA_EXAMBRO = 'Mozilla/5.0 (Linux; Android 11; SM-A125F; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/120.0.0.0 Mobile Safari/537.36';

    protected const UA_CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    protected Siswa $siswa;

    protected Ujian $ujian;

    protected UjianPeserta $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $siswa = Siswa::first();

        if (! $siswa) {
            $this->markTestSkipped('Database datacenter belum berisi data siswa.');
        }

        $this->siswa = $siswa;

        $paket = PaketSoal::create(['kode_paket' => 'EXB-1', 'nama_paket' => 'Paket', 'is_aktif' => true]);
        $soal = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Ibu kota Jabar?', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'Bandung'], ['key' => 'B', 'text' => 'Serang']],
            'kunci' => ['A'],
        ]);
        PaketSoalDetail::create(['paket_soal_id' => $paket->id, 'soal_id' => $soal->id, 'nomor_urut' => 1, 'bobot' => 1]);

        $this->ujian = Ujian::create([
            'kode_ujian' => 'EXB', 'nama_ujian' => 'Ujian ExamBro', 'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subMinutes(5), 'waktu_selesai' => now()->addHours(2),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
            'wajib_exambro' => true,
        ]);

        $this->peserta = UjianPeserta::create([
            'ujian_id' => $this->ujian->id, 'siswa_id' => $this->siswa->id,
            'nomor_peserta' => '001', 'status' => UjianPeserta::TERDAFTAR,
        ]);
    }

    public function test_peramban_biasa_ditolak_memulai_ujian(): void
    {
        $this->actingAs($this->siswa, 'siswa')
            ->withHeader('User-Agent', self::UA_CHROME)
            ->post(route('siswa.ujian.mulai', $this->peserta))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'ExamBro'));

        // Ujiannya benar-benar tidak dimulai.
        $this->assertSame(UjianPeserta::TERDAFTAR, $this->peserta->fresh()->status);
        $this->assertNull($this->peserta->fresh()->waktu_mulai);

        // Penolakannya tercatat untuk pengawas, lengkap dengan user agent.
        $log = UjianLog::where('event', 'tolak_non_exambro')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Chrome', (string) $log->keterangan);
    }

    public function test_exambro_boleh_memulai_ujian(): void
    {
        $this->actingAs($this->siswa, 'siswa')
            ->withHeader('User-Agent', self::UA_EXAMBRO)
            ->post(route('siswa.ujian.mulai', $this->peserta))
            ->assertRedirect(route('siswa.ujian.kerjakan', $this->peserta));

        $this->assertSame(UjianPeserta::MULAI, $this->peserta->fresh()->status);
    }

    public function test_lembar_berjalan_tidak_bisa_dilanjutkan_di_peramban_biasa(): void
    {
        $this->actingAs($this->siswa, 'siswa')
            ->withHeader('User-Agent', self::UA_EXAMBRO)
            ->post(route('siswa.ujian.mulai', $this->peserta));

        $this->actingAs($this->siswa, 'siswa')
            ->withHeader('User-Agent', self::UA_EXAMBRO)
            ->get(route('siswa.ujian.kerjakan', $this->peserta))
            ->assertOk()
            ->assertSee('Ibu kota Jabar?');

        // Alamat yang sama disalin ke peramban biasa.
        $this->actingAs($this->siswa, 'siswa')
            ->withHeader('User-Agent', self::UA_CHROME)
            ->get(route('siswa.ujian.kerjakan', $this->peserta))
            ->assertRedirect(route('siswa.ujian.konfirmasi', $this->peserta))
            ->assertSessionHas('error');
    }

    public function test_halaman_konfirmasi_memberi_tahu_sebelum_tombol_ditekan(): void
    {
        $this->actingAs($this->siswa, 'siswa')
            ->withHeader('User-Agent', self::UA_CHROME)
            ->get(route('siswa.ujian.konfirmasi', $this->peserta))
            ->assertOk()
            ->assertSee('wajib dikerjakan lewat aplikasi ExamBro')
            // Tombol Mulai dimatikan, bukan sekadar gagal saat ditekan.
            ->assertSee('disabled', false);

        $this->actingAs($this->siswa, 'siswa')
            ->withHeader('User-Agent', self::UA_EXAMBRO)
            ->get(route('siswa.ujian.konfirmasi', $this->peserta))
            ->assertOk()
            ->assertSee('Peramban ujian terdeteksi');
    }

    /** Tanpa saklar itu, peramban biasa tetap boleh seperti sebelumnya. */
    public function test_ujian_tanpa_kewajiban_tidak_terpengaruh(): void
    {
        $this->ujian->update(['wajib_exambro' => false]);

        $this->actingAs($this->siswa, 'siswa')
            ->withHeader('User-Agent', self::UA_CHROME)
            ->post(route('siswa.ujian.mulai', $this->peserta))
            ->assertRedirect(route('siswa.ujian.kerjakan', $this->peserta));

        $this->assertSame(UjianPeserta::MULAI, $this->peserta->fresh()->status);
    }

    public function test_penanda_tambahan_dari_env_ikut_dikenali(): void
    {
        config(['ujian.penanda_exambro' => ['SekolahBro/']]);

        $this->actingAs($this->siswa, 'siswa')
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0 SekolahBro/2.1')
            ->post(route('siswa.ujian.mulai', $this->peserta))
            ->assertRedirect(route('siswa.ujian.kerjakan', $this->peserta));
    }

    public function test_saklar_tersimpan_dari_formulir_ujian(): void
    {
        $admin = User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('ujian.edit', $this->ujian))
            ->assertOk()
            ->assertSee('Wajib lewat ExamBro');

        $this->actingAs($admin)->put(route('ujian.update', $this->ujian), [
            'kode_ujian' => $this->ujian->kode_ujian,
            'nama_ujian' => $this->ujian->nama_ujian,
            'paket_soal_id' => $this->ujian->paket_soal_id,
            'waktu_mulai' => $this->ujian->waktu_mulai->toDateTimeString(),
            'waktu_selesai' => $this->ujian->waktu_selesai->toDateTimeString(),
            'durasi_menit' => 60, 'kkm' => 75, 'maks_pelanggaran' => 3,
            'status' => Ujian::AKTIF,
            'rombongan_belajar_id' => [$this->siswa->rombelPada()?->id ?? 1],
            // wajib_exambro sengaja tidak dikirim = kotak centang dilepas.
        ])->assertSessionHasNoErrors();

        $this->assertFalse($this->ujian->fresh()->wajib_exambro);
    }
}
