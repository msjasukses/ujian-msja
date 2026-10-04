<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\GuruMapel;
use App\Models\MataPelajaran;
use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Soal;
use App\Models\TingkatKelas;
use App\Models\Topik;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Services\RegistrasiPesertaService;
use App\Support\Referensi;
use Database\Seeders\Support\VideoPcm;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Contoh ujian bersoal audio dan video:
 *
 *   php artisan db:seed --class=ContohUjianMediaSeeder
 *
 * Isinya dua butir Seni Budaya Kelas 7 — satu menyimak rekaman tangga nada,
 * satu mengamati tayangan pola ketukan — dalam satu paket dan satu jadwal
 * ujian yang sedang dibuka, supaya pemutar audio dan video di lembar ujian
 * siswa bisa langsung dicoba.
 *
 * Rekaman dan tayangannya dibangkitkan di sini, bukan diunduh: seeder harus
 * berjalan di jaringan sekolah tanpa internet. Hasilnya nada sintetis dan
 * video bergambar sederhana tanpa suara — cukup untuk mencoba alurnya; ganti
 * dengan rekaman sungguhan lewat menu Input Soal untuk ujian betulan.
 *
 * Aman diulang: topik & soal diperbarui berdasarkan kodenya, sedangkan paket
 * dan jadwalnya (berawalan CTHM-) dibuat ulang. Awalannya sengaja berbeda
 * dari CTH- milik ContohSeeder supaya kedua seeder tidak saling menghapus.
 */
class ContohUjianMediaSeeder extends Seeder
{
    protected const AWALAN = 'CTHM-';

    public const TOKEN = 'MEDIA7';

    protected const MAPEL = 'Seni Budaya';

    protected const TINGKAT = 'Kelas 7';

    // Ukuran video. Kecil saja: makrobloknya disimpan tanpa kompresi.
    protected const LEBAR = 320;

    protected const TINGGI = 240;

