<?php

namespace App\Support;

use App\Models\Guru;
use App\Models\GuruMapel;
use App\Models\MataPelajaran;
use App\Models\RombonganBelajar;
use App\Models\TahunAjaran;
use App\Models\TingkatKelas;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Daftar pilihan yang berulang kali dipakai di form dan filter, semuanya
 * berasal dari database datacenter. Hasilnya di-cache per request supaya
 * satu halaman tidak menembak database referensi berkali-kali.
 */
class Referensi
{
    /** @var array<string, mixed> */
    protected static array $cache = [];

    /**
     * Mata pelajaran yang boleh dipilih pengguna yang sedang masuk.
     *
     * Guru hanya mendapat mapel yang benar-benar diampunya menurut Data
     * Center; admin/operator mendapat semuanya. Bila penugasan guru itu belum
     * terisi di Data Center, daftarnya dikembalikan utuh — lebih baik guru
     * melihat semua mapel daripada tidak bisa menyusun soal sama sekali.
     *
     * @return Collection<int, MataPelajaran>
     */
    public static function mapel(): Collection
    {
        $guruId = Pengguna::guruId();

        return static::ingat('mapel:'.($guruId ?? 'semua'), function () use ($guruId) {
            $semua = MataPelajaran::aktif()->orderBy('nama_mapel')->get();

            if (! $guruId) {
                return $semua;
            }

            $ids = static::mapelGuru($guruId);

            return $ids === [] ? $semua : $semua->whereIn('id', $ids)->values();
        });
    }

    /**
     * Tingkat kelas yang boleh dipilih pengguna yang sedang masuk. Untuk guru,
     * diturunkan dari rombel yang diajarnya pada tahun ajaran aktif.
     *
     * @return Collection<int, TingkatKelas>
     */
    public static function tingkat(): Collection
    {
        $guruId = Pengguna::guruId();

        return static::ingat('tingkat:'.($guruId ?? 'semua'), function () use ($guruId) {
            $semua = TingkatKelas::aktif()->urut()->get();

            if (! $guruId) {
                return $semua;
            }

            // Kolom rombongan_belajar.tingkat berisi angka tingkatnya (7, 8, 9),
            // yang dicocokkan dengan kolom "nomor" pada tabel tingkat kelas.
            $nomor = static::rombel($guruId)->pluck('tingkat')
                ->filter(fn ($t) => filled($t))->map(fn ($t) => (int) $t)->unique()->all();

            return $nomor === [] ? $semua : $semua->whereIn('nomor', $nomor)->values();
        });
    }

    /**
     * Aturan validasi mata_pelajaran_id / tingkat_kelas_id: hanya yang ada di
     * dropdown pengguna ini. $nilaiLama (isi data yang sedang diubah) tetap
     * diterima supaya data buatan admin bisa disimpan ulang oleh guru tanpa
     * kehilangan penempatannya.
     */
    public static function aturanMapel(int|string|null $nilaiLama = null): array
    {
        return static::aturanPilihan(static::mapel()->pluck('id')->all(), $nilaiLama);
    }

    public static function aturanTingkat(int|string|null $nilaiLama = null): array
    {
        return static::aturanPilihan(static::tingkat()->pluck('id')->all(), $nilaiLama);
    }

    protected static function aturanPilihan(array $boleh, int|string|null $nilaiLama): array
    {
        if (filled($nilaiLama)) {
            $boleh[] = (int) $nilaiLama;
        }

        return ['nullable', 'integer', Rule::in(array_unique($boleh))];
    }

    /**
     * Id mapel yang diampu guru: diutamakan penugasan tahun ajaran aktif,
     * lalu penugasan tahun mana pun bila tahun berjalan belum diisi.
     *
     * @return array<int, int>
     */
    protected static function mapelGuru(int $guruId): array
    {
        $guru = Guru::find($guruId);

        if (! $guru) {
            return [];
        }

        $ids = $guru->mapelIds(static::tahunAjaranAktif()?->id);

        return $ids ?: $guru->mapelIds();
    }

    /** @return Collection<int, TahunAjaran> */
    public static function tahunAjaran(): Collection
    {
        return static::ingat('tahun_ajaran', fn () => TahunAjaran::orderByDesc('kode_tahun_ajaran')->get());
    }

    public static function tahunAjaranAktif(): ?TahunAjaran
    {
        return static::ingat('tahun_ajaran_aktif', fn () => TahunAjaran::aktif());
    }

    /** Nama tahun ajaran aktif, mis. "2025/2026". */
    public static function namaTahunAjaranAktif(): ?string
    {
        return static::tahunAjaranAktif()?->nama_tahun_ajaran;
    }

    /**
     * Aturan validasi isian tahun_ajaran: harus salah satu tahun ajaran di
     * Data Center. $nilaiLama (isi data yang sedang diubah) tetap diterima
     * supaya data lama bisa disimpan ulang meski tahunnya sudah dihapus
     * dari Data Center.
     */
    public static function aturanTahunAjaran(?string $nilaiLama = null): array
    {
        $boleh = static::tahunAjaran()->pluck('nama_tahun_ajaran')
            ->when(filled($nilaiLama), fn ($c) => $c->push($nilaiLama))
            ->all();

        return ['nullable', 'string', 'max:30', Rule::in($boleh)];
    }

    /**
     * Rombel pada tahun ajaran aktif. Bila $guruId diisi, hanya rombel yang
     * diampu guru itu yang dikembalikan.
     *
     * @return Collection<int, RombonganBelajar>
     */
    public static function rombel(?int $guruId = null): Collection
    {
        $kunci = 'rombel:'.($guruId ?? 'semua');

        return static::ingat($kunci, function () use ($guruId) {
            $tahunAjaranId = static::tahunAjaranAktif()?->id;

            return RombonganBelajar::with('jurusan')
                ->when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
                ->when($guruId, function ($q) use ($guruId) {
                    $q->whereIn('id', GuruMapel::where('guru_id', $guruId)
                        ->whereNotNull('rombongan_belajar_id')
                        ->pluck('rombongan_belajar_id'));
                })
                ->orderBy('tingkat')->orderBy('nama_rombel')
                ->get();
        });
    }

    /** @return Collection<int, Guru> */
    public static function guru(): Collection
    {
        return static::ingat('guru', fn () => Guru::aktif()->orderBy('nama_ptk')->get());
    }

    /** @return array<string, string> */
    public static function semester(): array
    {
        return ['Ganjil' => 'Ganjil', 'Genap' => 'Genap'];
    }

    /** @return array<string, string> */
    public static function levelKognitif(): array
    {
        return [
            'C1' => 'C1 — Mengingat',
            'C2' => 'C2 — Memahami',
            'C3' => 'C3 — Menerapkan',
            'C4' => 'C4 — Menganalisis',
            'C5' => 'C5 — Mengevaluasi',
            'C6' => 'C6 — Mencipta',
        ];
    }

    protected static function ingat(string $kunci, \Closure $isi): mixed
    {
        return static::$cache[$kunci] ??= $isi();
    }
}
