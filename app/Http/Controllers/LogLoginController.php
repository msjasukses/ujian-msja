<?php

namespace App\Http\Controllers;

use App\Models\LoginAttempt;
use App\Models\Siswa;
use App\Support\ExcelExport;
use Illuminate\Http\Request;

/** Menu "Log Login" — jejak percobaan masuk semua peran. */
class LogLoginController extends Controller
{
    public function index(Request $r)
    {
        $items = $this->kueri($r)->latest('id')->paginate(30)->withQueryString();

        $stat = [
            'total' => LoginAttempt::count(),
            'sukses' => LoginAttempt::where('success', true)->count(),
            'gagal' => LoginAttempt::where('success', false)->count(),
            'hari_ini' => LoginAttempt::whereDate('created_at', today())->count(),
            // Sesi siswa yang berhasil hari ini namun tidak lewat peramban
            // ujian — angka inilah yang dicari pengawas.
            'siswa_luar_exambro' => LoginAttempt::where('guard', 'siswa')
                ->where('success', true)
                ->whereDate('created_at', today())
                ->exambro(false)
                ->count(),
            // Dihitung per siswa, bukan per kejadian: pengawas perlu tahu
            // berapa anak yang akunnya dipakai bersamaan, bukan berapa kali
            // mereka saling menggeser.
            'siswa_login_ganda' => LoginAttempt::where('guard', 'siswa')
                ->where('login_ganda', true)
                ->whereDate('created_at', today())
                ->distinct()
                ->count('username'),
        ];

        // Nama siswa untuk baris di halaman ini, sekali kueri. Tanpa nama,
        // pengawas harus mencocokkan NISN satu per satu.
        $namaSiswa = Siswa::whereIn('nisn', $items->where('guard', 'siswa')->pluck('username')->unique())
            ->pluck('nama_siswa', 'nisn');

        return view('log-login.index', compact('items', 'stat', 'namaSiswa'));
    }

    public function export(Request $r)
    {
        $rows = $this->kueri($r)->latest('id')->limit(10000)->get()
            ->values()
            ->map(fn (LoginAttempt $l, $i) => [
                $i + 1,
                $l->created_at->format('d/m/Y H:i:s'),
                $l->username,
                $l->guard_label,
                $l->success ? 'Berhasil' : 'Gagal',
                $l->ip_address,
                $l->device_type,
                $l->browser,
                $l->os,
                $l->lewat_exambro ? 'Ya' : 'Tidak',
                $l->login_ganda ? 'Ya' : 'Tidak',
                $l->login_ganda ? ($l->login_ganda_ip ?: '-') : '',
                $l->attempt_no,
            ]);

        return ExcelExport::make('Log Login')
            ->judul('LOG PERCOBAAN LOGIN', 'Dicetak: '.now()->format('d/m/Y H:i'))
            ->header(['No', 'Waktu', 'Username', 'Peran', 'Hasil', 'IP', 'Perangkat', 'Browser', 'Sistem Operasi',
                'Lewat ExamBro', 'Login Ganda', 'Menggeser Sesi di IP', 'Percobaan ke'])
            ->rows($rows)
            ->unduh('log-login-'.now()->format('Ymd-His').'.xlsx');
    }

    /** Bersihkan log lama agar tabel tidak menggelembung. */
    public function bersihkan(Request $r)
    {
        $r->validate(['sebelum' => 'required|date']);

        $jumlah = LoginAttempt::whereDate('created_at', '<', $r->sebelum)->delete();

        return back()->with('success', "{$jumlah} baris log sebelum ".date('d/m/Y', strtotime($r->sebelum)).' dihapus.');
    }

    protected function kueri(Request $r)
    {
        return LoginAttempt::query()
            ->when($r->q, fn ($q, $v) => $q->where('username', 'like', "%{$v}%"))
            ->when($r->guard, fn ($q, $v) => $q->where('guard', $v))
            ->when($r->status, fn ($q, $v) => $q->where('success', $v === 'sukses'))
            ->when($r->device, fn ($q, $v) => $q->where('device_type', $v))
            ->when($r->exambro, fn ($q, $v) => $q->exambro($v === 'ya'))
            ->when($r->ganda === 'ya', fn ($q) => $q->where('login_ganda', true))
            ->when($r->dari, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($r->sampai, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
