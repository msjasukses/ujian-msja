<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianLog;
use App\Models\UjianPeserta;
use App\Services\PengawasanUjianService;
use App\Services\PengerjaanUjianService;
use App\Support\Pengguna;
use Illuminate\Http\Request;

/** Ruang ujian peserta: daftar ujian, lembar pengerjaan, dan hasilnya. */
class UjianSiswaController extends Controller
{
    public function __construct(
        protected PengerjaanUjianService $pengerjaan,
        protected PengawasanUjianService $pengawasan,
    ) {}

    public function index()
    {
        $peserta = UjianPeserta::with(['ujian.mataPelajaran'])
            ->where('siswa_id', Pengguna::siswaId())
            ->whereHas('ujian', fn ($q) => $q->whereIn('status', [Ujian::AKTIF, Ujian::SELESAI]))
            ->get()
            ->sortByDesc(fn ($p) => $p->ujian->waktu_mulai)
            ->values();

        return view('siswa.index', [
            'siswa' => Pengguna::siswa(),
            'berlangsung' => $peserta->filter(fn ($p) => $p->ujian->sedang_berlangsung
                && $p->status !== UjianPeserta::SELESAI),
            'lainnya' => $peserta->reject(fn ($p) => $p->ujian->sedang_berlangsung
                && $p->status !== UjianPeserta::SELESAI),
        ]);
    }

    /** Halaman konfirmasi + isian token sebelum masuk lembar ujian. */
    public function konfirmasi(UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        if ($peserta->status === UjianPeserta::SELESAI) {
            return redirect()->route('siswa.ujian.hasil', $peserta);
        }

        return view('siswa.konfirmasi', [
            'peserta' => $peserta->load('ujian.paketSoal', 'ujian.mataPelajaran'),
            'jumlahButir' => $peserta->ujian->paketSoal->detail()->count(),
        ]);
    }

    public function mulai(Request $r, UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        try {
            $this->pengerjaan->mulai($peserta, $r->input('token'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('siswa.ujian.kerjakan', $peserta);
    }

    /** Lembar pengerjaan. */
    public function kerjakan(UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        if ($peserta->status === UjianPeserta::SELESAI) {
            return redirect()->route('siswa.ujian.hasil', $peserta);
        }

        if ($peserta->status !== UjianPeserta::MULAI) {
            return redirect()->route('siswa.ujian.konfirmasi', $peserta);
        }

        // Waktu habis sementara halaman ditutup — kumpulkan otomatis.
        if ($peserta->sisaWaktuDetik() <= 0) {
            $this->pengerjaan->selesaikan($peserta, 'auto_selesai');

            return redirect()->route('siswa.ujian.hasil', $peserta)
                ->with('error', 'Waktu ujian sudah habis, lembar jawaban Anda dikumpulkan otomatis.');
        }

        return view('siswa.kerjakan', [
            'peserta' => $peserta->load('ujian'),
            'jawaban' => $peserta->jawaban()->with('soal')->get(),
            'sisaDetik' => $peserta->sisaWaktuDetik(),
            'pengawasan' => $this->pengawasan->keadaan($peserta) + [
                'aktif' => (bool) $peserta->ujian->proteksi_ketat,
                'url' => route('siswa.ujian.pelanggaran', $peserta),
                'url_status' => route('siswa.ujian.pengawasan', $peserta),
                // Ditampilkan pada layar blokir supaya pengawas yang
                // dipanggil langsung tahu lembar siapa yang dihadapinya.
                'nama' => $peserta->siswa?->nama_siswa ?? Pengguna::nama(),
                'kelas' => $peserta->siswa?->rombelPada()?->nama_rombel,
            ],
        ]);
    }

    /** Simpan jawaban satu butir (dipanggil lewat fetch dari lembar ujian). */
    public function simpan(Request $r, UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        $r->validate([
            'soal_id' => 'required|integer',
            'ragu' => 'nullable|boolean',
        ]);

        // Lembar yang terkunci tetap menerima hitung mundur, tetapi tidak
        // menerima jawaban baru: kalau tidak, siswa masih bisa mengerjakan di
        // balik tirai peringatan dengan menonaktifkan JavaScript.
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
            (int) $r->input('soal_id'),
            $r->input('jawaban'),
            $r->boolean('ragu')
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

    /** Catat peserta berpindah tab / meninggalkan halaman ujian. */
    public function catatKeluar(Request $r, UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        if ($peserta->status === UjianPeserta::MULAI) {
            UjianLog::catat($peserta->ujian_id, $peserta->id, 'keluar_halaman', $r->input('keterangan'));
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Terima laporan pelanggaran dari lembar ujian.
     *
     * Pengenalannya memang terjadi di peramban, tetapi hitungan dan
     * penguncian dikerjakan di sini — nilai yang dikirim peramban tidak
     * pernah dipercaya sebagai jumlah, hanya sebagai kabar bahwa satu
     * kejadian baru saja terjadi.
     */
    public function pelanggaran(Request $r, UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        $r->validate([
            'jenis' => 'required|string|in:'.implode(',', UjianLog::PELANGGARAN),
            'keterangan' => 'nullable|string|max:255',
        ]);

        // 'dikunci' hanya boleh lahir dari service, bukan dari kiriman peramban.
        abort_if($r->input('jenis') === 'dikunci', 422, 'Jenis pelanggaran tidak dikenal.');

        return response()->json(
            $this->pengawasan->catat($peserta, $r->input('jenis'), $r->input('keterangan'))
        );
    }

    /**
     * Keadaan pengawasan terkini.
     *
     * Dibutuhkan saat peserta kembali dari aplikasi lain: pelanggarannya
     * dikirim lewat sendBeacon yang tidak mengembalikan apa pun, jadi
     * hitungan dan status kuncinya ditanyakan ulang begitu halaman aktif.
     */
    public function statusPengawasan(UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        return response()->json($this->pengawasan->keadaan($peserta));
    }

    public function selesai(UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        $this->pengerjaan->selesaikan($peserta);

        return redirect()->route('siswa.ujian.hasil', $peserta)
            ->with('success', 'Lembar jawaban Anda berhasil dikumpulkan.');
    }

    public function hasil(UjianPeserta $peserta)
    {
        $this->pastikanMilikSiswa($peserta);

        if ($peserta->status !== UjianPeserta::SELESAI) {
            return redirect()->route('siswa.ujian.index');
        }

        return view('siswa.hasil', [
            'peserta' => $peserta->load('ujian.mataPelajaran', 'siswa'),
            // Pembahasan hanya dibuka bila guru mengizinkan hasil ditampilkan
            // dan jendela waktu ujian sudah lewat, agar tidak bocor ke peserta lain.
            'bolehLihatPembahasan' => $peserta->ujian->tampilkan_hasil && $peserta->ujian->sudah_lewat,
            'jawaban' => $peserta->jawaban()->with('soal')->get(),
            'jenisEssay' => Soal::ESSAY,
        ]);
    }

    protected function pastikanMilikSiswa(UjianPeserta $peserta): void
    {
        abort_unless($peserta->siswa_id === Pengguna::siswaId(), 403, 'Ini bukan lembar ujian Anda.');
    }
}
