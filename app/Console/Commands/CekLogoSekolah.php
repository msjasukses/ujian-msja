<?php

namespace App\Console\Commands;

use App\Models\Sekolah;
use App\Support\LogoSekolah;
use Illuminate\Console\Command;

/**
 * Menjawab satu pertanyaan: kenapa logo sekolah tidak tampil di kop kartu?
 *
 * Logonya melewati beberapa tempat yang bisa gagal sendiri-sendiri — kolom
 * sekolah.logo di database Data Center, dan berkasnya di direktori unggahan
 * aplikasi itu — dan semuanya gagal tanpa suara: kartu tetap tercetak, hanya
 * memakai lambang bawaan.
 *
 * Pencariannya sendiri sudah menyisir sampai ketemu (lihat LogoSekolah), jadi
 * perintah ini bukan syarat agar logonya tampil. Gunanya memastikan: ia
 * memperlihatkan di mana berkasnya ketemu, atau kenapa tidak ketemu.
 */
class CekLogoSekolah extends Command
{
    protected $signature = 'sekolah:cek-logo {--segarkan : Lupakan letak yang diingat lalu cari ulang}';

    protected $description = 'Periksa sumber logo sekolah dari aplikasi Data Center';

    /** Calon yang dicetak sebelum sisanya diringkas. */
    protected const CALON_DICETAK = 6;

    public function handle(): int
    {
        $this->line('Database Data Center : '.config('database.connections.datacenter.database'));

        $sekolah = rescue(fn () => Sekolah::profil(), null, false);

        if (! $sekolah) {
            $this->error('Profil sekolah tidak terbaca. Periksa koneksi "datacenter" di .env.');

            return self::FAILURE;
        }

        $this->line('Nama sekolah         : '.($sekolah->nama_sekolah ?: '(kosong)'));
        $this->line('Kolom sekolah.logo   : '.($sekolah->logo ?: '(kosong)'));

        $logo = $sekolah->logoTercatat();

        if (! $logo) {
            $this->newLine();

            $this->warn($sekolah->logo
                ? 'Isi kolomnya bukan nama berkas gambar yang wajar, jadi ditolak demi keamanan.'
                : 'Data Center belum punya logo. Unggah lewat menu Pengaturan di aplikasi itu.');

            return self::FAILURE;
        }

        if ($this->option('segarkan')) {
            LogoSekolah::lupakan($logo);
            $this->line('(letak yang diingat dilupakan)');
        }

        $calon = LogoSekolah::calon($logo);

        $this->newLine();
        $this->line('Dicoba tanpa menyisir ('.count($calon).' tempat):');

        // Calonnya sebanyak aplikasi Laravel di direktori tetangga; yang
        // dicetak cukup sampai yang pertama berisi berkasnya, selebihnya
        // hanya memenuhi layar.
        foreach ($calon as $nomor => $berkas) {
            $tanda = $this->tanda($berkas);

            $this->line('  ['.$tanda.'] '.$berkas);

            if ($tanda === 'ada' || $nomor >= self::CALON_DICETAK - 1) {
                $sisa = count($calon) - $nomor - 1;

                if ($sisa > 0) {
                    $this->line('  ... dan '.$sisa.' tempat lain');
                }

                break;
            }
        }

        $path = LogoSekolah::path($logo);

        if (! $path) {
            $this->newLine();
            $this->line('Disisir di: '.implode(', ', (array) config('datacenter.akar_pencarian', [])));
            $this->newLine();
            $this->error('Berkasnya tidak ada di mana pun di bawah direktori itu.');
            $this->line('Dua kemungkinan:');
            $this->line('  1. Unggahannya tidak pernah mendarat di disk — buka Pengaturan di Data Center,');
            $this->line('     bila logonya juga tidak tampil di sana, periksa izin tulis folder storage-nya.');
            $this->line('  2. Data Center dipasang di luar direktori itu — setel DATACENTER_STORAGE_PATH');
            $this->line('     di .env ke direktori unggahannya, lalu: php artisan config:clear');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Logo ketemu: '.$path.' ('.number_format(filesize($path) / 1024, 1).' KB)');

        if ($akar = LogoSekolah::akarDiingat()) {
            $this->line('Akar yang diingat       : '.$akar);
        }

        $this->line('Dilayani aplikasi ini di: '.route('logo-sekolah'));

        return self::SUCCESS;
    }

    /** Keterangan singkat kenapa sebuah calon gagal. */
    protected function tanda(string $berkas): string
    {
        return match (true) {
            is_file($berkas) && is_readable($berkas) => 'ada',
            is_file($berkas) => 'tak terbaca',
            is_dir(dirname($berkas)) => 'tidak ada',
            default => 'folder tidak ada',
        };
    }
}
