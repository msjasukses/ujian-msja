<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Siswa;
use App\Models\SiswaApiToken;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lembar ujian dengan pengacakan opsi menyala.
 *
 * Yang diacak hanya isi opsinya. Abjad di kolom huruf tetap urut A, B, C, D
 * dari atas ke bawah, sementara nilai yang dikirim ke server tetap huruf asli
 * tiap opsi — baik dari peramban maupun dari aplikasi Android — sehingga
 * koreksinya tidak terpengaruh urutan tampil.
 */
class AcakOpsiLembarTest extends TestCase
{
    use RefreshDatabase;

    protected Siswa $siswa;

    protected Soal $soal;

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

        $this->soal = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Ibu kota Indonesia adalah ...', 'bobot' => 1,
            'opsi' => [
                ['key' => 'A', 'text' => 'Jakarta'],
                ['key' => 'B', 'text' => 'Bandung'],
                ['key' => 'C', 'text' => 'Surabaya'],
                ['key' => 'D', 'text' => 'Medan'],
            ],
            'kunci' => ['A'],
        ]);

        $paket = PaketSoal::create(['kode_paket' => 'ACK-1', 'nama_paket' => 'Paket Acak', 'is_aktif' => true]);
        PaketSoalDetail::create([
            'paket_soal_id' => $paket->id, 'soal_id' => $this->soal->id, 'nomor_urut' => 1, 'bobot' => 1,
        ]);

        $this->ujian = Ujian::create([
            'kode_ujian' => 'ACK', 'nama_ujian' => 'Ujian Acak Opsi', 'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subMinute(), 'waktu_selesai' => now()->addHours(2),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
            'acak_opsi' => true, 'tampilkan_hasil' => true,
        ]);

        $this->peserta = UjianPeserta::create([
            'ujian_id' => $this->ujian->id, 'siswa_id' => $this->siswa->id,
            'nomor_peserta' => '001', 'status' => UjianPeserta::TERDAFTAR,
        ]);
    }

    /**
     * Mulai mengerjakan, lalu urutan tampil opsinya dipaksa ke susunan yang
     * sudah diketahui supaya yang diuji bentuk tampilannya, bukan hasil undian.
     */
    protected function mulaiDenganUrutan(array $urutan = ['C', 'A', 'D', 'B']): void
    {
        $this->actingAs($this->siswa, 'siswa')
            ->post(route('siswa.ujian.mulai', $this->peserta))
            ->assertRedirect(route('siswa.ujian.kerjakan', $this->peserta));

        $this->peserta->jawaban()->update(['urutan_opsi' => json_encode($urutan)]);
    }

    public function test_pengacakan_menyala_mengisi_urutan_opsi(): void
    {
        $this->actingAs($this->siswa, 'siswa')
            ->post(route('siswa.ujian.mulai', $this->peserta))
            ->assertRedirect();

        $urutan = $this->peserta->jawaban()->firstOrFail()->urutan_opsi;

        // Isinya huruf asli opsi, lengkap tanpa ada yang hilang atau ganda.
        $this->assertNotNull($urutan);
        $this->assertSame(['A', 'B', 'C', 'D'], collect($urutan)->sort()->values()->all());
    }

    public function test_abjad_di_lembar_tetap_urut_sedangkan_isinya_berpindah(): void
    {
        $this->mulaiDenganUrutan();

        $html = $this->actingAs($this->siswa, 'siswa')
            ->get(route('siswa.ujian.kerjakan', $this->peserta))
            ->assertOk()
            ->getContent();

        // Huruf yang terbaca di kolom opsi: tetap A, B, C, D berurutan.
        preg_match_all('/class="opsi-huruf">([A-D])</', $html, $huruf);
        $this->assertSame(['A', 'B', 'C', 'D'], $huruf[1]);

        // Isi opsinya yang berpindah tempat.
        preg_match_all('/class="opsi-teks[^"]*"[^>]*>(Jakarta|Bandung|Surabaya|Medan)</', $html, $teks);
        $this->assertSame(['Surabaya', 'Jakarta', 'Medan', 'Bandung'], $teks[1]);

        // Nilai yang dikirim tetap huruf asli opsi, mengikuti urutan tampil.
        preg_match_all('/name="soal-'.$this->soal->id.'" value="([A-D])"/', $html, $nilai);
        $this->assertSame(['C', 'A', 'D', 'B'], $nilai[1]);
    }

    public function test_jawaban_pada_baris_b_yang_berisi_kunci_dinilai_benar(): void
    {
        $this->mulaiDenganUrutan();

        // Baris berhuruf B pada susunan ini berisi "Jakarta", yaitu kuncinya,
        // dan nilai yang dikirim peramban adalah huruf aslinya: "A".
        $this->actingAs($this->siswa, 'siswa')
            ->postJson(route('siswa.ujian.simpan', $this->peserta), [
                'soal_id' => $this->soal->id, 'jawaban' => 'A',
            ])->assertOk();

        $this->actingAs($this->siswa, 'siswa')
            ->post(route('siswa.ujian.selesai', $this->peserta))
            ->assertRedirect(route('siswa.ujian.hasil', $this->peserta));

        $this->assertTrue($this->peserta->jawaban()->firstOrFail()->is_benar);
        $this->assertSame(100.0, (float) $this->peserta->refresh()->nilai);
    }

    public function test_halaman_hasil_memakai_huruf_yang_sama_dengan_lembarnya(): void
    {
        $this->mulaiDenganUrutan();

        $this->actingAs($this->siswa, 'siswa')
            ->postJson(route('siswa.ujian.simpan', $this->peserta), [
                'soal_id' => $this->soal->id, 'jawaban' => 'A',
            ])->assertOk();

        $this->actingAs($this->siswa, 'siswa')
            ->post(route('siswa.ujian.selesai', $this->peserta));

        // Pembahasan baru dibuka setelah jendela ujian lewat.
        $this->travelTo($this->ujian->waktu_selesai->addMinute());

        $html = $this->actingAs($this->siswa, 'siswa')
            ->get(route('siswa.ujian.hasil', $this->peserta))
            ->assertOk()
            ->getContent();

        // Huruf pada pembahasan tetap urut seperti di lembar tadi.
        preg_match_all('/min-width:1.75rem">([A-D])\./', $html, $huruf);
        $this->assertSame(['A', 'B', 'C', 'D'], $huruf[1]);

        // "Jakarta" tetap berada di baris B seperti saat dikerjakan, dan di
        // baris itulah badge "jawaban Anda" menempel.
        $this->assertMatchesRegularExpression(
            '/>B\.<\/strong>.{0,600}?Jakarta.{0,600}?jawaban Anda/s',
            $html
        );
    }

    public function test_lembar_di_aplikasi_mengirim_label_dan_key_sekaligus(): void
    {
        $this->mulaiDenganUrutan();

        $mentah = SiswaApiToken::acak();
        SiswaApiToken::create([
            'siswa_id' => $this->siswa->id,
            'token' => SiswaApiToken::sidik($mentah),
            'perangkat' => 'Uji Android',
            'terakhir_dipakai_at' => now(),
            'kedaluwarsa_at' => now()->addDays(SiswaApiToken::UMUR_HARI),
        ]);

        $opsi = $this->withHeader('Authorization', 'Bearer '.$mentah)
            ->getJson('/api/v1/ujian/'.$this->peserta->id.'/lembar')
            ->assertOk()
            ->json('butir.0.opsi');

        $this->assertSame(['A', 'B', 'C', 'D'], array_column($opsi, 'label'));
        $this->assertSame(['C', 'A', 'D', 'B'], array_column($opsi, 'key'));
        $this->assertStringContainsString('Surabaya', $opsi[0]['text']);
        $this->assertStringContainsString('Jakarta', $opsi[1]['text']);
    }

    public function test_guru_melihat_lembar_dengan_huruf_yang_sama(): void
    {
        $this->mulaiDenganUrutan();

        $this->actingAs($this->siswa, 'siswa')
            ->postJson(route('siswa.ujian.simpan', $this->peserta), [
                'soal_id' => $this->soal->id, 'jawaban' => 'A',
            ])->assertOk();

        $this->actingAs($this->siswa, 'siswa')
            ->post(route('siswa.ujian.selesai', $this->peserta));

        $admin = User::create([
            'name' => 'Admin Uji', 'email' => 'uji-acak@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        // Guard disebut tegas: pengujian sebelumnya masuk sebagai siswa, dan
        // tanpa ini admin akan dipasang pada guard siswa lalu ditolak.
        $html = $this->actingAs($admin, 'web')
            ->get(route('laporan.nilai.detail', [$this->ujian, $this->peserta]))
            ->assertOk()
            ->getContent();

        // Guru membaca jawaban siswa pada penomoran yang sama dengan lembarnya:
        // huruf urut A, B, C, D, dan "Jakarta" ada di baris B yang dipilih siswa.
        preg_match_all('/min-width:1.75rem">([A-D])\./', $html, $huruf);
        $this->assertSame(['A', 'B', 'C', 'D'], $huruf[1]);

        $this->assertMatchesRegularExpression(
            '/>B\.<\/span>.{0,600}?Jakarta.{0,600}?dipilih siswa/s',
            $html
        );
    }
}
