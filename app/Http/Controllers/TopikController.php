<?php

namespace App\Http\Controllers;

use App\Models\Topik;
use App\Services\SinkronCpTpAtpService;
use App\Support\ExcelExport;
use App\Support\Referensi;
use Illuminate\Http\Request;

class TopikController extends Controller
{
    public function index(Request $r)
    {
        $items = Topik::with(['mataPelajaran', 'tingkatKelas'])
            ->when($r->q, fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('nama_topik', 'like', "%{$v}%")
                    ->orWhere('kode_topik', 'like', "%{$v}%")
                    ->orWhere('elemen', 'like', "%{$v}%");
            }))
            ->when($r->mata_pelajaran_id, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
            ->when($r->tingkat_kelas_id, fn ($q, $v) => $q->where('tingkat_kelas_id', $v))
            ->when($r->sumber, fn ($q, $v) => $q->where('sumber', $v))
            ->when($r->semester, fn ($q, $v) => $q->where('semester', $v))
            ->withCount('soal')
            ->orderBy('mata_pelajaran_id')
            ->orderBy('urutan')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $stat = [
            'total' => Topik::count(),
            'manual' => Topik::where('sumber', Topik::SUMBER_MANUAL)->count(),
            'sinkron' => Topik::where('sumber', Topik::SUMBER_SINKRON)->count(),
            'tanpa_soal' => Topik::doesntHave('soal')->count(),
        ];

        return view('topik.index', compact('items', 'stat'));
    }

    public function create()
    {
        $item = new Topik([
            'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
            'is_aktif' => true,
        ]);

        return view('topik.form', compact('item'));
    }

    public function store(Request $r)
    {
        Topik::create($this->validasi($r) + ['sumber' => Topik::SUMBER_MANUAL]);

        return redirect()->route('topik.index')->with('success', 'Topik berhasil ditambahkan.');
    }

    public function edit(Topik $topik)
    {
        return view('topik.form', ['item' => $topik]);
    }

    public function update(Request $r, Topik $topik)
    {
        $topik->update($this->validasi($r));

        return redirect()->route('topik.index')->with('success', 'Topik berhasil diperbarui.');
    }

    public function destroy(Topik $topik)
    {
        if ($topik->soal()->exists()) {
            return back()->with('error', 'Topik tidak bisa dihapus karena masih dipakai '.$topik->soal()->count().' butir soal.');
        }

        $topik->delete();

        return back()->with('success', 'Topik dihapus.');
    }

    public function hapusMassal(Request $r)
    {
        return $this->hapusBanyak($r, Topik::query(), function (Topik $t) {
            $jumlahSoal = $t->soal()->count();

            return $jumlahSoal ? "Topik \"{$t->nama_topik}\" masih dipakai {$jumlahSoal} butir soal." : null;
        }, 'topik');
    }

    /** Halaman fitur Sinkron CP-TP-ATP: pratinjau dulu, baru tarik. */
    public function sinkron(Request $r, SinkronCpTpAtpService $service)
    {
        if (! $service->koneksiTersedia()) {
            return view('topik.sinkron', [
                'items' => collect(),
                'tersedia' => false,
                'tahunAjaranKurikulum' => [],
            ]);
        }

        $filter = $r->only(['mata_pelajaran_id', 'tingkat_kelas_id', 'tahun_ajaran', 'semester']);

        return view('topik.sinkron', [
            'items' => $service->pratinjau($filter),
            'tersedia' => true,
            'tahunAjaranKurikulum' => $service->daftarTahunAjaran(),
        ]);
    }

    /** Jalankan sinkron atas baris pemetaan yang dicentang (atau seluruh hasil filter). */
    public function sinkronJalankan(Request $r, SinkronCpTpAtpService $service)
    {
        $r->validate([
            'ids' => 'array',
            'ids.*' => 'integer',
        ]);

        if (! $service->koneksiTersedia()) {
            return back()->with('error', 'Database kurikulum tidak dapat dihubungi. Periksa pengaturan KURIKULUM_DB_* pada berkas .env.');
        }

        $filter = $r->only(['mata_pelajaran_id', 'tingkat_kelas_id', 'tahun_ajaran', 'semester']);
        $hasil = $service->jalankan($r->input('ids', []), $filter);

        if ($hasil['total'] === 0) {
            return back()->with('error', 'Tidak ada baris pemetaan CP-TP-ATP yang cocok untuk disinkronkan.');
        }

        $pesan = "Sinkron selesai: {$hasil['baru']} topik baru, {$hasil['diperbarui']} diperbarui";
        if ($hasil['dilewati'] > 0) {
            $pesan .= ", {$hasil['dilewati']} dilewati karena tidak punya tujuan pembelajaran maupun elemen";
        }

        return redirect()->route('topik.index')->with('success', $pesan.'.');
    }

    public function export(Request $r)
    {
        $items = Topik::with(['mataPelajaran', 'tingkatKelas'])
            ->when($r->mata_pelajaran_id, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
            ->when($r->tingkat_kelas_id, fn ($q, $v) => $q->where('tingkat_kelas_id', $v))
            ->withCount('soal')
            ->orderBy('mata_pelajaran_id')->orderBy('urutan')
            ->get();

        $rows = $items->values()->map(fn ($t, $i) => [
            $i + 1,
            $t->kode_topik,
            $t->nama_topik,
            $t->mataPelajaran->nama_mapel ?? '-',
            $t->tingkatKelas->nama ?? '-',
            $t->fase,
            $t->semester,
            $t->tahun_ajaran,
            strip_tags((string) $t->elemen),
            strip_tags((string) $t->tujuan_pembelajaran),
            $t->sumber === Topik::SUMBER_SINKRON ? 'Sinkron CP-TP-ATP' : 'Manual',
            $t->soal_count,
        ]);

        return ExcelExport::make('Topik')
            ->judul('DAFTAR TOPIK / MATERI UJIAN', 'Dicetak: '.now()->format('d/m/Y H:i'))
            ->header(['No', 'Kode', 'Nama Topik', 'Mata Pelajaran', 'Tingkat', 'Fase', 'Semester',
                'Tahun Ajaran', 'Elemen', 'Tujuan Pembelajaran', 'Sumber', 'Jumlah Soal'])
            ->rows($rows)
            ->unduh('daftar-topik-'.now()->format('Ymd-His').'.xlsx');
    }

    protected function validasi(Request $r): array
    {
        return $r->validate([
            'kode_topik' => 'nullable|string|max:40',
            'nama_topik' => 'required|string|max:255',
            'mata_pelajaran_id' => Referensi::aturanMapel($r->route('topik')?->mata_pelajaran_id),
            'tingkat_kelas_id' => Referensi::aturanTingkat($r->route('topik')?->tingkat_kelas_id),
            'fase' => 'nullable|string|max:10',
            'semester' => 'nullable|string|max:20',
            'tahun_ajaran' => Referensi::aturanTahunAjaran($r->route('topik')?->tahun_ajaran),
            'elemen' => 'nullable|string',
            'capaian_pembelajaran' => 'nullable|string',
            'tujuan_pembelajaran' => 'nullable|string',
            'alur_tujuan_pembelajaran' => 'nullable|string',
            'indikator_kktp' => 'nullable|string',
            'urutan' => 'nullable|integer|min:0|max:9999',
            'is_aktif' => 'nullable|boolean',
        ]) + ['is_aktif' => $r->boolean('is_aktif')];
    }
}
