<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Siswa;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianLog;
use App\Models\UjianPeserta;
use App\Models\User;
use App\Services\PengerjaanUjianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Proteksi kecurangan: tab baru, layar terbelah, dan jendela mengambang.
 *
 * Pengenalan pelanggarannya memang terjadi di peramban dan tidak dapat diuji
 * dari sini. Yang diuji adalah bagian yang menentukan hasilnya: server tidak
 * boleh mempercayai angka kiriman peramban, tidak boleh mengunci lembar
 * sebelum batasnya tercapai, dan yang terkunci tidak boleh masih menerima
 * jawaban baru.
 */
class ProteksiUjianTest extends TestCase
{
    use RefreshDatabase;

    protected Siswa $siswa;

    protected Ujian $ujian;

    protected UjianPeserta $peserta;

    protected Soal $soal;

    protected function setUp(): void
    {
        parent::setUp();

        $siswa = Siswa::first();

        if (! $siswa) {
            $this->markTestSkipped('Database datacenter belum berisi data siswa.');
        }

        $this->siswa = $siswa;

        $paket = PaketSoal::create(['kode_paket' => 'PRO-1', 'nama_paket' => 'Paket', 'is_aktif' => true]);

        $this->soal = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Ibu kota Jawa Barat?', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'Bandung'], ['key' => 'B', 'text' => 'Serang']],
            'kunci' => ['A'],
        ]);

        PaketSoalDetail::create([
            'paket_soal_id' => $paket->id, 'soal_id' => $this->soal->id,
            'nomor_urut' => 1, 'bobot' => 1,
        ]);

        $this->ujian = Ujian::create([
            'kode_ujian' => 'PRO-UJN', 'nama_ujian' => 'Ujian Berproteksi',
            'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subMinutes(10), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
            'proteksi_ketat' => true, 'maks_pelanggaran' => 3,
        ]);

        $this->peserta = UjianPeserta::create([
            'ujian_id' => $this->ujian->id, 'siswa_id' => $this->siswa->id,
            'status' => UjianPeserta::TERDAFTAR,
        ]);

        app(PengerjaanUjianService::class)->mulai($this->peserta);
        $this->peserta->refresh();
    }

    // =====================================================================
    // Lembar ujian
    // =====================================================================

    public function test_lembar_memuat_lapisan_pengawasan(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $halaman = $this->get("/siswa/ujian/{$this->peserta->id}/kerjakan");

        $halaman->assertOk();
        $halaman->assertSee('tiraiPengawas', false);
        $halaman->assertSee('Layar terbelah terdeteksi', false);
        $halaman->assertSee('Jendela mengambang terdeteksi', false);
        // Alamat pelaporan tertanam lewat @json, yang meng-escape garis miring
        // menjadi \/ — dibandingkan dalam bentuk itu pula, bukan bentuk aslinya.
        $halaman->assertSee(
            trim(json_encode(route('siswa.ujian.pelanggaran', $this->peserta)), '"'),
            false
        );
    }

    /** Ulangan harian yang diawasi langsung tidak perlu diperlakukan seketat ujian sekolah. */
    public function test_lapisan_pengawasan_dapat_dimatikan_per_ujian(): void
    {
        $this->ujian->update(['proteksi_ketat' => false]);
        $this->actingAs($this->siswa, 'siswa');

        $this->get("/siswa/ujian/{$this->peserta->id}/kerjakan")
            ->assertOk()
            ->assertDontSee('Layar terbelah terdeteksi', false);
    }

    // =====================================================================
    // Pelaporan pelanggaran
    // =====================================================================

    #[DataProvider('jenisBerat')]
    public function test_pelanggaran_tercatat_dan_terhitung(string $jenis): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->lapor($jenis, 'Jendela 360x400 pada layar 360x800')
            ->assertOk()
            ->assertJson(['pelanggaran' => 1, 'maks' => 3, 'terkunci' => false, 'sisa' => 2]);

        $this->assertDatabaseHas('ujian_log', [
            'ujian_peserta_id' => $this->peserta->id,
            'event' => $jenis,
            'keterangan' => 'Jendela 360x400 pada layar 360x800',
        ]);

        $this->assertSame(1, $this->peserta->fresh()->pelanggaran);
    }

    /** @return array<string, array{string}> */
    public static function jenisBerat(): array
    {
        return [
            'tab baru' => ['tab_baru'],
            'layar terbelah' => ['layar_terbelah'],
            'keluar peramban ujian' => ['keluar_aplikasi'],
            'kehilangan fokus' => ['hilang_fokus'],
            'keluar layar penuh' => ['keluar_layar_penuh'],
        ];
    }

    /**
     * Menyalin dicatat tetapi tidak dihitung.
     *
     * Percobaannya kerap tidak disengaja — siswa menahan jarinya terlalu lama
     * di layar sentuh. Terlalu mahal bila tiga sentuhan panjang mengunci
     * lembar jawaban.
     */
    public function test_percobaan_menyalin_dicatat_tanpa_menambah_hitungan(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->lapor('salin_tempel')
            ->assertOk()
            ->assertJson(['pelanggaran' => 0, 'terkunci' => false]);

        $this->assertDatabaseHas('ujian_log', [
            'ujian_peserta_id' => $this->peserta->id,
            'event' => 'salin_tempel',
        ]);
    }

    /** Proteksi mati: pelanggarannya tetap masuk jejak, hanya tidak dihitung. */
    public function test_tanpa_proteksi_ketat_pelanggaran_hanya_dicatat(): void
    {
        $this->ujian->update(['proteksi_ketat' => false]);
        $this->actingAs($this->siswa, 'siswa');

        $this->lapor('layar_terbelah')->assertOk()->assertJson(['pelanggaran' => 0]);

        $this->assertDatabaseHas('ujian_log', ['event' => 'layar_terbelah']);
        $this->assertSame(0, $this->peserta->fresh()->pelanggaran);
    }

    // =====================================================================
    // Ketentuan hitung & kunci, satu tabel untuk seluruh jenis
    // =====================================================================

    /**
     * Setiap jenis pelanggaran diperlakukan sesuai ketentuannya.
     *
     * Ditulis sebagai satu tabel supaya jenis baru tidak bisa ditambahkan
     * diam-diam tanpa memutuskan bagaimana ia dihitung dan kapan ia mengunci.
     *
     * @param  bool  $dihitung  menambah hitungan pelanggaran peserta
     * @param  bool  $kunciSeketika  mengunci lembar pada kejadian pertama
     */
    #[DataProvider('ketentuanTiapJenis')]
    public function test_setiap_jenis_dihitung_dan_dikunci_sesuai_ketentuan(
        string $jenis, bool $dihitung, bool $kunciSeketika
    ): void {
        $this->actingAs($this->siswa, 'siswa');

        $this->lapor($jenis, 'Pemeriksaan ketentuan')->assertOk();

        $segar = $this->peserta->fresh();

        $this->assertSame($dihitung ? 1 : 0, $segar->pelanggaran,
            "Jenis {$jenis} salah dihitung.");

        $this->assertSame($kunciSeketika, (bool) $segar->dikunci_at,
            "Jenis {$jenis} salah dalam menentukan kunci seketika.");

        // Apa pun ketentuannya, kejadiannya selalu masuk jejak ujian.
        $this->assertDatabaseHas('ujian_log', [
            'ujian_peserta_id' => $this->peserta->id,
            'event' => $jenis,
        ]);
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function ketentuanTiapJenis(): array
    {
        return [
            // jenis                    dihitung  kunci seketika
            'tab baru' => ['tab_baru',           true,  false],
            'layar terbelah' => ['layar_terbelah',     true,  false],
            'keluar aplikasi' => ['keluar_aplikasi',    true,  false],
            'kehilangan fokus' => ['hilang_fokus',       true,  false],
            'keluar layar penuh' => ['keluar_layar_penuh', true,  false],

            // Berat: jendela kedua di atas lembar ujian tidak muncul sendiri.
            'jendela sembulan' => ['jendela_popup',      true,  true],
            'jendela mengambang' => ['layar_mengambang',   true,  true],

            // Ringan: sentuhan panjang di layar sentuh kerap tidak disengaja.
            'menyalin isi soal' => ['salin_tempel',       false, false],
        ];
    }

    // =====================================================================
    // Penguncian
    // =====================================================================

    /** Jendela sembulan dan mengambang mengunci tanpa menunggu batas. */
    #[DataProvider('jenisBerat2')]
    public function test_pelanggaran_berat_mengunci_seketika(string $jenis): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->lapor($jenis)
            ->assertOk()
            ->assertJson(['pelanggaran' => 1, 'terkunci' => true]);

        $this->assertDatabaseHas('ujian_log', [
            'ujian_peserta_id' => $this->peserta->id,
            'event' => 'dikunci',
        ]);

        $this->assertStringContainsString('dikunci seketika',
            (string) UjianLog::where('ujian_peserta_id', $this->peserta->id)
                ->where('event', 'dikunci')->value('keterangan'));
    }

    /** @return array<string, array{string}> */
    public static function jenisBerat2(): array
    {
        return [
            'jendela sembulan' => ['jendela_popup'],
            'jendela mengambang' => ['layar_mengambang'],
        ];
    }

    /**
     * Batas "Tidak pernah" tetap dihormati, juga oleh pelanggaran berat.
     *
     * Guru yang memilih setelan itu sudah memutuskan lembar tidak boleh
     * dikunci sendiri oleh sistem. Mengabaikannya membuat setelan itu
     * berbohong kepada yang memilihnya.
     */
    public function test_batas_nol_menahan_kunci_seketika(): void
    {
        $this->ujian->update(['maks_pelanggaran' => 0]);
        $this->actingAs($this->siswa, 'siswa');

        $this->lapor('layar_mengambang')
            ->assertOk()
            ->assertJson(['pelanggaran' => 1, 'terkunci' => false]);

        $this->assertNull($this->peserta->fresh()->dikunci_at);
    }

    /** Tombol keluar dari layar terkunci, supaya peserta tidak terpaku. */
    public function test_layar_terkunci_menyediakan_tautan_ruang_ujian(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->get("/siswa/ujian/{$this->peserta->id}/kerjakan")
            ->assertOk()
            ->assertSee('tiraiDashboard', false)
            ->assertSee('Kembali ke Ruang Ujian', false)
            ->assertSee(route('siswa.ujian.index'), false);
    }

    public function test_lembar_dikunci_setelah_mencapai_batas(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        // Ketiganya sengaja jenis biasa: yang diuji di sini penguncian karena
        // batas tercapai, bukan penguncian seketika milik pelanggaran berat.
        $this->lapor('layar_terbelah')->assertJson(['terkunci' => false, 'sisa' => 2]);
        $this->lapor('tab_baru')->assertJson(['terkunci' => false, 'sisa' => 1]);
        $this->lapor('keluar_layar_penuh')->assertJson(['terkunci' => true, 'sisa' => 0]);

        $this->assertNotNull($this->peserta->fresh()->dikunci_at);

        $this->assertDatabaseHas('ujian_log', [
            'ujian_peserta_id' => $this->peserta->id,
            'event' => 'dikunci',
            'keterangan' => 'Pelanggaran mencapai batas 3 kali.',
        ]);
    }

    /** Batas 0 berarti hanya memperingatkan; lembarnya tidak pernah dikunci sendiri. */
    public function test_batas_nol_tidak_pernah_mengunci(): void
    {
        $this->ujian->update(['maks_pelanggaran' => 0]);
        $this->actingAs($this->siswa, 'siswa');

        for ($i = 0; $i < 6; $i++) {
            $this->lapor('layar_terbelah')->assertJson(['terkunci' => false]);
        }

        $this->assertSame(6, $this->peserta->fresh()->pelanggaran);
        $this->assertNull($this->peserta->fresh()->dikunci_at);
    }

    /**
     * Lembar terkunci menolak jawaban baru.
     *
     * Tirai di layar hanya menutupi soal; siswa yang mematikan JavaScript
     * masih bisa mengirim jawaban langsung ke endpoint ini kalau server tidak
     * ikut menolaknya.
     */
    public function test_lembar_terkunci_menolak_jawaban_baru(): void
    {
        $this->peserta->forceFill(['dikunci_at' => now(), 'pelanggaran' => 3])->save();
        $this->actingAs($this->siswa, 'siswa');

        $this->postJson("/siswa/ujian/{$this->peserta->id}/simpan", [
            'soal_id' => $this->soal->id,
            'jawaban' => 'A',
        ])->assertStatus(423)->assertJson(['ok' => false, 'terkunci' => true]);

        $this->assertDatabaseMissing('ujian_jawaban', [
            'ujian_peserta_id' => $this->peserta->id,
            'jawaban' => '"A"',
        ]);
    }

    /**
     * Keluar dari peramban ujian dihitung sebagai pelanggaran.
     *
     * Inilah jalan keluar yang paling mudah ditempuh siswa: menekan tombol
     * keluar ExamBro, menekan tombol Beranda, atau berpindah aplikasi.
     * Halaman tidak dapat mencegahnya, jadi harus menghitungnya.
     */
    public function test_keluar_peramban_ujian_dihitung_dan_mengunci(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->lapor('keluar_aplikasi', 'Halaman ujian disembunyikan')
            ->assertOk()
            ->assertJson(['pelanggaran' => 1, 'sisa' => 2]);

        $this->lapor('keluar_aplikasi')->assertJson(['pelanggaran' => 2]);
        $this->lapor('keluar_aplikasi')->assertJson(['pelanggaran' => 3, 'terkunci' => true]);

        $this->assertNotNull($this->peserta->fresh()->dikunci_at);
    }

    /**
     * Keadaan pengawasan dapat ditanyakan ulang.
     *
     * Kepergian dilaporkan lewat sendBeacon yang tidak mengembalikan apa pun,
     * jadi lembar ujian menanyakan hasilnya begitu peserta kembali.
     */
    public function test_status_pengawasan_dapat_ditanyakan(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->getJson(route('siswa.ujian.pengawasan', $this->peserta))
            ->assertOk()
            ->assertJson(['pelanggaran' => 0, 'maks' => 3, 'terkunci' => false, 'sisa' => 3]);

        $this->lapor('keluar_aplikasi');

        $this->getJson(route('siswa.ujian.pengawasan', $this->peserta))
            ->assertOk()
            ->assertJson(['pelanggaran' => 1, 'terkunci' => false, 'sisa' => 2]);
    }

    /**
     * Pelanggaran terakhir ikut dikabarkan.
     *
     * Peramban ujian yang ditutup lalu dibuka lagi memuat halaman dari awal,
     * sehingga tirai yang seharusnya tampil saat peserta pergi tidak pernah
     * sempat muncul. Keterangan inilah yang dipakai menampilkannya menyusul.
     */
    public function test_keadaan_menyebut_pelanggaran_terakhir(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $awal = $this->getJson(route('siswa.ujian.pengawasan', $this->peserta))->json();
        $this->assertNull($awal['terakhir']);
        $this->assertNull($awal['terakhir_detik']);

        $this->lapor('keluar_aplikasi');

        $sesudah = $this->getJson(route('siswa.ujian.pengawasan', $this->peserta))->json();
        $this->assertSame('keluar_aplikasi', $sesudah['terakhir']);
        $this->assertIsInt($sesudah['terakhir_detik']);
        $this->assertLessThan(45, $sesudah['terakhir_detik']);
    }

    /** Penguncian bukan perbuatan peserta, jadi tidak boleh jadi "terakhir". */
    public function test_kejadian_dikunci_tidak_dihitung_sebagai_pelanggaran_terakhir(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->lapor('keluar_aplikasi');
        $this->lapor('tab_baru');
        $this->lapor('layar_terbelah');       // mencapai batas, lembar dikunci

        $keadaan = $this->getJson(route('siswa.ujian.pengawasan', $this->peserta))->json();

        $this->assertTrue($keadaan['terkunci']);
        $this->assertSame('layar_terbelah', $keadaan['terakhir']);
    }

    public function test_status_pengawasan_tertutup_bagi_peserta_lain(): void
    {
        $lain = Siswa::where('id', '!=', $this->siswa->id)->first();

        if (! $lain) {
            $this->markTestSkipped('Butuh dua siswa untuk menguji pembatasan antar peserta.');
        }

        $this->actingAs($lain, 'siswa')
            ->getJson(route('siswa.ujian.pengawasan', $this->peserta))
            ->assertForbidden();
    }

    /** Lembar ujian memuat penegakan fokus, layar penuh, dan tombol muat ulang. */
    public function test_lembar_memuat_proteksi_fokus_layar_penuh_dan_tombol_muat_ulang(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->get("/siswa/ujian/{$this->peserta->id}/kerjakan")
            ->assertOk()
            ->assertSee('Lembar ujian kehilangan fokus', false)
            ->assertSee('Anda keluar dari mode layar penuh', false)
            ->assertSee('tombolMuatUlang', false)
            ->assertSee('Muat ulang lembar ujian', false);
    }

    /**
     * Kehilangan fokus kembali diperiksa di perangkat sentuh, tanpa
     * document.hasFocus().
     *
     * Panel yang melayang di atas lembar ujian — asisten Gemini, gelembung
     * obrolan — tidak menyembunyikan halaman dan tidak mengubah ukurannya.
     * Satu-satunya jejaknya adalah fokus yang pindah. Pemeriksaan ini sempat
     * dimatikan di perangkat sentuh karena document.hasFocus() melapor palsu di
     * WebView Android, dan akibatnya panel Gemini lolos.
     */
    public function test_kehilangan_fokus_diperiksa_tanpa_hasfocus(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $isi = $this->get("/siswa/ujian/{$this->peserta->id}/kerjakan")->getContent();

        // Tidak lagi dimatikan di perangkat sentuh.
        $this->assertStringNotContainsString('if (perangkatSentuh()) return;', $isi);

        // Tidak lagi bergantung pada document.hasFocus() — hanya komentar
        // penjelasnya yang boleh menyebutnya.
        $kode = preg_replace('#/\*.*?\*/#s', '', $isi);
        $this->assertStringNotContainsString('document.hasFocus()', $kode);

        // Kehilangan fokus harus bertahan sebelum dilaporkan.
        $this->assertStringContainsString('var TUNDA_FOKUS = 1500;', $isi);

        // Kotak pilihan penjodohan dikecualikan: di Android ia dibuka sebagai
        // dialog sistem yang ikut mengambil fokus jendela.
        $this->assertStringContainsString("aktif.tagName === 'SELECT'", $isi);

        // Menyentuh halaman membatalkan laporan yang sedang ditunggu.
        $this->assertStringContainsString("addEventListener('pointerdown', batalkanPenantiFokus, true)", $isi);
    }

    /**
     * Ambang geometri diukur dari ukuran istirahat perangkat.
     *
     * Angka tetap tidak bisa dipakai: perangkat sekolah yang diuji menyisakan
     * viewport setinggi 0,61 layar dalam keadaan diam, sementara ambang
     * tetapnya 0,62 — perangkat itu dinyatakan terbelah hanya karena diam.
     */
    public function test_ambang_geometri_relatif_terhadap_ukuran_istirahat(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $isi = $this->get("/siswa/ujian/{$this->peserta->id}/kerjakan")->getContent();

        $this->assertStringContainsString('puncak.lebar  * SUSUT', $isi);
        $this->assertStringContainsString('LANTAI_TINGGI', $isi);

        // Lebar dibandingkan dengan lebar, tinggi dengan tinggi. Normalisasi
        // sisi panjang/pendek yang lama salah mengenali layar ponsel yang
        // dibelah ke bawah sebagai jendela mengambang.
        $this->assertStringContainsString('sebagaiPotret', $isi);
        $this->assertStringNotContainsString('Math.max(w, h) / Math.max(sw, sh)', $isi);

        // Acuannya dipelajari ulang setelah layar diputar, sebab rasio yang
        // bisa dicapai tiap sisi ikut berubah.
        $this->assertStringContainsString('puncak = null;', $isi);

        // Angka tetap yang lama tidak boleh tertinggal.
        $this->assertStringNotContainsString('AMBANG_PANJANG', $isi);
    }

    /**
     * Lembar ujian dibuka dengan gerbang layar penuh.
     *
     * Layar penuh tidak bisa dibawa dari tombol "Mulai Ujian": peramban
     * melepasnya begitu halaman berpindah. Gerbang inilah tindakan pengguna
     * di halaman soal yang sah dipakai memintanya.
     */
    public function test_lembar_dibuka_dengan_gerbang_layar_penuh(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $isi = $this->get("/siswa/ujian/{$this->peserta->id}/kerjakan")
            ->assertOk()
            ->assertSee('gerbangLayarPenuh', false)
            ->assertSee('Mulai dalam layar penuh', false)
            ->getContent();

        // Gerbang tidak boleh bisa menjebak: ada lingkungan yang janji
        // requestFullscreen()-nya tidak pernah selesai.
        $this->assertStringContainsString('setTimeout(lepasGerbang, 2000)', $isi);

        // Memuat ulang dan mengumpulkan lembar melepas layar penuh secara sah.
        $this->assertStringContainsString('if (keluarSah || bernavigasi) return;', $isi);
    }

    /** Halaman konfirmasi memberi tahu lebih dulu, sebelum tombol Mulai ditekan. */
    public function test_konfirmasi_menyebut_mode_layar_penuh(): void
    {
        $peserta = UjianPeserta::create([
            'ujian_id' => $this->ujian->id,
            'siswa_id' => Siswa::where('id', '!=', $this->siswa->id)->value('id') ?? $this->siswa->id,
            'status' => UjianPeserta::TERDAFTAR,
        ]);

        $this->actingAs($peserta->siswa, 'siswa')
            ->get("/siswa/ujian/{$peserta->id}")
            ->assertOk()
            ->assertSee('mode layar penuh', false);

        $this->ujian->update(['proteksi_ketat' => false]);

        $this->get("/siswa/ujian/{$peserta->id}")
            ->assertOk()
            ->assertDontSee('mode layar penuh', false);
    }

    /** Layar blokir menyebut keadaannya, bukan sekadar memperingatkan. */
    public function test_lembar_memuat_layar_terblokir(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->get("/siswa/ujian/{$this->peserta->id}/kerjakan")
            ->assertOk()
            ->assertSee('Ujian Terblokir', false)
            ->assertSee('Anda meninggalkan lembar ujian', false)
            ->assertSee('Lembar jawaban dikunci', false)
            // Identitas peserta ikut tampil supaya pengawas yang dipanggil
            // langsung tahu lembar siapa yang dihadapinya.
            ->assertSee(trim(json_encode($this->siswa->nama_siswa), '"'), false);
    }

    // =====================================================================
    // Batas kepercayaan terhadap kiriman peramban
    // =====================================================================

    public function test_jenis_pelanggaran_di_luar_daftar_ditolak(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->lapor('mulai')->assertStatus(422);
        $this->lapor('apa-saja')->assertStatus(422);

        $this->assertSame(0, $this->peserta->fresh()->pelanggaran);
    }

    /** Kunci hanya boleh lahir dari perhitungan server, bukan dari kiriman peramban. */
    public function test_peramban_tidak_dapat_mengirim_kejadian_dikunci(): void
    {
        $this->actingAs($this->siswa, 'siswa');

        $this->lapor('dikunci')->assertStatus(422);

        $this->assertNull($this->peserta->fresh()->dikunci_at);
        $this->assertDatabaseMissing('ujian_log', ['event' => 'dikunci']);
    }

    public function test_pelanggaran_tidak_dapat_dilaporkan_atas_nama_peserta_lain(): void
    {
        $lain = Siswa::where('id', '!=', $this->siswa->id)->first();

        if (! $lain) {
            $this->markTestSkipped('Butuh dua siswa untuk menguji pembatasan antar peserta.');
        }

        $this->actingAs($lain, 'siswa');

        $this->lapor('layar_terbelah')->assertForbidden();

        $this->assertSame(0, $this->peserta->fresh()->pelanggaran);
    }

    // =====================================================================
    // Pengawas
    // =====================================================================

    public function test_pengawas_membuka_kunci_dan_menolkan_hitungan(): void
    {
        $this->peserta->forceFill(['dikunci_at' => now(), 'pelanggaran' => 3])->save();

        $this->actingAs($this->admin())
            ->post(route('monitoring.buka-kunci', [$this->ujian, $this->peserta]), ['alasan' => 'Salah tekan'])
            ->assertRedirect();

        $segar = $this->peserta->fresh();

        $this->assertNull($segar->dikunci_at);
        $this->assertSame(0, $segar->pelanggaran);

        $this->assertDatabaseHas('ujian_log', [
            'ujian_peserta_id' => $this->peserta->id,
            'event' => 'dibuka_pengawas',
            'keterangan' => 'Salah tekan',
        ]);

        // Setelah dibuka, lembarnya menerima jawaban lagi.
        $this->actingAs($this->siswa, 'siswa')
            ->postJson("/siswa/ujian/{$this->peserta->id}/simpan", [
                'soal_id' => $this->soal->id, 'jawaban' => 'A',
            ])->assertOk();
    }

    /**
     * Reset ikut membuka kuncinya.
     *
     * Reset berarti mengulang dari awal. Tanpa ini peserta memulai lagi dengan
     * lembar yang masih terblokir dan langsung berhenti di layar yang sama —
     * pengawas harus menekan dua tombol untuk satu maksud.
     */
    public function test_reset_pengerjaan_ikut_membuka_kunci(): void
    {
        $this->peserta->forceFill(['dikunci_at' => now(), 'pelanggaran' => 3])->save();

        $this->actingAs($this->admin())
            ->post(route('monitoring.reset', [$this->ujian, $this->peserta]), ['alasan' => 'Mengulang dari awal'])
            ->assertRedirect();

        $segar = $this->peserta->fresh();

        $this->assertNull($segar->dikunci_at);
        $this->assertSame(0, $segar->pelanggaran);
        $this->assertSame(UjianPeserta::TERDAFTAR, $segar->status);
        $this->assertSame(1, $segar->reset_count);
    }

    /** Kedua aksi tetap ada pada tabel yang disegarkan, bukan hanya saat halaman dimuat. */
    public function test_tabel_monitoring_menyediakan_aksi_reset_dan_aktifkan(): void
    {
        $this->peserta->forceFill(['dikunci_at' => now(), 'pelanggaran' => 3])->save();

        $this->actingAs($this->admin())
            ->get(route('monitoring.show', $this->ujian))
            ->assertOk()
            ->assertSee('Aktifkan kembali lembar terblokir')
            ->assertSee('Reset pengerjaan')
            // Pola alamat yang dipakai penyegar untuk membangun ulang tombolnya.
            ->assertSee('__PESERTA__', false)
            ->assertSee(trim(json_encode(route('monitoring.reset', [$this->ujian, '__PESERTA__'])), '"'), false)
            ->assertSee(trim(json_encode(route('monitoring.buka-kunci', [$this->ujian, '__PESERTA__'])), '"'), false);
    }

    public function test_membuka_lembar_yang_tidak_terkunci_tidak_mengubah_apa_pun(): void
    {
        $this->peserta->forceFill(['pelanggaran' => 2])->save();

        $this->actingAs($this->admin())
            ->post(route('monitoring.buka-kunci', [$this->ujian, $this->peserta]))
            ->assertRedirect()
            ->assertSessionHas('error');

        // Hitungannya tidak ikut dinolkan oleh penekanan tombol yang keliru.
        $this->assertSame(2, $this->peserta->fresh()->pelanggaran);
    }

    public function test_monitoring_menampilkan_pelanggaran_dan_kunci(): void
    {
        $this->peserta->forceFill(['dikunci_at' => now(), 'pelanggaran' => 3])->save();

        $this->actingAs($this->admin())
            ->get(route('monitoring.show', $this->ujian))
            ->assertOk()
            ->assertSee('lembar jawaban terkunci')
            ->assertSee('Pelanggaran');

        $this->actingAs($this->admin())
            ->getJson(route('monitoring.data', $this->ujian))
            ->assertOk()
            ->assertJsonPath('ringkasan.terkunci', 1)
            ->assertJsonPath('peserta.0.pelanggaran', 3)
            ->assertJsonPath('peserta.0.terkunci', true);
    }

    public function test_export_monitoring_memuat_kolom_pelanggaran(): void
    {
        $this->peserta->forceFill(['dikunci_at' => now(), 'pelanggaran' => 3])->save();

        $respons = $this->actingAs($this->admin())->get(route('monitoring.export', $this->ujian));
        $respons->assertOk();

        ob_start();
        $respons->baseResponse->sendContent();
        $isi = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'uji').'.xlsx';
        file_put_contents($path, $isi);

        $teks = collect(IOFactory::load($path)->getActiveSheet()->toArray())
            ->flatten()->filter()->implode(' | ');

        @unlink($path);

        $this->assertStringContainsString('Jml Pelanggaran', $teks);
        $this->assertStringContainsString('Terkunci', $teks);
    }

    // =====================================================================

    private function lapor(string $jenis, ?string $keterangan = null)
    {
        return $this->postJson("/siswa/ujian/{$this->peserta->id}/pelanggaran", array_filter([
            'jenis' => $jenis,
            'keterangan' => $keterangan,
        ]));
    }

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'pengawas@ujian.test'],
            ['name' => 'Pengawas', 'password' => 'rahasia123', 'role' => User::ROLE_ADMIN, 'is_aktif' => true]
        );
    }
}
