<?php

namespace App\Http\Controllers;

use App\Models\LoginAttempt;
use App\Models\PaketSoal;
use App\Models\Soal;
use App\Models\Topik;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Support\Pengguna;

class DashboardController extends Controller
{
    public function index()
    {
        $guruId = Pengguna::guruId();
        $milikSaya = fn ($q) => $q->when($guruId, fn ($w, $g) => $w->where('guru_id', $g));

        $ujian = Ujian::query()->tap($milikSaya);

        return view('dashboard', [
            'stat' => [
                'topik' => Topik::count(),
                'soal' => Soal::query()->tap($milikSaya)->count(),
                'paket' => PaketSoal::query()->tap($milikSaya)->count(),
                'ujian_aktif' => (clone $ujian)->where('status', Ujian::AKTIF)->count(),
            ],
            'soalPerJenis' => collect(Soal::JENIS)
                ->map(fn ($label, $jenis) => Soal::query()->tap($milikSaya)->where('jenis', $jenis)->count())
                ->all(),
            'ujianTerdekat' => (clone $ujian)
                ->with('mataPelajaran')
                ->whereIn('status', [Ujian::DRAFT, Ujian::AKTIF])
                ->where('waktu_selesai', '>=', now())
                ->withCount('peserta')
                ->orderBy('waktu_mulai')
                ->limit(5)
                ->get(),
            'sedangBerlangsung' => (clone $ujian)
                ->with('mataPelajaran')
                ->where('status', Ujian::AKTIF)
                ->withCount([
                    'peserta',
                    'peserta as sedang_count' => fn ($q) => $q->where('status', UjianPeserta::MULAI),
                    'peserta as selesai_count' => fn ($q) => $q->where('status', UjianPeserta::SELESAI),
                ])
                ->where('waktu_mulai', '<=', now())
                ->where('waktu_selesai', '>=', now())
                ->get(),
            'loginTerakhir' => Pengguna::isAdmin()
                ? LoginAttempt::latest('id')->limit(8)->get()
                : collect(),
        ]);
    }
}
