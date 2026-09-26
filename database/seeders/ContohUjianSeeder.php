<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\LoginAttempt;
use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Siswa;
use App\Models\Soal;
use App\Models\TindakLanjut;
use App\Models\Topik;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\Models\UjianKelas;
use App\Models\UjianLog;
use App\Models\UjianPeserta;
use App\Services\PenilaianService;
use App\Services\RegistrasiPesertaService;
use App\Support\Referensi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Contoh paket soal, jadwal ujian, dan hasil pengerjaannya.
 *
 * Jawaban peserta dibangkitkan dari sebuah model sederhana: tiap siswa punya
 * "kemampuan" dan tiap butir punya "kemudahan" sesuai tingkat kesukarannya,
 * sehingga peluang menjawab benar = kemampuan × kemudahan. Cara ini membuat
 * daya pembeda pada menu Analisis Butir Soal bernilai positif dan masuk akal,
 * tidak seperti jawaban yang diacak seragam.
 *
 * Satu butir sengaja dibuat bermasalah (peluang benarnya berbanding terbalik
 * dengan kemampuan) agar halaman analisis memperlihatkan contoh butir dengan
 * daya pembeda negatif dan keputusan "Buang / perbaiki kunci".
 *
 * Bilangan acaknya diberi benih tetap, jadi hasil seeding selalu sama.
 */
class ContohUjianSeeder extends Seeder
{
    /** Awalan kode agar data contoh mudah dikenali dan dibersihkan ulang. */
    protected const AWALAN = 'CTH-';

    /**
     * Peluang menjawab benar bila kemampuan peserta penuh. Angkanya dipilih
     * agar sebaran nilai akhir menyerupai hasil PAS yang wajar: rata-rata di
     * kisaran 70, terendah sekitar 45, dan ketuntasan sekitar separuh kelas.
     */
    protected const KEMUDAHAN = ['mudah' => 1.0, 'sedang' => 0.95, 'sukar' => 0.82];

    /**
     * Ambang kemampuan pemisah pada butir bermasalah. Diletakkan dekat nilai
     * tengah sebaran kemampuan agar kelompok atas dan bawah sama-sama terisi.
     */
    protected const AMBANG_BERMASALAH = 0.72;

    protected PenilaianService $penilaian;

    protected RegistrasiPesertaService $registrasi;

    /** Butir yang sengaja dibuat menyesatkan, diisi saat paket dibangun. */
    protected ?int $soalBermasalahId = null;

    public function __construct()
    {
        $this->penilaian = app(PenilaianService::class);
        $this->registrasi = app(RegistrasiPesertaService::class);
    }

    public function run(): void
    {
        mt_srand(20262027);

        if (Soal::count() === 0) {
            $this->command->error('Bank soal masih kosong. Jalankan ContohBankSoalSeeder terlebih dahulu.');

            return;
        }

        $rombelIds = Referensi::rombel()->pluck('id')->all();

        if ($rombelIds === []) {
            $this->command->error('Tidak ada rombongan belajar pada tahun ajaran aktif di Data Center. Seeder dibatalkan.');

            return;
        }

        $this->bersihkanDataContoh();

        $this->ujianSelesai($rombelIds);
        $this->ujianBerlangsung($rombelIds);
        $this->ujianTerjadwal($rombelIds);
        $this->contohLogLogin();

        $this->command->info('Contoh ujian: '.Ujian::count().' jadwal, '
            .UjianPeserta::count().' peserta, '.UjianJawaban::count().' lembar jawaban butir.');
    }

    // =====================================================================
    // 1. Ujian yang sudah selesai — sumber data laporan & analisis butir
    // =====================================================================

