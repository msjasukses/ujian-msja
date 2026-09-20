<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Siswa;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Models\User;
use App\Services\RegistrasiPesertaService;
use App\Support\Referensi;
use App\Support\TeksSoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/**
 * Soal berbahasa Arab dan bersimbol matematika ditelusuri dari ujung ke ujung:
 * disimpan lewat formulir, dibaca kembali di halaman guru, dikerjakan siswa,
 * masuk lewat impor Word & Excel, dan keluar lewat export.
 *
 * Karakter di luar ASCII gampang rusak di titik mana pun — pengaturan tabel
 * database, penyaringan HTML, parser impor, penulis berkas Excel — jadi setiap
 * titik itu diuji, bukan hanya penyimpanannya.
 */
class SoalArabMatematikaTest extends TestCase
{
    use RefreshDatabase;

    protected const ARAB = 'مَا مَعْنَى كَلِمَةِ "الْمَدْرَسَةُ" فِي اللُّغَةِ الْإِنْدُونِيسِيَّةِ؟';

    protected const MATEMATIKA = 'Nilai dari √144 × (−3)² ÷ 6 adalah ...';

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $this->actingAs($this->admin);
    }

    // =====================================================================
    // Formulir input soal
    // =====================================================================

    public function test_soal_arab_tersimpan_lewat_formulir(): void
    {
        $this->post(route('soal.store'), [
            'jenis' => Soal::PG,
            'pertanyaan' => self::ARAB,
            'bobot' => 2,
            'opsi_text' => ['مَكْتَبَةٌ', 'مَدْرَسَةٌ', 'Sekolah', 'Perpustakaan'],
            'kunci_pg' => ['C'],
        ])->assertRedirect();

        $soal = Soal::latest('id')->first();

        $this->assertSame(self::ARAB, $soal->pertanyaan);
        // Harakat pada opsi juga harus utuh, bukan hanya pada pertanyaan.
        $this->assertSame('مَدْرَسَةٌ', $soal->opsi[1]['text']);
    }

    public function test_soal_matematika_dengan_penanda_tersimpan_dan_tampil(): void
    {
        $pecahan = '<span class="pecahan"><span class="pembilang">1</span><span class="penyebut">2</span></span>';

        $this->post(route('soal.store'), [
            'jenis' => Soal::PG,
            'pertanyaan' => 'Hasil dari '.$pecahan.' + ¼ adalah ... (petunjuk: x<sup>2</sup> ≥ 0)',
            'bobot' => 2,
            'opsi_text' => ['¾', '⅔', '⅘', '½'],
            'kunci_pg' => ['A'],
        ])->assertRedirect();

        $soal = Soal::latest('id')->first();

        $tampilan = $this->get(route('soal.show', $soal));
        $tampilan->assertOk();

        // Penanda pecahan dan pangkat harus sampai ke halaman sebagai HTML,
        // bukan sebagai tulisan "&lt;sup&gt;".
        $tampilan->assertSee('class="pecahan"', false);
        $tampilan->assertSee('<sup>2</sup>', false);
        $tampilan->assertSee('≥', false);
        // Simbol pada opsi jawaban ikut tampil.
        $tampilan->assertSee('¾', false);
    }

    public function test_script_dalam_soal_tidak_ikut_tersimpan_ke_halaman(): void
    {
        $this->post(route('soal.store'), [
            'jenis' => Soal::PG,
            'pertanyaan' => 'Perhatikan gambar <script>alert("kunci")</script> berikut.',
            'bobot' => 1,
            'opsi_text' => ['A', 'B'],
            'kunci_pg' => ['A'],
        ])->assertRedirect();

        $soal = Soal::latest('id')->first();

        $this->get(route('soal.show', $soal))
            ->assertOk()
            ->assertDontSee('<script>alert', false)
            ->assertSee('Perhatikan gambar');
    }

    /**
     * Formulir harus mengirimkan editor beserta tombol rumusnya.
     *
     * Ketiga tombol ini sempat "tidak berfungsi" bagi guru karena kolomnya
     * masih berupa textarea polos — penanda yang disisipkan tampil sebagai tag
     * mentah, bukan sebagai pangkat, akar, dan pecahan.
     */
    public function test_formulir_menyediakan_editor_dan_tombol_rumus(): void
    {
        $halaman = $this->get(route('soal.create'));

        $halaman->assertOk();
        $halaman->assertSee('window.EditorSoal', false);
        // Tiap kolom teks soal ditandai untuk dipasangi editor.
        $halaman->assertSee('data-sisip-target', false);

        // Pangkat & indeks memakai perintah bawaan peramban.
        $halaman->assertSee('data-perintah="superscript"', false);
        $halaman->assertSee('data-perintah="subscript"', false);

        // Pecahan bertingkat dan akar disisipkan sebagai penanda siap pakai,
        // lengkap dengan bentuk untuk keadaan tanpa teks yang ditandai.
        $halaman->assertSee('class="pembilang" data-kursor="1"', false);
        $halaman->assertSee('class="radikan" data-kursor="1"', false);
    }

    /** Susunan yang dihasilkan editor harus lolos utuh sampai ke layar siswa. */
    public function test_rumus_hasil_editor_bertahan_sampai_tampil(): void
    {
        // Persis bentuk yang dikirim editor, termasuk spasi tanpa pemisah.
        $rumus = 'Hitung&nbsp;<span class="akar"><span class="radikan">225</span></span>'
            .' + <span class="pecahan"><span class="pembilang">3</span>'
            .'<span class="penyebut">4</span></span> + r<sup>2</sup> cm';

        $this->post(route('soal.store'), [
            'jenis' => Soal::PG,
            'pertanyaan' => $rumus,
            'bobot' => 2,
            'opsi_text' => ['15 cm<sup>2</sup>', '16 cm<sup>2</sup>'],
            'kunci_pg' => ['A'],
        ])->assertRedirect();

        $soal = Soal::latest('id')->firstOrFail();
        $tampilan = $this->get(route('soal.show', $soal));

        $tampilan->assertOk();
        $tampilan->assertSee('class="akar"', false);
        $tampilan->assertSee('class="radikan"', false);
        $tampilan->assertSee('class="pembilang"', false);
        $tampilan->assertSee('class="penyebut"', false);
        $tampilan->assertSee('r<sup>2</sup>', false);
        // Pangkat pada opsi jawaban ikut terbawa.
        $tampilan->assertSee('15 cm<sup>2</sup>', false);

        // Versi polosnya tetap terbaca untuk daftar dan berkas export.
        $this->assertSame('Hitung 225 + 34 + r2 cm', TeksSoal::polos($soal->pertanyaan));
    }

    /**
     * Formulir harus membawa pengenal rumus untuk tempelan dari luar.
     *
     * Word dan Google Dokumen jarang memakai <sup>: keduanya menandai pangkat
     * lewat style "vertical-align", sedangkan rumus dari Word 365, Wikipedia,
     * dan situs bermatematika datang sebagai MathML atau KaTeX. Tanpa
     * pengenalan itu, penyaring tempelan membuang bentuk aslinya dan rumus
     * rata menjadi teks datar.
     */
    public function test_formulir_mengenali_rumus_dari_tempelan_luar(): void
    {
        $halaman = $this->get(route('soal.create'));

        $halaman->assertOk();
        $halaman->assertSee('normalkanRumus', false);

        // MathML: pangkat, indeks, pecahan, akar, dan akar berderajat.
        foreach (['msup', 'msub', 'mfrac', 'msqrt', 'mroot'] as $tag) {
            $halaman->assertSee("case '{$tag}'", false);
        }

        // Pangkat yang ditulis sebagai gaya, bukan sebagai tag.
        $halaman->assertSee('vertical-align', false);

        // Salinan ganda dari KaTeX dan MathJax.
        $halaman->assertSee('.katex, mjx-container', false);

        // Bungkus <b> dari Google Dokumen yang menetralkan gayanya sendiri.
        $halaman->assertSee('font-weight', false);
    }

    /**
     * Formulir harus membawa pengurai LaTeX.
     *
     * Bentuk tempelan yang paling sering ditemui bukan MathML, melainkan kode
     * LaTeX apa adanya — "\(4\sqrt{3}\)" — karena banyak situs dan keluaran
     * asisten AI hanya mengirim teks itu ke papan klip. Tanpa penguraian,
     * kode mentahnya yang muncul di badan soal.
     */
    public function test_formulir_mengurai_rumus_latex(): void
    {
        $halaman = $this->get(route('soal.create'));

        $halaman->assertOk();
        $halaman->assertSee('dariLatex', false);
        $halaman->assertSee('konversiLatexDalamTeks', false);

        // Perintah yang paling sering dipakai pada soal sekolah.
        $halaman->assertSee("nama === 'frac'", false);
        $halaman->assertSee("nama === 'sqrt'", false);

        // Rumus LaTeX yang tersisip di dalam tempelan HTML ikut diurai.
        $halaman->assertSee('uraiLatexPadaTeks', false);

        // Jalan cadangan untuk kode yang terlanjur tertempel sebagai tulisan.
        $halaman->assertSee('ubahLatexTerpilih', false);
        $halaman->assertSee('Ubah LaTeX terpilih', false);
    }

    /** Rumus hasil penguraian LaTeX harus lolos penyaring seperti rumus lain. */
    public function test_hasil_penguraian_latex_lolos_penyaring(): void
    {
        // Persis yang dihasilkan pengurai dari "\(4\sqrt{3} - 2\sqrt{3}\)".
        $rumus = 'Berapakah hasil dari 4<span class="akar"><span class="radikan">3</span></span>'
            .' - 2<span class="akar"><span class="radikan">3</span></span>?';

        $this->post(route('soal.store'), [
            'jenis' => Soal::PG,
            'pertanyaan' => $rumus,
            'bobot' => 1,
            'opsi_text' => ['2<span class="akar"><span class="radikan">3</span></span>', '6'],
            'kunci_pg' => ['A'],
        ])->assertRedirect();

        $soal = Soal::latest('id')->firstOrFail();

        $this->get(route('soal.show', $soal))
            ->assertOk()
            ->assertSee('class="akar"', false)
            ->assertSee('class="radikan"', false);

        // Tidak ada sisa kode LaTeX yang ikut tersimpan.
        $this->assertStringNotContainsString('\\sqrt', $soal->pertanyaan);
        $this->assertStringNotContainsString('\\(', $soal->pertanyaan);
    }

    /**
     * Penyaring tempelan harus ikut membuang simpul komentar.
     *
     * Tanpa itu, penanda bawaan Google Dokumen ikut tersimpan ke bank soal:
     * tidak tampak di layar, tetapi menyulitkan siapa pun yang kelak membaca
     * isi soalnya — dan sempat benar-benar terjadi pada data contoh.
     */
    public function test_penyaring_tempelan_membuang_komentar(): void
    {
        $halaman = $this->get(route('soal.create'));

        $halaman->assertOk();
        $halaman->assertSee('SHOW_COMMENT', false);
        $halaman->assertSee('StartFragment', false);
    }

    // =====================================================================
    // Ringkasan kunci penjodohan
    // =====================================================================

    /**
     * Ringkasan kunci penjodohan memakai panah "→" tepat sesudah variabel.
     *
     * PHP memperlakukan bita 0x80–0xFF sebagai huruf yang sah untuk pengenal,
     * jadi "$k→$v" terbaca sebagai variabel bernama "k→" dan halaman daftar
     * soal berhenti dengan galat. Kedua jalur yang menampilkannya diuji di
     * sini — kesalahan seperti ini tidak menghasilkan tanda apa pun sampai
     * halamannya benar-benar dibuka.
     */
    public function test_daftar_soal_menampilkan_ringkasan_kunci_penjodohan(): void
    {
        $soal = Soal::create([
            'jenis' => Soal::PENJODOHAN,
            'pertanyaan' => 'Jodohkan negara dengan ibu kotanya!',
            'opsi' => [
                'kiri' => [['key' => '1', 'text' => 'Jepang'], ['key' => '2', 'text' => 'Korea']],
                'kanan' => [['key' => 'A', 'text' => 'Tokyo'], ['key' => 'B', 'text' => 'Seoul']],
            ],
            'kunci' => ['1' => 'A', '2' => 'B'],
            'bobot' => 2,
            'is_aktif' => true,
        ]);

        $this->assertSame('1→A, 2→B', $soal->kunci_ringkas);

        $this->get(route('soal.index'))
            ->assertOk()
            ->assertSee('1→A, 2→B');
    }

    /** Halaman pratinjau impor menampilkan ringkasan yang sama. */
    public function test_pratinjau_impor_menampilkan_kunci_penjodohan(): void
    {
        $path = $this->berkasWord([
            '1. Jodohkan negara dengan ibu kotanya!',
            'Jepang ## Tokyo',
            'Korea ## Seoul',
        ]);

        $this->post('/soal/import/pratinjau', [
            'sumber' => 'word',
            'berkas' => new UploadedFile($path, 'naskah.docx', null, null, true),
        ])->assertRedirect();

        $this->get(route('soal.import.form', ['sumber' => 'word']))
            ->assertOk()
            ->assertSee('1→A, 2→B');
    }

    // =====================================================================
    // Halaman siswa
    // =====================================================================

    public function test_siswa_melihat_soal_arab_dan_simbol_saat_ujian(): void
    {
        $rombel = Referensi::rombel()->first();
        $siswa = $rombel
            ? Siswa::aktif()->whereHas('rombelSemua', fn ($q) => $q->where('rombongan_belajar_id', $rombel->id))->first()
            : null;

        if (! $siswa) {
            $this->markTestSkipped('Database datacenter belum berisi rombel aktif berisi siswa.');
        }

        $soal = Soal::create([
            'jenis' => Soal::PG,
            'pertanyaan' => self::ARAB,
            'opsi' => [
                ['key' => 'A', 'text' => 'Sekolah'],
                ['key' => 'B', 'text' => 'x² ≥ 0'],
            ],
            'kunci' => ['A'],
            'bobot' => 1,
            'is_aktif' => true,
        ]);

        $ujian = $this->ujianBerisi($soal, $rombel->id);
        $peserta = $ujian->peserta()->where('siswa_id', $siswa->id)->firstOrFail();

        $this->actingAs($siswa, 'siswa');
        $this->post("/siswa/ujian/{$peserta->id}/mulai", ['token' => 'ARAB01']);

        $halaman = $this->get("/siswa/ujian/{$peserta->id}/kerjakan");

        $halaman->assertOk();
        // assertSee membubuhkan escape yang sama seperti penyaji, jadi tanda
        // kutip di dalam kalimat Arab ikut terbandingkan sebagaimana tampil.
        $halaman->assertSee(self::ARAB);
        $halaman->assertSee('x² ≥ 0');
        // Arah teks otomatis supaya kalimat Arab rata kanan.
        $halaman->assertSee('dir="auto"', false);
    }

    // =====================================================================
    // Impor
    // =====================================================================

    public function test_impor_word_mempertahankan_arab_dan_simbol(): void
    {
        $path = $this->berkasWord([
            '1. '.self::MATEMATIKA,
            'A. 6',
            'B. ½',
            'C. √2',
            'D. 18',
            'KUNCI: A',
            '2. '.self::ARAB,
            'A. مَكْتَبَةٌ',
            'B. Sekolah',
            'KUNCI: B',
        ]);

        $this->post('/soal/import/pratinjau', [
            'sumber' => 'word',
            'berkas' => new UploadedFile($path, 'naskah.docx', null, null, true),
        ])->assertRedirect();

        $this->simpanSeluruhPratinjau();

        $this->assertDatabaseHas('soal', ['pertanyaan' => self::MATEMATIKA]);
        $this->assertDatabaseHas('soal', ['pertanyaan' => self::ARAB]);

        $matematika = Soal::where('pertanyaan', self::MATEMATIKA)->firstOrFail();
        $this->assertSame('√2', $matematika->opsi[2]['text']);

        $arab = Soal::where('pertanyaan', self::ARAB)->firstOrFail();
        $this->assertSame('مَكْتَبَةٌ', $arab->opsi[0]['text']);
    }

    public function test_impor_excel_mempertahankan_arab_dan_simbol(): void
    {
        $path = $this->berkasExcel([
            ['jenis', 'pertanyaan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'kunci', 'bobot'],
            ['pg', self::MATEMATIKA, '6', '½', '√2', '18', '', 'A', 2],
            ['pg', self::ARAB, 'مَكْتَبَةٌ', 'مَدْرَسَةٌ', 'Sekolah', 'Rumah', '', 'B', 2],
        ]);

        $this->post('/soal/import/pratinjau', [
            'sumber' => 'excel',
            'berkas' => new UploadedFile($path, 'bank.xlsx', null, null, true),
        ])->assertRedirect();

        $this->simpanSeluruhPratinjau();

        $arab = Soal::where('pertanyaan', self::ARAB)->firstOrFail();

        $this->assertSame('مَكْتَبَةٌ', $arab->opsi[0]['text']);
        $this->assertSame('مَدْرَسَةٌ', $arab->opsi[1]['text']);
    }

    // =====================================================================
    // Export
    // =====================================================================

    public function test_export_bank_soal_mempertahankan_arab_dan_simbol(): void
    {
        Soal::create([
            'jenis' => Soal::PG,
            'pertanyaan' => self::ARAB,
            'opsi' => [['key' => 'A', 'text' => 'Sekolah'], ['key' => 'B', 'text' => 'Rumah']],
            'kunci' => ['A'],
            'bobot' => 1,
            'is_aktif' => true,
        ]);

        Soal::create([
            'jenis' => Soal::PG,
            'pertanyaan' => self::MATEMATIKA,
            'opsi' => [['key' => 'A', 'text' => '6'], ['key' => 'B', 'text' => '½']],
            'kunci' => ['A'],
            'bobot' => 1,
            'is_aktif' => true,
        ]);

        $isi = $this->isiUnduhan($this->get(route('soal.export')));

        // Berkas xlsx berupa arsip zip, jadi diperiksa lewat pembacaan ulang.
        $path = tempnam(sys_get_temp_dir(), 'uji').'.xlsx';
        file_put_contents($path, $isi);

        $teks = collect(
            \PhpOffice\PhpSpreadsheet\IOFactory::load($path)
                ->getActiveSheet()
                ->toArray()
        )->flatten()->filter()->implode(' | ');

        @unlink($path);

        $this->assertStringContainsString(self::ARAB, $teks);
        $this->assertStringContainsString('√144', $teks);
        $this->assertStringContainsString('(−3)²', $teks);
    }

    // =====================================================================
    // Pembantu
    // =====================================================================

    protected function ujianBerisi(Soal $soal, int $rombelId): Ujian
    {
        $paket = PaketSoal::create([
            'kode_paket' => 'UJI-ARAB',
            'nama_paket' => 'Paket Uji Arab',
            'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
            'is_aktif' => true,
        ]);

        PaketSoalDetail::create([
            'paket_soal_id' => $paket->id,
            'soal_id' => $soal->id,
            'nomor_urut' => 1,
            'bobot' => $soal->bobot,
        ]);

        $ujian = Ujian::create([
            'kode_ujian' => 'UJI-ARAB',
            'nama_ujian' => 'Ujian Uji Arab',
            'paket_soal_id' => $paket->id,
            'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
            'waktu_mulai' => now()->subMinutes(5),
            'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60,
            'token' => 'ARAB01',
            'kkm' => 75,
            'status' => Ujian::AKTIF,
        ]);

        UjianKelas::create(['ujian_id' => $ujian->id, 'rombongan_belajar_id' => $rombelId]);
        app(RegistrasiPesertaService::class)->sinkronkan($ujian);

        return $ujian;
    }

    /** Simpan seluruh butir hasil pratinjau impor. */
    /**
     * Tempuh alur simpan sebagaimana guru melakukannya: buka halaman
     * pratinjau lebih dulu, baru tekan Simpan.
     *
     * Langkah "buka halaman" itu bukan hiasan — pratinjau sempat dititipkan
     * sebagai flash session yang habis begitu halamannya digambar, sehingga
     * penekanan tombol Simpan selalu ditolak. Menyuntikkan ulang isi session
     * di dalam tes akan menyembunyikan persis kegagalan itu.
     */
    protected function simpanSeluruhPratinjau(): void
    {
        $pratinjau = session('import_soal_pratinjau');
        $this->assertNotNull($pratinjau, 'Pratinjau tidak tersimpan setelah berkas diunggah.');

        $this->get(route('soal.import.form'))->assertOk();

        $this->post('/soal/import/simpan', ['pilih' => array_keys($pratinjau['soal'])])
            ->assertRedirect(route('soal.index'));
    }

    /** @param array<int, string> $baris */
    protected function berkasWord(array $baris): string
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        foreach ($baris as $teks) {
            $section->addText($teks);
        }

        $path = tempnam(sys_get_temp_dir(), 'uji').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    /** @param array<int, array<int, mixed>> $baris */
    protected function berkasExcel(array $baris): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();

        foreach ($baris as $i => $kolom) {
            foreach (array_values($kolom) as $j => $nilai) {
                $sheet->setCellValue([$j + 1, $i + 1], $nilai);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'uji').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    protected function isiUnduhan($respons): string
    {
        ob_start();
        $respons->baseResponse->sendContent();

        return ob_get_clean();
    }
}
