<?php

namespace App\Console\Commands;

use App\Services\CadanganService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Buat cadangan dari terminal server — dipakai penjadwal untuk cadangan
 * otomatis setiap malam (lihat routes/console.php).
 */
class BuatCadangan extends Command
{
    protected $signature = 'cadangan:buat
                            {--tanpa-berkas : Hanya data, tanpa gambar dan audio/video soal}
                            {--otomatis : Tandai sebagai cadangan otomatis}
                            {--simpan= : Sisakan sejumlah cadangan sejenis yang terbaru, hapus yang lebih lama}';

    protected $description = 'Buat cadangan data aplikasi ujian (ZIP di storage/app/private/cadangan)';

    public function handle(CadanganService $cadangan): int
    {
        $jenis = $this->option('otomatis') ? 'otomatis' : 'manual';

        try {
            $nama = $cadangan->buat($jenis, ! $this->option('tanpa-berkas'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Cadangan dibuat: {$nama}");

        if (filled($this->option('simpan'))) {
            $dihapus = $cadangan->pangkas($jenis, max(1, (int) $this->option('simpan')));

            if ($dihapus > 0) {
                $this->line("{$dihapus} cadangan {$jenis} yang lebih lama dihapus.");
            }
        }

        return self::SUCCESS;
    }
}
