<?php

namespace App\Support;

use App\Models\Guru;
use App\Models\GuruMapel;
use App\Models\Jurusan;
use App\Models\MataPelajaran;
use App\Models\Pengaturan;
use App\Models\RombonganBelajar;
use App\Models\Sekolah;
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
    /** Jenjang yang membagi siswanya per jurusan. */
    public const JENJANG_BERJURUSAN = ['SMA', 'SMK'];

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

    /**
     * Aturan validasi jurusan_id, sudah lengkap dengan kuncinya. Pada jenjang
     * tanpa jurusan hasilnya kosong, sehingga jurusan_id tidak ikut tersimpan
     * dan jurusan yang sudah tercatat tidak terhapus hanya karena jenjangnya
     * diganti.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function aturanJurusan(int|string|null $nilaiLama = null): array
    {
        if (! static::pakaiJurusan()) {
            return [];
        }

        return ['jurusan_id' => static::aturanPilihan(static::jurusan()->pluck('id')->all(), $nilaiLama)];
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

    /** @return array<string, string> */
    public static function daftarJenjang(): array
    {
        return [
            'SD' => 'SD / MI',
            'SMP' => 'SMP / MTs',
            'SMA' => 'SMA / MA',
            'SMK' => 'SMK / MAK',
        ];
    }

    /**
     * Jenjang sekolah: SD, SMP, SMA, atau SMK.
     *
     * Diatur admin lewat menu Pengaturan. Selama belum pernah disimpan,
     * dipakai jenjang sekolah yang tercatat di Data Center.
     */
    public static function jenjang(): ?string
    {
        return static::normalJenjang(Pengaturan::ambil(Pengaturan::JENJANG))
            ?? static::jenjangDataCenter();
    }

    public static function jenjangDataCenter(): ?string
    {
        // Disimpan sebagai '' bila tidak dikenali, karena nilai null tidak
        // tertahan di cache dan Data Center akan ditanya ulang terus.
        return static::ingat('jenjang_datacenter',
            fn () => static::normalJenjang(Sekolah::profil()?->jenjang) ?? '') ?: null;
    }

    /** Pilihan jurusan hanya muncul di jenjang SMA dan SMK. */
    public static function pakaiJurusan(): bool
    {
        return in_array(static::jenjang(), self::JENJANG_BERJURUSAN, true);
    }

    /**
     * Seragamkan tulisan jenjang ("smk", "SMK/MAK", "MA") menjadi kodenya;
     * yang tidak dikenali menjadi null.
     */
    public static function normalJenjang(?string $nilai): ?string
    {
        $nilai = strtoupper(trim((string) $nilai));

        return match (true) {
            $nilai === '' => null,
            array_key_exists($nilai, static::daftarJenjang()) => $nilai,
            str_contains($nilai, 'SMK') || str_contains($nilai, 'MAK') => 'SMK',
            str_contains($nilai, 'SMA') || $nilai === 'MA' => 'SMA',
            str_contains($nilai, 'SMP') || str_contains($nilai, 'MTS') => 'SMP',
            str_contains($nilai, 'SD') || $nilai === 'MI' => 'SD',
            default => null,
        };
    }

    /**
     * Jurusan yang boleh dipilih pengguna yang sedang masuk. Untuk guru,
     * diturunkan dari rombel yang diajarnya, sama seperti tingkat kelas.
     *
     * @return Collection<int, Jurusan>
     */
    public static function jurusan(): Collection
    {
        $guruId = Pengguna::guruId();

        return static::ingat('jurusan:'.($guruId ?? 'semua'), function () use ($guruId) {
            $semua = Jurusan::aktif()->orderBy('nama_jurusan')->get();

            if (! $guruId) {
                return $semua;
            }

            $ids = static::rombel($guruId)->pluck('jurusan_id')
                ->filter()->map(fn ($id) => (int) $id)->unique()->all();

            return $ids === [] ? $semua : $semua->whereIn('id', $ids)->values();
        });
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
