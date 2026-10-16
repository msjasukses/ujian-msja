<?php

namespace App\Console\Commands;

use App\Services\CadanganService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Pulihkan cadangan dari terminal server.
 *
 * Berguna untuk cadangan yang terlalu besar diunggah lewat peramban: salin
 * berkasnya ke server lewat SFTP, lalu jalankan perintah ini.
 */
class PulihkanCadangan extends Command
{
    protected $signature = 'cadangan:pulihkan
                            {berkas? : Nama berkas di storage/app/private/cadangan, atau jalur lengkap ke berkas ZIP}
                            {--tanpa-pengaman : Jangan cadangkan data saat ini lebih dulu}
                            {--force : Jangan minta konfirmasi}';

    protected $description = 'Ganti seluruh data aplikasi ujian dengan isi sebuah cadangan';

    public function handle(CadanganService $cadangan): int
    {
        $berkas = $this->argument('berkas');

        if (! $berkas) {
            $this->table(['Nama', 'Dibuat', 'Jenis'], $cadangan->daftar()->map(fn ($c) => [
                $c['nama'], $c['waktu']->format('d/m/Y H:i'), $c['manifest']['jenis'] ?? 'tidak sah',
            ]));
            $this->line('Jalankan: php artisan cadangan:pulihkan <nama-berkas>');

            return self::SUCCESS;
        }

        try {
            $jalur = is_file($berkas) ? $berkas : $cadangan->jalur($berkas);
            $manifest = $cadangan->bacaManifest($jalur);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("Cadangan   : {$berkas}");
        $this->line('Dibuat     : '.$manifest['dibuat'].' oleh '.($manifest['oleh'] ?? '-'));
        $this->line('Isi        : '.array_sum($manifest['tabel']).' baris data, '.($manifest['berkas']['jumlah'] ?? 0).' berkas media');

        if (! $this->option('force') && ! $this->confirm('Seluruh data aplikasi saat ini akan diganti. Lanjutkan?')) {
            return self::FAILURE;
        }

        try {
            $hasil = $cadangan->pulihkan($jalur, ! $this->option('tanpa-pengaman'));
        } catch (RuntimeException $e) {
            $this->error('Pemulihan dibatalkan, data tidak berubah. '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Pemulihan selesai: '.array_sum($hasil['tabel']).' baris data, '.$hasil['berkas'].' berkas media.');

        if ($hasil['pengaman']) {
            $this->line("Data sebelum pemulihan tersimpan sebagai {$hasil['pengaman']}.");
        }

        foreach ($hasil['catatan'] as $catatan) {
            $this->warn($catatan);
        }

        return self::SUCCESS;
    }
}
