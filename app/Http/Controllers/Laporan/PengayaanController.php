<?php

namespace App\Http\Controllers\Laporan;

use App\Models\TindakLanjut;
use App\Models\UjianPeserta;

/** Sub menu "Pengayaan" — siswa yang nilainya sudah mencapai / melampaui KKM. */
class PengayaanController extends TindakLanjutController
{
    protected function jenis(): string
    {
        return TindakLanjut::PENGAYAAN;
    }

    protected function routeDasar(): string
    {
        return 'laporan.pengayaan';
    }

    protected function folderView(): string
    {
        return 'laporan.pengayaan';
    }

    protected function memenuhiSyarat(UjianPeserta $peserta, float $kkm): bool
    {
        return (float) $peserta->nilai >= $kkm;
    }

    protected function daftarBentuk(): array
    {
        return TindakLanjut::BENTUK_PENGAYAAN;
    }
}
