<?php

namespace App\Services;

use App\Models\KelasMovingSiswa;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\TahunAjaran;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use Illuminate\Support\Collection;
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
     * menghapusnya berarti membuang jawaban yang sudah ada, atau ia memang
     * ditambahkan guru satu per satu (sumber "manual", peserta kelas moving).
     *
     * Pada ujian kelas moving, rombongannya diambil dari kelas moving yang
     * dipasang pada ujian. Bila tidak ada satu pun kelas moving terpasang,
     * pesertanya murni pilihan guru sehingga tidak ada yang ditarik maupun
     * dicabut di sini.
     *
     * @return array{ditambah:int, dicabut:int, dipertahankan:int, total:int}
     */
    public function sinkronkan(Ujian $ujian): array
    {
        $tahunAjaranId = TahunAjaran::aktif()?->id;

        $calon = $ujian->kelas_moving
            ? $this->calonDariKelasMoving($ujian, $tahunAjaranId)
            : $this->calonDariRombel($ujian, $tahunAjaranId);

        if ($calon === null) {
            $total = $ujian->peserta()->count();

            return ['ditambah' => 0, 'dicabut' => 0, 'dipertahankan' => $total, 'total' => $total];
        }

        // Hanya siswa berstatus aktif yang didaftarkan.
        $siswaAktif = Siswa::aktif()
            ->whereIn('id', $calon->keys()->all() ?: [0])
            ->pluck('nis', 'id');

        $seharusnya = $calon->filter(fn ($rombelId, $siswaId) => $siswaAktif->has($siswaId));

        $sekarang = $ujian->peserta()->get()->keyBy('siswa_id');

        $ditambah = $dicabut = 0;

        DB::transaction(function () use ($ujian, $seharusnya, $sekarang, $siswaAktif, &$ditambah, &$dicabut) {
            foreach ($seharusnya as $siswaId => $rombelId) {
                if ($sekarang->has($siswaId)) {
                    // Ikut perbarui kelasnya bila siswa pindah rombel.
                    $sekarang[$siswaId]->update(['rombongan_belajar_id' => $rombelId]);

                    continue;
                }

                UjianPeserta::create([
                    'ujian_id' => $ujian->id,
                    'siswa_id' => $siswaId,
                    'rombongan_belajar_id' => $rombelId,
                    'sumber' => UjianPeserta::SUMBER_KELAS,
                    'nomor_peserta' => $siswaAktif[$siswaId] ?: null,
                    'status' => UjianPeserta::TERDAFTAR,
                ]);
                $ditambah++;
            }

            foreach ($sekarang as $siswaId => $peserta) {
                if ($seharusnya->has($siswaId)) {
                    continue;
                }
                // Peserta pilihan guru sendiri bukan bawaan kelas, jadi tetap
                // bertahan walau rombelnya tidak terdaftar pada ujian ini.
                if ($peserta->sumber === UjianPeserta::SUMBER_MANUAL) {
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
     * Calon peserta dari rombel yang dipasang pada ujian.
     *
     * @return Collection<int, int> siswa_id => rombel_id
     */
    protected function calonDariRombel(Ujian $ujian, ?int $tahunAjaranId): Collection
    {
        return SiswaRombel::whereIn('rombongan_belajar_id', $ujian->rombelIds() ?: [0])
            ->when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->pluck('rombongan_belajar_id', 'siswa_id')
            ->map('intval');
    }

    /**
     * Calon peserta dari kelas moving yang dipasang pada ujian. Rombel asal
     * siswa tetap dicatat supaya rekap nilai per kelas tetap benar.
     *
     * Null berarti ujian ini tidak memasang kelas moving sama sekali —
     * pesertanya pilihan guru, bukan urusan sinkron.
     *
     * @return Collection<int, int|null>|null
     */
    protected function calonDariKelasMoving(Ujian $ujian, ?int $tahunAjaranId): ?Collection
    {
        $kelasMovingIds = $ujian->kelasMovingIds();

        if ($kelasMovingIds === []) {
            return null;
        }

        $siswaIds = KelasMovingSiswa::whereIn('kelas_moving_id', $kelasMovingIds)
            ->pluck('siswa_id')
            ->map('intval')
            ->unique();

        $rombel = SiswaRombel::whereIn('siswa_id', $siswaIds->all() ?: [0])
            ->when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->pluck('rombongan_belajar_id', 'siswa_id');

        return $siswaIds->mapWithKeys(fn ($id) => [$id => $rombel->get($id) ? (int) $rombel->get($id) : null]);
    }

    /**
     * Daftarkan sekumpulan siswa tertentu ke sebuah ujian — dipakai saat
     * membuat ujian remidial yang pesertanya hanya siswa di bawah KKM, dan
     * saat guru memilih peserta satu per satu pada ujian kelas moving.
     *
     * Sumber "manual" menandai peserta pilihan guru supaya tidak ikut
     * tercabut oleh sinkron peserta dari kelas.
     *
     * @param  array<int, int>  $siswaIds
     */
    public function daftarkanSiswa(Ujian $ujian, array $siswaIds, string $sumber = UjianPeserta::SUMBER_KELAS): int
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
                    'sumber' => $sumber,
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

    /**
     * Cabut seorang peserta dari ujian. Yang sudah mulai atau selesai
     * mengerjakan tidak boleh dicabut karena jawabannya akan ikut terbuang —
     * untuk itu pakai tombol batalkan keikutsertaan.
     */
    public function cabutPeserta(UjianPeserta $peserta): bool
    {
        if (! in_array($peserta->status, [UjianPeserta::TERDAFTAR, UjianPeserta::DIBATALKAN], true)) {
            return false;
        }

        $peserta->delete();

        return true;
    }
}
