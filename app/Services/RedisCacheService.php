<?php

namespace App\Services;

use App\Models\Ujian;
use App\Models\UjianPeserta;
use Illuminate\Foundation\Application;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Predis\Client;
use RuntimeException;
use Throwable;

/**
 * Memindahkan tembolok (cache) dan sesi login aplikasi dari database ke Redis,
 * lewat menu — tanpa SSH.
 *
 * Alasannya murni beban server saat ujian. Dengan penyimpan bawaan "database",
 * setiap permintaan menjalankan satu SELECT dan satu UPDATE ke tabel sessions,
 * sementara denyut lembar ujian berdetak tiap belasan detik untuk tiap peserta.
 * Tiga ratus peserta berarti puluhan tulisan per detik ke satu tabel, semuanya
 * memperebutkan kunci baris yang sama, padahal isinya cuma penanda "saya masih
 * di sini". Redis menyimpannya di memori: tidak ada kunci tabel, tidak ada
 * tulisan ke disk, dan tabel sessions tidak lagi ikut membesar selama ujian.
 *
 * Yang membuat penggantinya tidak sesederhana mengubah satu baris: driver sesi
 * dan tembolok dibaca dari .env sebelum aplikasi hidup, bukan dari tabel
 * pengaturan. Jadi kelas ini menulis .env, lalu membangun ulang konfigurasi
 * yang ditembolok. Tiga hal yang gampang terlewat dan sudah ditangani di sini:
 *
 *  - koneksinya diuji LEBIH DULU. Menulis "redis" ke .env sementara Redis
 *    tidak hidup membuat seluruh aplikasi mati pada permintaan berikutnya,
 *    dan pemulihannya hanya bisa lewat SSH — persis yang ingin dihindari;
 *  - .env dikembalikan apa adanya bila pembangunan ulang konfigurasi gagal;
 *  - OPcache diberi tahu. Di server produksi yang menyetel
 *    opcache.validate_timestamps=0, berkas konfigurasi yang baru ditulis tidak
 *    pernah dibaca ulang — perubahannya seolah tidak berpengaruh sama sekali.
 */
class RedisCacheService
{
    /** Nilai yang boleh dipakai untuk tembolok maupun sesi. */
    public const PENYIMPAN = ['database', 'redis'];

    /**
     * Batas tunggu koneksi, dalam detik.
     *
     * Sengaja pendek. Uji koneksi berjalan di dalam permintaan web: alamat yang
     * salah ketik tidak boleh menggantung halaman admin sampai PHP-FPM
     * kehabisan pekerja.
     */
    protected const TIMEOUT = 2.0;

    public function __construct(protected Application $app) {}

    // ---------------------------------------------------------------- keadaan

    /** Potret keadaan sekarang untuk ditampilkan di halaman Kinerja. */
    public function keadaan(): array
    {
        return [
            'klien' => $this->klien(),
            'tembolok' => (string) config('cache.default'),
            'sesi' => (string) config('session.driver'),
            'antrean' => (string) config('queue.default'),
            'env_path' => $this->app->environmentFilePath(),
            'env_bisa_ditulis' => $this->envBisaDitulis(),
            'config_tercache' => $this->app->configurationIsCached(),
            'sedang_ujian' => $this->pesertaSedangUjian(),
            'parameter' => $this->parameterSekarang(),
        ];
    }

    /**
     * Pustaka klien Redis yang tersedia di PHP server ini.
     *
     * phpredis adalah ekstensi PHP (apt install php8.x-redis); predis pustaka
     * murni PHP lewat Composer. Tanpa salah satunya, Laravel tidak bisa bicara
     * dengan Redis sama sekali, betapapun Redis-nya sudah berjalan.
     */
    public function klien(): ?string
    {
        if (extension_loaded('redis')) {
            return 'phpredis';
        }

        if (class_exists(Client::class)) {
            return 'predis';
        }

        return null;
    }

