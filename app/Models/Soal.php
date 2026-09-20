<?php

namespace App\Models;

use App\Models\Concerns\MilikGuru;
use App\Support\TeksSoal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Butir soal pada bank soal.
 *
 * Kolom `opsi` dan `kunci` berbentuk JSON dengan struktur yang berbeda per
 * jenis soal — lihat komentar di migration create_ujian_tables serta metode
 * koreksi() di bawah, yang menjadi satu-satunya tempat aturan penilaian
 * tiap jenis soal didefinisikan.
 */
class Soal extends Model
{
    use MilikGuru;

    protected $table = 'soal';

    public const PG = 'pg';

    public const PG_KOMPLEKS = 'pg_kompleks';

    public const ESSAY = 'essay';

    public const PENJODOHAN = 'penjodohan';

    public const BENAR_SALAH = 'benar_salah';

    /** @var array<string, string> */
    public const JENIS = [
        self::PG => 'Pilihan Ganda',
        self::PG_KOMPLEKS => 'Pilihan Ganda Kompleks',
        self::ESSAY => 'Essay / Uraian',
        self::PENJODOHAN => 'Penjodohan',
        self::BENAR_SALAH => 'Benar / Salah',
    ];

    /** @var array<string, string> */
    public const KESUKARAN = [
        'mudah' => 'Mudah',
        'sedang' => 'Sedang',
        'sukar' => 'Sukar',
    ];

    protected $fillable = [
        'kode_soal',
        'topik_id',
        'mata_pelajaran_id',
        'tingkat_kelas_id',
        'tahun_ajaran',
        'jenis',
        'pertanyaan',
        'gambar',
        'opsi',
        'kunci',
        'bobot',
        'level_kognitif',
        'tingkat_kesukaran',
        'pembahasan',
        'guru_id',
        'is_aktif',
    ];

    protected $casts = [
        'opsi' => 'array',
        'kunci' => 'array',
        'bobot' => 'decimal:2',
        'is_aktif' => 'boolean',
    ];