    protected function ujianSelesai(array $rombelIds): void
    {
        $paket = $this->paket(
            kode: self::AWALAN.'PAS-MTK',
            nama: 'PAS Ganjil Matematika Kelas 7',
            kodeTopik: ['MTK-7-01', 'MTK-7-02', 'MTK-7-03'],
            jenisUjian: 'PAS',
        );

        // Butir nomor 1 dijadikan contoh butir bermasalah supaya mudah dicari
        // saat menelusuri halaman Analisis Butir Soal.
        $this->soalBermasalahId = (int) $paket->detail()
            ->whereHas('soal', fn ($q) => $q->where('jenis', Soal::PG))
            ->value('soal_id');

        $ujian = Ujian::create([
            'kode_ujian' => self::AWALAN.'UJN-PAS-MTK',
            'nama_ujian' => 'PAS Ganjil Matematika Kelas 7',
            'paket_soal_id' => $paket->id,
            'mata_pelajaran_id' => $paket->mata_pelajaran_id,
            'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
            'semester' => 'Ganjil',
            'waktu_mulai' => now()->subDays(14)->setTime(7, 30),
            'waktu_selesai' => now()->subDays(14)->setTime(9, 30),
            'durasi_menit' => 90,
            'token' => 'MTK7PAS',
            'kkm' => 75,
            'tampilkan_hasil' => true,
            'acak_soal' => true,
            'acak_opsi' => true,
            'status' => Ujian::SELESAI,
            'guru_id' => $paket->guru_id,
        ]);

        $this->pasangKelas($ujian, $rombelIds);
        $this->registrasi->sinkronkan($ujian);

        $detail = $paket->detail()->with('soal')->get();
        $belumDikoreksi = 0;

        foreach ($ujian->peserta()->get() as $i => $peserta) {
            $kemampuan = $this->kemampuan();
            $mulai = $ujian->waktu_mulai->copy()->addMinutes(mt_rand(0, 6));

            $peserta->update([
                'status' => UjianPeserta::SELESAI,
                'waktu_mulai' => $mulai,
                'waktu_selesai' => $mulai->copy()->addMinutes(mt_rand(38, 88)),
                'ip_address' => '192.168.1.'.(20 + ($i % 60)),
                'browser' => ['Chrome', 'Edge', 'Firefox'][$i % 3],
            ]);

            $this->isiLembarJawaban($peserta, $detail, $kemampuan);
            $this->penilaian->koreksi($peserta);

            // Beberapa lembar sengaja dibiarkan menunggu penilaian essay agar
            // peringatan "belum final" di laporan bisa dilihat dan guru punya
            // bahan untuk mencoba mengoreksi.
            //
            // Jumlahnya dijaga sedikit dengan alasan yang tidak kelihatan:
            // lembar tanpa nilai essay kehilangan sekitar sepertiga bobot,
            // sehingga pemiliknya terlempar ke peringkat bawah betapapun
            // tinggi kemampuannya. Bila porsinya besar, urutan nilai berhenti
            // mencerminkan kemampuan, dan kelompok 27% atas serta bawah pada
            // Analisis Butir Soal jadi tercampur — daya pembeda seluruh butir
            // ikut melemah, termasuk butir bermasalah yang mestinya negatif.
            if ($i % 15 !== 14) {
                $this->nilaiEssay($peserta, $kemampuan);
            } else {
                $belumDikoreksi++;
            }

            UjianLog::catat($ujian->id, $peserta->id, 'mulai');
            UjianLog::catat($ujian->id, $peserta->id, 'selesai');
        }

        $this->rencanaTindakLanjut($ujian);

        $this->command->info("  • {$ujian->nama_ujian}: {$ujian->peserta()->count()} peserta selesai, "
            ."{$belumDikoreksi} lembar menunggu penilaian essay.");
    }

    // =====================================================================
    // 2. Ujian yang sedang berlangsung — sumber data menu Monitoring
    // =====================================================================

