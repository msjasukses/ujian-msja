<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Penyimpan audio/video yang menyertai butir soal (soal menyimak dan soal
 * bertumpu tayangan).
 *
 * Berkasnya disimpan di disk publik dan dilayani server ini sendiri, bukan
 * ditautkan ke YouTube atau layanan lain: ujian dijalankan di jaringan lokal
 * tanpa internet, dan peramban ujian mengunci akses ke luar alamat server.
 *
 * Nama berkas diambil dari sidik jari isinya, jadi satu rekaman percakapan
 * yang dipakai sepuluh butir sekaligus hanya menempati ruang sekali — dan
 * karena itu pula berkas tidak pernah dihapus saat satu butir melepasnya:
 * butir lain bisa jadi masih memakainya.
 */
class MediaSoalService
{
    /**
     * Jenis yang diterima, dipetakan ke akhiran berkasnya.
     *
     * Sengaja hanya format yang dapat diputar peramban tanpa pemasangan
     * codec tambahan. MP3 dan MP4/H.264 berjalan di semua peramban ujian yang
     * dipakai sekolah; MKV, AVI, dan WMA tidak, sehingga ditolak sejak awal
     * ketimbang gagal diam-diam di layar siswa saat ujian berlangsung.
     *
     * @var array<string, array{tipe: string, ext: string}>
     */
    public const JENIS = [
        'audio/mpeg' => ['tipe' => 'audio', 'ext' => 'mp3'],
        'audio/mp3' => ['tipe' => 'audio', 'ext' => 'mp3'],
        'audio/wav' => ['tipe' => 'audio', 'ext' => 'wav'],
        'audio/x-wav' => ['tipe' => 'audio', 'ext' => 'wav'],
        'audio/ogg' => ['tipe' => 'audio', 'ext' => 'ogg'],
        'audio/mp4' => ['tipe' => 'audio', 'ext' => 'm4a'],
        'audio/x-m4a' => ['tipe' => 'audio', 'ext' => 'm4a'],
        'audio/aac' => ['tipe' => 'audio', 'ext' => 'aac'],
        'audio/webm' => ['tipe' => 'audio', 'ext' => 'weba'],
        'video/mp4' => ['tipe' => 'video', 'ext' => 'mp4'],
        'video/webm' => ['tipe' => 'video', 'ext' => 'webm'],
        'video/ogg' => ['tipe' => 'video', 'ext' => 'ogv'],
    ];

    /**
     * Wadah yang tidak menyebut audio atau video pada mime-nya.
     *
     * MP4 dan Ogg dipakai untuk keduanya, dan berkas yang ramping — audio M4A,
     * atau MP4 yang kotak "moov"-nya di belakang — kerap terbaca sekadar
     * "application/mp4". Untuk berkas seperti itu tipenya ditentukan dari
     * akhiran nama berkasnya, bukan ditebak.
     *
     * @var list<string>
     */
    protected const WADAH_AMBIGU = ['application/mp4', 'application/ogg', 'application/x-matroska'];

    /** @var array<string, array{tipe: string, ext: string}> */
    protected const AKHIRAN = [
        'mp4' => ['tipe' => 'video', 'ext' => 'mp4'],
        'm4v' => ['tipe' => 'video', 'ext' => 'mp4'],
        'ogv' => ['tipe' => 'video', 'ext' => 'ogv'],
        'webm' => ['tipe' => 'video', 'ext' => 'webm'],
        'm4a' => ['tipe' => 'audio', 'ext' => 'm4a'],
        'aac' => ['tipe' => 'audio', 'ext' => 'aac'],
        'mp3' => ['tipe' => 'audio', 'ext' => 'mp3'],
        'oga' => ['tipe' => 'audio', 'ext' => 'ogg'],
        'ogg' => ['tipe' => 'audio', 'ext' => 'ogg'],
        'wav' => ['tipe' => 'audio', 'ext' => 'wav'],
    ];

    protected const FOLDER = 'soal-media';

    /** Batas ukuran per jenis, dalam MB. */
    public static function maksMb(string $tipe): int
    {
        return (int) config("ujian.maks_media_mb.{$tipe}", $tipe === 'video' ? 50 : 20);
    }

    /**
     * Simpan satu berkas unggahan dan kembalikan jalur serta tipenya.
     *
     * @return array{path: string, tipe: string}
     *
     * @throws RuntimeException bila jenisnya tidak dikenali atau terlalu besar.
     */
    public function simpan(UploadedFile $berkas): array
    {
        $jenis = $this->kenali($berkas);

        if (! $jenis) {
            throw new RuntimeException(
                'Berkas yang diunggah bukan audio atau video yang dikenali. '
                .'Pakai MP3, WAV, OGG, M4A untuk audio, atau MP4, WebM untuk video.'
            );
        }

        $maks = static::maksMb($jenis['tipe']) * 1024 * 1024;

        if ($berkas->getSize() > $maks) {
            throw new RuntimeException(
                'Ukuran berkas melebihi '.static::maksMb($jenis['tipe']).' MB untuk '.$jenis['tipe'].'.'
            );
        }

        $isi = @file_get_contents($berkas->getRealPath());

        if ($isi === false) {
            throw new RuntimeException('Berkas gagal dibaca.');
        }

        $nama = self::FOLDER.'/'.sha1($isi).'.'.$jenis['ext'];

        if (! Storage::disk('public')->exists($nama)) {
            Storage::disk('public')->put($nama, $isi);
        }

        return ['path' => $nama, 'tipe' => $jenis['tipe']];
    }

    /**
     * Tipe dan akhiran berkas ini, atau null bila bukan audio/video yang
     * didukung. Isi berkas yang menentukan — akhiran nama hanya dipakai untuk
     * memilah audio dari video pada wadah yang memuat keduanya.
     *
     * @return array{tipe: string, ext: string}|null
     */
    protected function kenali(UploadedFile $berkas): ?array
    {
        $mime = strtolower((string) $berkas->getMimeType());

        if (isset(self::JENIS[$mime])) {
            return self::JENIS[$mime];
        }

        if (in_array($mime, self::WADAH_AMBIGU, true)) {
            $akhiran = strtolower($berkas->getClientOriginalExtension());

            return self::AKHIRAN[$akhiran] ?? null;
        }

        return null;
    }

    /** Batas unggah yang ditampilkan di formulir. */
    public static function keteranganBatas(): string
    {
        return 'Audio maksimal '.static::maksMb('audio').' MB (MP3, WAV, OGG, M4A), '
            .'video maksimal '.static::maksMb('video').' MB (MP4, WebM).';
    }
}
