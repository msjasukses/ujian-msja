<?php

namespace App\Http\Controllers;

use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Models\User;
use App\Services\CadanganService;
use App\Support\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Menu "Cadangan & Pemulihan".
 *
 * Khusus peran admin, tidak termasuk operator: pemulihan mengganti seluruh
 * data aplikasi — bank soal, jadwal, nilai, sampai akun pengelola — dan berkas
 * cadangan memuat hash kata sandi seluruh akun admin/operator.
 */
class CadanganController extends Controller
{
    public function __construct(protected CadanganService $cadangan) {}

    public function index()
    {
        $this->pastikanAdmin();

        $publik = Storage::disk('public');

        return view('cadangan.index', [
            'daftar' => $this->cadangan->daftar(),
            'zipTersedia' => class_exists(\ZipArchive::class),
            'ukuranMedia' => collect($publik->allFiles())->sum(fn ($f) => $publik->size($f)),
            'sisaDisk' => @disk_free_space(storage_path()) ?: null,
            'batasUnggah' => ini_get('upload_max_filesize'),
            'sedangUjian' => UjianPeserta::where('status', UjianPeserta::MULAI)
                ->whereIn('ujian_id', Ujian::aktif()->where('waktu_mulai', '<=', now())->where('waktu_selesai', '>=', now())->select('id'))
                ->count(),
        ]);
    }

    public function buat(Request $r)
    {
        $this->pastikanAdmin();

        try {
            $nama = $this->cadangan->buat('manual', $r->boolean('sertakan_berkas'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Cadangan {$nama} berhasil dibuat. Unduh dan simpan salinannya di luar server.");
    }

    public function unggah(Request $r)
    {
        $this->pastikanAdmin();

        $r->validate(
            ['berkas' => 'required|file|extensions:zip'],
            ['berkas.uploaded' => 'Berkas gagal diunggah — kemungkinan melebihi batas unggah server ('.ini_get('upload_max_filesize').').'],
            ['berkas' => 'Berkas cadangan'],
        );

        try {
            $nama = $this->cadangan->simpanUnggahan($r->file('berkas'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Berkas {$nama} diunggah. Periksa isinya pada daftar di bawah, lalu tekan Pulihkan bila sudah yakin.");
    }

    public function unduh(string $nama)
    {
        $this->pastikanAdmin();

        try {
            return response()->download($this->cadangan->jalur($nama));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pulihkan(Request $r, string $nama)
    {
        $this->pastikanAdmin();

        $r->validate(
            ['konfirmasi' => 'required|in:PULIHKAN'],
            ['konfirmasi.in' => 'Ketik PULIHKAN dengan huruf kapital untuk melanjutkan pemulihan.', 'konfirmasi.required' => 'Ketik PULIHKAN untuk melanjutkan pemulihan.'],
        );

        // Pemulihan yang terputus di tengah karena tab ditutup tetap aman
        // (transaksinya dibatalkan), tetapi lebih baik dibiarkan selesai.
        ignore_user_abort(true);

        try {
            $hasil = $this->cadangan->pulihkan($this->cadangan->jalur($nama));
        } catch (RuntimeException $e) {
            return back()->with('error', 'Pemulihan dibatalkan, data tidak berubah. '.$e->getMessage());
        }

        $pesan = 'Pemulihan dari '.$nama.' selesai: '.array_sum($hasil['tabel']).' baris data dan '
            .$hasil['berkas'].' berkas media dipulihkan.'
            .($hasil['pengaman'] ? ' Data sebelum pemulihan tersimpan sebagai '.$hasil['pengaman'].'.' : '')
            .($hasil['catatan'] ? ' Catatan: '.implode(' ', $hasil['catatan']) : '');

        // Akun pengelola ikut dipulihkan, jadi sesi ini tidak lagi bisa
        // dipercaya menunjuk orang yang sama. Masuk kembali dengan akun
        // yang tercatat di cadangan.
        if (isset($hasil['tabel']['users'])) {
            Auth::guard('web')->logout();
            $r->session()->invalidate();
            $r->session()->regenerateToken();

            return redirect()->route('login')->with('success', $pesan.' Silakan masuk kembali.');
        }

        return redirect()->route('cadangan.index')->with('success', $pesan);
    }

    public function hapus(string $nama)
    {
        $this->pastikanAdmin();

        try {
            $this->cadangan->hapus($nama);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Cadangan {$nama} dihapus.");
    }

    protected function pastikanAdmin(): void
    {
        $pengguna = Pengguna::user();

        abort_unless($pengguna instanceof User && $pengguna->isAdmin(), 403, 'Menu cadangan hanya untuk peran admin.');
    }
}
