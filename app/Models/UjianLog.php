<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Jejak aktivitas peserta selama ujian, ditampilkan di menu Monitoring Ujian. */
class UjianLog extends Model
{
    protected $table = 'ujian_log';

    protected $fillable = [
        'ujian_id',
        'ujian_peserta_id',
        'event',
        'keterangan',
        'ip_address',
    ];

    /** @var array<string, string> */
    public const EVENT = [
        'mulai' => 'Mulai mengerjakan',
        'lanjut' => 'Melanjutkan ujian',
        'selesai' => 'Menyelesaikan ujian',
        'auto_selesai' => 'Selesai otomatis (waktu habis)',
        'keluar_halaman' => 'Berpindah / meninggalkan halaman ujian',
        'reset' => 'Direset pengawas',
        'token_salah' => 'Token salah',
        'tolak_non_exambro' => 'Ditolak: tidak memakai peramban ujian (ExamBro)',

        // Pelanggaran yang dilaporkan lapisan pengawasan lembar ujian.
        'tab_baru' => 'Mencoba membuka tab / jendela baru',
        'layar_terbelah' => 'Layar dibelah dengan aplikasi lain',
        'layar_mengambang' => 'Jendela mengambang / gambar dalam gambar',
        'jendela_popup' => 'Membuka jendela sembulan (pop-up)',
        'keluar_aplikasi' => 'Keluar dari peramban ujian / berpindah aplikasi',
        'hilang_fokus' => 'Lembar ujian kehilangan fokus',
        'keluar_layar_penuh' => 'Keluar dari mode layar penuh',
        'salin_tempel' => 'Mencoba menyalin atau menempel isi soal',
        'dikunci' => 'Lembar dikunci karena pelanggaran berulang',
        'dibuka_pengawas' => 'Kunci dibuka pengawas',
        'sesi_ganda' => 'Akun masuk dari perangkat lain — sesi lama diakhiri',
    ];

    public function ujian()
    {
        return $this->belongsTo(Ujian::class);
    }

    public function peserta()
    {
        return $this->belongsTo(UjianPeserta::class, 'ujian_peserta_id');
    }

    /**
     * Kejadian yang dihitung sebagai pelanggaran tata tertib.
     *
     * Dipisahkan dari EVENT karena jejak biasa — mulai, lanjut, selesai —
     * tidak boleh ikut tersorot merah di layar pengawas.
     *
     * @var list<string>
     */
    public const PELANGGARAN = ['tab_baru', 'layar_terbelah', 'layar_mengambang', 'jendela_popup',
        'keluar_aplikasi', 'hilang_fokus', 'keluar_layar_penuh', 'salin_tempel', 'dikunci'];

    /**
     * Nama pendek tiap pelanggaran, untuk rincian di tabel monitoring. Nama
     * panjang di EVENT terlalu lebar untuk dijejerkan dalam satu sel.
     *
     * @var array<string, string>
     */
    public const LABEL_SINGKAT = [
        'tab_baru' => 'Tab baru',
        'layar_terbelah' => 'Layar terbelah',
        'layar_mengambang' => 'Jendela mengambang',
        'jendela_popup' => 'Pop-up',
        'keluar_aplikasi' => 'Keluar aplikasi',
        'hilang_fokus' => 'Hilang fokus',
        'keluar_layar_penuh' => 'Keluar layar penuh',
    ];

    public function getPelanggaranAttribute(): bool
    {
        return in_array($this->event, self::PELANGGARAN, true);
    }

    public function scopePelanggaran($query)
    {
        return $query->whereIn('event', self::PELANGGARAN);
    }

    public function getEventLabelAttribute(): string
    {
        return self::EVENT[$this->event] ?? $this->event;
    }

    /** Catat satu kejadian; dipakai controller pengerjaan & monitoring. */
    public static function catat(int $ujianId, ?int $pesertaId, string $event, ?string $keterangan = null): void
    {
        static::create([
            'ujian_id' => $ujianId,
            'ujian_peserta_id' => $pesertaId,
            'event' => $event,
            'keterangan' => $keterangan,
            'ip_address' => request()->ip(),
        ]);
    }
}
