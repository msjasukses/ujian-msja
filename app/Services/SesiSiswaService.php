<?php

namespace App\Services;

use App\Models\SesiSiswa;
use App\Models\UjianLog;
use App\Models\UjianPeserta;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Satu akun siswa hanya boleh masuk di satu perangkat pada satu waktu.
 *
 * Aturannya: login terbaru yang menang, sesi lama dikeluarkan. Kebalikannya —
 * menolak login baru selama sesi lama masih hidup — justru menjebak siswa
 * jujur yang HP-nya mati di tengah ujian lalu pindah perangkat: ia tidak akan
 * bisa masuk lagi sampai sesi lamanya kedaluwarsa sendiri.
 *
 * Cara kerjanya: setiap login siswa melahirkan token acak yang disimpan di
 * sesi peramban dan di tabel sesi_siswa. Setiap permintaan membandingkan
 * keduanya. Login baru menimpa token di tabel, sehingga token di perangkat
 * lama tidak lagi cocok dan sesinya diakhiri pada permintaan berikutnya.
 */
class SesiSiswaService
{
    public const KUNCI_SESI = 'sesi_siswa_token';

    /**
     * Selama inilah sebuah sesi dianggap masih dipakai sejak permintaan
     * terakhirnya. Lembar ujian berdenyut tiap 15 detik, jadi sesi yang sedang
     * mengerjakan selalu jauh di dalam batas ini.
     */
    public const MENIT_AKTIF = 10;

    /**
     * Dipanggil tepat setelah siswa berhasil masuk.
     *
     * @return SesiSiswa|null sesi perangkat lain yang baru saja diambil alih,
     *                        bila kejadian ini termasuk login ganda
     */
    public function mulai(Request $r, int $siswaId): ?SesiSiswa
    {
        $lama = SesiSiswa::where('siswa_id', $siswaId)->first();
        $ganda = $lama && $this->termasukGanda($lama, $r) ? clone $lama : null;
        $token = Str::random(64);

        SesiSiswa::updateOrCreate(['siswa_id' => $siswaId], [
            'token' => $token,
            'ip_address' => $r->ip(),
            'user_agent' => $this->uaPendek($r),
            'login_at' => now(),
            'last_seen_at' => now(),
        ]);

        $r->session()->put(self::KUNCI_SESI, $token);

        // Bila siswanya sedang mengerjakan ujian, login ganda dicatat untuk
        // pengawas: di tengah ujian ia bisa berarti joki, atau jawaban yang
        // dikerjakan bersama.
        if ($ganda) {
            $this->catatUntukPengawas($siswaId, $ganda, $r);
        }

        return $ganda;
    }

    /**
     * Apakah login ini benar-benar dua perangkat memakai satu akun.
     *
     * Penanda sesi lama tidak terhapus bila siswa sekadar menutup peramban
     * ujiannya — hanya bila ia menekan Keluar. Kalau keberadaan penanda itu
     * saja yang dipakai, hampir setiap login pagi tertandai ganda karena sisa
     * sesi kemarin. Dua syarat karenanya:
     *
     *  - sesi lama masih dipakai dalam MENIT_AKTIF menit terakhir;
     *  - dan perangkatnya lain. ExamBro yang ditutup lalu dibuka lagi di HP
     *    yang sama kehilangan sesinya dan masuk ulang dengan IP dan peramban
     *    yang persis sama — itu bukan dua perangkat.
     */
    protected function termasukGanda(SesiSiswa $lama, Request $r): bool
    {
        $terakhir = $lama->last_seen_at ?? $lama->login_at;

        if (! $terakhir || $terakhir->lt(now()->subMinutes(self::MENIT_AKTIF))) {
            return false;
        }

        $perangkatSama = $lama->ip_address === $r->ip()
            && $lama->user_agent === $this->uaPendek($r);

        return ! $perangkatSama;
    }

    protected function uaPendek(Request $r): string
    {
        return Str::limit((string) $r->userAgent(), 490, '');
    }

    /**
     * Apakah sesi peramban ini masih sesi yang sah untuk siswanya.
     *
     * Sesi tanpa token diperlakukan istimewa. Sesi seperti itu lahir sebelum
     * fitur ini dipasang — bila aplikasi diperbarui di tengah ujian, seluruh
     * siswa yang sedang mengerjakan memegang sesi tanpa token. Mengeluarkan
     * mereka semua sekaligus justru mengacaukan ujian, jadi sesi itu diakui
     * dan diberi token — kecuali akun itu sudah punya sesi resmi di perangkat
     * lain, yang berarti sesi tanpa token ini yang harus mengalah.
     */
    public function sah(Request $r, int $siswaId): bool
    {
        $tokenSesi = $r->session()->get(self::KUNCI_SESI);
        $resmi = SesiSiswa::where('siswa_id', $siswaId)->first();

        if (! $tokenSesi) {
            if ($resmi) {
                return false;
            }

            $this->mulai($r, $siswaId);

            return true;
        }

        $cocok = $resmi && hash_equals($resmi->token, (string) $tokenSesi);

        // Kapan sesi ini terakhir dipakai, untuk menilai login ganda. Ditulis
        // paling sering semenit sekali — cukup teliti untuk ukuran sepuluh
        // menit, tanpa menulis ke database pada setiap permintaan.
        if ($cocok && (! $resmi->last_seen_at || $resmi->last_seen_at->lt(now()->subMinute()))) {
            $resmi->forceFill(['last_seen_at' => now()])->save();
        }

        return $cocok;
    }

    /** Keterangan perangkat yang kini memegang akun, untuk pesan ke sesi lama. */
    public function pemegang(int $siswaId): ?SesiSiswa
    {
        return SesiSiswa::where('siswa_id', $siswaId)->first();
    }

    /**
     * Siswa keluar dengan sengaja. Barisnya hanya dihapus bila memang milik
     * sesi ini — keluar dari perangkat lama yang sudah dikalahkan tidak boleh
     * ikut mengakhiri sesi di perangkat yang baru.
     */
    public function akhiri(Request $r, int $siswaId): void
    {
        $token = $r->session()->get(self::KUNCI_SESI);

        if ($token) {
            SesiSiswa::where('siswa_id', $siswaId)->where('token', $token)->delete();
        }
    }

    protected function catatUntukPengawas(int $siswaId, SesiSiswa $lama, Request $r): void
    {
        $sedang = UjianPeserta::where('siswa_id', $siswaId)
            ->where('status', UjianPeserta::MULAI)
            ->get();

        foreach ($sedang as $peserta) {
            UjianLog::catat($peserta->ujian_id, $peserta->id, 'sesi_ganda',
                'Masuk dari '.($r->ip() ?: '?').'; sesi di '.($lama->ip_address ?: '?').' diakhiri.');
        }
    }
}
