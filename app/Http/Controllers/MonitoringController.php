<?php

namespace App\Http\Controllers;

use App\Models\MataPelajaran;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Models\UjianLog;
use App\Models\UjianPeserta;
use App\Services\PengawasanUjianService;
use App\Services\PengerjaanUjianService;
use App\Support\ExcelExport;
use App\Support\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Menu "Monitoring Ujian" — pengawasan pelaksanaan secara langsung:
 * siapa yang sudah masuk, sedang mengerjakan, berapa butir yang sudah
 * diisi, dan sisa waktunya. Halaman detail menyegarkan datanya lewat
 * endpoint JSON agar tabel tetap hidup tanpa memuat ulang seluruh halaman.
 */
class MonitoringController extends Controller
{
    public function __construct(
        protected PengerjaanUjianService $pengerjaan,
        protected PengawasanUjianService $pengawasan,
    ) {}

    public function index(Request $r)
    {
        $guruId = Pengguna::guruId();
        $mapelId = $r->integer('mata_pelajaran_id') ?: null;
        $rombelId = $r->integer('rombongan_belajar_id') ?: null;

        // Setiap hitungan peserta ikut menyempit bila satu kelas dipilih: yang
        // ingin dilihat pengawas kelas itu adalah anak-anaknya sendiri, bukan
        // jumlah seluruh angkatan.
        $diKelas = fn ($q) => $q->when($rombelId, fn ($q, $v) => $q->where('rombongan_belajar_id', $v));

        $items = $this->ujianTerlihat($guruId)
            ->with(['mataPelajaran', 'paketSoal'])
            ->withCount([
                'peserta' => $diKelas,
                'peserta as sedang_count' => fn ($q) => $diKelas($q->where('status', UjianPeserta::MULAI)),
                'peserta as selesai_count' => fn ($q) => $diKelas($q->where('status', UjianPeserta::SELESAI)),
                'peserta as terkunci_count' => fn ($q) => $diKelas($q->whereNotNull('dikunci_at')),
            ])
            ->addSelect(['melanggar_count' => $this->kueriSiswaMelanggar($rombelId)])
            ->when($r->q, fn ($q, $v) => $q->where('nama_ujian', 'like', "%{$v}%"))
            ->when($mapelId, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
            ->when($rombelId, fn ($q, $v) => $q->whereHas('kelas', fn ($k) => $k->where('rombongan_belajar_id', $v)))
            // Ujian yang sedang berlangsung diletakkan paling atas.
            ->when(! $r->filled('status'), fn ($q) => $q->whereIn('status', [Ujian::AKTIF, Ujian::DRAFT]))
            ->when($r->status, fn ($q, $v) => $q->where('status', $v))
            ->orderByRaw("FIELD(status, 'aktif', 'draft', 'selesai')")
            ->orderByDesc('waktu_mulai')
            ->paginate(15)
            ->withQueryString();

        // Pilihan penyaring hanya berisi yang memang punya ujian, supaya tidak
        // ada pilihan yang berujung pada daftar kosong.
        //
        // Daftar ID-nya diambil lebih dulu, bukan disisipkan sebagai subkueri:
        // mata pelajaran dan rombel tinggal di database Data Center, ujian di
        // database ujian. Subkueri lintas koneksi akan dijalankan di database
        // yang salah.
        $idMapel = $this->ujianTerlihat($guruId)->whereNotNull('mata_pelajaran_id')
            ->distinct()->pluck('mata_pelajaran_id');

        $idRombel = UjianKelas::whereIn('ujian_id', $this->ujianTerlihat($guruId)->select('id'))
            ->distinct()->pluck('rombongan_belajar_id');

        $pilihanMapel = MataPelajaran::whereIn('id', $idMapel)
            ->orderBy('nama_mapel')->get(['id', 'nama_mapel']);

        $pilihanRombel = RombonganBelajar::whereIn('id', $idRombel)
            ->orderBy('tingkat')->orderBy('nama_rombel')->get(['id', 'nama_rombel']);

        return view('monitoring.index', [
            'items' => $items,
            'pilihanMapel' => $pilihanMapel,
            'pilihanRombel' => $pilihanRombel,
            'rombelTerpilih' => $rombelId ? $pilihanRombel->firstWhere('id', $rombelId) : null,
        ]);
    }

    /** Ujian yang boleh dipantau pengguna ini: guru hanya ujian miliknya. */
    protected function ujianTerlihat(?int $guruId)
    {
        return Ujian::query()->when($guruId, fn ($q, $g) => $q->where('guru_id', $g));
    }

    /**
     * Banyaknya siswa yang pernah melanggar pada sebuah ujian.
     *
     * Dihitung dari jejak pelanggaran, bukan dari penghitung pelanggaran
     * peserta: penghitung itu kembali ke nol setiap kali pengawas membuka
     * kunci atau mereset, sehingga siswa yang sudah melanggar akan tampak
     * bersih. Yang dihitung hanya pelanggaran yang memang dihitung sistem —
     * percobaan menyalin tidak, dan kejadian "dikunci" bukan perbuatan siswa.
     * Satu siswa dihitung satu, berapa kali pun ia melanggar.
     */
    protected function kueriSiswaMelanggar(?int $rombelId)
    {
        return UjianLog::query()
            ->selectRaw('count(distinct ujian_peserta_id)')
            ->whereColumn('ujian_log.ujian_id', 'ujian.id')
            ->whereIn('event', PengawasanUjianService::jenisMelanggar())
            ->when($rombelId, fn ($q, $v) => $q->whereIn('ujian_peserta_id',
                UjianPeserta::select('id')->where('rombongan_belajar_id', $v)
            ));
    }

    public function show(Request $r, Ujian $ujian)
    {
        // Tutup dulu peserta yang waktunya sudah habis supaya angka di layar
        // pengawas mencerminkan keadaan sebenarnya.
        $this->pengerjaan->tutupYangKedaluwarsa($ujian);

        $semua = $this->dataPeserta($ujian, $r->get('rombongan_belajar_id'));
        $status = $this->statusSaring($r);

        return view('monitoring.show', [
            'ujian' => $ujian->load(['paketSoal', 'mataPelajaran']),
            // Angka di kartu dan peringatan di atas selalu dari seluruh
            // peserta; hanya tabelnya yang ikut disaring. Kalau tidak,
            // memilih "Selesai" membuat kartu lain menjadi nol dan peringatan
            // lembar terkunci ikut menghilang.
            'semuaPeserta' => $semua,
            'peserta' => $this->saring($semua, $status),
            'ringkasan' => $this->ringkasan($semua),
            'statusTerpilih' => $status,
            'jumlahButir' => $ujian->paketSoal->detail()->count(),
        ]);
    }

    /**
     * Halaman jejak aktivitas satu ujian.
     *
     * Dipisahkan dari layar monitoring karena keduanya dipakai pada saat yang
     * berbeda: layar monitoring dipantau selama ujian berlangsung dan hanya
     * perlu keadaan terkini, sedangkan jejak aktivitas dibuka saat menelusuri
     * kejadian — sehingga butuh seluruh catatan, penyaring, dan penomoran
     * halaman, bukan sekadar 50 baris terakhir.
     */
    public function jejak(Request $r, Ujian $ujian)
    {
        $items = UjianLog::where('ujian_id', $ujian->id)
            ->with(['peserta.siswa', 'peserta.rombel'])
            ->when($r->event, fn ($q, $v) => $v === 'pelanggaran'
                ? $q->pelanggaran()
                : $q->where('event', $v))
            ->when($r->rombongan_belajar_id, fn ($q, $v) => $q->whereHas('peserta',
                fn ($p) => $p->where('rombongan_belajar_id', $v)))
            // Nama siswa ada di database datacenter, jadi tidak bisa ikut
            // dalam satu kueri JOIN; id-nya dicari lebih dulu di sana.
            ->when($r->q, fn ($q, $v) => $q->whereHas('peserta',
                fn ($p) => $p->whereIn('siswa_id', Siswa::where('nama_siswa', 'like', "%{$v}%")
                    ->orWhere('nisn', 'like', "%{$v}%")->pluck('id'))))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $dasar = UjianLog::where('ujian_id', $ujian->id);

        return view('monitoring.jejak', [
            'ujian' => $ujian->load(['paketSoal', 'mataPelajaran']),
            'items' => $items,
            'stat' => [
                'total' => (clone $dasar)->count(),
                'pelanggaran' => (clone $dasar)->pelanggaran()->count(),
                'sesi_ganda' => (clone $dasar)->where('event', 'sesi_ganda')->count(),
                'ditolak' => (clone $dasar)->whereIn('event', ['token_salah', 'tolak_non_exambro'])->count(),
            ],
        ]);
    }

    /** Endpoint penyegar tabel monitoring (dipanggil berkala oleh halaman detail). */
    public function data(Request $r, Ujian $ujian)
    {
        $this->pengerjaan->tutupYangKedaluwarsa($ujian);

        $semua = $this->dataPeserta($ujian, $r->get('rombongan_belajar_id'));
        $peserta = $this->saring($semua, $this->statusSaring($r));

        return response()->json([
            'diperbarui' => now()->format('H:i:s'),
            'ringkasan' => $this->ringkasan($semua),
            'peserta' => $peserta->map(fn ($p) => [
                'id' => $p->id,
                'nama' => $p->siswa->nama_siswa ?? '-',
                'nisn' => $p->siswa->nisn ?? '-',
                'kelas' => $p->rombel->nama_rombel ?? '-',
                'status' => $p->status,
                'status_label' => $p->status_label,
                'terjawab' => $p->terjawab,
                'sisa_waktu' => $p->status === UjianPeserta::MULAI ? $this->formatDurasi($p->sisaWaktuDetik()) : '-',
                'nilai' => $p->status === UjianPeserta::SELESAI ? (float) $p->nilai : null,
                'ip' => $p->ip_address,
                'browser' => $p->browser,
                'reset_count' => $p->reset_count,
                'pelanggaran' => (int) $p->pelanggaran,
                'terkunci' => (bool) $p->dikunci_at,
                'login_ganda' => $p->login_ganda,
                'login_ganda_ket' => $p->login_ganda_ket,
                'melanggar' => $p->melanggar,
                'rincian' => $p->rincian_pelanggaran,
            ])->values(),
        ]);
    }

    /** Reset pengerjaan seorang peserta (jawaban dihapus, sesi dibuka lagi). */
    public function reset(Request $r, Ujian $ujian, UjianPeserta $peserta)
    {
        abort_unless($peserta->ujian_id === $ujian->id, 404);

        $r->validate(['alasan' => 'nullable|string|max:255']);

        $this->pengerjaan->reset($peserta, $r->input('alasan'));

        return back()->with('success', 'Pengerjaan peserta direset. Siswa dapat memulai ujian dari awal.');
    }

    /**
     * Buka lembar yang dikunci sistem karena pelanggaran berulang.
     *
     * Sengaja hanya ada di sini, bukan di layar siswa: kunci yang bisa dibuka
     * sendiri oleh yang melanggar bukan kunci. Pengawas ruang yang menilai
     * apakah pelanggarannya disengaja atau sekadar salah tekan.
     */
    public function bukaKunci(Request $r, Ujian $ujian, UjianPeserta $peserta)
    {
        abort_unless($peserta->ujian_id === $ujian->id, 404);

        if (! $peserta->dikunci_at) {
            return back()->with('error', 'Lembar peserta ini sedang tidak terkunci.');
        }

        $r->validate(['alasan' => 'nullable|string|max:255']);

        $this->pengawasan->bukaKunci($peserta, $r->input('alasan'));

        return back()->with('success', 'Kunci dibuka. Peserta dapat melanjutkan ujian.');
    }

    /**
     * Izinkan seluruh peserta yang lembarnya terkunci melanjutkan ujian.
     *
     * Hanya yang terkunci yang disentuh: peserta yang pernah melanggar tetapi
     * belum mencapai batas memang sudah boleh melanjutkan, dan hitungannya
     * tidak dinolkan — mengampuni pelanggaran yang belum mengunci apa pun
     * bukan maksud tombol ini.
     *
     * Bila halaman sedang disaring per kelas, izinnya hanya untuk kelas itu,
     * sesuai angka yang sedang dilihat pengawas.
     */
    public function bukaKunciSemua(Request $r, Ujian $ujian)
    {
        $r->validate(['rombongan_belajar_id' => 'nullable|integer']);

        $terkunci = $ujian->peserta()
            ->whereNotNull('dikunci_at')
            ->when($r->integer('rombongan_belajar_id') ?: null,
                fn ($q, $v) => $q->where('rombongan_belajar_id', $v))
            ->get();

        if ($terkunci->isEmpty()) {
            return back()->with('error', 'Tidak ada lembar jawaban yang sedang terkunci.');
        }

        foreach ($terkunci as $p) {
            $this->pengawasan->bukaKunci($p, 'Diizinkan serentak bersama '.$terkunci->count().' peserta.');
        }

        return back()->with('success', $terkunci->count().' peserta yang terkunci diizinkan melanjutkan ujian. '
            .'Hitungan pelanggaran mereka dinolkan; rincian pelanggarannya tetap tercatat.');
    }

    /** Kumpulkan paksa lembar jawaban seorang peserta. */
    public function selesaikan(Ujian $ujian, UjianPeserta $peserta)
    {
        abort_unless($peserta->ujian_id === $ujian->id, 404);

        if ($peserta->status !== UjianPeserta::MULAI) {
            return back()->with('error', 'Peserta ini sedang tidak dalam status mengerjakan.');
        }

        $this->pengerjaan->selesaikan($peserta, 'selesai');

        return back()->with('success', 'Lembar jawaban peserta dikumpulkan dan sudah dikoreksi.');
    }

    /** Kumpulkan paksa seluruh peserta yang masih mengerjakan. */
    public function selesaikanSemua(Ujian $ujian)
    {
        $jumlah = 0;

        $ujian->peserta()->where('status', UjianPeserta::MULAI)->get()
            ->each(function (UjianPeserta $p) use (&$jumlah) {
                $this->pengerjaan->selesaikan($p, 'selesai');
                $jumlah++;
            });

        return back()->with('success', "{$jumlah} lembar jawaban dikumpulkan paksa dan dikoreksi.");
    }

    public function export(Request $r, Ujian $ujian)
    {
        // Yang diekspor adalah yang sedang tampil: kelas dan status yang
        // dipilih ikut terbawa lewat tautan tombol Export.
        $status = $this->statusSaring($r);
        $peserta = $this->saring($this->dataPeserta($ujian, $r->get('rombongan_belajar_id')), $status);

        $rows = $peserta->values()->map(fn ($p, $i) => [
            $i + 1,
            $p->siswa->nisn ?? '-',
            $p->siswa->nama_siswa ?? '-',
            $p->rombel->nama_rombel ?? '-',
            $p->status_label,
            $p->waktu_mulai?->format('d/m/Y H:i') ?? '-',
            $p->waktu_selesai?->format('d/m/Y H:i') ?? '-',
            $p->terjawab,
            $p->status === UjianPeserta::SELESAI ? (float) $p->nilai : '-',
            $p->ip_address ?: '-',
            $p->browser ?: '-',
            $p->reset_count,
            (int) $p->pelanggaran,
            $p->dikunci_at ? 'Terkunci' : '-',
            $p->login_ganda,
            collect($p->rincian_pelanggaran)->map(fn ($x) => $x['label'].' '.$x['jumlah'].'x')->implode('; ') ?: '-',
        ]);

        return ExcelExport::make('Monitoring')
            ->judul(
                'MONITORING PELAKSANAAN UJIAN',
                $ujian->nama_ujian.' ('.$ujian->kode_ujian.')',
                'Diambil: '.now()->format('d/m/Y H:i:s')
                    .($status ? ' · Hanya: '.self::SARING_STATUS[$status] : '')
            )
            ->header(['No', 'NISN', 'Nama Siswa', 'Kelas', 'Status', 'Mulai', 'Selesai',
                'Butir Terjawab', 'Nilai', 'IP', 'Browser', 'Jml Reset',
                'Jml Pelanggaran', 'Kunci', 'Login Ganda (kali)', 'Rincian Pelanggaran'])
            ->rows($rows)
            ->unduh('monitoring-'.Str::slug($ujian->kode_ujian).'.xlsx');
    }

    /**
     * Peserta beserta jumlah butir yang sudah terisi. Perhitungan terjawab
     * dilakukan di PHP karena "terisi" punya aturan khusus untuk essay.
     *
     * @return Collection<int, UjianPeserta>
     */
    protected function dataPeserta(Ujian $ujian, ?string $rombelId)
    {
        // Login ganda selama ujian ini, dikumpulkan per peserta dalam satu
        // kueri. Hanya yang terjadi saat peserta sedang mengerjakan yang
        // tercatat di sini — itulah yang relevan bagi pengawas ruang.
        $ganda = UjianLog::where('ujian_id', $ujian->id)
            ->where('event', 'sesi_ganda')
            ->orderBy('id')
            ->get(['ujian_peserta_id', 'keterangan', 'created_at'])
            ->groupBy('ujian_peserta_id');

        // Rincian pelanggaran per peserta, satu kueri. Diambil dari jejak,
        // bukan dari penghitung pelanggaran peserta: penghitung itu kembali ke
        // nol saat pengawas membuka kunci atau mereset.
        $langgar = UjianLog::where('ujian_id', $ujian->id)
            ->whereIn('event', PengawasanUjianService::jenisMelanggar())
            ->selectRaw('ujian_peserta_id, event, count(*) as jumlah')
            ->groupBy('ujian_peserta_id', 'event')
            ->get()
            ->groupBy('ujian_peserta_id');

        return $ujian->peserta()
            ->with(['siswa', 'rombel', 'jawaban'])
            ->when($rombelId, fn ($q, $v) => $q->where('rombongan_belajar_id', $v))
            ->get()
            ->each(function (UjianPeserta $p) use ($ganda, $langgar) {
                $jejak = $langgar->get($p->id, collect());

                $p->melanggar = $jejak->isNotEmpty();
                $p->rincian_pelanggaran = $jejak->sortByDesc('jumlah')
                    ->map(fn ($b) => [
                        'label' => UjianLog::LABEL_SINGKAT[$b->event] ?? $b->event,
                        'jumlah' => (int) $b->jumlah,
                    ])
                    ->values()
                    ->all();

                $p->terjawab = $p->jawaban->filter(fn ($j) => $j->terisi)->count();

                $kejadian = $ganda->get($p->id, collect());
                $terakhir = $kejadian->last();

                $p->login_ganda = $kejadian->count();
                $p->login_ganda_ket = $terakhir
                    ? 'Terakhir '.$terakhir->created_at->format('H:i').' — '.$terakhir->keterangan
                    : null;
            })
            ->sortBy([
                fn ($a, $b) => strcmp($a->rombel->nama_rombel ?? '', $b->rombel->nama_rombel ?? ''),
                fn ($a, $b) => strcmp($a->siswa->nama_siswa ?? '', $b->siswa->nama_siswa ?? ''),
            ])
            ->values();
    }

    /** @var array<string, string> Saringan status pada tabel peserta. */
    public const SARING_STATUS = [
        'belum' => 'Belum mulai',
        'sedang' => 'Sedang mengerjakan',
        'selesai' => 'Selesai',
        'melanggar' => 'Melanggar',
    ];

    protected function statusSaring(Request $r): ?string
    {
        $status = $r->get('status');

        return is_string($status) && array_key_exists($status, self::SARING_STATUS) ? $status : null;
    }

    /**
     * @param  Collection<int, UjianPeserta>  $peserta
     * @return Collection<int, UjianPeserta>
     */
    protected function saring(Collection $peserta, ?string $status): Collection
    {
        return (match ($status) {
            'belum' => $peserta->where('status', UjianPeserta::TERDAFTAR),
            'sedang' => $peserta->where('status', UjianPeserta::MULAI),
            'selesai' => $peserta->where('status', UjianPeserta::SELESAI),
            'melanggar' => $peserta->filter(fn ($p) => $p->melanggar),
            default => $peserta,
        })->values();
    }

    /**
     * @param  Collection<int, UjianPeserta>  $peserta
     * @return array<string, int>
     */
    protected function ringkasan(Collection $peserta): array
    {
        return [
            'terdaftar' => $peserta->count(),
            'belum' => $peserta->where('status', UjianPeserta::TERDAFTAR)->count(),
            'sedang' => $peserta->where('status', UjianPeserta::MULAI)->count(),
            'selesai' => $peserta->where('status', UjianPeserta::SELESAI)->count(),
            'melanggar' => $peserta->filter(fn ($p) => $p->melanggar)->count(),
            'terkunci' => $peserta->filter(fn ($p) => (bool) $p->dikunci_at)->count(),
            'login_ganda' => $peserta->filter(fn ($p) => $p->login_ganda > 0)->count(),
        ];
    }

    protected function formatDurasi(int $detik): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($detik, 3600), intdiv($detik % 3600, 60), $detik % 60);
    }
}
