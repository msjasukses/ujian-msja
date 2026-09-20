<?php

namespace App\Console\Commands;

use App\Models\Soal;
use App\Services\GambarSoalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Hapus gambar soal yang sudah tidak dirujuk butir mana pun.
 *
 * Gambar diunggah sebelum soalnya disimpan, jadi berkas tetap tertinggal bila
 * guru mengunggah lalu membatalkan, menyunting soal dan mengganti gambarnya,
 * atau membatalkan pratinjau impor. Tanpa pembersihan, sisa itu menumpuk
 * diam-diam sepanjang tahun ajaran.
 *
 * Perintah ini menampilkan temuannya lebih dulu dan baru menghapus bila
 * diminta dengan --hapus, karena penghapusan berkas tidak bisa dibatalkan.
 */
class BersihkanGambarSoal extends Command
{
    protected $signature = 'soal:bersihkan-gambar
                            {--hapus : Benar-benar hapus berkasnya, bukan sekadar mendaftar}';

    protected $description = 'Daftar (atau hapus) gambar soal yang tidak lagi dirujuk butir mana pun';

    public function handle(GambarSoalService $gambar): int
    {
        $disk = Storage::disk('public');
        $tersimpan = collect($disk->allFiles('soal'))
            ->mapWithKeys(fn ($jalur) => ['/storage/'.$jalur => $jalur]);

        if ($tersimpan->isEmpty()) {
            $this->info('Belum ada gambar soal yang tersimpan.');

            return self::SUCCESS;
        }

        $terpakai = $this->alamatTerpakai($gambar);
        $menganggur = $tersimpan->except($terpakai);

        $this->line("Gambar tersimpan   : {$tersimpan->count()}");
        $this->line('Masih dirujuk soal : '.($tersimpan->count() - $menganggur->count()));
        $this->line("Tidak dirujuk      : {$menganggur->count()}");

        if ($menganggur->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->option('hapus')) {
            $this->newLine();
            $this->comment('Jalankan ulang dengan --hapus untuk menghapusnya:');
            $this->line('  php artisan soal:bersihkan-gambar --hapus');

            return self::SUCCESS;
        }

        $ukuran = 0;

        foreach ($menganggur as $jalur) {
            $ukuran += $disk->size($jalur);
            $disk->delete($jalur);
        }

        $this->newLine();
        $this->info("{$menganggur->count()} berkas dihapus (".round($ukuran / 1024).' KB).');

        return self::SUCCESS;
    }

    /**
     * Alamat gambar yang masih dirujuk butir soal mana pun.
     *
     * Gambar bisa berada di badan pertanyaan, di teks opsi jawaban, di kunci
     * essay, maupun di pembahasan — keempatnya ditelusuri, sebab melewatkan
     * satu saja berarti menghapus gambar yang masih tampil di layar siswa.
     *
     * @return array<int, string>
     */
    protected function alamatTerpakai(GambarSoalService $gambar): array
    {
        $terpakai = [];

        Soal::select(['id', 'pertanyaan', 'opsi', 'kunci', 'pembahasan'])
            ->chunkById(200, function ($butir) use ($gambar, &$terpakai) {
                foreach ($butir as $soal) {
                    $sumber = [$soal->pertanyaan, $soal->pembahasan];

                    // Opsi dan kunci berupa struktur bersarang. Nilainya
                    // ditelusuri satu per satu, bukan disandikan ulang menjadi
                    // JSON: pada bentuk JSON tanda kutip di dalam src="..."
                    // ikut ter-escape sehingga pencarian rujukan meleset, dan
                    // gambar yang masih dipakai akan tampak menganggur.
                    foreach ([$soal->opsi, $soal->kunci] as $struktur) {
                        // Ditampung ke variabel lebih dulu — array_walk_recursive
                        // menerima argumen pertamanya sebagai referensi.
                        $isi = (array) ($struktur ?? []);

                        array_walk_recursive($isi, function ($nilai) use (&$sumber) {
                            if (is_string($nilai)) {
                                $sumber[] = $nilai;
                            }
                        });
                    }

                    foreach ($sumber as $teks) {
                        $terpakai = array_merge($terpakai, $gambar->rujukanDalam($teks));
                    }
                }
            });

        return array_values(array_unique($terpakai));
    }
}
