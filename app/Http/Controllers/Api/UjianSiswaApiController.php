<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ujian;
use App\Models\UjianLog;
use App\Models\UjianPeserta;
use App\Services\PengawasanUjianService;
use App\Services\PengerjaanUjianService;
use App\Support\ApiSiswa;
use App\Support\Pengguna;
use Illuminate\Http\Request;

/**
 * Ruang ujian peserta versi aplikasi ponsel.
 *
 * Kembaran dari Siswa\UjianSiswaController, dan sengaja tetap setipis itu:
 * seluruh aturan pengerjaan — boleh mulai atau tidak, pengacakan, koreksi,
 * penguncian karena pelanggaran — tinggal di service yang sama. Yang berbeda
 * hanya pembungkusnya: di sini JSON, di sana halaman Blade. Dengan begitu
 * siswa yang memakai aplikasi dan yang memakai peramban tidak pernah
 * tunduk pada dua aturan yang berbeda.
 */
class UjianSiswaApiController extends Controller
{
    public function __construct(
        protected PengerjaanUjianService $pengerjaan,
        protected PengawasanUjianService $pengawasan,
    ) {}

    /** Daftar ujian milik peserta: yang sedang berlangsung dan selebihnya. */
    public function index()
    {
        $peserta = UjianPeserta::with(['ujian.mataPelajaran'])
            ->where('siswa_id', Pengguna::siswaId())
            ->whereHas('ujian', fn ($q) => $q->whereIn('status', [Ujian::AKTIF, Ujian::SELESAI]))
            ->get()
            ->sortByDesc(fn ($p) => $p->ujian->waktu_mulai)
            ->values();

        $berlangsung = $peserta->filter(fn ($p) => $p->ujian->sedang_berlangsung
            && $p->status !== UjianPeserta::SELESAI);

        return response()->json([
            'siswa' => ApiSiswa::siswa(Pengguna::siswa()),
            'berlangsung' => $berlangsung->map(fn ($p) => ApiSiswa::ujianRingkas($p))->values(),
            'lainnya' => $peserta->reject(fn ($p) => $berlangsung->contains($p))
                ->map(fn ($p) => ApiSiswa::ujianRingkas($p))->values(),
        ]);
    }

    /** Rincian sebelum masuk lembar: jumlah butir, perlu token atau tidak. */
    public function show(UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        $peserta->load('ujian.paketSoal', 'ujian.mataPelajaran');

        return response()->json(ApiSiswa::ujianRingkas($peserta) + [
            'jumlah_butir' => $peserta->ujian->paketSoal->detail()->count(),
            'pengawasan' => $this->pengawasan->keadaan($peserta),
        ]);
    }

    public function mulai(Request $request, UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        if ($peserta->status === UjianPeserta::SELESAI) {
            return response()->json(['pesan' => 'Anda sudah menyelesaikan ujian ini.'], 409);
        }

        try {
            $this->pengerjaan->mulai($peserta, $request->input('token'));
        } catch (\RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 422);
        }