    public function run(): void
    {
        if (! extension_loaded('gd')) {
            $this->command->error('Ekstensi PHP GD dibutuhkan untuk membangkitkan video contoh. Seeder dibatalkan.');

            return;
        }

        $mapelId = MataPelajaran::where('nama_mapel', self::MAPEL)->value('id');
        $tingkat = TingkatKelas::where('nama', self::TINGKAT)->first() ?? TingkatKelas::aktif()->urut()->first();
        $guruId = $this->guruPengampu($mapelId);

        $topik = Topik::updateOrCreate(
            ['kode_topik' => 'SBD-7-01'],
            [
                'nama_topik' => 'Unsur Musik: Tinggi Nada dan Birama',
                'mata_pelajaran_id' => $mapelId,
                'tingkat_kelas_id' => $tingkat?->id,
                'fase' => 'D',
                'semester' => 'Ganjil',
                'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
                'elemen' => 'Mengalami',
                'capaian_pembelajaran' => 'Peserta didik mengenali unsur-unsur musik, seperti tinggi-rendah nada '
                    .'dan pola ketukan, melalui kegiatan mendengar dan mengamati.',
                'tujuan_pembelajaran' => 'Membedakan arah tinggi-rendah nada dan mengenali birama dari pola ketukan kuat dan lemah.',
                'sumber' => Topik::SUMBER_MANUAL,
                'is_aktif' => true,
            ]
        );

        $atribut = [
            'topik_id' => $topik->id,
            'mata_pelajaran_id' => $mapelId,
            'tingkat_kelas_id' => $tingkat?->id,
            'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
            'jenis' => Soal::PG,
            'bobot' => 1,
            'guru_id' => $guruId,
            'is_aktif' => true,
        ];

        $soalAudio = Soal::updateOrCreate(['kode_soal' => self::AWALAN.'01'], $atribut + [
            'pertanyaan' => 'Putar rekaman di atas dan dengarkan urutan nadanya sampai selesai. '
                .'Bagaimana arah tinggi-rendah nada yang diperdengarkan?',
            'media_path' => $this->simpanMedia($this->tanggaNada(), 'wav'),
            'media_tipe' => 'audio',
            'opsi' => $this->opsi([
                'Naik, dari nada rendah ke nada tinggi',
                'Turun, dari nada tinggi ke nada rendah',
                'Datar, semua nada sama tingginya',
                'Naik lalu turun kembali',
            ]),
            'kunci' => ['A'],
            'level_kognitif' => 'C2',
            'tingkat_kesukaran' => 'mudah',
            'pembahasan' => 'Rekaman memperdengarkan tangga nada C mayor do–re–mi–fa–sol–la–si–do\' berurutan. '
                .'Setiap nada lebih tinggi dari nada sebelumnya, jadi arahnya naik.',
        ]);

        $soalVideo = Soal::updateOrCreate(['kode_soal' => self::AWALAN.'02'], $atribut + [
            'pertanyaan' => 'Putar tayangan di atas. Lingkaran merah menandai ketukan kuat, lingkaran biru '
                .'menandai ketukan lemah. Pola ketukan yang berulang pada tayangan tersebut menunjukkan birama ...',
            'media_path' => $this->simpanMedia($this->videoBirama(), 'mp4'),
            'media_tipe' => 'video',
            'opsi' => $this->opsi(['2/4', '3/4', '4/4', '6/8']),
            'kunci' => ['B'],
            'level_kognitif' => 'C3',
            'tingkat_kesukaran' => 'sedang',
            'pembahasan' => 'Tayangan memperlihatkan pola kuat–lemah–lemah yang berulang setiap tiga ketukan, '
                .'ciri birama 3/4. Birama 2/4 berpola kuat–lemah, sedangkan 4/4 berpola kuat–lemah–sedang–lemah.',
        ]);

        $this->bersihkan();

        $paket = PaketSoal::create([
            'kode_paket' => self::AWALAN.'PKT-SBD',
            'nama_paket' => 'Contoh Soal Audio & Video — Seni Budaya Kelas 7',
            'mata_pelajaran_id' => $mapelId,
            'tingkat_kelas_id' => $tingkat?->id,
            'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
            'semester' => 'Ganjil',
            'jenis_ujian' => 'UH',
            'deskripsi' => 'Paket contoh hasil seeder: satu butir menyimak rekaman, satu butir mengamati tayangan.',
            'guru_id' => $guruId,
            'is_aktif' => true,
        ]);

        foreach ([$soalAudio, $soalVideo] as $i => $soal) {
            PaketSoalDetail::create([
                'paket_soal_id' => $paket->id,
                'soal_id' => $soal->id,
                'nomor_urut' => $i + 1,
                'bobot' => $soal->bobot,
            ]);
        }

        $ujian = Ujian::create([
            'kode_ujian' => self::AWALAN.'UJN-SBD',
            'nama_ujian' => 'Contoh Ujian Audio & Video — Seni Budaya Kelas 7',
            'paket_soal_id' => $paket->id,
            'mata_pelajaran_id' => $mapelId,
            'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
            'semester' => 'Ganjil',
            // Dibuka seminggu supaya bisa dicoba kapan saja setelah seeding.
            'waktu_mulai' => now()->subMinutes(10),
            'waktu_selesai' => now()->addDays(7),
            'durasi_menit' => 30,
            'token' => self::TOKEN,
            'kkm' => 75,
            'tampilkan_hasil' => true,
            'status' => Ujian::AKTIF,
            'guru_id' => $guruId,
        ]);

        // Satu kelas saja, seperti ulangan harian biasa.
        $rombel = Referensi::rombel()->firstWhere('tingkat', $tingkat?->nomor) ?? Referensi::rombel()->first();

        if ($rombel) {
            UjianKelas::create(['ujian_id' => $ujian->id, 'rombongan_belajar_id' => $rombel->id]);
            app(RegistrasiPesertaService::class)->sinkronkan($ujian);
        }

        $this->command->info("Contoh ujian audio & video: \"{$ujian->nama_ujian}\", token ".self::TOKEN.', '
            .($rombel ? "kelas {$rombel->nama_rombel} ({$ujian->peserta()->count()} peserta)" : 'tanpa kelas peserta').'.');

        if (! $rombel) {
            $this->command->warn('Tidak ada rombel pada tahun ajaran aktif di Data Center; pasang kelas peserta lewat menu Registrasi Ujian.');
        }
    }

