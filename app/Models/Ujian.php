<?php

namespace App\Models;

use App\Models\Concerns\MilikGuru;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jadwal ujian hasil menu "Registrasi Ujian": memasangkan satu paket soal
 * dengan kelas peserta, jendela waktu, durasi, token dan KKM.
 */
class Ujian extends Model
{
    use MilikGuru;

    protected $table = 'ujian';

    public const DRAFT = 'draft';

    public const AKTIF = 'aktif';

    public const SELESAI = 'selesai';

    /** @var array<string, string> */
    public const STATUS = [
        self::DRAFT => 'Draft',
        self::AKTIF => 'Aktif',
        self::SELESAI => 'Selesai',
    ];

    /** Peserta ditarik utuh dari kelas yang dipilih. */
    public const MODE_KELAS = 'kelas';

    /** Kelas moving: peserta dipilih per-siswa, boleh lintas kelas. */
    public const MODE_MANUAL = 'manual';

    /** @var array<string, string> */
    public const MODE_PESERTA = [
        self::MODE_KELAS => 'Seluruh siswa pada kelas yang dipilih',
        self::MODE_MANUAL => 'Kelas moving — pilih siswa tertentu lintas kelas',
    ];

    protected $fillable = [
        'kode_ujian',
        'nama_ujian',
        'paket_soal_id',
        'mata_pelajaran_id',
        'tahun_ajaran',
        'semester',
        'waktu_mulai',
        'waktu_selesai',
        'durasi_menit',
        'token',
        'kkm',
        'tampilkan_hasil',
        'acak_soal',
        'acak_opsi',
        'proteksi_ketat',
        'maks_pelanggaran',
        'wajib_exambro',
        'status',
        'mode_peserta',
        'ujian_induk_id',
        'is_remidial',
        'guru_id',
    ];

    protected $casts = [
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'kkm' => 'decimal:2',
        'tampilkan_hasil' => 'boolean',
        'acak_soal' => 'boolean',
        'acak_opsi' => 'boolean',
        'proteksi_ketat' => 'boolean',
        'maks_pelanggaran' => 'integer',
        'wajib_exambro' => 'boolean',
        'is_remidial' => 'boolean',
    ];

    public function paketSoal()
    {
        return $this->belongsTo(PaketSoal::class);
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }

    public function ujianInduk()
    {
        return $this->belongsTo(Ujian::class, 'ujian_induk_id');
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(UjianKelas::class);
    }

    public function kelasMovingTerpasang(): HasMany
    {
        return $this->hasMany(UjianKelasMoving::class);
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(UjianPeserta::class);
    }

    public function log(): HasMany
    {
        return $this->hasMany(UjianLog::class);
    }

    public function tindakLanjut(): HasMany
    {
        return $this->hasMany(TindakLanjut::class);
    }

    /** Id rombel peserta ujian ini. @return array<int,int> */
    public function rombelIds(): array
    {
        return $this->kelas()->pluck('rombongan_belajar_id')->map('intval')->all();
    }

    /**
     * Rombel peserta ujian ini, untuk penyaring kelas di menu laporan.
     *
     * Yang dipakai rombel yang benar-benar terdaftar pada ujian ini, bukan
     * seluruh rombel sekolah: daftar kelas di laporan tidak boleh memuat
     * kelas yang tak pernah ikut ujian tersebut.
     *
     * Pada ujian kelas moving pesertanya dipilih per-siswa sehingga rombelnya
     * belum tentu terdaftar di ujian_kelas — kelas asal peserta ikut dihitung
     * supaya rekap nilai tetap bisa disaring per kelas.
     *
     * @return \Illuminate\Support\Collection<int, RombonganBelajar>
     */
    public function rombelDipakai(): \Illuminate\Support\Collection
    {
        $ids = array_values(array_unique(array_merge(
            $this->rombelIds(),
            $this->peserta()->whereNotNull('rombongan_belajar_id')
                ->distinct()->pluck('rombongan_belajar_id')->map('intval')->all(),
        )));

        return $ids === []
            ? collect()
            : RombonganBelajar::whereIn('id', $ids)->orderBy('tingkat')->orderBy('nama_rombel')->get();
    }

    /** Ujian kelas moving: pesertanya dipilih per-siswa, bukan per rombel. */
    public function getKelasMovingAttribute(): bool
    {
        return $this->mode_peserta === self::MODE_MANUAL;
    }

    /** Id kelas moving yang dipasang pada ujian ini. @return array<int,int> */
    public function kelasMovingIds(): array
    {
        return $this->kelasMovingTerpasang()->pluck('kelas_moving_id')->map('intval')->all();
    }

    /**
     * Kelas moving peserta ujian ini, beserta anggotanya. Kosong berarti
     * pesertanya murni dipilih guru satu per satu.
     *
     * @return \Illuminate\Support\Collection<int, KelasMoving>
     */
    public function kelasMovingDipakai(): \Illuminate\Support\Collection
    {
        $ids = $this->kelasMovingIds();

        return $ids === []
            ? collect()
            : KelasMoving::with('anggota')->whereIn('id', $ids)->orderBy('nama_kelas_moving')->get();
    }

    /** Ujian sedang berada dalam jendela waktu pelaksanaan. */
    public function getSedangBerlangsungAttribute(): bool
    {
        return $this->status === self::AKTIF
            && now()->betweenIncluded($this->waktu_mulai, $this->waktu_selesai);
    }

    public function getBelumMulaiAttribute(): bool
    {
        return now()->lt($this->waktu_mulai);
    }

    public function getSudahLewatAttribute(): bool
    {
        return now()->gt($this->waktu_selesai);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /**
     * Waktu ditutup yang ringkas untuk siswa: jamnya saja bila tutup hari
     * ini, selain itu lengkap dengan tanggalnya — "Ditutup 18:21" pada ujian
     * yang dibuka seminggu terbaca seolah tutup sore ini.
     */
    public function getDitutupRingkasAttribute(): string
    {
        return $this->waktu_selesai->isToday()
            ? $this->waktu_selesai->format('H:i')
            : $this->waktu_selesai->translatedFormat('j M, H:i');
    }

    public function scopeAktif($query)
    {
        return $query->where('status', self::AKTIF);
    }

    /** Nilai maksimum yang bisa dicapai (total bobot butir di paket). */
    public function getTotalBobotAttribute(): float
    {
        return (float) PaketSoalDetail::where('paket_soal_id', $this->paket_soal_id)->sum('bobot');
    }
}