        return response()->json(ApiSiswa::ujianRingkas($peserta->refresh()));
    }

    /**
     * Lembar pengerjaan: seluruh butir sekaligus.
     *
     * Dikirim sekali jalan, bukan sebutir demi sebutir. Jaringan sekolah
     * kerap putus-nyambung di tengah ujian, dan lembar yang sudah berada di
     * tangan aplikasi tetap bisa dikerjakan sementara pengiriman jawabannya
     * menyusul.
     */
    public function lembar(UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        if ($peserta->status === UjianPeserta::SELESAI) {
            return response()->json(['pesan' => 'Ujian sudah dikumpulkan.', 'selesai' => true], 409);
        }

        if ($peserta->status !== UjianPeserta::MULAI) {
            return response()->json(['pesan' => 'Ujian belum dimulai.'], 409);
        }

        // Waktu habis sementara aplikasi tertutup — kumpulkan otomatis,
        // persis seperti yang dilakukan lembar ujian di peramban.
        if ($peserta->sisaWaktuDetik() <= 0) {
            $this->pengerjaan->selesaikan($peserta, 'auto_selesai');

            return response()->json([
                'pesan' => 'Waktu ujian sudah habis, lembar jawaban Anda dikumpulkan otomatis.',
                'selesai' => true,
            ], 409);
        }

        $jawaban = $peserta->jawaban()->with('soal')->get();

        return response()->json([
            'peserta' => ApiSiswa::ujianRingkas($peserta),
            'sisa_detik' => $peserta->sisaWaktuDetik(),
            'pengawasan' => $this->pengawasan->keadaan($peserta),
            'butir' => $jawaban->map(fn ($j) => ApiSiswa::butir($j))->values(),
        ]);
    }

    /** Simpan jawaban satu butir. */
    public function jawaban(Request $request, UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        $request->validate([
            'soal_id' => 'required|integer',
            'ragu' => 'nullable|boolean',
        ]);

        // Lembar yang terkunci tetap menerima hitung mundur, tetapi tidak
        // menerima jawaban baru.
        if ($peserta->dikunci_at) {
            return response()->json([
                'ok' => false,
                'terkunci' => true,
                'pesan' => 'Lembar jawaban Anda dikunci. Panggil pengawas ruang.',
                'sisa_detik' => $peserta->sisaWaktuDetik(),
            ], 423);
        }

        $tersimpan = $this->pengerjaan->simpanJawaban(
            $peserta,
            (int) $request->input('soal_id'),
            $request->input('jawaban'),
            $request->boolean('ragu')
        );

        if (! $tersimpan) {
            return response()->json([
                'ok' => false,
                'pesan' => 'Sesi ujian sudah berakhir.',
                'sisa_detik' => 0,
            ], 409);
        }

        $terisi = $peserta->jawaban()->with('soal')->get()->filter(fn ($j) => $j->terisi);

        return response()->json([
            'ok' => true,
            'sisa_detik' => $peserta->sisaWaktuDetik(),
            'terjawab' => $terisi->count(),
            'soal_terjawab' => $terisi->pluck('soal_id')->values(),
        ]);
    }

    /**
     * Denyut dari aplikasi: satu permintaan yang menjawab tiga pertanyaan
     * sekaligus — berapa sisa waktunya, apakah lembarnya terkunci, dan apakah
     * pengawas sudah menutup sesinya dari jauh.
     */
    public function denyut(UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        return response()->json([
            'status' => $peserta->status,
            'sisa_detik' => $peserta->sisaWaktuDetik(),
            'pengawasan' => $this->pengawasan->keadaan($peserta),
        ]);
    }

    /** Catat peserta meninggalkan layar ujian. */
    public function keluar(Request $request, UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        if ($peserta->status === UjianPeserta::MULAI) {
            UjianLog::catat($peserta->ujian_id, $peserta->id, 'keluar_halaman',
                $request->input('keterangan'));
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Terima laporan pelanggaran dari aplikasi.
     *
     * Pengenalannya terjadi di perangkat, tetapi hitungan dan penguncian
     * dikerjakan di sini — angka yang dikirim aplikasi tidak pernah dipercaya
     * sebagai jumlah, hanya sebagai kabar bahwa satu kejadian baru terjadi.
     */
    public function pelanggaran(Request $request, UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        $request->validate([
            'jenis' => 'required|string|in:'.implode(',', UjianLog::PELANGGARAN),
            'keterangan' => 'nullable|string|max:255',
        ]);

        // 'dikunci' hanya boleh lahir dari service, bukan dari kiriman klien.
        abort_if($request->input('jenis') === 'dikunci', 422, 'Jenis pelanggaran tidak dikenal.');

        return response()->json(
            $this->pengawasan->catat($peserta, $request->input('jenis'), $request->input('keterangan'))
        );
    }

    public function pengawasan(UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        return response()->json($this->pengawasan->keadaan($peserta));
    }

    public function selesai(UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        $this->pengerjaan->selesaikan($peserta);

        return response()->json(ApiSiswa::ujianRingkas($peserta->refresh()));
    }

    public function hasil(UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        if ($peserta->status !== UjianPeserta::SELESAI) {
            return response()->json(['pesan' => 'Ujian ini belum dikumpulkan.'], 409);
        }

        $peserta->load('ujian.mataPelajaran', 'siswa');

        // Pembahasan hanya dibuka bila guru mengizinkan hasil ditampilkan dan
        // jendela waktu ujian sudah lewat, agar tidak bocor ke peserta lain.
        $bolehPembahasan = $peserta->ujian->tampilkan_hasil && $peserta->ujian->sudah_lewat;

        return response()->json(ApiSiswa::ujianRingkas($peserta) + [
            'skor_objektif' => (float) $peserta->skor_objektif,
            'skor_essay' => (float) $peserta->skor_essay,
            'jumlah_benar' => (int) $peserta->jumlah_benar,
            'jumlah_salah' => (int) $peserta->jumlah_salah,
            'jumlah_kosong' => (int) $peserta->jumlah_kosong,
            'essay_dinilai' => (bool) $peserta->essay_dinilai,
            'waktu_mulai' => $peserta->waktu_mulai?->toIso8601String(),
            'waktu_selesai' => $peserta->waktu_selesai?->toIso8601String(),
            'boleh_lihat_pembahasan' => $bolehPembahasan,
            'butir' => $peserta->jawaban()->with('soal')->get()
                ->map(fn ($j) => ApiSiswa::butirHasil($j, $bolehPembahasan))->values(),
        ]);
    }

    protected function pastikanMilikSiswa(UjianPeserta $peserta): void
    {
        abort_unless($peserta->siswa_id === Pengguna::siswaId(), 403, 'Ini bukan lembar ujian Anda.');
    }
}
