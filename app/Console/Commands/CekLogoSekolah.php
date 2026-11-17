<?php

namespace App\Console\Commands;

use App\Models\Sekolah;
use App\Support\BerkasDataCenter;
use Illuminate\Console\Command;

/**
 * Menjawab satu pertanyaan: kenapa logo atau tanda tangan tidak tampil di
 * kartu peserta?
 *
 * Berkasnya melewati beberapa tempat yang bisa gagal sendiri-sendiri — kolom
 * di database Data Center, dan berkasnya di direktori unggahan aplikasi itu —
 * dan semuanya gagal tanpa suara: kartu tetap tercetak, hanya memakai lambang
 * bawaan dan ruang tanda tangan kosong.
 *
 * Pencariannya sendiri sudah menyisir sampai ketemu (lihat BerkasDataCenter),
 * jadi perintah ini bukan syarat agar gambarnya tampil. Gunanya memastikan:
 * ia memperlihatkan di mana berkasnya ketemu, atau kenapa tidak ketemu.
 */
class CekLogoSekolah extends Command
{
    protected $signature = 'sekolah:cek-logo {--segarkan : Lupakan letak yang diingat lalu cari ulang}';

    protected $description = 'Periksa logo & tanda tangan sekolah dari aplikasi Data Center';

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

        $berkas = [
            'Logo sekolah' => [$sekolah->logo, $sekolah->logoTercatat(), 'Pengaturan → Profil Sekolah'],
            'Tanda tangan kepsek' => [$sekolah->ttd_kepala_sekolah, $sekolah->ttdTercatat(), 'Pengaturan → Profil Sekolah'],
        ];

        $hasil = self::SUCCESS;

        foreach ($berkas as $judul => [$kolom, $relatif, $menu]) {
            $this->newLine();
            $this->line(str_repeat('─', 60));
            $this->line($judul.' — kolomnya berisi: '.($kolom ?: '(kosong)'));

            if ($this->periksa($relatif, $kolom, $menu) !== self::SUCCESS) {
                $hasil = self::FAILURE;
            }
        }

        return $hasil;
    }

    /** Telusuri satu berkas, dari isi kolom sampai letaknya di disk. */
    protected function periksa(?string $relatif, ?string $kolom, string $menu): int
    {
        if (! $relatif) {
            $this->warn($kolom
                ? 'Isi kolomnya bukan nama berkas gambar yang wajar, jadi ditolak demi keamanan.'
                : 'Belum diunggah di Data Center. Unggah lewat menu '.$menu.' di aplikasi itu.');

            return self::FAILURE;
        }

        if ($this->option('segarkan')) {
            BerkasDataCenter::lupakan($relatif);
        }

        $calon = BerkasDataCenter::calon($relatif);

        $this->line('Dicoba tanpa menyisir ('.count($calon).' tempat):');

        // Calonnya sebanyak aplikasi Laravel di direktori tetangga; yang
        // dicetak cukup sampai yang pertama berisi berkasnya, selebihnya
        // hanya memenuhi layar.
        foreach ($calon as $nomor => $satu) {
            $tanda = $this->tanda($satu);

            $this->line('  ['.$tanda.'] '.$satu);

            if ($tanda === 'ada' || $nomor >= self::CALON_DICETAK - 1) {
                $sisa = count($calon) - $nomor - 1;

                if ($sisa > 0) {
                    $this->line('  ... dan '.$sisa.' tempat lain');
                }

                break;
            }
        }

        $path = BerkasDataCenter::path($relatif);

        if (! $path) {
            $this->line('Disisir di: '.implode(', ', (array) config('datacenter.akar_pencarian', [])));
            $this->error('Berkasnya tidak ada di mana pun di bawah direktori itu.');
            $this->line('Dua kemungkinan:');
            $this->line('  1. Unggahannya tidak pernah mendarat di disk — buka '.$menu.' di Data Center,');
            $this->line('     bila gambarnya juga tidak tampil di sana, periksa izin tulis folder storage-nya.');
            $this->line('  2. Data Center dipasang di luar direktori itu — setel DATACENTER_STORAGE_PATH');
            $this->line('     di .env ke direktori unggahannya, lalu: php artisan config:clear');

            return self::FAILURE;
        }

        $this->info('Ketemu: '.$path.' ('.number_format(filesize($path) / 1024, 1).' KB)');

        if ($akar = BerkasDataCenter::akarDiingat()) {
            $this->line('Akar yang diingat: '.$akar);
        }

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