    /** Nilai isian formulir: dari .env bila sudah pernah diisi, sisanya bawaan. */
    public function parameterSekarang(): array
    {
        return [
            'host' => (string) config('database.redis.default.host', '127.0.0.1'),
            'port' => (string) config('database.redis.default.port', '6379'),
            'database' => (string) config('database.redis.default.database', '0'),
            'cache_database' => (string) config('database.redis.cache.database', '1'),
            'prefix' => (string) config('database.redis.options.prefix', $this->prefixSaran()),
            // Kata sandinya sendiri tidak pernah dikirim ke peramban; halaman
            // hanya perlu tahu apakah sudah terisi.
            'ada_sandi' => filled(config('database.redis.default.password')),
        ];
    }

    /**
     * Awalan kunci yang disarankan.
     *
     * Satu server Virtualmin kerap menampung beberapa sekolah sekaligus, dan
     * semuanya memasang aplikasi ini dengan APP_NAME bawaan yang sama persis.
     * Tanpa awalan yang berbeda, mereka menulis ke kunci yang sama di Redis
     * yang sama: sesi siswa sekolah A bisa terbaca sebagai milik sekolah B.
     * Sidik jari folder pemasangan membuat awalannya pasti berbeda.
     */
    public function prefixSaran(): string
    {
        $nama = Str::slug((string) config('app.name', 'ujian'), '_');
        $sidik = substr(sha1($this->app->basePath()), 0, 6);

        return $nama.'_'.$sidik.'_';
    }

    public function envBisaDitulis(): bool
    {
        $berkas = $this->app->environmentFilePath();

        return is_file($berkas) && is_writable($berkas);
    }

    /**
     * Peserta yang sedang mengerjakan ujian saat ini.
     *
     * Mengganti driver sesi mengakhiri SELURUH sesi yang sedang hidup: penanda
     * sesi di peramban menunjuk ke penyimpan lama, dan penyimpan baru masih
     * kosong. Bagi peserta, itu berarti lembar ujiannya terlempar ke halaman
     * masuk di tengah mengerjakan.
     */
    public function pesertaSedangUjian(): int
    {
        return UjianPeserta::where('status', UjianPeserta::MULAI)
            ->whereIn('ujian_id', Ujian::aktif()
                ->where('waktu_mulai', '<=', now())
                ->where('waktu_selesai', '>=', now())
                ->select('id'))
            ->count();
    }

    // -------------------------------------------------------------------- uji

    /**
     * Mencoba menyambung, menulis, lalu membaca kembali satu kunci percobaan.
     *
     * Ping saja tidak cukup: Redis yang maxmemory-nya penuh dengan kebijakan
     * noeviction tetap menjawab ping, tetapi menolak setiap tulisan. Yang ingin
     * dipastikan adalah bahwa ia benar-benar bisa dipakai menembolok.
     *
     * @return array{ok: bool, pesan: string, versi: ?string, memori: ?string}
     */
    public function uji(array $p): array
    {
        if (! $this->klien()) {
            return [
                'ok' => false,
                'pesan' => 'PHP di server ini belum punya klien Redis. Pasang ekstensinya '
                    .'(apt install php8.2-redis lalu systemctl reload php8.2-fpm), atau '
                    .'tambahkan paket predis/predis lewat Composer.',
                'versi' => null,
                'memori' => null,
            ];
        }

        try {
            $koneksi = $this->koneksi($p);
            $kunci = 'ujian:uji-koneksi:'.Str::random(12);

            $koneksi->setex($kunci, 10, 'ok');
            $terbaca = $koneksi->get($kunci);
            $koneksi->del($kunci);

            if ($terbaca !== 'ok') {
                return [
                    'ok' => false,
                    'pesan' => 'Redis tersambung, tetapi nilai yang baru ditulis tidak terbaca '
                        .'kembali. Biasanya karena maxmemory sudah penuh, atau Redis berjalan '
                        .'sebagai replica yang hanya bisa dibaca.',
                    'versi' => null,
                    'memori' => null,
                ];
            }

            $info = $this->info($koneksi);

            return [
                'ok' => true,
                'pesan' => 'Redis tersambung dan bisa menulis.',
                'versi' => $info['versi'],
                'memori' => $info['memori'],
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'pesan' => 'Tidak bisa menyambung ke Redis: '.$e->getMessage(),
                'versi' => null,
                'memori' => null,
            ];
        }
    }

