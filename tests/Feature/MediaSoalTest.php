<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Siswa;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lampiran audio/video pada butir soal: diunggah guru, disimpan di server
 * ujian sendiri, lalu diputar peserta di lembar ujiannya.
 */
class MediaSoalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $this->actingAs($this->admin);
    }

    public function test_audio_diunggah_bersama_soal(): void
    {
        $this->post('/soal', $this->isian(['media' => $this->berkasAudio()]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $soal = Soal::firstOrFail();

        $this->assertSame('audio', $soal->media_tipe);
        $this->assertStringStartsWith('soal-media/', $soal->media_path);
        Storage::disk('public')->assertExists($soal->media_path);
        $this->assertStringStartsWith('/storage/soal-media/', $soal->media_url);
    }

    public function test_video_diunggah_bersama_soal(): void
    {
        $this->post('/soal', $this->isian(['media' => $this->berkasVideo()]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('video', Soal::firstOrFail()->media_tipe);
    }

    public function test_berkas_selain_audio_video_ditolak(): void
    {
        $this->post('/soal', $this->isian([
            'media' => UploadedFile::fake()->createWithContent('naskah.pdf', '%PDF-1.4 palsu'),
        ]))->assertSessionHasErrors('media');

        $this->assertSame(0, Soal::count());
    }

    public function test_berkas_melebihi_batas_ditolak(): void
    {
        config(['ujian.maks_media_mb.audio' => 1]);

        $this->post('/soal', $this->isian([
            'media' => UploadedFile::fake()->createWithContent('panjang.mp3', $this->isiMp3(2 * 1024 * 1024)),
        ]))->assertSessionHasErrors('media');

        $this->assertSame(0, Soal::count());
    }

    public function test_berkas_sama_tidak_disimpan_dua_kali(): void
    {
        $this->post('/soal', $this->isian(['media' => $this->berkasAudio('satu.mp3')]));
        $this->post('/soal', $this->isian(['media' => $this->berkasAudio('dua.mp3')]));

        $this->assertCount(1, Storage::disk('public')->allFiles('soal-media'));
        $this->assertSame(2, Soal::whereNotNull('media_path')->count());
    }

    public function test_lampiran_bertahan_saat_soal_disunting_tanpa_berkas_baru(): void
    {
        $this->post('/soal', $this->isian(['media' => $this->berkasAudio()]));
        $soal = Soal::firstOrFail();

        $this->put("/soal/{$soal->id}", $this->isian(['pertanyaan' => 'Pertanyaan diperbaiki']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Pertanyaan diperbaiki', $soal->fresh()->pertanyaan);
        $this->assertSame($soal->media_path, $soal->fresh()->media_path);
    }

    public function test_lampiran_bisa_dilepas_dari_soal(): void
    {
        $this->post('/soal', $this->isian(['media' => $this->berkasAudio()]));
        $soal = Soal::firstOrFail();

        $this->put("/soal/{$soal->id}", $this->isian(['hapus_media' => 1]))
            ->assertSessionHasNoErrors();

        $this->assertNull($soal->fresh()->media_path);
        $this->assertNull($soal->fresh()->media_tipe);

        // Berkasnya sendiri sengaja dibiarkan: butir lain bisa memakainya.
        Storage::disk('public')->assertExists($soal->media_path);
    }

    public function test_pemutar_tampil_di_lembar_ujian_siswa(): void
    {
        $siswa = Siswa::first();

        if (! $siswa) {
            $this->markTestSkipped('Database datacenter belum berisi data siswa.');
        }

        $this->post('/soal', $this->isian(['media' => $this->berkasAudio()]));
        $soal = Soal::firstOrFail();

        // Guru melihat pemutarnya di pratinjau butir.
        $this->get("/soal/{$soal->id}")->assertOk()->assertSee('<audio', false);

        $paket = PaketSoal::create(['kode_paket' => 'MDA-1', 'nama_paket' => 'Paket', 'is_aktif' => true]);
        PaketSoalDetail::create(['paket_soal_id' => $paket->id, 'soal_id' => $soal->id, 'nomor_urut' => 1, 'bobot' => 1]);

        $ujian = Ujian::create([
            'kode_ujian' => 'MDA', 'nama_ujian' => 'Ujian Menyimak', 'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subMinutes(5), 'waktu_selesai' => now()->addHours(2),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
        ]);
        $peserta = UjianPeserta::create([
            'ujian_id' => $ujian->id, 'siswa_id' => $siswa->id,
            'nomor_peserta' => '001', 'status' => UjianPeserta::TERDAFTAR,
        ]);

        $this->actingAs($siswa, 'siswa')->post(route('siswa.ujian.mulai', $peserta));

        $this->actingAs($siswa, 'siswa')
            ->get(route('siswa.ujian.kerjakan', $peserta))
            ->assertOk()
            ->assertSee('<audio', false)
            ->assertSee($soal->media_url, false)
            ->assertSee('boleh diputar ulang');
    }

    /** @return array<string, mixed> */
    protected function isian(array $tambahan = []): array
    {
        return $tambahan + [
            'jenis' => Soal::PG,
            'pertanyaan' => 'Apa isi percakapan tersebut?',
            'bobot' => 1,
            'opsi_text' => ['Tentang cuaca', 'Tentang jadwal'],
            'kunci_pg' => ['A'],
        ];
    }

    protected function berkasAudio(string $nama = 'rekaman.mp3'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, $this->isiMp3());
    }

    protected function berkasVideo(string $nama = 'tayangan.mp4'): UploadedFile
    {
        // Kotak "ftyp" di awal berkas — penanda yang dibaca finfo sebagai video/mp4.
        $isi = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", 512);

        return UploadedFile::fake()->createWithContent($nama, $isi);
    }

    /** Isi MP3 palsu: header ID3 secukupnya agar dikenali sebagai audio/mpeg. */
    protected function isiMp3(int $panjang = 2048): string
    {
        return "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x44", intdiv($panjang, 4));
    }
}
