<?php

namespace App\Services;

use App\Models\UjianLog;
use App\Models\UjianPeserta;

/**
 * Penegakan tata tertib pengerjaan: tab baru, layar terbelah, dan jendela
 * mengambang.
 *
 * Perlu ditegaskan sejak awal karena menentukan seluruh rancangan kelas ini:
 * halaman web tidak dapat *melarang* sistem operasi membelah layar atau
 * membuka jendela mengambang. Yang dapat dilakukan lembar ujian hanyalah
 * mengenali bahwa hal itu terjadi, lalu menutup soalnya sehingga membelah
 * layar tidak lagi menguntungkan siapa pun. Penguncian di tingkat perangkat
 * tetap menjadi tugas ExamBro.
 *
 * Karena itu pertahanannya berlapis, dan lapisan inilah yang menghitung:
 * setiap pelanggaran dicatat ke jejak ujian, dijumlahkan per peserta, dan
 * setelah melewati batas, lembarnya dikunci sampai pengawas membukanya.
 *
 * Kuncinya sengaja tidak dapat dibuka siswa dengan token ujian. Token itu
 * diumumkan ke seluruh kelas saat ujian dimulai, jadi siswa yang melanggar
 * pasti mengetahuinya — kunci yang bisa dibuka sendiri sama saja dengan
 * tidak ada kunci.
 */
class PengawasanUjianService
{
    /**
     * Pelanggaran yang cukup dicatat tanpa menambah hitungan.
     *
     * Menyalin teks soal memang dilarang, tetapi percobaannya kerap tidak
     * disengaja — siswa menahan jarinya terlalu lama di layar sentuh. Terlalu
     * mahal bila tiga sentuhan panjang mengunci lembar jawaban.
     *
     * @var list<string>
     */
    public const RINGAN = ['salin_tempel'];

    /**
     * Pelanggaran yang langsung mengunci lembar, tanpa menunggu batas.
     *
     * Jendela sembulan dan jendela mengambang berbeda sifatnya dari
     * pelanggaran lain di daftar ini. Berpindah aplikasi atau keluar layar
     * penuh masih bisa terjadi karena kaget, salah tekan, atau telepon masuk —
     * karena itu diberi kesempatan. Sebaliknya, jendela kedua yang melayang di
     * atas lembar ujian tidak muncul dengan sendirinya: ia harus dibuka, dan
     * satu-satunya gunanya selama ujian adalah menaruh sesuatu untuk dibaca
     * berdampingan dengan soal.
     *
     * @var list<string>
     */
    public const BERAT = ['jendela_popup', 'layar_mengambang'];

    /**
     * Jenis kejadian yang membuat seorang siswa terhitung "melanggar" di menu
     * Monitoring: pelanggaran yang memang dihitung sistem. Percobaan menyalin
     * tidak, dan kejadian "dikunci" bukan perbuatan siswa.
     *
     * Satu sumber untuk halaman daftar dan halaman detail, supaya kedua angka
     * itu tidak pernah berbeda pendapat.
     *
     * @return list<string>
     */
    public static function jenisMelanggar(): array
    {
        return array_values(array_diff(UjianLog::PELANGGARAN, self::RINGAN, ['dikunci']));
    }

    /**
     * Catat satu pelanggaran, lalu kunci lembarnya bila batasnya terlampaui.
     *
     * @return array{pelanggaran:int, maks:int, terkunci:bool, sisa:int}
     */
    public function catat(UjianPeserta $peserta, string $jenis, ?string $keterangan = null): array
    {
        $ujian = $peserta->ujian;

        abort_unless(array_key_exists($jenis, UjianLog::EVENT)
            && in_array($jenis, UjianLog::PELANGGARAN, true), 422, 'Jenis pelanggaran tidak dikenal.');

        UjianLog::catat($ujian->id, $peserta->id, $jenis, $keterangan);

        $dihitung = $ujian->proteksi_ketat
            && ! in_array($jenis, self::RINGAN, true)
            && $peserta->status === UjianPeserta::MULAI;

        if ($dihitung) {
            $peserta->increment('pelanggaran');
            $peserta->refresh();

            $maks = (int) $ujian->maks_pelanggaran;
            $berat = in_array($jenis, self::BERAT, true);

            // Batas 0 berarti guru memang memilih lembar tidak pernah dikunci
            // sendiri oleh sistem. Pilihan itu dihormati bahkan untuk
            // pelanggaran berat — kalau tidak, setelan "Tidak pernah" berbohong.
            $kunci = $maks > 0 && ($berat || $peserta->pelanggaran >= $maks);

            if ($kunci && ! $peserta->dikunci_at) {
                $peserta->forceFill(['dikunci_at' => now()])->save();

                UjianLog::catat($ujian->id, $peserta->id, 'dikunci', $berat
                    ? UjianLog::EVENT[$jenis].' — dikunci seketika.'
                    : "Pelanggaran mencapai batas {$maks} kali.");
            }
        }

        return $this->keadaan($peserta->refresh());
    }

    /** Pengawas membuka kembali lembar yang terkunci; hitungannya dinolkan. */
    public function bukaKunci(UjianPeserta $peserta, ?string $alasan = null): void
    {
        if (! $peserta->dikunci_at) {
            return;
        }

        $peserta->forceFill(['dikunci_at' => null, 'pelanggaran' => 0])->save();

        UjianLog::catat($peserta->ujian_id, $peserta->id, 'dibuka_pengawas', $alasan);
    }

    /**
     * Keadaan pengawasan seorang peserta, dipakai lembar ujian maupun respons
     * pelaporan pelanggaran.
     *
     * @return array{pelanggaran:int, maks:int, terkunci:bool, sisa:int}
     */
    public function keadaan(UjianPeserta $peserta): array
    {
        $maks = (int) $peserta->ujian->maks_pelanggaran;
        $jumlah = (int) $peserta->pelanggaran;

        // Pelanggaran terakhir ikut dikabarkan supaya lembar ujian dapat
        // menampilkan layar blokirnya setelah peramban ujian ditutup dan
        // dibuka lagi: kepergiannya tercatat, tetapi halamannya sudah mati
        // sebelum sempat menampilkan apa pun.
        $terakhir = UjianLog::where('ujian_peserta_id', $peserta->id)
            ->whereIn('event', array_diff(UjianLog::PELANGGARAN, ['dikunci']))
            ->latest('id')
            ->first();

        return [
            'pelanggaran' => $jumlah,
            'maks' => $maks,
            'terkunci' => (bool) $peserta->dikunci_at,
            // 0 berarti tanpa batas: lembar tidak pernah dikunci sendiri.
            'sisa' => $maks > 0 ? max(0, $maks - $jumlah) : 0,
            'terakhir' => $terakhir?->event,
            // Carbon 3 mengembalikan pecahan bertanda; lembar ujian hanya
            // membutuhkan umurnya dalam detik bulat.
            'terakhir_detik' => $terakhir ? (int) abs($terakhir->created_at->diffInSeconds(now())) : null,
        ];
    }
}
