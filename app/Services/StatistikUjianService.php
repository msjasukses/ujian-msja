<?php

namespace App\Services;

use App\Models\RombonganBelajar;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use Illuminate\Support\Collection;

/** Statistik deskriptif nilai sebuah ujian, per ujian dan per kelas. */
class StatistikUjianService
{
    /** Batas-batas interval distribusi nilai yang dipakai di grafik. */
    public const INTERVAL = [
        ['label' => '0 - 54', 'min' => 0, 'max' => 54.99],
        ['label' => '55 - 64', 'min' => 55, 'max' => 64.99],
        ['label' => '65 - 74', 'min' => 65, 'max' => 74.99],
        ['label' => '75 - 84', 'min' => 75, 'max' => 84.99],
        ['label' => '85 - 94', 'min' => 85, 'max' => 94.99],
        ['label' => '95 - 100', 'min' => 95, 'max' => 100],
    ];

    /**
     * @return array{
     *     ringkasan: array<string, mixed>,
     *     distribusi: array<int, array<string, mixed>>,
     *     per_kelas: Collection<int, object>,
     *     peserta: Collection<int, UjianPeserta>
     * }
     */
    public function untukUjian(Ujian $ujian, int|string|null $rombelId = null): array
    {
        // Dengan kelas dipilih, seluruh angka pada halaman ini — rata-rata,
        // sebaran, ketuntasan — dihitung dari kelas itu saja, bukan disaring
        // belakangan di tampilan.
        $peserta = $ujian->peserta()
            ->with('siswa')
            ->when($rombelId, fn ($q, $v) => $q->where('rombongan_belajar_id', $v))
            ->get();
        $selesai = $peserta->where('status', UjianPeserta::SELESAI);
        $nilai = $selesai->pluck('nilai')->map(fn ($n) => (float) $n)->sort()->values();

        return [
            'ringkasan' => $this->ringkasan($ujian, $peserta, $nilai),
            'distribusi' => $this->distribusi($nilai),
            'per_kelas' => $this->perKelas($ujian, $peserta),
            'peserta' => $peserta,
        ];
    }

    /**
     * @param  Collection<int, UjianPeserta>  $peserta
     * @param  Collection<int, float>  $nilai
     */
    protected function ringkasan(Ujian $ujian, Collection $peserta, Collection $nilai): array
    {
        $kkm = (float) $ujian->kkm;
        $n = $nilai->count();

        $tuntas = $nilai->filter(fn ($v) => $v >= $kkm)->count();

        return [
            'terdaftar' => $peserta->count(),
            'mengerjakan' => $peserta->whereIn('status', [UjianPeserta::MULAI, UjianPeserta::SELESAI])->count(),
            'selesai' => $n,
            'belum' => $peserta->where('status', UjianPeserta::TERDAFTAR)->count(),
            'kkm' => $kkm,
            'rata_rata' => $n ? round($nilai->avg(), 2) : 0,
            'median' => $this->median($nilai),
            'modus' => $this->modus($nilai),
            'tertinggi' => $n ? round($nilai->max(), 2) : 0,
            'terendah' => $n ? round($nilai->min(), 2) : 0,
            'jangkauan' => $n ? round($nilai->max() - $nilai->min(), 2) : 0,
            'simpangan_baku' => $this->simpanganBaku($nilai),
            'tuntas' => $tuntas,
            'belum_tuntas' => $n - $tuntas,
            'persen_tuntas' => $n ? round($tuntas / $n * 100, 1) : 0,
            'menunggu_koreksi' => $peserta->where('status', UjianPeserta::SELESAI)
                ->where('essay_dinilai', false)->count(),
        ];
    }

    /** @param Collection<int, float> $nilai */
    protected function distribusi(Collection $nilai): array
    {
        $total = max(1, $nilai->count());

        return array_map(function (array $interval) use ($nilai, $total) {
            $jumlah = $nilai->filter(
                fn ($v) => $v >= $interval['min'] && $v <= $interval['max']
            )->count();

            return $interval + [
                'jumlah' => $jumlah,
                'persen' => round($jumlah / $total * 100, 1),
            ];
        }, self::INTERVAL);
    }

    /**
     * @param  Collection<int, UjianPeserta>  $peserta
     * @return Collection<int, object>
     */
    protected function perKelas(Ujian $ujian, Collection $peserta): Collection
    {
        $namaKelas = RombonganBelajar::whereIn('id', $peserta->pluck('rombongan_belajar_id')->filter()->unique())
            ->pluck('nama_rombel', 'id');
        $kkm = (float) $ujian->kkm;

        return $peserta->groupBy('rombongan_belajar_id')
            ->map(function (Collection $grup, $rombelId) use ($namaKelas, $kkm) {
                $selesai = $grup->where('status', UjianPeserta::SELESAI);
                $nilai = $selesai->pluck('nilai')->map(fn ($n) => (float) $n);
                $tuntas = $nilai->filter(fn ($v) => $v >= $kkm)->count();

                return (object) [
                    'rombongan_belajar_id' => $rombelId,
                    'nama_kelas' => $namaKelas[$rombelId] ?? '-',
                    'terdaftar' => $grup->count(),
                    'selesai' => $selesai->count(),
                    'rata_rata' => $nilai->count() ? round($nilai->avg(), 2) : 0,
                    'tertinggi' => $nilai->count() ? round($nilai->max(), 2) : 0,
                    'terendah' => $nilai->count() ? round($nilai->min(), 2) : 0,
                    'tuntas' => $tuntas,
                    'belum_tuntas' => $nilai->count() - $tuntas,
                    'persen_tuntas' => $nilai->count() ? round($tuntas / $nilai->count() * 100, 1) : 0,
                ];
            })
            ->sortBy('nama_kelas')
            ->values();
    }

    /** @param Collection<int, float> $nilai Harus sudah terurut menaik. */
    protected function median(Collection $nilai): float
    {
        $n = $nilai->count();
        if ($n === 0) {
            return 0;
        }

        return $n % 2
            ? round($nilai[intdiv($n, 2)], 2)
            : round(($nilai[$n / 2 - 1] + $nilai[$n / 2]) / 2, 2);
    }

    /** @param Collection<int, float> $nilai */
    protected function modus(Collection $nilai): ?float
    {
        if ($nilai->isEmpty()) {
            return null;
        }

        // Nilai dijadikan string lebih dulu: dipakai langsung sebagai kunci
        // array, pecahan seperti 27,5 akan dipangkas menjadi 27 sehingga
        // nilai yang berbeda salah terhitung sebagai nilai yang sama.
        $hitung = $nilai->countBy(fn ($v) => (string) $v);
        $maks = $hitung->max();

        // Bila semua nilai muncul sama sering, sebaran itu tidak punya modus.
        if ($maks <= 1) {
            return null;
        }

        return (float) $hitung->filter(fn ($c) => $c === $maks)->keys()->first();
    }

    /** Simpangan baku populasi. @param Collection<int, float> $nilai */
    protected function simpanganBaku(Collection $nilai): float
    {
        $n = $nilai->count();
        if ($n < 2) {
            return 0;
        }

        $rata = $nilai->avg();
        $varian = $nilai->reduce(fn ($carry, $v) => $carry + ($v - $rata) ** 2, 0) / $n;

        return round(sqrt($varian), 2);
    }
}