    public function topik()
    {
        return $this->belongsTo(Topik::class);
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function tingkatKelas()
    {
        return $this->belongsTo(TingkatKelas::class);
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }

    public function paketDetail(): HasMany
    {
        return $this->hasMany(PaketSoalDetail::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    public function getJenisLabelAttribute(): string
    {
        return self::JENIS[$this->jenis] ?? $this->jenis;
    }

    /** Soal essay dinilai manual oleh guru, bukan otomatis oleh sistem. */
    public function getDinilaiManualAttribute(): bool
    {
        return $this->jenis === self::ESSAY;
    }

    /**
     * Daftar opsi jawaban siap tampil. Untuk penjodohan yang dikembalikan
     * adalah kolom kiri (pernyataan), sedangkan kolom kanan diambil lewat
     * opsiKanan().
     *
     * @return array<int, array{key:string, text:string}>
     */
    public function opsiPilihan(): array
    {
        if ($this->jenis === self::BENAR_SALAH) {
            return [
                ['key' => 'benar', 'text' => 'Benar'],
                ['key' => 'salah', 'text' => 'Salah'],
            ];
        }

        if ($this->jenis === self::PENJODOHAN) {
            return $this->opsi['kiri'] ?? [];
        }

        return is_array($this->opsi) ? array_values($this->opsi) : [];
    }

    /** @return array<int, array{key:string, text:string}> */
    public function opsiKanan(): array
    {
        return $this->opsi['kanan'] ?? [];
    }

    /**
     * Koreksi jawaban peserta terhadap kunci.
     *
     * @param  mixed  $jawaban  Isi kolom ujian_jawaban.jawaban (sudah di-decode).
     * @return array{is_benar: bool|null, skor: float}
     *                                                 is_benar null berarti belum bisa dinilai otomatis (essay).
     */
    public function koreksi($jawaban): array
    {
        $bobot = (float) $this->bobot;
        $kunci = $this->kunci ?? [];

        // Tidak menjawab -> selalu 0 dan dihitung salah, kecuali essay yang
        // tetap menunggu penilaian guru.
        $kosong = $jawaban === null || $jawaban === '' || $jawaban === [];

        return match ($this->jenis) {
            self::ESSAY => ['is_benar' => null, 'skor' => 0.0],

            self::PG, self::BENAR_SALAH => (function () use ($jawaban, $kunci, $bobot, $kosong) {
                if ($kosong) {
                    return ['is_benar' => false, 'skor' => 0.0];
                }
                $pilih = is_array($jawaban) ? ($jawaban[0] ?? null) : $jawaban;
                $benar = $pilih !== null
                    && strcasecmp((string) $pilih, (string) ($kunci[0] ?? '')) === 0;

                return ['is_benar' => $benar, 'skor' => $benar ? $bobot : 0.0];
            })(),

            // Pilihan ganda kompleks memakai skor parsial: tiap pilihan benar
            // menambah, tiap pilihan salah mengurangi, hasil minimal 0.
            self::PG_KOMPLEKS => (function () use ($jawaban, $kunci, $bobot, $kosong) {
                if ($kosong || ! is_array($jawaban)) {
                    return ['is_benar' => false, 'skor' => 0.0];
                }
                $kunciSet = array_map('strtoupper', array_map('strval', $kunci));
                $pilihSet = array_unique(array_map('strtoupper', array_map('strval', $jawaban)));
                if (count($kunciSet) === 0) {
                    return ['is_benar' => false, 'skor' => 0.0];
                }

                $tepat = count(array_intersect($pilihSet, $kunciSet));
                $meleset = count(array_diff($pilihSet, $kunciSet));
                $rasio = max(0, ($tepat - $meleset) / count($kunciSet));

                return [
                    'is_benar' => $tepat === count($kunciSet) && $meleset === 0,
                    'skor' => round($bobot * $rasio, 2),
                ];
            })(),

            // Penjodohan dinilai per pasangan yang tepat.
            self::PENJODOHAN => (function () use ($jawaban, $kunci, $bobot, $kosong) {
                if ($kosong || ! is_array($jawaban) || count($kunci) === 0) {
                    return ['is_benar' => false, 'skor' => 0.0];
                }
                $tepat = 0;
                foreach ($kunci as $kiri => $kanan) {
                    $dijawab = $jawaban[$kiri] ?? null;
                    if ($dijawab !== null && strcasecmp((string) $dijawab, (string) $kanan) === 0) {
                        $tepat++;
                    }
                }

                return [
                    'is_benar' => $tepat === count($kunci),
                    'skor' => round($bobot * ($tepat / count($kunci)), 2),
                ];
            })(),

            default => ['is_benar' => false, 'skor' => 0.0],
        };
    }

    /** Ringkasan kunci jawaban untuk ditampilkan di tabel. */
    public function getKunciRingkasAttribute(): string
    {
        $kunci = $this->kunci ?? [];

        return match ($this->jenis) {
            // Pedoman jawaban essay ditulis lewat editor kaya, jadi berisi
            // HTML (span Arab, rumus, <p>) yang harus dibuang sebelum diringkas.
            self::ESSAY => Str::limit(TeksSoal::polos($kunci['jawaban'] ?? null) ?: '-', 60),
            // Kurung kurawal wajib di sini: tanpa itu panah "→" ikut terserap
            // menjadi bagian nama variabel — PHP menganggap bita 0x80–0xFF
            // sebagai huruf yang sah untuk pengenal, sehingga "$k→" dibaca
            // sebagai satu variabel yang tidak pernah ada.
            self::PENJODOHAN => collect($kunci)->map(fn ($v, $k) => "{$k}→{$v}")->implode(', '),
            self::BENAR_SALAH => ucfirst((string) ($kunci[0] ?? '-')),
            default => implode(', ', (array) $kunci),
        };
    }
}
