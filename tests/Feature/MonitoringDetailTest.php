<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianLog;
use App\Models\UjianPeserta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Halaman detail Monitoring Ujian: kartu angka yang bisa diklik, penyaring
 * status, dan rincian pelanggaran per siswa.
 */
class MonitoringDetailTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Ujian $ujian;

    /** @var array<string, UjianPeserta> */
    protected array $p = [];

    protected function setUp(): void
    {
        parent::setUp();

        $siswa = Siswa::orderBy('id')->take(5)->pluck('id');

        if ($siswa->count() < 5) {
            $this->markTestSkipped('Database datacenter belum berisi cukup siswa.');
        }

        $this->admin = User::create([
            'name' => 'Pengawas', 'email' => 'pengawas@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $paket = PaketSoal::create(['kode_paket' => 'DET-P', 'nama_paket' => 'Paket', 'is_aktif' => true]);

        $this->ujian = Ujian::create([
            'kode_ujian' => 'DET-UJN', 'nama_ujian' => 'Ujian Detail', 'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subMinutes(10), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
        ]);

        $buat = fn (int $i, string $status) => UjianPeserta::create([
            'ujian_id' => $this->ujian->id, 'siswa_id' => $siswa[$i], 'status' => $status,
            'waktu_mulai' => $status === UjianPeserta::TERDAFTAR ? null : now()->subMinutes(5),
        ]);

        $this->p['belum'] = $buat(0, UjianPeserta::TERDAFTAR);
        $this->p['curang'] = $buat(1, UjianPeserta::MULAI);      // sedang, melanggar, terkunci
        $this->p['tertib'] = $buat(2, UjianPeserta::MULAI);      // sedang, bersih
        $this->p['direset'] = $buat(3, UjianPeserta::SELESAI);   // selesai, pernah melanggar
        $this->p['menyalin'] = $buat(4, UjianPeserta::SELESAI);  // selesai, hanya menyalin

        $this->langgar('curang', 'hilang_fokus');
        $this->langgar('curang', 'hilang_fokus');
        $this->langgar('curang', 'tab_baru');
        $this->langgar('curang', 'dikunci');
        $this->p['curang']->forceFill(['pelanggaran' => 3, 'dikunci_at' => now()])->save();

        // Pernah melanggar, lalu kuncinya dibuka: penghitungnya nol, jejaknya tetap.
        $this->langgar('direset', 'layar_terbelah');

        $this->langgar('menyalin', 'salin_tempel');
    }

    // =====================================================================
    // Kartu angka
    // =====================================================================

    public function test_kartu_menghitung_setiap_kelompok_termasuk_melanggar(): void
    {
        $this->assertSame(
            ['terdaftar' => 5, 'belum' => 1, 'sedang' => 2, 'selesai' => 2, 'melanggar' => 2],
            array_intersect_key($this->ringkasan(), array_flip(['terdaftar', 'belum', 'sedang', 'selesai', 'melanggar']))
        );
    }

    /** Menyaring tabel tidak boleh mengubah angka di kartu. */
    public function test_angka_kartu_tetap_saat_tabel_disaring(): void
    {
        $this->assertSame($this->ringkasan(), $this->ringkasan(['status' => 'selesai']));
    }

    /** Setiap kartu adalah tautan ke daftar kelompoknya. */
    public function test_kartu_menaut_ke_daftarnya(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('monitoring.show', $this->ujian))
            ->assertOk()
            ->assertSee('Melanggar')
            ->getContent();

        foreach (['belum', 'sedang', 'selesai', 'melanggar'] as $status) {
            $this->assertStringContainsString(
                e(route('monitoring.show', ['ujian' => $this->ujian, 'status' => $status])),
                $html
            );
        }
    }

    // =====================================================================
    // Penyaring status
    // =====================================================================

    /** @param  list<string>  $harapan */
    #[DataProvider('saringan')]
    public function test_penyaring_status(string $status, array $harapan): void
    {
        $tampil = collect($this->data(['status' => $status])['peserta'])->pluck('id')->sort()->values()->all();
        $semestinya = collect($harapan)->map(fn ($k) => $this->p[$k]->id)->sort()->values()->all();

        $this->assertSame($semestinya, $tampil);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function saringan(): array
    {
        return [
            'belum mulai' => ['belum', ['belum']],
            'sedang mengerjakan' => ['sedang', ['curang', 'tertib']],
            'selesai' => ['selesai', ['direset', 'menyalin']],
            // "direset" tetap terhitung; "menyalin" tidak.
            'melanggar' => ['melanggar', ['curang', 'direset']],
            'tak dikenal = semua' => ['ngawur', ['belum', 'curang', 'tertib', 'direset', 'menyalin']],
        ];
    }

    /** Halaman penuh juga menyaring, bukan hanya endpoint penyegarnya. */
    public function test_halaman_menyaring_dan_menyebut_saringannya(): void
    {
        $respons = $this->actingAs($this->admin)
            ->get(route('monitoring.show', ['ujian' => $this->ujian, 'status' => 'melanggar']))
            ->assertOk()
            ->assertSee('tampilkan semua');

        $this->assertEqualsCanonicalizing(
            [$this->p['curang']->id, $this->p['direset']->id],
            $respons->viewData('peserta')->pluck('id')->all()
        );
    }

    /** Peringatan lembar terkunci tetap tampil walau siswanya tersaring keluar dari tabel. */
    public function test_peringatan_terkunci_tidak_ikut_tersaring(): void
    {
        $this->actingAs($this->admin)
            ->get(route('monitoring.show', ['ujian' => $this->ujian, 'status' => 'belum']))
            ->assertOk()
            ->assertSee('1 lembar jawaban terkunci');
    }

    public function test_saringan_kosong_memberi_pesan(): void
    {
        $this->p['belum']->forceFill(['status' => UjianPeserta::MULAI])->save();

        $this->actingAs($this->admin)
            ->get(route('monitoring.show', ['ujian' => $this->ujian, 'status' => 'belum']))
            ->assertOk()
            ->assertSee('Tidak ada peserta dengan status ini.');
    }

    // =====================================================================
    // Rincian pelanggaran
    // =====================================================================

    public function test_rincian_pelanggaran_per_siswa(): void
    {
        $baris = collect($this->data()['peserta'])->keyBy('id');

        $this->assertSame(
            [['label' => 'Hilang fokus', 'jumlah' => 2], ['label' => 'Tab baru', 'jumlah' => 1]],
            $baris[$this->p['curang']->id]['rincian']
        );

        // Hitungannya sudah nol, tetapi rinciannya tetap terbaca.
        $this->assertSame(0, $baris[$this->p['direset']->id]['pelanggaran']);
        $this->assertSame([['label' => 'Layar terbelah', 'jumlah' => 1]], $baris[$this->p['direset']->id]['rincian']);

        // Percobaan menyalin tidak termasuk.
        $this->assertFalse($baris[$this->p['menyalin']->id]['melanggar']);
        $this->assertSame([], $baris[$this->p['menyalin']->id]['rincian']);

        $this->actingAs($this->admin)
            ->get(route('monitoring.show', $this->ujian))
            ->assertSee('Hilang fokus 2×')
            ->assertSee('Layar terbelah 1×');
    }

    /** Export mengikuti saringan yang sedang tampil, beserta rinciannya. */
    public function test_export_mengikuti_saringan(): void
    {
        $respons = $this->actingAs($this->admin)
            ->get(route('monitoring.export', ['ujian' => $this->ujian, 'status' => 'melanggar']));
        $respons->assertOk();

        ob_start();
        $respons->baseResponse->sendContent();
        $isi = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'uji').'.xlsx';
        file_put_contents($path, $isi);
        $baris = IOFactory::load($path)->getActiveSheet()->toArray();
        @unlink($path);

        $teks = collect($baris)->flatten()->filter()->implode(' | ');

        $this->assertStringContainsString('Hanya: Melanggar', $teks);
        $this->assertStringContainsString('Rincian Pelanggaran', $teks);
        $this->assertStringContainsString('Hilang fokus 2x; Tab baru 1x', $teks);

        // Dua peserta melanggar = dua baris bernomor.
        $bernomor = collect($baris)->filter(fn ($r) => is_numeric($r[0] ?? null));
        $this->assertCount(2, $bernomor);
    }

    // =====================================================================

    private function langgar(string $siapa, string $jenis): void
    {
        UjianLog::create(['ujian_id' => $this->ujian->id, 'ujian_peserta_id' => $this->p[$siapa]->id, 'event' => $jenis]);
    }

    /** @return array<string, mixed> */
    private function data(array $saring = []): array
    {
        return $this->actingAs($this->admin)
            ->getJson(route('monitoring.data', ['ujian' => $this->ujian] + $saring))
            ->assertOk()
            ->json();
    }

    /** @return array<string, int> */
    private function ringkasan(array $saring = []): array
    {
        return $this->data($saring)['ringkasan'];
    }
}
