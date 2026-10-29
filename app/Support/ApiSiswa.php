<?php

namespace App\Support;

use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\Soal;
use App\Models\UjianJawaban;
use App\Models\UjianPeserta;

/**
 * Penyaji data untuk API aplikasi ponsel peserta.
 *
 * Dikumpulkan di satu tempat supaya bentuk JSON yang dibaca aplikasi tidak
 * tersebar di beberapa controller — satu perubahan nama kolom cukup diurus
 * di sini, dan daftar ujian, lembar pengerjaan, serta halaman hasil tidak
 * pernah menyebut butir yang sama dengan bentuk yang berbeda.
 */
final class ApiSiswa
{
    /**
     * Teks soal siap tampil di aplikasi.
     *
     * Isinya disaring dengan penyaring yang sama dengan lembar ujian web,
     * lalu alamat gambarnya dijadikan mutlak: aplikasi ponsel tidak punya
     * "alamat halaman sekarang" yang bisa dipakai melengkapi "/storage/...".
     */
    public static function html(?string $teks): string
    {
        return self::mutlakkan((string) TeksSoal::html($teks));
    }

    /** Alamat mutlak sebuah berkas pada disk publik. */
    public static function berkas(?string $jalur): ?string
    {
        return $jalur ? self::asal().AlamatPublik::berkas($jalur) : null;
    }

    private static function asal(): string
    {
        return rtrim(request()->getSchemeAndHttpHost(), '/');
    }

    /** Lengkapi setiap src="/..." pada sepotong HTML menjadi alamat mutlak. */
    private static function mutlakkan(string $html): string
    {
        if (stripos($html, 'src=') === false) {
            return $html;
        }

        $pola = '#(\ssrc\s*=\s*["\x27])(/(?![/\\\\]))#i';

        return (string) preg_replace($pola, '${1}'.self::asal().'${2}', $html);
    }

    /** @return array<string, mixed> */
    public static function siswa(Siswa $siswa): array
    {
        $rombel = $siswa->rombelPada();
        $sekolah = Sekolah::profil();

        return [
            'id' => (int) $siswa->id,
            'nisn' => (string) $siswa->nisn,
            'nis' => $siswa->nis,
            'nama' => (string) $siswa->nama_siswa,
            'jenis_kelamin' => $siswa->jenis_kelamin,
            'tempat_lahir' => $siswa->tempat_lahir,
            'tanggal_lahir' => $siswa->tanggal_lahir?->toDateString(),
            'kelas' => $rombel?->nama_rombel,
            'tingkat' => $rombel === null ? null : (int) $rombel->tingkat,
            'wali_kelas' => $rombel?->waliKelas?->nama_ptk,
            'sekolah' => $sekolah?->nama_sekolah,
            'npsn' => $sekolah?->npsn,
        ];
    }

    /**
     * Satu baris pada daftar ujian siswa.
     *
     * Waktu dikirim mentah dalam ISO-8601, bukan hanya sebagai teks jadi:
     * aplikasi perlu membandingkannya untuk memutuskan tombol "Mulai" hidup
     * atau mati, dan untuk menghitung mundur sendiri di antara dua denyut.
     *
     * @return array<string, mixed>
     */
    public static function ujianRingkas(UjianPeserta $peserta): array
    {
        $ujian = $peserta->ujian;

        return [
            'peserta_id' => (int) $peserta->id,
            'nomor_peserta' => $peserta->nomor_peserta,
            'status' => $peserta->status,
            'status_label' => $peserta->status_label,
            'ujian' => [
                'id' => (int) $ujian->id,
                'kode' => $ujian->kode_ujian,
                'nama' => $ujian->nama_ujian,
                'mata_pelajaran' => $ujian->mataPelajaran?->nama_mapel,
                'waktu_mulai' => $ujian->waktu_mulai?->toIso8601String(),
                'waktu_selesai' => $ujian->waktu_selesai?->toIso8601String(),
                'ditutup_ringkas' => $ujian->ditutup_ringkas,
                'durasi_menit' => (int) $ujian->durasi_menit,
                'kkm' => (float) $ujian->kkm,
                'butuh_token' => filled($ujian->token),
                'proteksi_ketat' => (bool) $ujian->proteksi_ketat,
                'maks_pelanggaran' => (int) $ujian->maks_pelanggaran,
                'wajib_exambro' => (bool) $ujian->wajib_exambro,
                'tampilkan_hasil' => (bool) $ujian->tampilkan_hasil,
                'sedang_berlangsung' => (bool) $ujian->sedang_berlangsung,
                'belum_mulai' => (bool) $ujian->belum_mulai,
                'sudah_lewat' => (bool) $ujian->sudah_lewat,
            ],
            'sisa_detik' => $peserta->sisaWaktuDetik(),
            'nilai' => $peserta->nilai === null ? null : (float) $peserta->nilai,
            'lulus' => $peserta->lulus,
            'selesai' => $peserta->status === UjianPeserta::SELESAI,
        ];
    }

