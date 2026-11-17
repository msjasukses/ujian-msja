<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\SiswaApiToken;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ambang tombol "Selesai & Kumpulkan" (ujian.menit_boleh_kumpul).
 *
 * Peserta baru boleh mengumpulkan lembarnya sendiri bila sisa waktu lembar
 * itu sudah kurang dari atau sama dengan ambang yang diisi admin. Yang diuji
 * di sini bukan hanya tombol yang mati di layar, tetapi penolakan di sisi
 * server: alamat "selesai" tetap bisa dipanggil langsung tanpa menekan
 * tombol apa pun, dari peramban maupun dari aplikasi Android.
 */
class AmbangSelesaiUjianTest extends TestCase
{
    use RefreshDatabase;

    /** Ambang yang dipakai sepanjang kelas ini: 15 menit terakhir. */
    protected const AMBANG_MENIT = 15;

    /** Durasi lembar tiap peserta pada ujian uji ini. */
    protected const DURASI_MENIT = 60;

    protected Siswa $siswa;

    protected Ujian $ujian;

    protected UjianPeserta $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        // Waktu dibekukan pada detik bulat supaya sisa waktu lembar bisa
        // dipastikan sampai ke detiknya: yang diuji di sini justru ambang
        // detik, dan pecahan detik pada waktu_mulai — yang tersimpan di basis
        // data tanpa pecahannya — sudah cukup menggeser angkanya satu detik.
        $this->travelTo(now()->startOfSecond());

        $siswa = Siswa::first();

        if (! $siswa) {
            $this->markTestSkipped('Database datacenter belum berisi data siswa.');
        }

        $this->siswa = $siswa;

