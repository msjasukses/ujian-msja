<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use Database\Seeders\ContohUjianMediaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Seeder contoh ujian audio & video: dua butir berlampiran media yang
 * berkasnya benar-benar ada dan berformat sah, satu paket, satu jadwal
 * yang sedang dibuka.
 */
class ContohUjianMediaSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Ekstensi GD tidak tersedia.');
        }

        Storage::fake('public');
        $this->seed(ContohUjianMediaSeeder::class);
    }

    public function test_membuat_satu_soal_audio_dan_satu_soal_video_dalam_ujian_aktif(): void
    {
        $soal = Soal::where('kode_soal', 'like', 'CTHM-%')->orderBy('kode_soal')->get();

        $this->assertSame(['audio', 'video'], $soal->pluck('media_tipe')->all());

        $ujian = Ujian::where('token', ContohUjianMediaSeeder::TOKEN)->sole();
        $this->assertSame(Ujian::AKTIF, $ujian->status);
        $this->assertTrue($ujian->sedang_berlangsung);
        $this->assertEqualsCanonicalizing($soal->pluck('id')->all(), $ujian->paketSoal->detail()->pluck('soal_id')->all());
    }

    public function test_berkas_audio_berupa_wav_yang_sah(): void
    {
        $isi = Storage::disk('public')->get(Soal::where('media_tipe', 'audio')->sole()->media_path);

        $this->assertStringStartsWith('RIFF', $isi);
        $this->assertSame('WAVE', substr($isi, 8, 4));
        $this->assertSame(strlen($isi) - 8, unpack('V', substr($isi, 4, 4))[1]);

        // 16 kHz mono 16-bit: lebih dari empat detik nada.
        $this->assertGreaterThan(16000 * 2 * 4, strlen($isi));
    }

    /**
     * Struktur MP4-nya diperiksa sampai ke isi sampelnya: kotak moov di depan
     * mdat, tabel sampel menunjuk tepat ke data di mdat, dan sampel pertama
     * adalah bingkai kunci (IDR) — tanpa itu peramban tidak dapat memulai
     * pemutaran.
     */
    public function test_berkas_video_berupa_mp4_h264_yang_utuh(): void
    {
        $isi = Storage::disk('public')->get(Soal::where('media_tipe', 'video')->sole()->media_path);

        $kotak = $this->kotak($isi, 0, strlen($isi));
        $this->assertSame(['ftyp', 'moov', 'mdat'], array_keys($kotak));

        $moov = $kotak['moov'];
        $stbl = $this->turun($isi, $moov, ['trak', 'mdia', 'minf', 'stbl']);

        $stsd = substr($isi, $stbl['stsd'][0], $stbl['stsd'][1]);
        $this->assertStringContainsString('avc1', $stsd);
        $this->assertStringContainsString('avcC', $stsd);

        // Ukuran tiap sampel menurut stsz, dijumlah, harus sama dengan isi mdat.
        $stsz = substr($isi, $stbl['stsz'][0], $stbl['stsz'][1]);
        $jumlah = unpack('N', substr($stsz, 8, 4))[1];
        $ukuran = array_values(unpack("N{$jumlah}", substr($stsz, 12, 4 * $jumlah)));
        [$awalMdat, $panjangMdat] = $kotak['mdat'];
        $this->assertSame($panjangMdat, array_sum($ukuran));

        // stco menunjuk ke awal data mdat.
        $stco = substr($isi, $stbl['stco'][0], $stbl['stco'][1]);
        $this->assertSame($awalMdat, unpack('N', substr($stco, 8, 4))[1]);

        // Sampel pertama: satu unit NAL berjenis 5 (IDR), sisanya jenis 1.
        $this->assertSame(5, ord($isi[$awalMdat + 4]) & 0x1F);
        $this->assertSame(1, ord($isi[$awalMdat + $ukuran[0] + 4]) & 0x1F);

        // Animasi: lebih dari sekadar pembuka dan penutup.
        $this->assertGreaterThan(10, $jumlah);
    }

    public function test_pemutar_audio_dan_video_tampil_di_lembar_ujian_siswa(): void
    {
        $peserta = UjianPeserta::with('siswa')->first();

        if (! $peserta?->siswa) {
            $this->markTestSkipped('Database datacenter belum berisi rombel dan siswa pada tahun ajaran aktif.');
        }

        $this->actingAs($peserta->siswa, 'siswa')
            ->post(route('siswa.ujian.mulai', $peserta), ['token' => ContohUjianMediaSeeder::TOKEN])
            ->assertRedirect(route('siswa.ujian.kerjakan', $peserta));

        $halaman = $this->actingAs($peserta->siswa, 'siswa')
            ->get(route('siswa.ujian.kerjakan', $peserta))
            ->assertOk()
            ->assertSee('<audio', false)
            ->assertSee('<video', false);

        foreach (Soal::where('kode_soal', 'like', 'CTHM-%')->get() as $soal) {
            $halaman->assertSee($soal->media_url, false);
        }
    }

    public function test_aman_dijalankan_berulang(): void
    {
        $berkas = Storage::disk('public')->allFiles('soal-media');

        $this->seed(ContohUjianMediaSeeder::class);

        $this->assertSame(2, Soal::where('kode_soal', 'like', 'CTHM-%')->count());
        $this->assertSame(1, PaketSoal::where('kode_paket', 'like', 'CTHM-%')->count());
        $this->assertSame(1, Ujian::where('kode_ujian', 'like', 'CTHM-%')->count());

        // Isinya dibangkitkan sama persis, jadi berkas media tidak menumpuk.
        $this->assertSame($berkas, Storage::disk('public')->allFiles('soal-media'));
    }

    /**
     * Daftar kotak MP4 di dalam rentang [$awal, $akhir): jenis => [awal isi, panjang isi].
     *
     * @return array<string, array{0: int, 1: int}>
     */
    protected function kotak(string $isi, int $awal, int $akhir): array
    {
        $hasil = [];

        while ($awal < $akhir) {
            $ukuran = unpack('N', substr($isi, $awal, 4))[1];
            $this->assertGreaterThanOrEqual(8, $ukuran, 'Ukuran kotak MP4 tidak sah.');
            $hasil[substr($isi, $awal + 4, 4)] = [$awal + 8, $ukuran - 8];
            $awal += $ukuran;
        }

        $this->assertSame($akhir, $awal, 'Kotak MP4 melampaui induknya.');

        return $hasil;
    }

    /**
     * @param  array{0: int, 1: int}  $induk
     * @param  list<string>  $jalur
     * @return array<string, array{0: int, 1: int}>
     */
    protected function turun(string $isi, array $induk, array $jalur): array
    {
        $anak = $this->kotak($isi, $induk[0], $induk[0] + $induk[1]);

        foreach ($jalur as $jenis) {
            $this->assertArrayHasKey($jenis, $anak);
            $anak = $this->kotak($isi, $anak[$jenis][0], $anak[$jenis][0] + $anak[$jenis][1]);
        }

        return $anak;
    }
}
