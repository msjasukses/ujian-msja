<?php

namespace App\Http\Controllers\Laporan;

use App\Models\TindakLanjut;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Models\UjianPeserta;
use App\Services\RegistrasiPesertaService;
use App\Support\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Sub menu "Remidial" — siswa yang nilainya belum mencapai KKM. */
class RemidialController extends TindakLanjutController
{
    protected function jenis(): string
    {
        return TindakLanjut::REMIDIAL;
    }

    protected function routeDasar(): string
    {
        return 'laporan.remidial';
    }

    protected function folderView(): string
    {
        return 'laporan.remidial';
    }

    protected function memenuhiSyarat(UjianPeserta $peserta, float $kkm): bool
    {
        return (float) $peserta->nilai < $kkm;
    }

    protected function daftarBentuk(): array
    {
        return TindakLanjut::BENTUK_REMIDIAL;
    }

    /**
     * Buat jadwal ujian remidial dari ujian ini: paket soalnya sama, tetapi
     * pesertanya hanya siswa yang belum tuntas.
     */
    public function buatUjian(Request $r, Ujian $ujian, RegistrasiPesertaService $registrasi)
    {
        $r->validate([
            'waktu_mulai' => 'required|date',
            'waktu_selesai' => 'required|date|after:waktu_mulai',
            'durasi_menit' => 'required|integer|min:5|max:600',
            'paket_soal_id' => 'nullable|integer|exists:paket_soal,id',
        ], [], ['waktu_selesai' => 'Waktu selesai']);

        $siswaIds = $this->kandidat($ujian)->pluck('siswa_id')->all();

        if ($siswaIds === []) {
            return back()->with('error', 'Tidak ada siswa yang perlu remidial pada ujian ini.');
        }

        $remidial = Ujian::create([
            'kode_ujian' => 'RMD-'.now()->format('ymd').'-'.strtoupper(Str::random(4)),
            'nama_ujian' => 'Remidial — '.$ujian->nama_ujian,
            // Guru boleh memakai paket soal lain untuk naskah remidial.
            'paket_soal_id' => $r->input('paket_soal_id') ?: $ujian->paket_soal_id,
            'mata_pelajaran_id' => $ujian->mata_pelajaran_id,
            'tahun_ajaran' => $ujian->tahun_ajaran,
            'semester' => $ujian->semester,
            'waktu_mulai' => $r->input('waktu_mulai'),
            'waktu_selesai' => $r->input('waktu_selesai'),
            'durasi_menit' => $r->input('durasi_menit'),
            'token' => strtoupper(Str::random(6)),
            'kkm' => $ujian->kkm,
            'tampilkan_hasil' => $ujian->tampilkan_hasil,
            'acak_soal' => $ujian->acak_soal,
            'acak_opsi' => $ujian->acak_opsi,
            'status' => Ujian::DRAFT,
            'ujian_induk_id' => $ujian->id,
            'is_remidial' => true,
            'guru_id' => Pengguna::guruId() ?? $ujian->guru_id,
        ]);

        // Kelas peserta diwarisi supaya sinkron peserta berikutnya tetap benar.
        foreach ($ujian->rombelIds() as $rombelId) {
            UjianKelas::firstOrCreate([
                'ujian_id' => $remidial->id,
                'rombongan_belajar_id' => $rombelId,
            ]);
        }

        $jumlah = $registrasi->daftarkanSiswa($remidial, $siswaIds);

        return redirect()->route('ujian.edit', $remidial)->with(
            'success',
            "Ujian remidial dibuat dengan {$jumlah} peserta. Periksa jadwalnya lalu ubah statusnya menjadi Aktif."
        );
    }
}
