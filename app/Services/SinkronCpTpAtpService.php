<?php

namespace App\Services;

use App\Models\MataPelajaran;
use App\Models\PemetaanCpTpAtp;
use App\Models\TingkatKelas;
use App\Models\Topik;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Menarik pemetaan CP-TP-ATP dari database "kurikulum" menjadi Topik di
 * database "ujian".
 *
 * Sinkron bersifat idempoten: baris pemetaan yang sudah pernah ditarik
 * dikenali lewat pasangan (sumber = 'sinkron', sumber_ref_id = id pemetaan),
 * sehingga menjalankan sinkron berulang kali hanya memperbarui isinya —
 * tidak menggandakan topik. Topik yang diinput manual tidak pernah tersentuh.
 */
class SinkronCpTpAtpService
{
    /**
     * Ambil data pemetaan dari kurikulum, sudah dilengkapi nama mapel dan
     * nama tingkat kelas dari datacenter (join dilakukan di PHP karena
     * ketiga tabel berada pada koneksi database yang berbeda).
     *
     * @return Collection<int, object>
     */
    public function pratinjau(array $filter = []): Collection
    {
        $pemetaan = PemetaanCpTpAtp::query()
            ->when($filter['mata_pelajaran_id'] ?? null, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
            ->when($filter['tingkat_kelas_id'] ?? null, fn ($q, $v) => $q->where('tingkat_kelas_id', $v))
            ->when($filter['tahun_ajaran'] ?? null, fn ($q, $v) => $q->where('tahun_ajaran', $v))
            ->when($filter['semester'] ?? null, fn ($q, $v) => $q->where('semester', $v))
            ->orderBy('mata_pelajaran_id')
            ->orderBy('tingkat_kelas_id')
            ->orderBy('id')
            ->get();

        return $this->lengkapiNamaReferensi($pemetaan);
    }

    /**
     * Jalankan sinkron. Bila $ids diisi, hanya baris pemetaan tersebut yang
     * ditarik; bila kosong, seluruh baris yang lolos filter ikut ditarik.
     *
     * @param  array<int, int>  $ids  Id baris pemetaan_cp_tp_atp
     * @return array{baru:int, diperbarui:int, dilewati:int, total:int}
     */
    public function jalankan(array $ids = [], array $filter = []): array
    {
        $pemetaan = PemetaanCpTpAtp::query()
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->when(! $ids, function ($q) use ($filter) {
                $q->when($filter['mata_pelajaran_id'] ?? null, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
                    ->when($filter['tingkat_kelas_id'] ?? null, fn ($q, $v) => $q->where('tingkat_kelas_id', $v))
                    ->when($filter['tahun_ajaran'] ?? null, fn ($q, $v) => $q->where('tahun_ajaran', $v))
                    ->when($filter['semester'] ?? null, fn ($q, $v) => $q->where('semester', $v));
            })
            ->get();

        $hasil = ['baru' => 0, 'diperbarui' => 0, 'dilewati' => 0, 'total' => $pemetaan->count()];

        foreach ($pemetaan as $p) {
            $nama = $this->namaTopik($p);

            // Baris pemetaan tanpa TP maupun elemen tidak punya isi yang bisa
            // dijadikan judul topik yang bermakna — lewati daripada membuat
            // topik "Topik #12" yang tidak informatif.
            if ($nama === null) {
                $hasil['dilewati']++;

                continue;
            }

            $atribut = [
                'nama_topik' => $nama,
                'mata_pelajaran_id' => $p->mata_pelajaran_id,
                'tingkat_kelas_id' => $p->tingkat_kelas_id,
                'fase' => $p->fase,
                'semester' => $p->semester,
                'tahun_ajaran' => $p->tahun_ajaran,
                'elemen' => $p->elemen,
                'capaian_pembelajaran' => $p->capaian_pembelajaran,
                'tujuan_pembelajaran' => $p->tujuan_pembelajaran,
                'alur_tujuan_pembelajaran' => $p->alur_tujuan_pembelajaran,
                'indikator_kktp' => $p->indikator_kktp,
                'disinkron_pada' => now(),
            ];

            $topik = Topik::where('sumber', Topik::SUMBER_SINKRON)
                ->where('sumber_ref_id', $p->id)
                ->first();

            if ($topik) {
                $topik->fill($atribut)->save();
                $hasil['diperbarui']++;
            } else {
                Topik::create($atribut + [
                    'sumber' => Topik::SUMBER_SINKRON,
                    'sumber_ref_id' => $p->id,
                    'kode_topik' => 'CP-'.str_pad((string) $p->id, 4, '0', STR_PAD_LEFT),
                    'is_aktif' => true,
                ]);
                $hasil['baru']++;
            }
        }

        return $hasil;
    }

    /**
     * Judul topik diambil dari tujuan pembelajaran (paling spesifik), lalu
     * elemen, lalu capaian pembelajaran sebagai cadangan terakhir.
     */
    protected function namaTopik(PemetaanCpTpAtp $p): ?string
    {
        foreach (['tujuan_pembelajaran', 'elemen', 'alur_tujuan_pembelajaran', 'capaian_pembelajaran'] as $kolom) {
            $isi = trim(strip_tags((string) $p->{$kolom}));
            if ($isi !== '') {
                return Str::limit(preg_replace('/\s+/', ' ', $isi), 180, '');
            }
        }

        return null;
    }

    /**
     * Tempelkan nama_mapel & nama tingkat dari koneksi datacenter ke tiap
     * baris pemetaan, sekaligus tandai mana yang sudah pernah disinkron.
     *
     * @param  Collection<int, PemetaanCpTpAtp>  $pemetaan
     * @return Collection<int, object>
     */
    protected function lengkapiNamaReferensi(Collection $pemetaan): Collection
    {
        $mapel = MataPelajaran::whereIn('id', $pemetaan->pluck('mata_pelajaran_id')->filter()->unique())
            ->pluck('nama_mapel', 'id');
        $tingkat = TingkatKelas::whereIn('id', $pemetaan->pluck('tingkat_kelas_id')->filter()->unique())
            ->pluck('nama', 'id');
        $sudah = Topik::where('sumber', Topik::SUMBER_SINKRON)
            ->whereIn('sumber_ref_id', $pemetaan->pluck('id'))
            ->pluck('disinkron_pada', 'sumber_ref_id');

        return $pemetaan->map(function (PemetaanCpTpAtp $p) use ($mapel, $tingkat, $sudah) {
            return (object) [
                'id' => $p->id,
                'nama_mapel' => $mapel[$p->mata_pelajaran_id] ?? '-',
                'nama_tingkat' => $tingkat[$p->tingkat_kelas_id] ?? '-',
                'fase' => $p->fase,
                'semester' => $p->semester,
                'tahun_ajaran' => $p->tahun_ajaran,
                'elemen' => $p->elemen,
                'capaian_pembelajaran' => $p->capaian_pembelajaran,
                'tujuan_pembelajaran' => $p->tujuan_pembelajaran,
                'alur_tujuan_pembelajaran' => $p->alur_tujuan_pembelajaran,
                'indikator_kktp' => $p->indikator_kktp,
                'nama_topik' => $this->namaTopik($p),
                'sudah_disinkron' => isset($sudah[$p->id]),
                'disinkron_pada' => $sudah[$p->id] ?? null,
            ];
        });
    }

    /** Daftar tahun ajaran yang ada pada tabel pemetaan (untuk filter). */
    public function daftarTahunAjaran(): array
    {
        return PemetaanCpTpAtp::query()
            ->whereNotNull('tahun_ajaran')
            ->distinct()
            ->orderByDesc('tahun_ajaran')
            ->pluck('tahun_ajaran')
            ->all();
    }

    /** Apakah database kurikulum bisa dihubungi? Dipakai untuk pesan ramah di UI. */
    public function koneksiTersedia(): bool
    {
        try {
            PemetaanCpTpAtp::query()->limit(1)->exists();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