    protected function ujianBerlangsung(array $rombelIds): void
    {
        $paket = $this->paket(
            kode: self::AWALAN.'UH-BIN',
            nama: 'Ulangan Harian Teks Deskripsi Kelas 7',
            kodeTopik: ['BIN-7-01', 'BIN-7-02'],
            jenisUjian: 'UH',
        );

        $ujian = Ujian::create([
            'kode_ujian' => self::AWALAN.'UJN-UH-BIN',
            'nama_ujian' => 'Ulangan Harian Teks Deskripsi Kelas 7',
            'paket_soal_id' => $paket->id,
            'mata_pelajaran_id' => $paket->mata_pelajaran_id,
            'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
            'semester' => 'Ganjil',
            // Jendela waktunya sengaja dibuat panjang: peserta yang sedang
            // mengerjakan akan tersapu tutup-otomatis begitu durasinya habis,
            // dan menu Monitoring jadi kosong. Dengan 120 menit, data contoh
            // ini tetap bisa dipantau selama beberapa jam setelah seeding.
            'waktu_mulai' => now()->subMinutes(25),
            'waktu_selesai' => now()->addMinutes(120),
            'durasi_menit' => 120,
            'token' => 'BIN7UH',
            'kkm' => 75,
            'tampilkan_hasil' => true,
            'acak_soal' => false,
            'acak_opsi' => true,
            'status' => Ujian::AKTIF,
            'guru_id' => $paket->guru_id,
        ]);

        // Hanya satu kelas yang sedang mengerjakan, seperti jadwal ulangan biasa.
        $this->pasangKelas($ujian, [$rombelIds[0]]);
        $this->registrasi->sinkronkan($ujian);

        $detail = $paket->detail()->with('soal')->get();
        $sedang = $selesai = 0;

        foreach ($ujian->peserta()->get() as $i => $peserta) {
            // Sepertiga peserta belum menekan tombol mulai.
            if ($i % 3 === 2) {
                continue;
            }

            $kemampuan = $this->kemampuan();
            $mulai = now()->subMinutes(mt_rand(5, 22));

            $peserta->update([
                'status' => UjianPeserta::MULAI,
                'waktu_mulai' => $mulai,
                'ip_address' => '192.168.1.'.(20 + $i),
                'browser' => ['Chrome', 'Edge', 'Firefox'][$i % 3],
            ]);

            UjianLog::catat($ujian->id, $peserta->id, 'mulai');

            // Yang sedang mengerjakan baru mengisi sebagian butir.
            $porsi = $i % 4 === 0 ? 1.0 : mt_rand(30, 80) / 100;
            $this->isiLembarJawaban($peserta, $detail, $kemampuan, $porsi);

            if ($porsi === 1.0) {
                $this->penilaian->koreksi($this->kumpulkan($peserta));
                $this->nilaiEssay($peserta->refresh(), $kemampuan);
                $selesai++;
            } else {
                $sedang++;

                if ($i % 5 === 1) {
                    UjianLog::catat($ujian->id, $peserta->id, 'keluar_halaman', 'tab disembunyikan');
                }
            }
        }

        $this->command->info("  • {$ujian->nama_ujian}: {$sedang} sedang mengerjakan, {$selesai} sudah mengumpulkan.");
    }

    // =====================================================================
    // 3. Ujian yang baru dijadwalkan
    // =====================================================================

    protected function ujianTerjadwal(array $rombelIds): void
    {
        $paket = $this->paket(
            kode: self::AWALAN.'PTS-IPA',
            nama: 'PTS Ganjil IPA Kelas 7',
            kodeTopik: ['IPA-7-01', 'IPA-7-02'],
            jenisUjian: 'PTS',
        );

        $ujian = Ujian::create([
            'kode_ujian' => self::AWALAN.'UJN-PTS-IPA',
            'nama_ujian' => 'PTS Ganjil IPA Kelas 7',
            'paket_soal_id' => $paket->id,
            'mata_pelajaran_id' => $paket->mata_pelajaran_id,
            'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
            'semester' => 'Ganjil',
            'waktu_mulai' => now()->addWeek()->setTime(7, 30),
            'waktu_selesai' => now()->addWeek()->setTime(9, 0),
            'durasi_menit' => 75,
            'token' => 'IPA7PTS',
            'kkm' => 72,
            'tampilkan_hasil' => true,
            'acak_soal' => true,
            'acak_opsi' => true,
            'status' => Ujian::DRAFT,
            // Dipasang pada jadwal berstatus draft saja, supaya peragaan
            // "wajib lewat ExamBro" bisa dilihat tanpa menghalangi ujian
            // contoh yang sedang berlangsung dibuka dari peramban biasa.
            'wajib_exambro' => true,
            'guru_id' => $paket->guru_id,
        ]);

        $this->pasangKelas($ujian, $rombelIds);
        $this->registrasi->sinkronkan($ujian);

        // Paket Informatika dibuat tanpa jadwal, sebagai bahan latihan memilih
        // butir sendiri lewat menu Pemilihan Soal yang Diujikan.
        $this->paket(
            kode: self::AWALAN.'TO-INF',
            nama: 'Try Out Informatika Kelas 7',
            kodeTopik: ['INF-7-01', 'INF-7-02'],
            jenisUjian: 'TO',
        );

        $this->command->info("  • {$ujian->nama_ujian}: {$ujian->peserta()->count()} peserta terdaftar, status draft.");
    }