    /** Hapus paket & jadwal contoh sebelumnya; peserta dan jawabannya ikut terhapus lewat cascade. */
    protected function bersihkan(): void
    {
        Ujian::where('kode_ujian', 'like', self::AWALAN.'%')->get()->each->delete();
        PaketSoal::where('kode_paket', 'like', self::AWALAN.'%')->get()->each->delete();
    }

    /**
     * Simpan ke disk publik dengan nama sidik jari isinya, seperti
     * MediaSoalService. Seeding ulang menghasilkan isi yang sama persis,
     * jadi berkasnya tidak menumpuk.
     */
    protected function simpanMedia(string $isi, string $akhiran): string
    {
        $jalur = 'soal-media/'.sha1($isi).'.'.$akhiran;

        if (! Storage::disk('public')->exists($jalur)) {
            Storage::disk('public')->put($jalur, $isi);
        }

        return $jalur;
    }

    /**
     * @param  list<string>  $teks
     * @return list<array{key: string, text: string}>
     */
    protected function opsi(array $teks): array
    {
        return array_map(fn ($t, $i) => ['key' => chr(65 + $i), 'text' => $t], $teks, array_keys($teks));
    }

    protected function guruPengampu(?int $mapelId): ?int
    {
        if (! $mapelId) {
            return null;
        }

        return Guru::where('mata_pelajaran_id', $mapelId)->value('id')
            ?? GuruMapel::where('mata_pelajaran_id', $mapelId)->value('guru_id');
    }

    // =====================================================================
    // Audio: tangga nada C mayor naik
    // =====================================================================

    /** WAV PCM 16 kHz mono, sekitar 5 detik (±150 KB). */
    protected function tanggaNada(): string
    {
        $laju = 16000;
        // do re mi fa sol la si do'
        $frekuensi = [261.63, 293.66, 329.63, 349.23, 392.00, 440.00, 493.88, 523.25];
        $perNada = (int) ($laju * 0.5);

        // Jeda di awal supaya nada pertama tidak terpotong saat pemutar baru mulai.
        $data = str_repeat(pack('v', 0), (int) ($laju * 0.3));

        foreach ($frekuensi as $f) {
            for ($i = 0; $i < $perNada; $i++) {
                $t = $i / $laju;
                // Serangan 10 ms lalu meluruh, seperti bilah kolintang dipukul;
                // dua harmonik di atasnya membuat bunyinya tidak sedatar nada uji.
                $selubung = min(1, $t / 0.01) * exp(-4.5 * $t);
                $gelombang = sin(2 * M_PI * $f * $t) + 0.35 * sin(4 * M_PI * $f * $t) + 0.12 * sin(6 * M_PI * $f * $t);
                $data .= pack('v', (int) round(9000 * $selubung * $gelombang) & 0xFFFF);
            }
        }

        $data .= str_repeat(pack('v', 0), (int) ($laju * 0.4));

        return 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '
            .pack('V', 16).pack('v', 1).pack('v', 1)
            .pack('V', $laju).pack('V', $laju * 2).pack('v', 2).pack('v', 16)
            .'data'.pack('V', strlen($data)).$data;
    }

    // =====================================================================
    // Video: pola ketukan birama 3/4
    // =====================================================================

    /** MP4 tanpa suara, sekitar 11 detik: pembuka, empat birama 3/4, penutup. */
    protected function videoBirama(): string
    {
        $video = new VideoPcm(self::LEBAR, self::TINGGI);

        $video->tambah($this->layar(null, ['Perhatikan ketukan kuat', 'dan ketukan lemah', 'pada tayangan berikut.']), 2.0);

        for ($birama = 0; $birama < 4; $birama++) {
            foreach ([1, 2, 3] as $ketukan) {
                $video->tambah($this->layar($ketukan), 0.6);
            }
        }

        $video->tambah($this->layar(null, ['Tayangan selesai.', 'Jawablah soal di bawah', 'tayangan ini.']), 2.0);

        return $video->mp4();
    }