    /**
     * Koneksi sekali pakai dari parameter formulir.
     *
     * Dibuat lewat RedisManager tersendiri, bukan dengan menimpa
     * config('database.redis'): parameter yang belum tentu benar tidak boleh
     * bocor ke koneksi yang sedang dipakai aplikasi.
     */
    protected function koneksi(array $p)
    {
        $klien = $this->klien() ?? throw new RuntimeException('Klien Redis tidak tersedia.');

        $manager = new RedisManager($this->app, $klien, [
            'client' => $klien,
            // Tanpa awalan, supaya kunci percobaannya mudah dikenali dan dihapus
            // lagi — yang diuji koneksinya, bukan awalannya.
            'options' => ['prefix' => ''],
            'default' => [
                'host' => $p['host'],
                'port' => (int) $p['port'],
                'username' => blank($p['username'] ?? null) ? null : $p['username'],
                'password' => blank($p['password'] ?? null) ? null : $p['password'],
                'database' => (int) $p['database'],
                'timeout' => self::TIMEOUT,
                'read_timeout' => self::TIMEOUT,
            ],
        ]);

        return $manager->connection('default');
    }

    /** Versi dan pemakaian memori Redis; bentuk INFO berbeda antar klien. */
    protected function info($koneksi): array
    {
        try {
            $info = $koneksi->info();
        } catch (Throwable) {
            return ['versi' => null, 'memori' => null];
        }

        return [
            'versi' => $info['redis_version'] ?? $info['Server']['redis_version'] ?? null,
            'memori' => $info['used_memory_human'] ?? $info['Memory']['used_memory_human'] ?? null,
        ];
    }

    // --------------------------------------------------------------- terapkan

    /**
     * Menyimpan pilihan penyimpan tembolok dan sesi.
     *
     * @param  array  $p  parameter koneksi dari formulir
     * @param  string  $tembolok  database | redis
     * @param  string  $sesi  database | redis
     *
     * @throws RuntimeException bila Redis tidak bisa dipakai, ada peserta yang
     *                          sedang mengerjakan, atau .env tidak bisa ditulis
     */
    public function terapkan(array $p, string $tembolok, string $sesi): void
    {
        $this->pastikanPilihanSah($tembolok, $sesi);

        /*
         * Urutannya disengaja: yang ditolak karena aturan diperiksa sebelum
         * yang ditolak karena keadaan teknis. Admin yang mencoba memindahkan
         * sesi di tengah ujian harus membaca "ada peserta yang sedang
         * mengerjakan", bukan keluhan tentang koneksi Redis — yang kedua akan
         * ia perbaiki, lalu mencoba lagi, dan baru tahu sesudahnya.
         */
        if ($sesi !== config('session.driver') && ($jumlah = $this->pesertaSedangUjian()) > 0) {
            throw new RuntimeException(
                "Ada {$jumlah} peserta yang sedang mengerjakan ujian. Penyimpan sesi tidak "
                .'diubah sekarang karena pergantiannya akan melempar mereka ke halaman masuk '
                .'di tengah ujian. Ubah penyimpan tembolok dulu, dan setel sesi setelah ujian selesai.'
            );
        }

        if (! $this->envBisaDitulis()) {
            throw new RuntimeException(
                'Berkas .env tidak bisa ditulis oleh aplikasi ('.$this->app->environmentFilePath().'). '
                .'Beri izin tulis kepada pengguna web server, lalu coba lagi.'
            );
        }

        if ($tembolok === 'redis' || $sesi === 'redis') {
            $hasil = $this->uji($p);

            if (! $hasil['ok']) {
                throw new RuntimeException($hasil['pesan']);
            }
        }

        // Tembolok lama dikosongkan selagi masih bisa dibaca. Setelah .env
        // berganti, barisnya di tabel cache tidak akan pernah terbaca lagi —
        // tapi tetap menumpuk di database dan ikut ke setiap cadangan.
        $this->kosongkanTemboloklama($tembolok);

        $this->tulisDanMuatUlang($this->susunEnv($p, $tembolok, $sesi));
    }