    // =====================================================================
    // Pembuatan paket & peserta
    // =====================================================================

    /** @param array<int, string> $kodeTopik */
    protected function paket(string $kode, string $nama, array $kodeTopik, string $jenisUjian): PaketSoal
    {
        $soal = Soal::whereIn('topik_id', Topik::whereIn('kode_topik', $kodeTopik)->pluck('id'))
            ->aktif()
            ->orderBy('id')
            ->get();

        $pertama = $soal->first();

        $paket = PaketSoal::create([
            'kode_paket' => $kode,
            'nama_paket' => $nama,
            'mata_pelajaran_id' => $pertama?->mata_pelajaran_id,
            'tingkat_kelas_id' => $pertama?->tingkat_kelas_id,
            'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
            'semester' => 'Ganjil',
            'jenis_ujian' => $jenisUjian,
            'deskripsi' => 'Paket contoh hasil seeder. Kerjakan dengan jujur dan teliti.',
            'acak_soal' => true,
            'acak_opsi' => true,
            'guru_id' => $pertama?->guru_id,
            'is_aktif' => true,
        ]);

        // Soal objektif ditaruh lebih dulu, essay di bagian akhir naskah.
        $urut = $soal->sortBy(fn (Soal $s) => $s->jenis === Soal::ESSAY ? 1 : 0)->values();

        foreach ($urut as $i => $s) {
            PaketSoalDetail::create([
                'paket_soal_id' => $paket->id,
                'soal_id' => $s->id,
                'nomor_urut' => $i + 1,
                'bobot' => $s->bobot,
            ]);
        }

        return $paket;
    }

    /** @param array<int, int> $rombelIds */
    protected function pasangKelas(Ujian $ujian, array $rombelIds): void
    {
        foreach ($rombelIds as $id) {
            UjianKelas::create(['ujian_id' => $ujian->id, 'rombongan_belajar_id' => $id]);
        }
    }

    // =====================================================================
    // Simulasi jawaban
    // =====================================================================

    /**
     * Kemampuan seorang siswa, dibangkitkan dari rata-rata dua bilangan acak
     * supaya sebarannya menumpuk di tengah seperti nilai satu kelas pada
     * umumnya, bukan rata di semua nilai.
     *
     * Rentang undiannya sengaja dibuat lebar (0,35–1,10 lalu dipotong di 1,0):
     * jarak kemampuan antar siswa itulah yang membuat daya pembeda tiap butir
     * pada menu Analisis Butir Soal punya nilai yang berarti.
     */
    protected function kemampuan(): float
    {
        $a = mt_rand(35, 110) / 100;
        $b = mt_rand(35, 110) / 100;

        return round(min(1.0, ($a + $b) / 2), 3);
    }

