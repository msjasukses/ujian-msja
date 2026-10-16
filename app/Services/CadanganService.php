<?php

namespace App\Services;

use App\Models\Sekolah;
use App\Support\Pengguna;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Cadangan (backup) dan pemulihan (restore) data aplikasi ujian.
 *
 * Satu cadangan adalah satu berkas ZIP:
 *
 *   manifest.json        penanda, waktu, pembuat, jumlah baris tiap tabel
 *   data/<tabel>.jsonl   isi tabel — satu baris JSON untuk satu baris tabel
 *   berkas/...           gambar dan audio/video soal (opsional)
 *
 * Sengaja tidak memakai mysqldump. Di banyak VPS dan hosting sekolah,
 * exec() dimatikan dan mysqldump tidak terpasang; cadangan yang bergantung
 * padanya gagal persis di server tempat ia paling dibutuhkan. JSONL ditulis
 * dan dibaca baris demi baris, sehingga tabel jawaban ujian yang berisi
 * ratusan ribu baris tidak pernah dimuat ke memori sekaligus.
 *
 * Yang dicadangkan hanya database aplikasi ini. Data siswa, guru, kelas, dan
 * CP-TP-ATP milik Data Center dan SIM Kurikulum: keduanya punya cadangan
 * sendiri, dan aplikasi ini tidak pernah menulis ke sana.
 */
class CadanganService
{
    /** Versi susunan berkas cadangan. Naikkan bila susunannya berubah. */
    public const FORMAT = 1;

    /** Penanda di manifest, supaya ZIP sembarang tidak dikira cadangan. */
    public const PENANDA = 'aplikasi-ujian-sekolah';

    /** Folder cadangan di disk "local" (storage/app/private) — tidak bisa diakses dari web. */
    public const FOLDER = 'cadangan';

    /**
     * Tabel yang dicadangkan, induk lebih dulu.
     *
     * Ditulis satu per satu, bukan diambil dari daftar tabel database: pada
     * server MySQL yang dipakai bersama, daftar itu memuat tabel milik
     * aplikasi lain, dan pemulihan tidak boleh sekali-kali menyentuhnya.
     */
    public const TABEL = [
        'users', 'pengaturan', 'topik', 'soal', 'paket_soal', 'paket_soal_detail',
        'ujian', 'ujian_kelas', 'ujian_peserta', 'ujian_jawaban', 'ujian_log',
        'tindak_lanjut', 'login_attempts',
    ];

    /** Tabel kerangka Laravel dan keadaan sesaat — sengaja tidak dicadangkan. */
    public const TIDAK_DICADANGKAN = [
        'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'sessions', 'password_reset_tokens', 'sesi_siswa',
    ];

