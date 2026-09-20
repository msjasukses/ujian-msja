<?php

namespace App\Http\Controllers;

use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Soal;
use App\Models\Topik;
use App\Models\UjianPeserta;
use App\Support\ExcelExport;
use App\Support\Pengguna;
use App\Support\Referensi;
use App\Support\TeksSoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menu "Pemilihan Soal yang Diujikan".
 *
 * Satu paket soal = kumpulan butir terpilih dari bank soal, lengkap dengan
 * urutan dan bobot masing-masing. Paket inilah yang dipasang ke jadwal ujian.
 */
class PaketSoalController extends Controller
{
    public function index(Request $r)
    {
        $items = $this->kueriDasar()
            ->with(['mataPelajaran', 'tingkatKelas', 'guru'])
            ->withCount(['detail', 'ujian'])
            ->when($r->q, fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('nama_paket', 'like', "%{$v}%")->orWhere('kode_paket', 'like', "%{$v}%");
            }))
            ->when($r->mata_pelajaran_id, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
            ->when($r->tingkat_kelas_id, fn ($q, $v) => $q->where('tingkat_kelas_id', $v))
            ->when($r->jenis_ujian, fn ($q, $v) => $q->where('jenis_ujian', $v))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('paket-soal.index', compact('items'));
    }

    public function create()
    {
        $item = new PaketSoal([
            'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
            'kode_paket' => $this->kodeBaru(),
            'is_aktif' => true,
        ]);

        return view('paket-soal.form', compact('item'));
    }

    public function store(Request $r)
    {
        $paket = PaketSoal::create($this->validasi($r) + ['guru_id' => Pengguna::guruId()]);

        return redirect()->route('paket-soal.kelola', $paket)
            ->with('success', 'Paket dibuat. Sekarang pilih butir soal yang akan diujikan.');
    }

    public function edit(PaketSoal $paket_soal)
    {
        return view('paket-soal.form', ['item' => $paket_soal]);
    }

    public function update(Request $r, PaketSoal $paket_soal)
    {
        $paket_soal->update($this->validasi($r, $paket_soal->id));

        return redirect()->route('paket-soal.index')->with('success', 'Paket soal diperbarui.');
    }

    public function destroy(PaketSoal $paket_soal)
    {
        if ($paket_soal->ujian()->exists()) {
            return back()->with('error', 'Paket ini sudah dipakai pada jadwal ujian, jadi tidak bisa dihapus.');
        }

        $paket_soal->delete();

        return back()->with('success', 'Paket soal dihapus.');
    }

    public function hapusMassal(Request $r)
    {
        return $this->hapusBanyak($r, $this->kueriDasar(), fn (PaketSoal $p) => $p->ujian()->exists()
            ? "Paket \"{$p->nama_paket}\" sudah dipakai pada jadwal ujian."
            : null, 'paket soal');
    }

    /**
     * Halaman pemilihan butir: kiri berisi soal yang sudah masuk paket,
     * kanan berisi bank soal yang masih tersedia beserta filternya.
     */
    public function kelola(Request $r, PaketSoal $paket_soal)
    {
        $sudahMasuk = $paket_soal->detail()->pluck('soal_id');

        $tersedia = Soal::aktif()
            ->with(['topik', 'mataPelajaran'])
            ->whereNotIn('id', $sudahMasuk)
            ->when(Pengguna::guruId(), fn ($q, $g) => $q->where('guru_id', $g))
            // Secara bawaan bank soal disaring mengikuti mapel & tingkat paket
            // supaya guru tidak perlu memfilter ulang setiap kali membuka.
            ->when($r->filled('mata_pelajaran_id') ? $r->mata_pelajaran_id : $paket_soal->mata_pelajaran_id,
                fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
            ->when($r->filled('tingkat_kelas_id') ? $r->tingkat_kelas_id : $paket_soal->tingkat_kelas_id,
                fn ($q, $v) => $q->where('tingkat_kelas_id', $v))
            ->when($r->jenis, fn ($q, $v) => $q->where('jenis', $v))
            ->when($r->topik_id, fn ($q, $v) => $q->where('topik_id', $v))
            ->when($r->tingkat_kesukaran, fn ($q, $v) => $q->where('tingkat_kesukaran', $v))
            ->when($r->q, fn ($q, $v) => $q->where('pertanyaan', 'like', "%{$v}%"))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('paket-soal.kelola', [
            'paket' => $paket_soal->load('mataPelajaran', 'tingkatKelas'),
            'terpilih' => $paket_soal->detail()->with('soal.topik')->get(),
            'tersedia' => $tersedia,
            'daftarTopik' => Topik::aktif()->orderBy('nama_topik')->get(),
        ]);
    }

    /** Tambahkan satu atau banyak butir sekaligus ke paket. */
    public function tambahSoal(Request $r, PaketSoal $paket_soal)
    {
        $r->validate([
            'soal_id' => 'required|array|min:1',
            'soal_id.*' => 'integer|exists:soal,id',
        ], [], ['soal_id' => 'Butir soal']);

        $nomor = $paket_soal->nomorUrutBerikutnya();
        // Hanya butir yang boleh dilihat pengguna ini; id soal guru lain diabaikan.
        $bobotSoal = Soal::milikPengguna()->whereIn('id', $r->soal_id)->pluck('bobot', 'id');
        $jumlah = 0;

        DB::transaction(function () use ($paket_soal, $r, &$nomor, $bobotSoal, &$jumlah) {
            foreach ($r->soal_id as $soalId) {
                if (! $bobotSoal->has($soalId)) {
                    continue;
                }

                $detail = PaketSoalDetail::firstOrCreate(
                    ['paket_soal_id' => $paket_soal->id, 'soal_id' => $soalId],
                    ['nomor_urut' => $nomor, 'bobot' => $bobotSoal[$soalId] ?? 1]
                );

                if ($detail->wasRecentlyCreated) {
                    $nomor++;
                    $jumlah++;
                }
            }
        });

        return back()->with('success', "{$jumlah} butir soal ditambahkan ke paket.");
    }

    public function hapusSoal(PaketSoal $paket_soal, PaketSoalDetail $detail)
    {
        abort_unless($detail->paket_soal_id === $paket_soal->id, 404);

        if ($this->sudahDikerjakan($paket_soal)) {
            return back()->with('error', 'Paket sudah dipakai ujian yang berjalan — butir tidak boleh dilepas agar nilai peserta tetap sahih.');
        }

        $detail->delete();
        $paket_soal->rapikanUrutan();

        return back()->with('success', 'Butir soal dilepas dari paket.');
    }

    /** Lepas banyak butir sekaligus dari kotak centang di halaman kelola. */
    public function lepasSoalMassal(Request $r, PaketSoal $paket_soal)
    {
        $data = $r->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ], [
            'ids.required' => 'Pilih minimal satu butir soal yang akan dilepas.',
        ]);

        if ($this->sudahDikerjakan($paket_soal)) {
            return back()->with('error', 'Paket sudah dipakai ujian yang berjalan — butir tidak boleh dilepas agar nilai peserta tetap sahih.');
        }

        // Dibatasi ke paket ini, jadi id butir milik paket lain tidak ikut terlepas.
        $jumlah = DB::transaction(function () use ($paket_soal, $data) {
            $jumlah = PaketSoalDetail::where('paket_soal_id', $paket_soal->id)->whereIn('id', $data['ids'])->delete();
            $paket_soal->rapikanUrutan();

            return $jumlah;
        });

        return back()->with('success', "{$jumlah} butir soal dilepas dari paket.");
    }

    /** Simpan perubahan nomor urut & bobot sekaligus. */
    public function simpanUrutan(Request $r, PaketSoal $paket_soal)
    {
        $r->validate([
            'urut' => 'required|array',
            'urut.*' => 'integer|min:1',
            'bobot' => 'required|array',
            'bobot.*' => 'numeric|min:0.1|max:100',
        ]);

        DB::transaction(function () use ($r, $paket_soal) {
            foreach ($r->input('urut') as $detailId => $nomor) {
                PaketSoalDetail::where('paket_soal_id', $paket_soal->id)
                    ->where('id', $detailId)
                    ->update([
                        'nomor_urut' => (int) $nomor,
                        'bobot' => (float) ($r->input('bobot')[$detailId] ?? 1),
                    ]);
            }
        });

        $paket_soal->rapikanUrutan();

        return back()->with('success', 'Urutan dan bobot butir disimpan.');
    }

    /** Ambil sejumlah butir acak dari bank soal sesuai komposisi kesukaran. */
    public function ambilAcak(Request $r, PaketSoal $paket_soal)
    {
        $r->validate([
            'jumlah_mudah' => 'nullable|integer|min:0|max:100',
            'jumlah_sedang' => 'nullable|integer|min:0|max:100',
            'jumlah_sukar' => 'nullable|integer|min:0|max:100',
            'jenis_acak' => 'nullable|in:'.implode(',', array_keys(Soal::JENIS)),
        ]);

        $sudahMasuk = $paket_soal->detail()->pluck('soal_id');
        $nomor = $paket_soal->nomorUrutBerikutnya();
        $jumlah = 0;

        foreach (['mudah', 'sedang', 'sukar'] as $tingkat) {
            $n = (int) $r->input("jumlah_{$tingkat}", 0);
            if ($n < 1) {
                continue;
            }

            $soal = Soal::aktif()
                ->whereNotIn('id', $sudahMasuk)
                ->where('tingkat_kesukaran', $tingkat)
                ->when($paket_soal->mata_pelajaran_id, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
                ->when($paket_soal->tingkat_kelas_id, fn ($q, $v) => $q->where('tingkat_kelas_id', $v))
                ->when($r->jenis_acak, fn ($q, $v) => $q->where('jenis', $v))
                ->when(Pengguna::guruId(), fn ($q, $g) => $q->where('guru_id', $g))
                ->inRandomOrder()
                ->limit($n)
                ->get();

            foreach ($soal as $s) {
                PaketSoalDetail::create([
                    'paket_soal_id' => $paket_soal->id,
                    'soal_id' => $s->id,
                    'nomor_urut' => $nomor++,
                    'bobot' => $s->bobot,
                ]);
                $sudahMasuk->push($s->id);
                $jumlah++;
            }
        }

        return back()->with(
            $jumlah > 0 ? 'success' : 'error',
            $jumlah > 0
                ? "{$jumlah} butir soal diambil acak dari bank soal."
                : 'Tidak ada butir yang cocok dengan komposisi yang diminta. Periksa isi bank soal untuk mapel & tingkat paket ini.'
        );
    }

    /** Cetak kartu soal paket ke Excel (naskah + kunci). */
    public function export(PaketSoal $paket_soal)
    {
        $detail = $paket_soal->detail()->with('soal.topik')->get();

        $rows = $detail->map(function (PaketSoalDetail $d) {
            $s = $d->soal;

            return [
                $d->nomor_urut,
                $s?->jenis_label,
                TeksSoal::polos($s?->pertanyaan),
                collect($s?->opsiPilihan() ?? [])->map(fn ($o) => $o['key'].'. '.$o['text'])->implode(' | '),
                $s?->kunci_ringkas,
                (float) $d->bobot,
                $s?->tingkat_kesukaran,
                $s?->level_kognitif,
                $s?->topik->nama_topik ?? '-',
            ];
        });

        return ExcelExport::make('Paket Soal')
            ->judul(
                'NASKAH PAKET SOAL — '.$paket_soal->nama_paket,
                'Kode: '.$paket_soal->kode_paket,
                'Mata Pelajaran: '.($paket_soal->mataPelajaran->nama_mapel ?? '-'),
                'Jumlah butir: '.$detail->count().'   |   Total bobot: '.$detail->sum('bobot')
            )
            ->header(['No', 'Jenis', 'Pertanyaan', 'Opsi', 'Kunci', 'Bobot', 'Kesukaran', 'Level', 'Topik'])
            ->rows($rows)
            ->unduh('paket-soal-'.Str::slug($paket_soal->kode_paket).'.xlsx');
    }

    protected function kueriDasar()
    {
        return PaketSoal::query()
            ->when(Pengguna::guruId(), fn ($q, $guruId) => $q->where('guru_id', $guruId));
    }

    /** Paket sudah punya peserta yang mengerjakan? */
    protected function sudahDikerjakan(PaketSoal $paket): bool
    {
        return UjianPeserta::whereIn('ujian_id', $paket->ujian()->pluck('id'))
            ->whereIn('status', [UjianPeserta::MULAI, UjianPeserta::SELESAI])
            ->exists();
    }

    protected function kodeBaru(): string
    {
        return 'PKT-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
    }

    protected function validasi(Request $r, ?int $abaikanId = null): array
    {
        return $r->validate([
            'kode_paket' => 'required|string|max:40|unique:paket_soal,kode_paket'.($abaikanId ? ",{$abaikanId}" : ''),
            'nama_paket' => 'required|string|max:255',
            'mata_pelajaran_id' => Referensi::aturanMapel($r->route('paket_soal')?->mata_pelajaran_id),
            'tingkat_kelas_id' => Referensi::aturanTingkat($r->route('paket_soal')?->tingkat_kelas_id),
            'tahun_ajaran' => Referensi::aturanTahunAjaran($r->route('paket_soal')?->tahun_ajaran),
            'semester' => 'nullable|string|max:20',
            'jenis_ujian' => 'nullable|string|max:30',
            'deskripsi' => 'nullable|string',
        ]) + [
            'acak_soal' => $r->boolean('acak_soal'),
            'acak_opsi' => $r->boolean('acak_opsi'),
            'is_aktif' => $r->boolean('is_aktif', true),
        ];
    }
}