    /**
     * @param  Collection<int, PaketSoalDetail>  $detail
     * @param  float  $porsi  Bagian butir yang dikerjakan (1.0 = seluruhnya).
     */
    protected function isiLembarJawaban(UjianPeserta $peserta, Collection $detail, float $kemampuan, float $porsi = 1.0): void
    {
        $dikerjakan = (int) round($detail->count() * $porsi);

        foreach ($detail as $i => $d) {
            $soal = $d->soal;

            UjianJawaban::create([
                'ujian_peserta_id' => $peserta->id,
                'soal_id' => $d->soal_id,
                'nomor_urut' => $d->nomor_urut,
                'jawaban' => $i < $dikerjakan ? $this->jawabanUntuk($soal, $kemampuan) : null,
                // Butir sukar lebih sering ditandai ragu-ragu.
                'ragu' => $i < $dikerjakan && $soal->tingkat_kesukaran === 'sukar' && mt_rand(1, 100) <= 35,
            ]);
        }
    }

    /** Bangkitkan satu jawaban sesuai jenis soal dan peluang benarnya. */
    protected function jawabanUntuk(Soal $soal, float $kemampuan): mixed
    {
        $peluang = $this->peluangBenar($soal, $kemampuan);
        $benar = $this->undi($peluang);

        return match ($soal->jenis) {
            Soal::PG, Soal::BENAR_SALAH => $benar
                ? [$soal->kunci[0]]
                : [$this->opsiSalah($soal)],

            Soal::PG_KOMPLEKS => $this->jawabanPgKompleks($soal, $peluang),

            Soal::PENJODOHAN => $this->jawabanPenjodohan($soal, $peluang),

            Soal::ESSAY => ['teks' => $this->teksEssay($soal, $peluang)],

            default => null,
        };
    }

    protected function peluangBenar(Soal $soal, float $kemampuan): float
    {
        $kemudahan = self::KEMUDAHAN[$soal->tingkat_kesukaran] ?? 0.7;

        // Butir bermasalah, meniru soal yang kuncinya keliru: siswa yang
        // menguasai materi justru memilih opsi lain, sedangkan yang menebak
        // malah kena.
        //
        // Peluangnya dipatok pada dua nilai yang dipisah ambang kemampuan,
        // bukan diturunkan mulus dari kemampuan. Cara mulus sempat dipakai
        // dan hasilnya rapuh: pemisahan kelompok atas dan bawah bergantung
        // pada untung-untungan undian, sehingga cukup dengan menambah satu
        // butir ke bank soal — yang menggeser aliran bilangan acak — daya
        // pembedanya bisa berbalik menjadi positif dan contoh peraganya
        // hilang. Dengan ambang, pemisahannya menjadi sifat bawaan butir.
        if ($soal->id === $this->soalBermasalahId) {
            return $kemampuan < self::AMBANG_BERMASALAH ? 0.72 : 0.18;
        }

        return min(0.97, max(0.05, $kemampuan * $kemudahan));
    }

    /** @return array<int, string> */
    protected function jawabanPgKompleks(Soal $soal, float $peluang): array
    {
        $kunci = array_values((array) $soal->kunci);
        $semua = array_column($soal->opsiPilihan(), 'key');
        $pengecoh = array_values(array_diff($semua, $kunci));

        if ($this->undi($peluang)) {
            return $kunci;
        }

        // Jawaban tidak sempurna: sebagian kunci terlewat, kadang ikut
        // memilih satu pengecoh.
        $diambil = max(1, (int) round(count($kunci) * $this->undi(0.5) ? count($kunci) - 1 : count($kunci) / 2));
        $jawaban = array_slice($kunci, 0, max(1, min(count($kunci), $diambil)));

        if ($pengecoh !== [] && $this->undi(0.45)) {
            $jawaban[] = $pengecoh[array_rand($pengecoh)];
        }

        return array_values(array_unique($jawaban));
    }

    /** @return array<string, string> */
    protected function jawabanPenjodohan(Soal $soal, float $peluang): array
    {
        $kunci = (array) $soal->kunci;
        $kananKeys = array_column($soal->opsiKanan(), 'key');
        $jawaban = [];

        foreach ($kunci as $kiri => $kananBenar) {
            if ($this->undi($peluang)) {
                $jawaban[(string) $kiri] = $kananBenar;

                continue;
            }

            $salah = array_values(array_diff($kananKeys, [$kananBenar]));
            $jawaban[(string) $kiri] = $salah[array_rand($salah)] ?? $kananBenar;
        }

        return $jawaban;
    }

