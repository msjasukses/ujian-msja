<?php

namespace Tests\Feature;

use App\Models\GuruMapel;
use App\Models\MataPelajaran;
use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\Soal;
use App\Models\TahunAjaran;
use App\Models\Ujian;
use App\Models\User;
use App\Support\Referensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pilihan kelas dan kandidat peserta dikerucutkan menurut mata pelajaran
 * paket soal yang dipilih. Pada sekolah dengan kelas moving tidak semua
 * rombel mengambil mapel yang sama, jadi menawarkan seluruh kelas sekolah
 * membuat guru mendaftarkan siswa yang tidak mengambil mapel itu.
 */
class KelasMapelUjianTest extends TestCase
{
    use RefreshDatabase;

    protected ?int $tahunAjaranId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tahunAjaranId = TahunAjaran::aktif()?->id;

        $this->actingAs(User::create([
            'name' => 'Admin Mapel', 'email' => 'mapel@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]));
    }

    public function test_rombel_mapel_mengikuti_penugasan_di_data_center(): void
    {
        $mapelId = $this->mapelTerpakai();

        $seharusnya = GuruMapel::where('mata_pelajaran_id', $mapelId)
            ->whereNotNull('rombongan_belajar_id')
            ->when($this->tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $this->tahunAjaranId))
            ->pluck('rombongan_belajar_id')
            ->map('intval')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $didapat = Referensi::rombelMapel($mapelId)->pluck('id')->map('intval')->sort()->values()->all();

        $this->assertNotEmpty($seharusnya, 'Data contoh harus punya penugasan mapel.');
        $this->assertSame($seharusnya, $didapat);

        // Rombel di luar penugasan mapel itu tidak ikut terbawa.
        $diLuar = RombonganBelajar::when($this->tahunAjaranId,
            fn ($q) => $q->where('tahun_ajaran_id', $this->tahunAjaranId))
            ->whereNotIn('id', $seharusnya)
            ->pluck('id');

        foreach ($diLuar as $id) {
            $this->assertNotContains((int) $id, $didapat);
        }
    }

    public function test_mapel_tanpa_penugasan_tetap_menampilkan_semua_kelas(): void
    {
        // Jaring pengaman: kalau penugasan mengajar di Data Center belum
        // terisi, guru tetap bisa memilih kelas alih-alih melihat daftar
        // kosong dan tidak bisa mendaftarkan peserta sama sekali.
        $tanpaPenugasan = MataPelajaran::whereNotIn('id',
            GuruMapel::distinct()->pluck('mata_pelajaran_id')->filter())
            ->value('id') ?? 999999;

        $this->assertEqualsCanonicalizing(
            Referensi::rombel()->pluck('id')->all(),
            Referensi::rombelMapel($tanpaPenugasan)->pluck('id')->all()
        );

        // Begitu juga ujian yang paketnya belum punya mata pelajaran.
        $this->assertEqualsCanonicalizing(
            Referensi::rombel()->pluck('id')->all(),
            Referensi::rombelMapel(null)->pluck('id')->all()
        );
    }

    public function test_pilihan_kelas_di_halaman_peserta_hanya_pengambil_mapel(): void
    {
        // Jalur mundur: mapel tanpa kelas moving di Data Center, sehingga
        // kandidatnya memang seluruh siswa rombel pengambil mapel itu.
        $mapelId = $this->mapelTanpaKelasMoving();
        $ujian = $this->ujian($mapelId);

        $halaman = $this->get(route('ujian.peserta.pilih', $ujian))->assertOk();

        $boleh = Referensi::rombelMapel($mapelId);
        foreach ($boleh as $k) {
            $halaman->assertSee('value="'.$k->id.'"', false);
        }

        // Kelas yang tidak mengambil mapel ini tidak ditawarkan.
        $diLuar = RombonganBelajar::when($this->tahunAjaranId,
            fn ($q) => $q->where('tahun_ajaran_id', $this->tahunAjaranId))
            ->whereNotIn('id', $boleh->pluck('id'))
            ->get();

        foreach ($diLuar as $k) {
            $halaman->assertDontSee('>'.$k->nama_rombel.'</option>', false);
        }

        $halaman->assertSee('kelas pengambil mapel ini');
    }

    public function test_kelas_di_luar_mapel_tidak_memunculkan_kandidat(): void
    {
        $mapelId = $this->mapelTerpakai();
        $ujian = $this->ujian($mapelId);

        $boleh = Referensi::rombelMapel($mapelId)->pluck('id')->map('intval')->all();
        $luar = RombonganBelajar::when($this->tahunAjaranId,
            fn ($q) => $q->where('tahun_ajaran_id', $this->tahunAjaranId))
            ->whereNotIn('id', $boleh)
            ->first();

        if (! $luar) {
            $this->markTestSkipped('Semua rombel aktif mengambil mapel ini, tidak ada pembanding.');
        }

        // Kelas luar dipaksa lewat query string: penyaringnya diabaikan dan
        // siswa kelas itu tetap tidak boleh muncul sebagai kandidat.
        $halaman = $this->get(route('ujian.peserta.pilih', $ujian).'?rombongan_belajar_id='.$luar->id)
            ->assertOk();

        $siswaLuar = Siswa::aktif()
            ->whereIn('id', SiswaRombel::where('rombongan_belajar_id', $luar->id)->pluck('siswa_id'))
            ->first();

        if ($siswaLuar) {
            $halaman->assertDontSee($siswaLuar->nama_siswa);
        }
    }

    public function test_paket_tanpa_mata_pelajaran_menjelaskan_sebabnya(): void
    {
        // Mata pelajaran pada paket soal boleh kosong. Bila kosong, kandidat
        // tidak mungkin dikerucutkan — guru harus diberi tahu sebabnya,
        // bukan dibiarkan melihat seluruh kelas tanpa keterangan.
        $ujian = $this->ujian(null);

        $this->assertSame(Referensi::TANPA_MAPEL, Referensi::alasanKelasTidakMengerucut(null));

        $this->get(route('ujian.peserta.pilih', $ujian))
            ->assertOk()
            ->assertSee('belum diisi')
            ->assertSee('mata pelajaran')
            ->assertDontSee('mengikuti mata pelajaran paket soal ujian ini');
    }

    public function test_mapel_tanpa_penugasan_menjelaskan_sebabnya(): void
    {
        // Mapel yang pasti tidak ada di guru_mapel. Data contoh memetakan
        // semua mapel ke semua rombel, jadi id sintetis dipakai supaya jalur
        // ini tetap teruji tanpa menulis ke database datacenter yang read-only.
        $tanpaPenugasan = (int) (MataPelajaran::whereNotIn('id',
            GuruMapel::distinct()->pluck('mata_pelajaran_id')->filter())
            ->value('id') ?? 999999);

        $this->assertSame(
            Referensi::TANPA_PENUGASAN,
            Referensi::alasanKelasTidakMengerucut($tanpaPenugasan)
        );

        $halaman = $this->get(route('ujian.peserta.pilih', $this->ujian($tanpaPenugasan)))->assertOk();

        $halaman->assertSee('belum mencatat kelas mana yang mengambil');
        // Jaring pengamannya tetap jalan: seluruh kelas tetap bisa dipilih.
        foreach (Referensi::rombel() as $k) {
            $halaman->assertSee('value="'.$k->id.'"', false);
        }
    }

    public function test_mapel_dengan_penugasan_tidak_memunculkan_peringatan(): void
    {
        $mapelId = $this->mapelTanpaKelasMoving();

        $this->assertNull(Referensi::alasanKelasTidakMengerucut($mapelId));

        $this->get(route('ujian.peserta.pilih', $this->ujian($mapelId)))
            ->assertOk()
            ->assertSee('kelas pengambil mapel ini')
            ->assertDontSee('belum mencatat kelas mana yang mengambil');
    }

    /** Mapel yang punya penugasan mengajar tapi belum punya kelas moving. */
    protected function mapelTanpaKelasMoving(): int
    {
        $id = GuruMapel::whereNotNull('rombongan_belajar_id')
            ->when($this->tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $this->tahunAjaranId))
            ->pluck('mata_pelajaran_id')
            ->unique()
            ->first(fn ($m) => Referensi::kelasMoving((int) $m)->isEmpty());

        if (! $id) {
            $this->markTestSkipped('Semua mapel berpenugasan sudah punya kelas moving.');
        }

        return (int) $id;
    }