    /**
     * Jenis berkas yang boleh dipulihkan ke disk publik. Berkas lain di dalam
     * ZIP dilewati: disk publik dilayani langsung oleh server web, dan berkas
     * .php yang menyusup ke sana bisa ikut dijalankan.
     */
    public const AKHIRAN_BERKAS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'weba',
        'mp4', 'm4v', 'webm', 'ogv',
    ];

    /** Berkas yang sudah terkompresi tidak perlu dikompresi ulang — hanya membuang waktu. */
    protected const SIMPAN_APA_ADANYA = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp3', 'm4a', 'aac', 'weba', 'ogg', 'oga', 'mp4', 'm4v', 'webm', 'ogv'];

    protected const BARIS_PER_SISIP = 500;

    // =====================================================================
    // Membuat cadangan
    // =====================================================================

    /**
     * Buat satu berkas cadangan dan kembalikan namanya.
     *
     * @param  string  $jenis  manual | otomatis | pengaman
     */
    public function buat(string $jenis = 'manual', bool $sertakanBerkas = true): string
    {
        $this->pastikanSiap();

        return $this->dalamKunci(fn () => $this->tulisCadangan($jenis, $sertakanBerkas));
    }

    protected function tulisCadangan(string $jenis, bool $sertakanBerkas): string
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::FOLDER);

        $nama = $this->namaBaru($jenis);
        $sementara = $this->folderSementara();
        $zip = new ZipArchive;
        $terbuka = false;

        try {
            if ($zip->open($disk->path(self::FOLDER.'/'.$nama), ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('Berkas cadangan tidak bisa dibuat. Periksa izin tulis folder storage/app/private.');
            }
            $terbuka = true;

            $jumlah = [];

            foreach ($this->tabelTersedia() as $tabel) {
                $jalur = $sementara.'/'.$tabel.'.jsonl';
                $jumlah[$tabel] = $this->tulisTabel($tabel, $jalur);
                $zip->addFile($jalur, 'data/'.$tabel.'.jsonl');
            }

            $berkas = $sertakanBerkas ? $this->tambahBerkas($zip) : ['jumlah' => 0, 'ukuran' => 0];

            $zip->addFromString('manifest.json', json_encode(
                $this->manifest($jenis, $jumlah, $berkas),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));

            // Isi berkas yang ditambahkan lewat addFile() baru dibaca di sini.
            $terbuka = false;

            if (! $zip->close()) {
                throw new RuntimeException('Berkas cadangan gagal ditulis. Periksa sisa ruang disk server.');
            }

            return $nama;
        } catch (Throwable $e) {
            if ($terbuka) {
                @$zip->close();
            }
            $disk->delete(self::FOLDER.'/'.$nama);

            throw $e;
        } finally {
            File::deleteDirectory($sementara);
        }
    }

    /** Tulis isi satu tabel sebagai JSONL; kembalikan jumlah barisnya. */
    protected function tulisTabel(string $tabel, string $tujuan): int
    {
        $berkas = fopen($tujuan, 'wb');
        $jumlah = 0;

        try {
            $kueri = DB::table($tabel);
            $baris = Schema::hasColumn($tabel, 'id') ? $kueri->lazyById(1000) : $kueri->cursor();

            foreach ($baris as $b) {
                fwrite($berkas, json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
                $jumlah++;
            }
        } finally {
            fclose($berkas);
        }

        return $jumlah;
    }

    /** @return array{jumlah: int, ukuran: int} */
    protected function tambahBerkas(ZipArchive $zip): array
    {
        $disk = Storage::disk('public');
        $jumlah = $ukuran = 0;

        foreach ($disk->allFiles() as $jalur) {
            if (! $this->jalurBerkasAman($jalur)) {
                continue;
            }

            $nama = 'berkas/'.$jalur;
            $zip->addFile($disk->path($jalur), $nama);

            if (in_array(strtolower(pathinfo($jalur, PATHINFO_EXTENSION)), self::SIMPAN_APA_ADANYA, true)) {
                $zip->setCompressionName($nama, ZipArchive::CM_STORE);
            }

            $jumlah++;
            $ukuran += $disk->size($jalur);
        }

        return ['jumlah' => $jumlah, 'ukuran' => $ukuran];
    }

    /**
     * @param  array<string, int>  $jumlah
     * @param  array{jumlah: int, ukuran: int}  $berkas
     * @return array<string, mixed>
     */
    protected function manifest(string $jenis, array $jumlah, array $berkas): array
    {
        return [
            'penanda' => self::PENANDA,
            'format' => self::FORMAT,
            'jenis' => $jenis,
            'dibuat' => now()->toIso8601String(),
            'oleh' => Pengguna::user() ? Pengguna::nama() : 'Server (perintah artisan)',
            'aplikasi' => config('app.name'),
            // Data Center bisa saja sedang tidak terhubung; cadangan tidak
            // boleh gagal hanya karena nama sekolah tidak terbaca.
            'sekolah' => rescue(fn () => Sekolah::profil()?->nama_sekolah, null, false),
            'migrasi_terakhir' => Schema::hasTable('migrations')
                ? DB::table('migrations')->orderByDesc('id')->value('migration')
                : null,
            'tabel' => $jumlah,
            'berkas' => $berkas,
        ];
    }

    // =====================================================================
    // Daftar & pemeriksaan
    // =====================================================================

    /**
     * Seluruh cadangan yang tersimpan, terbaru lebih dulu.
     *
     * @return Collection<int, array{nama: string, jenis: ?string, ukuran: int, waktu: Carbon, manifest: ?array, galat: ?string}>
     */
    public function daftar(): Collection
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::FOLDER))
            ->filter(fn ($jalur) => str_ends_with(strtolower($jalur), '.zip'))
            ->map(function ($jalur) use ($disk) {
                $manifest = null;
                $galat = null;

                try {
                    $manifest = $this->bacaManifest($disk->path($jalur));
                } catch (RuntimeException $e) {
                    $galat = $e->getMessage();
                }

                $nama = basename($jalur);

                return [
                    'nama' => $nama,
                    // Unggahan dikenali dari namanya: manifestnya tetap
                    // menyebut jenis aslinya (manual/otomatis) di server asal.
                    'jenis' => str_starts_with($nama, 'cadangan-unggahan-') ? 'unggahan' : ($manifest['jenis'] ?? null),
                    'ukuran' => $disk->size($jalur),
                    'waktu' => $manifest
                        ? Carbon::parse($manifest['dibuat'])->timezone(config('app.timezone'))
                        : Carbon::createFromTimestamp($disk->lastModified($jalur), config('app.timezone')),
                    'manifest' => $manifest,
                    'galat' => $galat,
                ];
            })
            ->sortByDesc(fn ($c) => $c['waktu']->getTimestamp())
            ->values();
    }

    /** Jalur lengkap sebuah cadangan; menolak nama yang mencoba keluar dari folder cadangan. */
    public function jalur(string $nama): string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+\.zip$/', $nama) || str_contains($nama, '..')) {
            throw new RuntimeException('Nama berkas cadangan tidak sah.');
        }

        $jalur = self::FOLDER.'/'.$nama;

        if (! Storage::disk('local')->exists($jalur)) {
            throw new RuntimeException("Berkas cadangan {$nama} tidak ditemukan.");
        }

        return Storage::disk('local')->path($jalur);
    }

    /**
     * Baca dan periksa manifest sebuah cadangan.
     *
     * @return array<string, mixed>
     */
    public function bacaManifest(string $jalurZip): array
    {
        $this->pastikanSiap();

        $zip = new ZipArchive;

        if ($zip->open($jalurZip, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Berkas ini bukan ZIP yang sah atau sudah rusak.');
        }

        try {
            $teks = $zip->getFromName('manifest.json');
            $manifest = $teks === false ? null : json_decode($teks, true);

            if (! is_array($manifest) || ($manifest['penanda'] ?? null) !== self::PENANDA) {
                throw new RuntimeException('Berkas ini bukan cadangan Aplikasi Ujian.');
            }

            if ((int) ($manifest['format'] ?? 0) > self::FORMAT) {
                throw new RuntimeException('Cadangan ini dibuat oleh versi aplikasi yang lebih baru. Perbarui aplikasi terlebih dahulu.');
            }

            foreach (array_keys((array) ($manifest['tabel'] ?? [])) as $tabel) {
                if ($zip->locateName('data/'.$tabel.'.jsonl') === false) {
                    throw new RuntimeException("Cadangan tidak utuh: data tabel {$tabel} hilang.");
                }
            }

            return $manifest;
        } finally {
            $zip->close();
        }
    }

    /**
     * Simpan ZIP unggahan ke folder cadangan setelah dipastikan sah. Belum
     * dipulihkan: admin melihat isinya lebih dulu, baru memutuskan.
     */
    public function simpanUnggahan(UploadedFile $berkas): string
    {
        $this->bacaManifest($berkas->getRealPath());

        $nama = $this->namaBaru('unggahan');
        Storage::disk('local')->putFileAs(self::FOLDER, $berkas, $nama);

        return $nama;
    }

    public function hapus(string $nama): void
    {
        File::delete($this->jalur($nama));
    }

    /**
     * Hapus cadangan sejenis yang lebih lama, sisakan $simpan yang terbaru.
     *
     * @return int jumlah yang dihapus
     */
    public function pangkas(string $jenis, int $simpan): int
    {
        $lama = $this->daftar()
            ->filter(fn ($c) => $c['jenis'] === $jenis)
            ->slice(max(0, $simpan));

        $lama->each(fn ($c) => $this->hapus($c['nama']));

        return $lama->count();
    }

    // =====================================================================
    // Memulihkan
    // =====================================================================

    /**
     * Ganti data aplikasi dengan isi sebuah cadangan.
     *
     * Seluruh tabel diganti dalam satu transaksi: bila satu baris saja gagal
     * dimasukkan, database kembali persis seperti sebelum pemulihan. Sebelum
     * itu dibuat cadangan pengaman dari data saat ini, supaya pemulihan yang
     * keliru pilih pun masih bisa dibatalkan.
     *
     * Berkas gambar/media hanya ditambahkan, tidak ada yang dihapus. Namanya
     * berupa sidik jari isinya, jadi berkas bernama sama pasti berisi sama.
     *
     * @return array{tabel: array<string, int>, berkas: int, catatan: array<int, string>, pengaman: ?string, manifest: array<string, mixed>}
     */
    public function pulihkan(string $jalurZip, bool $buatPengaman = true): array
    {
        $this->pastikanSiap();
        $manifest = $this->bacaManifest($jalurZip);

        return $this->dalamKunci(function () use ($jalurZip, $manifest, $buatPengaman) {
            $pengaman = $buatPengaman ? $this->tulisCadangan('pengaman', false) : null;
            $catatan = [];

            $zip = new ZipArchive;
            $zip->open($jalurZip, ZipArchive::RDONLY);

            try {
                $tabel = $this->pulihkanTabel($zip, $manifest, $catatan);
                $berkas = $this->pulihkanBerkas($zip, $catatan);
            } finally {
                $zip->close();
            }

            // Tabel users baru saja diganti: sesi admin/operator yang tersimpan
            // bisa menunjuk akun lain dengan id yang sama. Semuanya diakhiri;
            // sesi siswa dan guru tidak bergantung pada tabel ini.
            if (isset($tabel['users']) && Schema::hasTable('sessions')) {
                DB::table('sessions')->whereNotNull('user_id')->delete();
            }

            // Pengaturan (mis. jenjang sekolah) dibaca sekali per permintaan.
            Once::flush();

            return compact('tabel', 'berkas', 'catatan', 'pengaman', 'manifest');
        });
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<int, string>  $catatan
     * @return array<string, int>
     */
    protected function pulihkanTabel(ZipArchive $zip, array $manifest, array &$catatan): array
    {
        $diharapkan = (array) $manifest['tabel'];
        $tersedia = $this->tabelTersedia();
        $hasil = [];

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($zip, $diharapkan, $tersedia, &$hasil, &$catatan) {
                foreach (self::TABEL as $tabel) {
                    // Cadangan versi lama yang belum punya tabel ini: data
                    // tabel itu dibiarkan apa adanya.
                    if (! array_key_exists($tabel, $diharapkan) || ! in_array($tabel, $tersedia, true)) {
                        continue;
                    }

                    DB::table($tabel)->delete();
                    $hasil[$tabel] = $this->isiTabel($zip, $tabel, $catatan);

                    if ($hasil[$tabel] !== (int) $diharapkan[$tabel]) {
                        throw new RuntimeException("Cadangan rusak: tabel {$tabel} seharusnya berisi {$diharapkan[$tabel]} baris, terbaca {$hasil[$tabel]}.");
                    }
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $hasil;
    }

    /** @param  array<int, string>  $catatan */
    protected function isiTabel(ZipArchive $zip, string $tabel, array &$catatan): int
    {
        $aliran = $zip->getStream('data/'.$tabel.'.jsonl');

        if (! $aliran) {
            throw new RuntimeException("Data tabel {$tabel} tidak bisa dibaca dari cadangan.");
        }

        // Kolom yang dikenal versi aplikasi ini. Cadangan dari versi lain
        // boleh punya kolom lebih banyak atau lebih sedikit; yang tidak
        // dikenal dibuang, yang tidak ada memakai nilai bawaan kolomnya.
        $kolom = array_flip(Schema::getColumnListing($tabel));
        $asing = [];
        $kumpulan = [];
        $jumlah = 0;

        try {
            while (($baris = fgets($aliran)) !== false) {
                if (trim($baris) === '') {
                    continue;
                }

                $data = json_decode($baris, true, 512, JSON_THROW_ON_ERROR);
                $asing += array_diff_key($data, $kolom);
                $kumpulan[] = array_intersect_key($data, $kolom);

                if (count($kumpulan) >= self::BARIS_PER_SISIP) {
                    DB::table($tabel)->insert($kumpulan);
                    $jumlah += count($kumpulan);
                    $kumpulan = [];
                }
            }

            if ($kumpulan !== []) {
                DB::table($tabel)->insert($kumpulan);
                $jumlah += count($kumpulan);
            }
        } finally {
            fclose($aliran);
        }

        if ($asing !== []) {
            $catatan[] = 'Kolom '.implode(', ', array_keys($asing))." pada tabel {$tabel} tidak dikenal versi aplikasi ini dan dilewati.";
        }

        return $jumlah;
    }

    /** @param  array<int, string>  $catatan */
    protected function pulihkanBerkas(ZipArchive $zip, array &$catatan): int
    {
        $disk = Storage::disk('public');
        $jumlah = $dilewati = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nama = (string) $zip->getNameIndex($i);

            if (! str_starts_with($nama, 'berkas/') || str_ends_with($nama, '/')) {
                continue;
            }

            $jalur = substr($nama, strlen('berkas/'));

            if (! $this->jalurBerkasAman($jalur)) {
                $dilewati++;

                continue;
            }

            // Nama berkas adalah sidik jari isinya: yang sudah ada dan sama
            // ukurannya tidak perlu ditulis ulang.
            if (! $disk->exists($jalur) || $disk->size($jalur) !== $zip->statIndex($i)['size']) {
                $aliran = $zip->getStream($nama);
                $disk->writeStream($jalur, $aliran);
                fclose($aliran);
            }

            $jumlah++;
        }

        if ($dilewati > 0) {
            $catatan[] = "{$dilewati} berkas selain gambar/audio/video di dalam cadangan dilewati.";
        }

        return $jumlah;
    }

    // =====================================================================
    // Pembantu
    // =====================================================================

    /** @return array<int, string> Tabel cadangan yang memang ada di database ini. */
    public function tabelTersedia(): array
    {
        return array_values(array_filter(self::TABEL, fn ($t) => Schema::hasTable($t)));
    }

    /** Jalur di dalam disk publik yang aman ditulis: tanpa "..", hanya jenis media. */
    protected function jalurBerkasAman(string $jalur): bool
    {
        return $jalur !== ''
            && ! str_contains($jalur, '..')
            && preg_match('#^[A-Za-z0-9_][A-Za-z0-9._/-]*$#', $jalur) === 1
            && in_array(strtolower(pathinfo($jalur, PATHINFO_EXTENSION)), self::AKHIRAN_BERKAS, true);
    }

    protected function namaBaru(string $jenis): string
    {
        $dasar = 'cadangan-'.$jenis.'-'.now()->format('Ymd-His');
        $nama = $dasar.'.zip';

        for ($i = 2; Storage::disk('local')->exists(self::FOLDER.'/'.$nama); $i++) {
            $nama = $dasar.'-'.$i.'.zip';
        }

        return $nama;
    }

    protected function folderSementara(): string
    {
        $folder = Storage::disk('local')->path(self::FOLDER.'/.sementara-'.Str::random(10));
        File::ensureDirectoryExists($folder);

        return $folder;
    }

    /**
     * Satu proses cadangan/pemulihan dalam satu waktu. Dua pemulihan yang
     * berjalan bersamaan akan saling menghapus data yang baru dimasukkan.
     *
     * @template T
     *
     * @param  callable(): T  $kerja
     * @return T
     */
    protected function dalamKunci(callable $kerja): mixed
    {
        $kunci = Cache::lock('cadangan-aplikasi', 3600);

        if (! $kunci->get()) {
            throw new RuntimeException('Proses cadangan atau pemulihan lain sedang berjalan. Coba lagi beberapa saat lagi.');
        }

        try {
            return $kerja();
        } finally {
            $kunci->release();
        }
    }

    protected function pastikanSiap(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP "zip" belum aktif di server. Aktifkan ekstensi tersebut agar fitur cadangan dapat dipakai.');
        }

        // Cadangan beserta media dan pemulihan tabel besar bisa melampaui
        // batas waktu bawaan PHP (30 detik). Server yang melarang perubahan
        // ini tetap bekerja untuk data yang lebih kecil.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }
}