    /**
     * Satu butir pada lembar pengerjaan, berikut jawaban yang sudah tersimpan.
     *
     * Urutan opsi mengikuti urutan_opsi yang dikunci saat peserta memulai,
     * persis seperti lembar ujian di peramban — membuka ulang aplikasi tidak
     * boleh mengacak ulang pilihan yang sedang dibaca siswa.
     *
     * @return array<string, mixed>
     */
    public static function butir(UjianJawaban $j): array
    {
        $soal = $j->soal;

        return [
            'id' => (int) $j->id,
            'soal_id' => (int) $j->soal_id,
            'nomor_urut' => (int) $j->nomor_urut,
            'jenis' => $soal?->jenis,
            'jenis_label' => $soal?->jenis_label,
            'pertanyaan' => self::html($soal?->pertanyaan),
            'media_url' => self::berkas($soal?->media_path),
            'media_tipe' => $soal?->media_tipe,
            'opsi' => self::opsi($j, 'kiri'),
            'opsi_kanan' => self::opsi($j, 'kanan'),
            'jawaban' => $j->jawaban,
            'ragu' => (bool) $j->ragu,
            'terisi' => $j->terisi,
        ];
    }

    /**
     * Butir pada halaman hasil: ditambah kunci dan pembahasan bila guru
     * mengizinkannya, dan tanpa keduanya bila tidak.
     *
     * @return array<string, mixed>
     */
    public static function butirHasil(UjianJawaban $j, bool $bolehPembahasan): array
    {
        $soal = $j->soal;

        $butir = self::butir($j) + [
            'is_benar' => $j->is_benar,
            'skor' => (float) $j->skor,
        ];

        if ($bolehPembahasan && $soal) {
            $butir['kunci'] = $soal->jenis === Soal::ESSAY
                ? ['jawaban' => self::html($soal->kunci['jawaban'] ?? null)]
                : $soal->kunci;
            $butir['pembahasan'] = self::html($soal->pembahasan);
        }

        return $butir;
    }

    /**
     * Opsi satu butir menurut urutan tampil yang berlaku bagi peserta ini.
     *
     * @return array<int, array{key: string, text: string}>
     */
    private static function opsi(UjianJawaban $j, string $sisi): array
    {
        $soal = $j->soal;

        if (! $soal) {
            return [];
        }

        $penjodohan = $soal->jenis === Soal::PENJODOHAN;

        if ($sisi === 'kanan') {
            $daftar = $penjodohan ? $soal->opsiKanan() : [];
            $diacak = $penjodohan;
        } else {
            $daftar = $soal->opsiPilihan();
            $diacak = in_array($soal->jenis, [Soal::PG, Soal::PG_KOMPLEKS, Soal::BENAR_SALAH_KOMPLEKS], true);
        }

        $daftar = collect($daftar);

        if ($diacak && $j->urutan_opsi) {
            $peta = $daftar->keyBy('key');
            $daftar = collect($j->urutan_opsi)->map(fn ($k) => $peta[$k] ?? null)->filter();
        }

        return $daftar->values()
            ->map(fn ($o) => ['key' => (string) $o['key'], 'text' => self::html($o['text'] ?? '')])
            ->all();
    }
}