    protected function pastikanPilihanSah(string $tembolok, string $sesi): void
    {
        foreach (['tembolok' => $tembolok, 'sesi' => $sesi] as $nama => $nilai) {
            if (! in_array($nilai, self::PENYIMPAN, true)) {
                throw new RuntimeException("Pilihan penyimpan {$nama} tidak dikenali: {$nilai}.");
            }
        }
    }

    /**
     * Baris .env yang akan ditulis.
     *
     * Parameter Redis selalu ikut ditulis, bahkan saat keduanya kembali ke
     * database: admin yang mencoba Redis, mundur sebentar, lalu mencoba lagi
     * tidak perlu mengetik ulang alamat dan kata sandinya.
     */
    protected function susunEnv(array $p, string $tembolok, string $sesi): array
    {
        $env = [
            'REDIS_CLIENT' => $this->klien() ?? 'phpredis',
            'REDIS_HOST' => $p['host'],
            'REDIS_PORT' => (string) (int) $p['port'],
            'REDIS_DB' => (string) (int) $p['database'],
            'REDIS_CACHE_DB' => (string) (int) $p['cache_database'],
            'REDIS_PREFIX' => $p['prefix'],
            'CACHE_STORE' => $tembolok,
            'SESSION_DRIVER' => $sesi,
        ];

        // Kata sandi hanya ditulis ulang bila admin mengisinya. Isian yang
        // dibiarkan kosong berarti "pakai yang sudah ada", bukan "hapus" —
        // halaman ini memang tidak pernah menampilkan sandi yang tersimpan.
        if (filled($p['password'] ?? null)) {
            $env['REDIS_PASSWORD'] = $p['password'];
        } elseif (! $this->punyaBaris('REDIS_PASSWORD')) {
            $env['REDIS_PASSWORD'] = 'null';
        }

        // Sesi Redis memakai koneksi "default" (REDIS_DB), terpisah dari
        // tembolok yang memakai koneksi "cache" (REDIS_CACHE_DB). Pemisahan ini
        // yang membuat `php artisan cache:clear` tidak ikut menghapus sesi
        // seluruh peserta yang sedang ujian.
        if ($sesi === 'redis') {
            $env['SESSION_CONNECTION'] = 'default';
        }

        return $env;
    }

    /** Tembolok yang ditinggalkan, dikosongkan selagi masih terbaca. */
    protected function kosongkanTemboloklama(string $tembolokBaru): void
    {
        $lama = (string) config('cache.default');

        if ($lama === $tembolokBaru) {
            return;
        }

        try {
            Cache::store($lama)->flush();
        } catch (Throwable) {
            // Bukan kegagalan yang pantas membatalkan penyetelan: sisa barisnya
            // hanya memakan ruang, tidak merusak apa pun.
        }
    }

    /**
     * Menulis .env lalu membangun ulang konfigurasi — atau mengembalikan
     * keduanya seperti semula bila ada yang gagal.
     */
    protected function tulisDanMuatUlang(array $env): void
    {
        $berkas = $this->app->environmentFilePath();
        $isiLama = (string) file_get_contents($berkas);

        $this->tulisEnv($berkas, $isiLama, $env);

        try {
            $this->muatUlangKonfigurasi();
        } catch (Throwable $e) {
            // Dikembalikan apa adanya. Tanpa ini, .env yang sudah menunjuk ke
            // Redis dan konfigurasi tembolok yang gagal dibangun akan membuat
            // permintaan berikutnya — termasuk halaman ini sendiri — mati.
            file_put_contents($berkas, $isiLama, LOCK_EX);
            $this->bersihkanKonfigurasi();

            throw new RuntimeException(
                'Penyetelan dibatalkan dan .env dikembalikan seperti semula. Penyebabnya: '.$e->getMessage()
            );
        }
    }