        $paket = PaketSoal::create(['kode_paket' => 'AMB-1', 'nama_paket' => 'Paket', 'is_aktif' => true]);
        $soal = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Ibu kota Jabar?', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'Bandung'], ['key' => 'B', 'text' => 'Serang']],
            'kunci' => ['A'],
        ]);
        PaketSoalDetail::create([
            'paket_soal_id' => $paket->id, 'soal_id' => $soal->id, 'nomor_urut' => 1, 'bobot' => 1,
        ]);

        // Jendela ujian dibuat jauh lebih lebar dari durasinya supaya yang
        // menentukan sisa waktu adalah durasi lembar peserta — persis keadaan
        // yang membedakan "15 menit sebelum lembar saya berakhir" dari
        // "15 menit sebelum jam tutup ujian".
        $this->ujian = Ujian::create([
            'kode_ujian' => 'AMB', 'nama_ujian' => 'Ujian Ambang Selesai',
            'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subMinutes(5), 'waktu_selesai' => now()->addHours(4),
            'durasi_menit' => self::DURASI_MENIT, 'menit_boleh_kumpul' => self::AMBANG_MENIT,
            'kkm' => 75, 'status' => Ujian::AKTIF,
        ]);

        $this->peserta = UjianPeserta::create([
            'ujian_id' => $this->ujian->id, 'siswa_id' => $this->siswa->id,
            'nomor_peserta' => '001', 'status' => UjianPeserta::TERDAFTAR,
        ]);
    }

    /** Mulai mengerjakan sekarang, sehingga lembarnya berakhir 60 menit lagi. */
    protected function mulaiMengerjakan(): void
    {
        $this->actingAs($this->siswa, 'siswa')
            ->post(route('siswa.ujian.mulai', $this->peserta))
            ->assertRedirect(route('siswa.ujian.kerjakan', $this->peserta));
    }

    /** Majukan waktu sampai sisa lembar peserta tinggal $menit menit. */
    protected function majuSampaiSisa(int $menit): void
    {
        $this->travel(self::DURASI_MENIT - $menit)->minutes();
    }

    public function test_tombol_selesai_mati_dan_diberi_keterangan_sebelum_ambang(): void
    {
        $this->mulaiMengerjakan();

        $this->actingAs($this->siswa, 'siswa')
            ->get(route('siswa.ujian.kerjakan', $this->peserta))
            ->assertOk()
            // Tombolnya benar-benar dimatikan, bukan sekadar gagal saat ditekan.
            ->assertSee('tombol-selesai', false)
            ->assertSee('disabled', false)
            // Siswa diberi tahu kapan tombolnya terbuka, bukan dibiarkan
            // menebak kenapa tombolnya mati.
            ->assertSee('terbuka pada')
            ->assertSee(self::AMBANG_MENIT.' menit terakhir masa pengerjaan');
    }

    public function test_tombol_selesai_aktif_setelah_ambang_tercapai(): void
    {
        $this->mulaiMengerjakan();
        $this->majuSampaiSisa(10);

        $html = $this->actingAs($this->siswa, 'siswa')
            ->get(route('siswa.ujian.kerjakan', $this->peserta))
            ->assertOk()
            ->getContent();

        // Tombolnya tidak lagi dimatikan, dan catatan ambangnya disembunyikan.
        $this->assertStringNotContainsString('tombol-selesai" disabled', $html);
        $this->assertStringContainsString('catatan-selesai mt-2" style="display:none"', $html);
    }

    public function test_pengumpulan_sebelum_ambang_ditolak_server(): void
    {
        $this->mulaiMengerjakan();

        // Tombol dilangkahi: alamatnya dipanggil langsung.
        $this->actingAs($this->siswa, 'siswa')
            ->from(route('siswa.ujian.kerjakan', $this->peserta))
            ->post(route('siswa.ujian.selesai', $this->peserta))
            ->assertRedirect(route('siswa.ujian.kerjakan', $this->peserta))
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'belum boleh dikumpulkan'));

        // Lembarnya memang tidak dikumpulkan.
        $this->assertSame(UjianPeserta::MULAI, $this->peserta->fresh()->status);
        $this->assertNull($this->peserta->fresh()->waktu_selesai);
    }

    public function test_pengumpulan_diterima_setelah_ambang_tercapai(): void
    {
        $this->mulaiMengerjakan();
        $this->majuSampaiSisa(self::AMBANG_MENIT - 1);

        $this->actingAs($this->siswa, 'siswa')
            ->post(route('siswa.ujian.selesai', $this->peserta))
            ->assertRedirect(route('siswa.ujian.hasil', $this->peserta))
            ->assertSessionHas('success');

        $this->assertSame(UjianPeserta::SELESAI, $this->peserta->fresh()->status);
    }

    /** Ambang 0 — dan ujian lama yang kolomnya belum pernah diisi — bebas. */
    public function test_ambang_nol_berarti_boleh_mengumpulkan_kapan_saja(): void
    {
        $this->ujian->update(['menit_boleh_kumpul' => 0]);
        $this->mulaiMengerjakan();

        // Masih tersisa 60 menit, jauh sebelum ambang mana pun.
        $this->actingAs($this->siswa, 'siswa')
            ->get(route('siswa.ujian.kerjakan', $this->peserta))
            ->assertOk()
            // Tanpa ambang, tidak ada catatan menunggu yang perlu ditampilkan.
            ->assertDontSee('menit terakhir masa pengerjaan');

        $this->actingAs($this->siswa, 'siswa')
            ->post(route('siswa.ujian.selesai', $this->peserta))
            ->assertRedirect(route('siswa.ujian.hasil', $this->peserta));

        $this->assertSame(UjianPeserta::SELESAI, $this->peserta->fresh()->status);
    }

    /**
     * Penyerahan otomatis saat waktu habis tidak boleh ikut tertahan ambang:
     * pada saat itu sisa waktunya nol, dan lembarnya justru wajib ditutup.
     */
    public function test_penyerahan_otomatis_saat_waktu_habis_tidak_terhalang(): void
    {
        $this->mulaiMengerjakan();
        $this->travel(self::DURASI_MENIT + 1)->minutes();

        $this->actingAs($this->siswa, 'siswa')
            ->get(route('siswa.ujian.kerjakan', $this->peserta))
            ->assertRedirect(route('siswa.ujian.hasil', $this->peserta));

        $this->assertSame(UjianPeserta::SELESAI, $this->peserta->fresh()->status);
    }

    /** Pengawas tetap bisa menutup paksa lembar yang belum mencapai ambang. */
    public function test_penutupan_paksa_pengawas_tidak_terhalang(): void
    {
        $this->mulaiMengerjakan();

        $admin = User::create([
            'name' => 'Pengawas Ambang', 'email' => 'pengawas-ambang@ujian.test',
            'password' => 'rahasia123', 'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        // Guard 'web' disebut terang-terangan: pemanggilan actingAs sebelumnya
        // sudah menjadikan 'siswa' guard bawaan, sehingga admin tanpa guard
        // akan dipasang pada guard yang salah dan tertolak middleware.
        $this->actingAs($admin, 'web')
            ->post(route('monitoring.selesai', ['ujian' => $this->ujian, 'peserta' => $this->peserta]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(UjianPeserta::SELESAI, $this->peserta->fresh()->status);
    }

    public function test_api_android_tunduk_pada_ambang_yang_sama(): void
    {
        $this->mulaiMengerjakan();

        $token = $this->tokenAplikasi();

        // Lembar yang dikirim ke aplikasi sudah memberi tahu tombolnya belum
        // boleh ditekan, supaya aplikasi bisa mematikan tombolnya sendiri.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/ujian/{$this->peserta->id}/lembar")
            ->assertOk()
            ->assertJsonPath('peserta.boleh_kumpul', false)
            ->assertJsonPath('peserta.ujian.menit_boleh_kumpul', self::AMBANG_MENIT)
            ->assertJsonPath('peserta.detik_tunggu_kumpul', (self::DURASI_MENIT - self::AMBANG_MENIT) * 60);

        // Aplikasi yang tetap memanggil endpoint-nya ditolak server.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/ujian/{$this->peserta->id}/selesai")
            ->assertStatus(422)
            ->assertJsonPath('pesan', fn ($pesan) => str_contains($pesan, 'belum boleh dikumpulkan'));

        $this->assertSame(UjianPeserta::MULAI, $this->peserta->fresh()->status);

        // Sesudah ambang tercapai, jalur yang sama diterima.
        $this->majuSampaiSisa(self::AMBANG_MENIT);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/ujian/{$this->peserta->id}/denyut")
            ->assertOk()
            ->assertJsonPath('boleh_kumpul', true)
            ->assertJsonPath('detik_tunggu_kumpul', 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/ujian/{$this->peserta->id}/selesai")
            ->assertOk()
            ->assertJsonPath('selesai', true);

        $this->assertSame(UjianPeserta::SELESAI, $this->peserta->fresh()->status);
    }

    public function test_ambang_tersimpan_dari_formulir_ujian(): void
    {
        $admin = User::create([
            'name' => 'Admin Ambang', 'email' => 'admin-ambang@ujian.test',
            'password' => 'rahasia123', 'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('ujian.edit', $this->ujian))
            ->assertOk()
            ->assertSee('name="menit_boleh_kumpul"', false)
            ->assertSee('menit terakhir');

        $this->actingAs($admin)
            ->put(route('ujian.update', $this->ujian), $this->isianFormulir(['menit_boleh_kumpul' => 20]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(20, (int) $this->ujian->fresh()->menit_boleh_kumpul);

        // Dikosongkan berarti bebas, bukan galat validasi.
        $this->actingAs($admin)
            ->put(route('ujian.update', $this->ujian), $this->isianFormulir(['menit_boleh_kumpul' => '']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, (int) $this->ujian->fresh()->menit_boleh_kumpul);

        // Angka negatif ditolak.
        $this->actingAs($admin)
            ->put(route('ujian.update', $this->ujian), $this->isianFormulir(['menit_boleh_kumpul' => -5]))
            ->assertSessionHasErrors('menit_boleh_kumpul');
    }

    /**
     * Isian lengkap formulir ujian, supaya yang diuji benar-benar kolom
     * ambangnya dan bukan validasi kolom lain yang kebetulan kosong.
     *
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    protected function isianFormulir(array $ubah = []): array
    {
        return array_merge([
            'kode_ujian' => $this->ujian->kode_ujian,
            'nama_ujian' => $this->ujian->nama_ujian,
            'paket_soal_id' => $this->ujian->paket_soal_id,
            'tahun_ajaran' => $this->ujian->tahun_ajaran,
            'waktu_mulai' => $this->ujian->waktu_mulai->format('Y-m-d\TH:i'),
            'waktu_selesai' => $this->ujian->waktu_selesai->format('Y-m-d\TH:i'),
            'durasi_menit' => $this->ujian->durasi_menit,
            'kkm' => $this->ujian->kkm,
            'maks_pelanggaran' => 3,
            'status' => $this->ujian->status,
            'rombongan_belajar_id' => [RombonganBelajar::value('id')],
        ], $ubah);
    }

    protected function tokenAplikasi(): string
    {
        $mentah = SiswaApiToken::acak();

        SiswaApiToken::create([
            'siswa_id' => $this->siswa->id,
            'token' => SiswaApiToken::sidik($mentah),
            'perangkat' => 'Uji Android',
            'terakhir_dipakai_at' => now(),
            'kedaluwarsa_at' => now()->addDays(SiswaApiToken::UMUR_HARI),
        ]);

        return $mentah;
    }
}
