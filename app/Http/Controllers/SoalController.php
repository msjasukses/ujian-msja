<?php

namespace App\Http\Controllers;

use App\Models\Soal;
use App\Models\Topik;
use App\Services\GambarSoalService;
use App\Services\ImportSoalExcelService;
use App\Services\MediaSoalService;
use App\Support\AlamatPublik;
use App\Support\ExcelExport;
use App\Support\Pengguna;
use App\Support\Referensi;
use App\Support\TeksSoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SoalController extends Controller
{
    public function index(Request $r)
    {
        $items = $this->kueriDasar()
            ->with(['topik', 'mataPelajaran', 'tingkatKelas', 'jurusan', 'guru'])
            ->when($r->q, fn ($q, $v) => $q->where('pertanyaan', 'like', "%{$v}%"))
            // Bank soal satu mapel kini dibagi antar guru; penyaring ini
            // mengembalikan daftar ke butir buatannya sendiri saja.
            ->when($r->pemilik === 'saya' && Pengguna::guruId(),
                fn ($q) => $q->where('guru_id', Pengguna::guruId()))
            ->when($r->jenis, fn ($q, $v) => $q->where('jenis', $v))
            ->when($r->topik_id, fn ($q, $v) => $q->where('topik_id', $v))
            ->when($r->mata_pelajaran_id, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
            ->when($r->tingkat_kelas_id, fn ($q, $v) => $q->where('tingkat_kelas_id', $v))
            ->when($r->jurusan_id, fn ($q, $v) => $q->where('jurusan_id', $v))
            ->when($r->tingkat_kesukaran, fn ($q, $v) => $q->where('tingkat_kesukaran', $v))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $stat = collect(Soal::JENIS)
            ->map(fn ($label, $jenis) => $this->kueriDasar()->where('jenis', $jenis)->count())
            ->all();

        return view('soal.index', [
            'items' => $items,
            'stat' => $stat,
            'total' => $this->kueriDasar()->count(),
            // Dua daftar topik yang berbeda luasnya, sesuai gunanya.
            // Penyaring daftar ikut selebar butir yang tampil — termasuk topik
            // tingkat lain pada mapel yang sama; sedangkan tujuan penyalinan
            // tetap topik yang memang diajarnya, karena menyalin menulis data.
            'daftarTopik' => Topik::aktif()->bisaDilihat()->orderBy('nama_topik')->get(),
            // Penempatan tiap topik ikut dimuat: jendela "Salin ke topik"
            // menampilkannya sebagai keterangan pilihan tujuan.
            'daftarTopikTujuan' => Topik::aktif()->milikPengguna()
                ->with(['mataPelajaran', 'tingkatKelas', 'jurusan'])
                ->orderBy('nama_topik')->get(),
        ]);
    }

    public function create(Request $r)
    {
        $item = new Soal([
            'jenis' => $r->get('jenis', Soal::PG),
            'bobot' => 1,
            'is_aktif' => true,
            'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
        ]);

        return view('soal.form', [
            'item' => $item,
            'daftarTopik' => Topik::aktif()->milikPengguna()->orderBy('nama_topik')->get(),
        ]);
    }

    public function store(Request $r)
    {
        $soal = Soal::create($this->siapkanData($r) + ['guru_id' => Pengguna::guruId()]);

        return redirect()
            ->route($r->boolean('lanjut') ? 'soal.create' : 'soal.index', $r->boolean('lanjut') ? ['jenis' => $soal->jenis] : [])
            ->with('success', 'Soal berhasil disimpan.');
    }

    public function show(Soal $soal)
    {
        return view('soal.show', ['item' => $soal->load(['topik', 'mataPelajaran', 'tingkatKelas', 'jurusan', 'guru'])]);
    }

    public function edit(Soal $soal)
    {
        $this->pastikanBisaDiubah($soal);

        return view('soal.form', [
            'item' => $soal,
            'daftarTopik' => Topik::aktif()->milikPengguna()->orderBy('nama_topik')->get(),
        ]);
    }

    public function update(Request $r, Soal $soal)
    {
        $this->pastikanBisaDiubah($soal);

        // guru_id tidak ikut siapkanData(), jadi butir guru lain yang
        // disunting rekan se-mapel tetap tercatat milik pembuatnya.
        $soal->update($this->siapkanData($r));

        return redirect()->route('soal.index')->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroy(Soal $soal)
    {
        $this->pastikanMilikSendiri($soal);

        if ($soal->paketDetail()->exists()) {
            return back()->with('error', 'Soal ini sedang dipakai pada paket soal. Lepaskan dari paket terlebih dahulu.');
        }

        $soal->delete();

        return back()->with('success', 'Soal dihapus.');
    }

    public function hapusMassal(Request $r)
    {
        // Butir guru lain ikut tampil di daftar, jadi bisa saja ikut
        // tercentang. Ia ditolak di sini dengan alasan yang terbaca, bukan
        // disaring diam-diam dari kuerinya — kalau tidak, guru hanya melihat
        // angka terhapus yang lebih kecil tanpa tahu apa sebabnya.
        return $this->hapusBanyak($r, $this->kueriDasar(), function (Soal $s) {
            $judul = '"'.Str::limit(TeksSoal::polos($s->pertanyaan), 40).'"';

            return match (true) {
                ! $s->milik_saya => $judul.' milik guru lain.',
                $s->paketDetail()->exists() => $judul.' masih dipakai pada paket soal.',
                default => null,
            };
        }, 'soal');
    }

    /**
     * Salin butir soal ke sebuah topik.
     *
     * Satu alamat melayani dua tombol di halaman daftar: tombol salin pada
     * satu baris, dan "Salin terpilih" untuk butir yang dicentang — keduanya
     * mengirim daftar id yang sama bentuknya, jadi menyalin satu butir tidak
     * lewat jalan yang berbeda dari menyalin lima puluh.
     *
     * Butirnya diambil lewat kueriDasar(), bukan langsung dari id kiriman,
     * supaya guru tidak bisa ikut menyalin milik guru lain dengan menyelipkan
     * id; topik tujuannya pun dibatasi pada topik yang tampil di daftarnya
     * sendiri, sama seperti pembatasan topik di formulir input soal.
     */
    public function salin(Request $r)
    {
        $data = $r->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'topik_id' => ['required', 'integer', Rule::in(Topik::milikPengguna()->pluck('id')->all())],
            'ikut_penempatan' => ['nullable', 'boolean'],
        ], [
            'ids.required' => 'Pilih minimal satu soal yang akan disalin.',
            'topik_id.required' => 'Pilih dulu topik tujuan salinannya.',
            'topik_id.in' => 'Topik tujuan tidak ada pada daftar topik Anda.',
        ]);

        $topik = Topik::findOrFail($data['topik_id']);
        $ikutPenempatan = $r->boolean('ikut_penempatan');
        $sumber = $this->kueriDasar()->whereIn('id', $data['ids'])->orderBy('id')->get();

        if ($sumber->isEmpty()) {
            return back()->with('error', 'Tidak ada soal yang disalin.');
        }

        DB::transaction(function () use ($sumber, $topik, $ikutPenempatan) {
            foreach ($sumber as $asal) {
                // Kode soal tidak ikut disalin: kode itu penanda satu butir,
                // dan dua baris berkode sama akan menyulitkan guru sendiri
                // saat menyunting, mengexport, lalu mengimpornya kembali.
                // Lampiran audio/video cukup ditunjuk bersama — berkasnya
                // dinamai dari sidik jari isinya dan tidak pernah dihapus
                // (lihat MediaSoalService), jadi salinan tetap bisa memutarnya.
                $salinan = $asal->replicate(['kode_soal']);
                $salinan->topik_id = $topik->id;

                // Menyalin butir guru lain yang se-mata pelajaran boleh, dan
                // salinannya menjadi milik yang menyalin: dialah yang akan
                // menyuntingnya, sedangkan butir asalnya tetap utuh di tangan
                // pembuatnya. Admin tidak punya guru_id, jadi di tangannya
                // kepemilikan butir aslinya yang diteruskan.
                $salinan->guru_id = Pengguna::guruId() ?? $asal->guru_id;

                // Penempatan salinan boleh ikut topik tujuannya — persis yang
                // dikerjakan formulir input soal ketika topiknya dipilih.
                // Kolom yang di topiknya sendiri belum terisi dibiarkan
                // seperti aslinya, supaya salinan tidak justru kehilangan
                // mapel atau tingkat yang sudah benar.
                if ($ikutPenempatan) {
                    foreach (['mata_pelajaran_id', 'tingkat_kelas_id', 'jurusan_id'] as $kolom) {
                        if (filled($topik->{$kolom})) {
                            $salinan->{$kolom} = $topik->{$kolom};
                        }
                    }
                }

                $salinan->save();
            }
        });

        return back()->with(
            'success',
            $sumber->count().' soal disalin ke topik "'.Str::limit($topik->nama_topik, 40).'".'
        );
    }

    /** Nonaktifkan / aktifkan cepat dari daftar. */
    public function toggle(Soal $soal)
    {
        $this->pastikanMilikSendiri($soal);

        $soal->update(['is_aktif' => ! $soal->is_aktif]);

        return back()->with('success', 'Status soal diperbarui.');
    }

    /**
     * Terima satu gambar dari formulir input soal dan kembalikan alamatnya.
     *
     * Dipanggil lewat fetch() oleh tiga jalan masuk yang berbeda — tombol
     * sisip gambar, tempelan papan klip, dan seret-lepas — sehingga gambar
     * sudah tersimpan sebelum soalnya sendiri disimpan. Cara ini membuat
     * pratinjau bisa langsung menampilkannya, dan kolom pertanyaan cukup
     * memuat <img src="..."> seperti penanda lainnya.
     */
    public function unggahGambar(Request $r, GambarSoalService $gambar)
    {
        $r->validate(
            ['gambar' => 'required|file|max:5120'],
            ['gambar.max' => 'Ukuran gambar melebihi 5 MB.'],
        );

        try {
            // Alamat siap tampil, berawalan subfolder bila ada; saat soal
            // disimpan, Soal mengembalikannya ke bentuk netral "/storage/...".
            return response()->json(['url' => AlamatPublik::tampil($gambar->dariUnggahan($r->file('gambar')))]);
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 422);
        }
    }

    public function export(Request $r)
    {
        $items = $this->kueriDasar()
            ->with(['topik', 'mataPelajaran'])
            ->when($r->jenis, fn ($q, $v) => $q->where('jenis', $v))
            ->when($r->topik_id, fn ($q, $v) => $q->where('topik_id', $v))
            ->when($r->mata_pelajaran_id, fn ($q, $v) => $q->where('mata_pelajaran_id', $v))
            ->when($r->jurusan_id, fn ($q, $v) => $q->where('jurusan_id', $v))
            ->orderBy('id')
            ->get();

        // Kolomnya sengaja dibuat sama persis dengan template import, supaya
        // hasil export bisa disunting lalu diimpor kembali.
        $rows = $items->map(function (Soal $s) {
            $opsi = array_fill(0, 5, '');

            if ($s->jenis === Soal::PENJODOHAN) {
                $kanan = collect($s->opsiKanan())->keyBy('key');
                foreach ($s->opsiPilihan() as $i => $kiri) {
                    $pasangan = $kanan[$s->kunci[$kiri['key']] ?? ''] ?? null;
                    $opsi[$i] = $kiri['text'].' ## '.($pasangan['text'] ?? '');
                }
                $kunci = '';
            } elseif ($s->jenis === Soal::ESSAY) {
                $opsi[0] = implode(', ', $s->kunci['kata_kunci'] ?? []);
                $kunci = $s->kunci['jawaban'] ?? '';
            } elseif ($s->jenis === Soal::BENAR_SALAH) {
                $kunci = $s->kunci[0] ?? '';
            } elseif ($s->jenis === Soal::BENAR_SALAH_KOMPLEKS) {
                // Bentuknya sama dengan penjodohan, "isi ## pasangannya",
                // hanya saja pasangannya berupa benar/salah.
                foreach ($s->opsiPilihan() as $i => $pernyataan) {
                    $opsi[$i] = TeksSoal::polos($pernyataan['text']).' ## '
                        .($s->kunci[$pernyataan['key']] ?? '');
                }
                $kunci = '';
            } else {
                foreach ($s->opsiPilihan() as $i => $o) {
                    $opsi[$i] = $o['text'];
                }
                $kunci = implode(',', (array) $s->kunci);
            }

            return array_merge(
                [$s->jenis, TeksSoal::polos($s->pertanyaan)],
                $opsi,
                [$kunci, (float) $s->bobot, $s->level_kognitif, $s->tingkat_kesukaran, $s->pembahasan]
            );
        });

        return ExcelExport::make('Bank Soal')
            ->judul('BANK SOAL', 'Jumlah butir: '.$items->count(), 'Dicetak: '.now()->format('d/m/Y H:i'))
            ->header(ImportSoalExcelService::HEADER)
            ->rows($rows)
            ->unduh('bank-soal-'.now()->format('Ymd-His').'.xlsx');
    }

    /**
     * Butir yang boleh dilihat: milik sendiri ditambah butir guru lain pada
     * mata pelajaran yang diampu, walau tingkat kelasnya berbeda (lihat
     * Soal::scopeBisaDilihat). Admin melihat semua. Dipakai daftar, export,
     * statistik, dan pemilihan butir yang disalin.
     */
    protected function kueriDasar()
    {
        return Soal::query()->bisaDilihat();
    }

    /**
     * Butir guru lain hanya boleh dihapus/dinonaktifkan pembuatnya. Dijawab
     * 404 seperti data milik orang lain yang lain pula.
     */
    protected function pastikanMilikSendiri(Soal $soal): void
    {
        abort_unless($soal->milik_saya, 404);
    }

    /** Menyunting boleh oleh pembuatnya atau guru se-mata pelajaran. */
    protected function pastikanBisaDiubah(Soal $soal): void
    {
        abort_unless($soal->bisa_diubah, 404);
    }

    /**
     * Validasi + rakit kolom opsi/kunci sesuai jenis soal yang dipilih.
     *
     * @return array<string, mixed>
     */
    protected function siapkanData(Request $r): array
    {
        $data = $r->validate([
            'jenis' => 'required|in:'.implode(',', array_keys(Soal::JENIS)),
            'kode_soal' => 'nullable|string|max:40',
            // Topik pun dibatasi seperti mapel & tingkat: guru hanya boleh
            // menempelkan butir pada topik yang tampil di daftarnya, sedangkan
            // topik lama butir ini tetap diterima agar tidak lepas saat diubah.
            'topik_id' => ['nullable', 'integer', Rule::in(
                Topik::milikPengguna()->pluck('id')->push($r->route('soal')?->topik_id)->filter()->all()
            )],
            'mata_pelajaran_id' => Referensi::aturanMapel($r->route('soal')?->mata_pelajaran_id),
            'tingkat_kelas_id' => Referensi::aturanTingkat($r->route('soal')?->tingkat_kelas_id),
            ...Referensi::aturanJurusan($r->route('soal')?->jurusan_id),
            'tahun_ajaran' => Referensi::aturanTahunAjaran($r->route('soal')?->tahun_ajaran),
            'pertanyaan' => 'required|string',
            'bobot' => 'required|numeric|min:0.1|max:100',
            'level_kognitif' => 'nullable|string|max:5',
            'tingkat_kesukaran' => 'nullable|in:mudah,sedang,sukar',
            'pembahasan' => 'nullable|string',
            'media' => 'nullable|file',
        ]);

        unset($data['media']);

        [$opsi, $kunci] = $this->opsiDanKunci($r, $data['jenis']);

        return $data + $this->media($r) + [
            'opsi' => $opsi,
            'kunci' => $kunci,
            'is_aktif' => $r->boolean('is_aktif', true),
        ];
    }

    /**
     * Lampiran audio/video butir ini.
     *
     * Mengembalikan array kosong bila tidak ada perubahan, sehingga lampiran
     * lama tetap menempel saat guru hanya membetulkan teks soal.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function media(Request $r): array
    {
        if ($r->boolean('hapus_media') && ! $r->hasFile('media')) {
            return ['media_path' => null, 'media_tipe' => null];
        }

        if (! $r->hasFile('media')) {
            return [];
        }

        try {
            $media = app(MediaSoalService::class)->simpan($r->file('media'));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['media' => $e->getMessage()]);
        }

        return ['media_path' => $media['path'], 'media_tipe' => $media['tipe']];
    }

    /**
     * @return array{0: array|null, 1: array}
     *
     * @throws ValidationException
     */
    protected function opsiDanKunci(Request $r, string $jenis): array
    {
        if ($jenis === Soal::BENAR_SALAH) {
            $r->validate(['kunci_bs' => 'required|in:benar,salah']);

            return [null, [$r->input('kunci_bs')]];
        }

        if ($jenis === Soal::BENAR_SALAH_KOMPLEKS) {
            $r->validate([
                'bsk_pernyataan' => 'required|array|min:2',
                'bsk_pernyataan.*' => 'required|string|max:1000',
                'bsk_kunci' => 'required|array|min:2',
                'bsk_kunci.*' => 'required|in:'.implode(',', array_keys(Soal::PILIHAN_BS)),
            ], [], [
                'bsk_pernyataan' => 'Pernyataan',
                'bsk_kunci' => 'Kunci tiap pernyataan',
            ]);

            // Nama kolomnya bernomor (bsk_pernyataan[0], bsk_kunci[0]) supaya
            // pernyataan dan kuncinya tetap berpasangan walau baris di
            // tengahnya dihapus di formulir. Yang dipakai hanya pernyataan
            // yang kuncinya ikut terkirim.
            $pernyataan = (array) $r->input('bsk_pernyataan');
            $kunciInput = (array) $r->input('bsk_kunci');

            $opsi = $kunci = [];

            foreach ($pernyataan as $i => $teks) {
                if (! isset($kunciInput[$i])) {
                    continue;
                }

                $nomor = (string) (count($opsi) + 1);
                $opsi[] = ['key' => $nomor, 'text' => trim((string) $teks)];
                $kunci[$nomor] = $kunciInput[$i];
            }

            if (count($opsi) < 2) {
                throw ValidationException::withMessages([
                    'bsk_pernyataan' => 'Soal benar/salah kompleks minimal punya 2 pernyataan.',
                ]);
            }

            return [$opsi, $kunci];
        }

        if ($jenis === Soal::ESSAY) {
            $r->validate([
                'kunci_essay' => 'required|string',
                'kata_kunci' => 'nullable|string',
            ], [], ['kunci_essay' => 'Kunci jawaban']);

            $kataKunci = trim((string) $r->input('kata_kunci'));

            return [null, [
                'jawaban' => $r->input('kunci_essay'),
                'kata_kunci' => $kataKunci === ''
                    ? []
                    : array_values(array_filter(array_map('trim', explode(',', $kataKunci)))),
            ]];
        }

        if ($jenis === Soal::PENJODOHAN) {
            $r->validate([
                'jodoh_kiri' => 'required|array|min:2',
                'jodoh_kiri.*' => 'required|string|max:500',
                'jodoh_kanan' => 'required|array|min:2',
                'jodoh_kanan.*' => 'required|string|max:500',
            ], [], [
                'jodoh_kiri' => 'Pernyataan',
                'jodoh_kanan' => 'Pasangan',
            ]);

            $kiriInput = array_values($r->input('jodoh_kiri'));
            $kananInput = array_values($r->input('jodoh_kanan'));

            if (count($kiriInput) !== count($kananInput)) {
                throw ValidationException::withMessages([
                    'jodoh_kiri' => 'Jumlah pernyataan dan pasangan harus sama.',
                ]);
            }

            $huruf = range('A', 'Z');
            $kiri = $kanan = $kunci = [];

            foreach ($kiriInput as $i => $teks) {
                $nomor = (string) ($i + 1);
                $kiri[] = ['key' => $nomor, 'text' => trim($teks)];
                $kanan[] = ['key' => $huruf[$i], 'text' => trim($kananInput[$i])];
                $kunci[$nomor] = $huruf[$i];
            }

            return [['kiri' => $kiri, 'kanan' => $kanan], $kunci];
        }

        // pg & pg_kompleks
        $r->validate([
            'opsi_text' => 'required|array|min:2',
            'opsi_text.*' => 'nullable|string|max:1000',
            'kunci_pg' => 'required|array|min:1',
        ], [], [
            'opsi_text' => 'Opsi jawaban',
            'kunci_pg' => 'Kunci jawaban',
        ]);

        $huruf = range('A', 'Z');
        $opsi = [];

        foreach (array_values($r->input('opsi_text')) as $i => $teks) {
            $teks = trim((string) $teks);

            // Opsi yang dibiarkan kosong diisi hurufnya sendiri. Ini melayani
            // soal yang pilihannya memang berupa huruf — mis. "Jawaban yang
            // benar adalah ..." dengan opsi A, B, C, D — sekaligus mencegah
            // opsi hampa lolos ke naskah ujian tanpa disadari.
            $opsi[] = [
                'key' => $huruf[$i],
                'text' => TeksSoal::kosong($teks) ? $huruf[$i] : $teks,
            ];
        }

        $kunci = array_values(array_unique(array_map('strval', $r->input('kunci_pg'))));
        $tersedia = array_column($opsi, 'key');

        foreach ($kunci as $k) {
            if (! in_array($k, $tersedia, true)) {
                throw ValidationException::withMessages([
                    'kunci_pg' => "Kunci \"{$k}\" tidak ada pada daftar opsi.",
                ]);
            }
        }

        if ($jenis === Soal::PG && count($kunci) !== 1) {
            throw ValidationException::withMessages([
                'kunci_pg' => 'Pilihan ganda biasa hanya boleh punya satu kunci. Gunakan jenis Pilihan Ganda Kompleks bila kuncinya lebih dari satu.',
            ]);
        }

        return [$opsi, $kunci];
    }
}