    /**
     * Satu bingkai: tiga lingkaran ketukan, dengan ketukan $aktif menyala.
     * Bila $aktif null dan $pesan diisi, yang tampil adalah layar pesan.
     *
     * @param  list<string>  $pesan
     */
    protected function layar(?int $aktif, array $pesan = []): GdImage
    {
        $g = imagecreatetruecolor(self::LEBAR, self::TINGGI);
        $warna = fn (int $rgb) => imagecolorallocate($g, ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);

        $latar = $warna(0xF7F3E8);
        $kepala = $warna(0x1F3A5F);
        $putih = $warna(0xFFFFFF);
        $gelap = $warna(0x1F2933);
        $kuat = $warna(0xD9483B);
        $lemah = $warna(0x3B7DD8);
        $redup = $warna(0xE3E6EB);
        $garis = $warna(0xB4BBC6);
        $abu = $warna(0x8A93A0);

        imagefilledrectangle($g, 0, 0, self::LEBAR - 1, self::TINGGI - 1, $latar);
        imagefilledrectangle($g, 0, 0, self::LEBAR - 1, 35, $kepala);
        $this->tulis($g, 'SENI MUSIK - POLA KETUKAN', 160, 10, $putih);

        if ($pesan !== []) {
            foreach ($pesan as $i => $baris) {
                $this->tulis($g, $baris, 160, 80 + $i * 26, $gelap);
            }
        } else {
            foreach ([1 => 70, 2 => 160, 3 => 250] as $nomor => $x) {
                $menyala = $nomor === $aktif;
                $isi = ! $menyala ? $redup : ($nomor === 1 ? $kuat : $lemah);
                $diameter = ! $menyala ? 60 : ($nomor === 1 ? 80 : 68);

                imagefilledellipse($g, $x, 108, $diameter + 4, $diameter + 4, $menyala ? $isi : $garis);
                imagefilledellipse($g, $x, 108, $diameter, $diameter, $isi);
                $this->tulis($g, (string) $nomor, $x, 108 - 15, $menyala ? $putih : $abu, 2);

                if ($menyala) {
                    $this->tulis($g, $nomor === 1 ? 'KUAT' : 'lemah', $x, 156, $nomor === 1 ? $kuat : $lemah);
                }
            }
        }

        // Keterangan warna selalu tampil di bawah.
        imagefilledellipse($g, 52, 213, 16, 16, $kuat);
        $this->tulis($g, '= kuat', 92, 206, $gelap);
        imagefilledellipse($g, 182, 213, 16, 16, $lemah);
        $this->tulis($g, '= lemah', 227, 206, $gelap);

        return $g;
    }

    /**
     * Tulis teks dengan fon bawaan GD, ditengahkan pada $tengahX. Huruf
     * diperbesar dengan menggambar ulang tiap pikselnya sebagai kotak, karena
     * fon bawaan GD tidak bisa diperbesar dan seeder tidak boleh bergantung
     * pada berkas fon TTF.
     */
    protected function tulis(GdImage $g, string $teks, int $tengahX, int $y, int $warna, int $skala = 1): void
    {
        $font = 5;
        $lebar = strlen($teks) * imagefontwidth($font);
        $tinggi = imagefontheight($font);

        $sementara = imagecreate($lebar, $tinggi);
        imagecolorallocate($sementara, 0, 0, 0);
        $tinta = imagecolorallocate($sementara, 255, 255, 255);
        imagestring($sementara, $font, 0, 0, $teks, $tinta);

        $x0 = $tengahX - intdiv($lebar * $skala, 2);

        for ($py = 0; $py < $tinggi; $py++) {
            for ($px = 0; $px < $lebar; $px++) {
                if (imagecolorat($sementara, $px, $py) === $tinta) {
                    imagefilledrectangle($g,
                        $x0 + $px * $skala, $y + $py * $skala,
                        $x0 + ($px + 1) * $skala - 1, $y + ($py + 1) * $skala - 1,
                        $warna);
                }
            }
        }
    }
}
