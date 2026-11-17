<?php

namespace App\Console\Commands;

use App\Models\Sekolah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Menjawab satu pertanyaan: kenapa logo sekolah tidak tampil di kop kartu?
 *
 * Logonya melewati dua tempat yang bisa gagal sendiri-sendiri — kolom
 * sekolah.logo di database Data Center, dan berkasnya di direktori unggahan
 * aplikasi itu — dan keduanya gagal tanpa suara: kartu tetap tercetak, hanya
 * memakai lambang bawaan. Perintah ini menampilkan isi kedua tempat tersebut
 * sehingga ketahuan mana yang perlu dibereskan.
 */
class CekLogoSekolah extends Command
{
    protected $signature = 'sekolah:cek-logo';

    protected $description = 'Periksa sumber logo sekolah dari aplikasi Data Center';

    public function handle(): int
    {
        $akar = config('filesystems.disks.datacenter.root');

        $this->line('Database Data Center : '.config('database.connections.datacenter.database'));
        $this->line('Direktori unggahan   : '.$akar);
        $this->line('  ada direktorinya?  : '.($akar && is_dir($akar) ? 'ya' : 'TIDAK'));

        $sekolah = rescue(fn () => Sekolah::profil(), null, false);

        if (! $sekolah) {
            $this->newLine();
            $this->error('Profil sekolah tidak terbaca. Periksa koneksi "datacenter" di .env.');

            return self::FAILURE;
        }

        $this->line('Nama sekolah         : '.($sekolah->nama_sekolah ?: '(kosong)'));
        $this->line('Kolom sekolah.logo   : '.($sekolah->logo ?: '(kosong)'));

        if (! $sekolah->logo) {
            $this->newLine();
            $this->warn('Data Center belum punya logo. Unggah lewat menu Pengaturan → Profil Sekolah di aplikasi itu.');

            return self::FAILURE;
        }

        $path = $sekolah->pathLogo();

        if (! $path) {
            $this->line('Berkas dicari di     : '.Storage::disk('datacenter')->path($sekolah->logo));
            $this->newLine();
            $this->warn('Nama berkasnya tercatat, tetapi berkasnya tidak ada di sana.');
            $this->line('Setel DATACENTER_STORAGE_PATH di .env ke direktori storage/app/public milik Data Center,');
            $this->line('lalu jalankan: php artisan config:clear');

            return self::FAILURE;
        }

        $ukuran = rescue(fn () => Storage::disk('datacenter')->size($path), 0, false);

        $this->newLine();
        $this->info('Logo ditemukan: '.Storage::disk('datacenter')->path($path).' ('.number_format($ukuran / 1024, 1).' KB)');
        $this->line('Dilayani aplikasi ini di: '.route('logo-sekolah'));

        return self::SUCCESS;
    }
}