    protected function teksEssay(Soal $soal, float $peluang): string
    {
        $kataKunci = $soal->kunci['kata_kunci'] ?? [];
        $jawabanModel = (string) ($soal->kunci['jawaban'] ?? '');

        // Makin tinggi peluangnya, makin banyak kata kunci yang muncul pada
        // jawaban — jadi guru punya bahan nyata saat mencoba menilai essay.
        $jumlah = (int) round(count($kataKunci) * $peluang);
        $dipakai = array_slice($kataKunci, 0, max(0, $jumlah));

        $isi = $peluang > 0.75
            ? $jawabanModel
            : 'Menurut saya '.mb_strtolower(mb_substr($jawabanModel, 0, (int) (mb_strlen($jawabanModel) * max(0.25, $peluang))));

        return $dipakai === []
            ? $isi
            : rtrim($isi, '. ').'. Kata kunci yang saya gunakan: '.implode(', ', $dipakai).'.';
    }

    protected function opsiSalah(Soal $soal): string
    {
        $kunci = array_map('strval', (array) $soal->kunci);
        $pilihan = array_values(array_diff(array_column($soal->opsiPilihan(), 'key'), $kunci));

        return $pilihan === [] ? ($kunci[0] ?? 'A') : (string) $pilihan[array_rand($pilihan)];
    }

    /** Undian berpeluang $p bernilai true. */
    protected function undi(float $p): bool
    {
        return mt_rand(1, 1000) <= $p * 1000;
    }

    // =====================================================================
    // Penilaian & tindak lanjut
    // =====================================================================

    protected function kumpulkan(UjianPeserta $peserta): UjianPeserta
    {
        $peserta->update([
            'status' => UjianPeserta::SELESAI,
            'waktu_selesai' => now()->subMinutes(mt_rand(1, 8)),
        ]);

        return $peserta;
    }

    /** Guru menilai butir essay sebanding dengan mutu jawabannya. */
    protected function nilaiEssay(UjianPeserta $peserta, float $kemampuan): void
    {
        $essay = $peserta->jawaban()->with('soal')
            ->whereHas('soal', fn ($q) => $q->where('jenis', Soal::ESSAY))
            ->get();

        if ($essay->isEmpty()) {
            return;
        }

        foreach ($essay as $jawaban) {
            $maks = (float) PaketSoalDetail::where('paket_soal_id', $peserta->ujian->paket_soal_id)
                ->where('soal_id', $jawaban->soal_id)
                ->value('bobot');

            $skor = $jawaban->terisi
                ? round($maks * min(1, $kemampuan * (mt_rand(85, 108) / 100)) * 2) / 2
                : 0;

            $this->penilaian->nilaiEssay($jawaban, $skor);
        }

        $this->penilaian->hitungNilai($peserta);
    }