    protected function mapelTerpakai(): int
    {
        $id = GuruMapel::whereNotNull('rombongan_belajar_id')
            ->when($this->tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $this->tahunAjaranId))
            ->value('mata_pelajaran_id');

        if (! $id) {
            $this->markTestSkipped('Database datacenter belum berisi penugasan guru_mapel.');
        }

        return (int) $id;
    }

    protected function ujian(?int $mapelId): Ujian
    {
        $paket = PaketSoal::create([
            'kode_paket' => 'KMAP-'.uniqid(), 'nama_paket' => 'Paket Mapel',
            'mata_pelajaran_id' => $mapelId, 'is_aktif' => true,
        ]);
        $soal = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Ibu kota Indonesia?', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'Jakarta'], ['key' => 'B', 'text' => 'Bandung']],
            'kunci' => ['A'],
        ]);
        PaketSoalDetail::create([
            'paket_soal_id' => $paket->id, 'soal_id' => $soal->id, 'nomor_urut' => 1, 'bobot' => 1,
        ]);

        return Ujian::create([
            'kode_ujian' => 'KMAP-'.uniqid(), 'nama_ujian' => 'Ujian Mapel Moving',
            'paket_soal_id' => $paket->id, 'mata_pelajaran_id' => $mapelId,
            'waktu_mulai' => now()->subHour(), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
            'mode_peserta' => Ujian::MODE_MANUAL,
        ]);
    }
}
