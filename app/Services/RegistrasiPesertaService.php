<?php

namespace App\Services;

use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\TahunAjaran;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use Illuminate\Support\Facades\DB;

/**
 * Mendaftarkan peserta ujian dari kelas-kelas yang dipilih.
 *
 * Daftar siswa diambil dari database datacenter (tabel siswa_rombel), jadi
 * mutasi kelas yang terjadi di sana otomatis ikut terbawa saat pendaftaran
 * disinkronkan ulang.
 */
class RegistrasiPesertaService
{
    /**
     * Sinkronkan daftar peserta dengan kelas yang terpasang pada ujian.
     *
     * Siswa baru ditambahkan; siswa yang sudah tidak lagi berada di kelas
     * peserta akan dicabut — kecuali ia sudah mulai mengerjakan, karena
     * menghapusnya berarti membuang jawaban yang sudah ada.
     *
     * @return array{ditambah:int, dicabut:int, dipertahankan:int, total:int}
     */
    public function sinkronkan(Ujian $ujian): array
    {
        $rombelIds = $ujian->rombelIds();
        $tahunAjaranId = TahunAjaran::aktif()?->id;

        $penempatan = SiswaRombel::whereIn('rombongan_belajar_id', $rombelIds)
            ->when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->get();

        // Hanya siswa berstatus aktif yang didaftarkan.
        $siswaAktif = Siswa::aktif()
            ->whereIn('id', $penempatan->pluck('siswa_id'))
            ->pluck('nis', 'id');

        $seharusnya = $penempatan
            ->filter(fn ($p) => $siswaAktif->has($p->siswa_id))
            ->keyBy('siswa_id');

        $sekarang = $ujian->peserta()->get()->keyBy('siswa_id');

        $ditambah = $dicabut = 0;

        DB::transaction(function () use ($ujian, $seharusnya, $sekarang, $siswaAktif, &$ditambah, &$dicabut) {
            foreach ($seharusnya as $siswaId => $p) {
                if ($sekarang->has($siswaId)) {
                    // Ikut perbarui kelasnya bila siswa pindah rombel.
                    $sekarang[$siswaId]->update(['rombongan_belajar_id' => $p->rombongan_belajar_id]);

                    continue;
                }

                UjianPeserta::create([
                    'ujian_id' => $ujian->id,
                    'siswa_id' => $siswaId,
                    'rombongan_belajar_id' => $p->rombongan_belajar_id,
                    'nomor_peserta' => $siswaAktif[$siswaId] ?: null,
                    'status' => UjianPeserta::TERDAFTAR,
                ]);
                $ditambah++;
            }

            foreach ($sekarang as $siswaId => $peserta) {
                if ($seharusnya->has($siswaId)) {
                    continue;
                }
                // Peserta yang sudah mengerjakan tidak dicabut otomatis.
                if ($peserta->status === UjianPeserta::TERDAFTAR) {
                    $peserta->delete();
                    $dicabut++;
                }
            }
        });

        $total = $ujian->peserta()->count();

        return [
            'ditambah' => $ditambah,
            'dicabut' => $dicabut,
            'dipertahankan' => $total - $ditambah,
            'total' => $total,
        ];
    }

    /**
     * Daftarkan sekumpulan siswa tertentu ke sebuah ujian — dipakai saat
     * membuat ujian remidial yang pesertanya hanya siswa di bawah KKM.
     *
     * @param  array<int, int>  $siswaIds
     */
    public function daftarkanSiswa(Ujian $ujian, array $siswaIds): int
    {
        $tahunAjaranId = TahunAjaran::aktif()?->id;
        $kelas = SiswaRombel::whereIn('siswa_id', $siswaIds)
            ->when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->pluck('rombongan_belajar_id', 'siswa_id');
        $nis = Siswa::whereIn('id', $siswaIds)->pluck('nis', 'id');

        $jumlah = 0;

        foreach (array_unique($siswaIds) as $siswaId) {
            $peserta = UjianPeserta::firstOrCreate(
                ['ujian_id' => $ujian->id, 'siswa_id' => $siswaId],
                [
                    'rombongan_belajar_id' => $kelas[$siswaId] ?? null,
                    'nomor_peserta' => $nis[$siswaId] ?? null,
                    'status' => UjianPeserta::TERDAFTAR,
                ]
            );

            if ($peserta->wasRecentlyCreated) {
                $jumlah++;
            }
        }

        return $jumlah;
    }
}