    /**
     * Mengubah atau menambahkan baris di .env, dan membiarkan sisanya utuh.
     *
     * Jangkar ^ dengan penanda multiline membuat baris yang sudah dikomentari
     * (#CACHE_STORE=...) tidak ikut tergantikan — itu catatan admin, bukan
     * setelan yang berlaku.
     */
    protected function tulisEnv(string $berkas, string $isi, array $env): void
    {
        $baru = $isi;

        foreach ($env as $kunci => $nilai) {
            $baris = $kunci.'='.$this->kutip((string) $nilai);
            $pola = '/^'.preg_quote($kunci, '/').'=.*$/m';

            // Penggantinya dikembalikan lewat callback, bukan sebagai pola
            // pengganti: kata sandi Redis yang memuat $ atau \ akan dibaca
            // preg_replace sebagai rujukan grup dan berubah isinya.
            $baru = preg_match($pola, $baru) === 1
                ? (string) preg_replace_callback($pola, fn () => $baris, $baru, 1)
                : rtrim($baru, "\r\n")."\n".$baris."\n";
        }

        if (file_put_contents($berkas, $baru, LOCK_EX) === false) {
            throw new RuntimeException('Gagal menulis berkas .env.');
        }
    }

    protected function punyaBaris(string $kunci): bool
    {
        $isi = (string) @file_get_contents($this->app->environmentFilePath());

        return preg_match('/^'.preg_quote($kunci, '/').'=/m', $isi) === 1;
    }

    /**
     * Nilai yang mengandung spasi, tanda pagar, atau tanda kutip harus dikutip;
     * tanpa itu Dotenv memotongnya di tengah — kata sandi Redis acak sering
     * kena justru karena ini.
     */
    protected function kutip(string $nilai): string
    {
        if ($nilai === '') {
            return '';
        }

        if (preg_match('/\A[A-Za-z0-9_.\-:\/]+\z/', $nilai) === 1) {
            return $nilai;
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $nilai).'"';
    }

    /**
     * Membuang konfigurasi yang ditembolok, lalu membangunnya lagi bila memang
     * tadinya ditembolok. Server yang sengaja berjalan tanpa config:cache tidak
     * diam-diam diubah kebiasaannya oleh menu ini.
     */
    protected function muatUlangKonfigurasi(): void
    {
        $tadinyaTercache = $this->app->configurationIsCached();

        $this->bersihkanKonfigurasi();

        if ($tadinyaTercache) {
            Artisan::call('config:cache');
            $this->segarkanOpcache();
        }
    }

    protected function bersihkanKonfigurasi(): void
    {
        Artisan::call('config:clear');
        $this->segarkanOpcache();
    }

    /**
     * Memberi tahu OPcache bahwa berkas konfigurasi sudah berganti.
     *
     * Di server yang menyetel opcache.validate_timestamps=0 — setelan yang
     * justru dianjurkan demi kecepatan — PHP tidak pernah memeriksa ulang
     * waktu ubah berkas. Tanpa pembatalan ini, config.php versi lama tetap
     * dipakai sampai PHP-FPM dimuat ulang, dan menu ini tampak tidak berfungsi.
     */
    protected function segarkanOpcache(): void
    {
        if (! function_exists('opcache_invalidate')) {
            return;
        }

        $berkas = $this->app->getCachedConfigPath();

        if (is_file($berkas)) {
            @opcache_invalidate($berkas, true);
        }
    }
}