    /**
     * Rencana remidial & pengayaan untuk sebagian peserta, agar kedua sub
     * menu itu langsung memperlihatkan daftar yang sudah maupun belum digarap.
     */
    protected function rencanaTindakLanjut(Ujian $ujian): void
    {
        $kkm = (float) $ujian->kkm;
        $peserta = $ujian->peserta()->selesai()->get();

        $belumTuntas = $peserta->filter(fn ($p) => (float) $p->nilai < $kkm)->values();
        $tuntas = $peserta->filter(fn ($p) => (float) $p->nilai >= $kkm)->sortByDesc('nilai')->values();

        // Separuh siswa remidial sudah punya rencana; sisanya dibiarkan kosong.
        foreach ($belumTuntas->take((int) ceil($belumTuntas->count() / 2)) as $i => $p) {
            $selesai = $i % 3 !== 2;

            TindakLanjut::create([
                'ujian_id' => $ujian->id,
                'siswa_id' => $p->siswa_id,
                'jenis' => TindakLanjut::REMIDIAL,
                'nilai_awal' => $p->nilai,
                'nilai_akhir' => $selesai ? min(100, round(max($kkm, (float) $p->nilai + mt_rand(8, 22)))) : null,
                'tanggal' => $ujian->waktu_mulai->copy()->addWeek()->toDateString(),
                'bentuk' => $i % 2 === 0 ? 'tes_ulang' : 'tutor_sebaya',
                'keterangan' => 'Mengulang materi operasi bilangan bulat dan PLSV.',
                'status' => $selesai ? 'selesai' : 'direncanakan',
            ]);
        }

        // Sepuluh nilai teratas diberi pengayaan.
        foreach ($tuntas->take(10) as $i => $p) {
            TindakLanjut::create([
                'ujian_id' => $ujian->id,
                'siswa_id' => $p->siswa_id,
                'jenis' => TindakLanjut::PENGAYAAN,
                'nilai_awal' => $p->nilai,
                'nilai_akhir' => $i < 6 ? min(100, round((float) $p->nilai + mt_rand(2, 6))) : null,
                'tanggal' => $ujian->waktu_mulai->copy()->addWeek()->toDateString(),
                'bentuk' => $i % 2 === 0 ? 'soal_hots' : 'tutor_sebaya',
                'keterangan' => 'Latihan soal HOTS bilangan bulat dan pendampingan teman sekelas.',
                'status' => $i < 6 ? 'selesai' : 'direncanakan',
            ]);
        }
    }

    // =====================================================================
    // Log login contoh
    // =====================================================================

    protected function contohLogLogin(): void
    {
        $siswa = Siswa::aktif()->limit(12)->pluck('nisn');
        $guru = Guru::aktif()->limit(3)->pluck('nip');

        $perangkat = [
            ['desktop', 'Chrome', 'Windows 10/11'],
            ['mobile', 'Chrome', 'Android'],
            ['tablet', 'Safari', 'iOS'],
            ['desktop', 'Edge', 'Windows 10/11'],
        ];

        $baris = [];

        foreach (range(1, 60) as $i) {
            $peran = match (true) {
                $i % 9 === 0 => ['web', 'admin@ujian.test'],
                $i % 5 === 0 => ['guru', $guru[$i % max(1, $guru->count())] ?? '198001012005011001'],
                default => ['siswa', $siswa[$i % max(1, $siswa->count())] ?? '3201000001'],
            };

            [$device, $browser, $os] = $perangkat[$i % 4];
            // Sekitar seperlima percobaan sengaja gagal, seperti keadaan nyata.
            $sukses = $i % 5 !== 3;
            $waktu = now()->subDays(intdiv($i, 8))->subMinutes($i * 7);

            $baris[] = [
                'username' => $peran[1],
                'guard' => $peran[0],
                'success' => $sukses,
                'ip_address' => '192.168.1.'.(20 + ($i % 60)),
                'user_agent' => 'Contoh data seeder',
                'device_type' => $device,
                'browser' => $browser,
                'os' => $os,
                'attempt_no' => $sukses ? 1 : mt_rand(1, 4),
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ];
        }

        // Urutkan menaik menurut waktu sebelum disisipkan, supaya urutan id
        // sejalan dengan urutan waktunya seperti pada pemakaian sungguhan —
        // daftar "login terakhir" mengurutkan berdasarkan id.
        usort($baris, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);

        LoginAttempt::insert($baris);
    }

    // =====================================================================
    // Pembersihan
    // =====================================================================

    /**
     * Hapus data contoh dari seeding sebelumnya supaya perintah ini aman
     * dijalankan berulang kali. Ujian dihapus lebih dulu karena peserta,
     * jawaban, log dan tindak lanjutnya ikut terhapus lewat cascade.
     */
    protected function bersihkanDataContoh(): void
    {
        Ujian::where('kode_ujian', 'like', self::AWALAN.'%')->get()->each->delete();
        PaketSoal::where('kode_paket', 'like', self::AWALAN.'%')->get()->each->delete();
        LoginAttempt::where('user_agent', 'Contoh data seeder')->delete();
    }
}
