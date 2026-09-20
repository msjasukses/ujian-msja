<?php

namespace Tests\Feature;

use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seluruh berkas gaya, ikon, dan font harus dilayani server ini sendiri.
 *
 * Bukan soal selera. Ujian dijalankan lewat ExamBro dan peramban ujian
 * sejenis yang mengunci akses ke luar alamat server ujian, dan banyak sekolah
 * menjalankannya di jaringan lokal tanpa internet sama sekali. Satu rujukan
 * ke CDN saja membuat halaman siswa tampil tanpa gaya apa pun: tombol menjadi
 * tautan bergaris bawah dan seluruh ikon hilang — persis yang pernah terjadi.
 *
 * Tes ini menjaga agar rujukan semacam itu tidak diam-diam kembali.
 */
class AsetLokalTest extends TestCase
{
    use RefreshDatabase;

    /** Berkas yang wajib ada di dalam public/. */
    public const BERKAS = [
        'vendor/bootstrap/bootstrap.min.css',
        'vendor/bootstrap/bootstrap.bundle.min.js',
        'vendor/bootstrap-icons/bootstrap-icons.min.css',
        'vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
        'vendor/font/amiri.css',
    ];

    public function test_berkas_aset_tersedia_di_dalam_aplikasi(): void
    {
        foreach (self::BERKAS as $berkas) {
            $jalur = public_path($berkas);

            $this->assertFileExists($jalur, "Berkas aset {$berkas} tidak ada di public/.");
            $this->assertGreaterThan(1000, filesize($jalur), "Berkas aset {$berkas} tampak kosong atau gagal terunduh.");
        }
    }

    /** Font Arab harus menunjuk berkas lokal, bukan fonts.gstatic.com. */
    public function test_berkas_font_arab_menunjuk_ke_dalam(): void
    {
        $css = file_get_contents(public_path('vendor/font/amiri.css'));

        $this->assertStringNotContainsString('fonts.gstatic.com', $css);

        // Alamatnya relatif terhadap berkas gaya, bukan diawali "/", supaya
        // font tetap termuat saat aplikasi dibuka dari subfolder, mis.
        // http://192.168.x.x/ujian/public.
        preg_match_all('#url\(([^)]+)\)#', $css, $cocok);

        $this->assertNotEmpty($cocok[1], 'Berkas gaya font tidak merujuk satu pun berkas font.');

        foreach (array_unique($cocok[1]) as $berkas) {
            $this->assertStringStartsWith('amiri-', $berkas);
            $this->assertFileExists(public_path('vendor/font/'.$berkas));
        }
    }

    /** Ikon Bootstrap harus menunjuk berkas font di sebelahnya. */
    public function test_berkas_ikon_menunjuk_ke_dalam(): void
    {
        $css = file_get_contents(public_path('vendor/bootstrap-icons/bootstrap-icons.min.css'));

        $this->assertStringNotContainsString('cdn.jsdelivr.net', $css);
        $this->assertStringContainsString('fonts/bootstrap-icons.woff2', $css);
    }

    /** Tidak boleh ada satu pun rujukan CDN yang tersisa di berkas tampilan. */
    public function test_tidak_ada_rujukan_cdn_di_tampilan(): void
    {
        $temuan = [];
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iter as $berkas) {
            if (! str_ends_with($berkas->getFilename(), '.blade.php')) {
                continue;
            }

            foreach (file($berkas->getPathname()) as $i => $baris) {
                // Hanya rujukan yang benar-benar memuat berkas yang diperiksa —
                // tautan biasa di dalam kalimat tidak jadi soal.
                if (preg_match('/(?:href|src)\s*=\s*["\']https?:\/\//i', $baris)) {
                    $temuan[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $berkas->getPathname())
                        .':'.($i + 1).'  '.trim($baris);
                }
            }
        }

        $this->assertSame([], $temuan, "Masih ada aset yang dimuat dari alamat luar:\n".implode("\n", $temuan));
    }

    /** Halaman yang dilihat siswa hanya memuat aset dari alamat sendiri. */
    public function test_halaman_siswa_hanya_memuat_aset_sendiri(): void
    {
        $siswa = Siswa::aktif()->first();

        if (! $siswa) {
            $this->markTestSkipped('Database datacenter belum berisi data siswa.');
        }

        $halaman = [
            $this->get(route('login')),
            $this->actingAs($siswa, 'siswa')->get(route('siswa.ujian.index')),
        ];

        foreach ($halaman as $respons) {
            $respons->assertOk();
            $isi = $respons->getContent();

            preg_match_all('/(?:href|src)\s*=\s*"([^"]+)"/i', $isi, $cocok);

            foreach ($cocok[1] as $alamat) {
                $this->assertStringNotContainsString('cdn.jsdelivr.net', $alamat);
                $this->assertStringNotContainsString('fonts.googleapis.com', $alamat);
                $this->assertStringNotContainsString('fonts.gstatic.com', $alamat);
            }

            // Berkas gaya utamanya memang termuat.
            $this->assertStringContainsString('vendor/bootstrap/bootstrap.min.css', $isi);
        }
    }

    /**
     * Skrip halaman pengerjaan tidak boleh memakai sintaks yang lebih baru
     * dari ES2017.
     *
     * ExamBro berjalan di atas WebView Android bawaan perangkat, yang pada
     * perangkat sekolah kerap jauh lebih tua. Operator ?. dan ?? menjadi galat
     * sintaks di sana, dan satu galat sintaks membatalkan seluruh blok skrip —
     * hitung mundur mati, jawaban tidak tersimpan, tombol kumpulkan diam.
     */
    public function test_skrip_halaman_ujian_memakai_sintaks_lama(): void
    {
        $isi = file_get_contents(resource_path('views/siswa/kerjakan.blade.php'));

        // Ambil hanya blok <script>, sebab kode PHP di atasnya memang memakai ??.
        preg_match_all('#<script>(.*?)</script>#s', $isi, $cocok);

        $this->assertNotEmpty($cocok[1], 'Blok skrip halaman pengerjaan tidak ditemukan.');

        foreach ($cocok[1] as $skrip) {
            // Baris komentar dikecualikan: penjelasannya sendiri menyebut ?. dan ??.
            $tanpaKomentar = preg_replace('#^\s*(//|\*|/\*).*$#m', '', $skrip);

            $this->assertDoesNotMatchRegularExpression(
                '/\?\./',
                $tanpaKomentar,
                'Skrip halaman pengerjaan memakai operator ?. yang tidak dikenal WebView lama.'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\?\?/',
                $tanpaKomentar,
                'Skrip halaman pengerjaan memakai operator ?? yang tidak dikenal WebView lama.'
            );
        }
    }
}
