<?php

namespace Tests\Feature;

use App\Models\KelasMoving;
use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\Soal;
use App\Models\TahunAjaran;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Models\User;
use App\Support\Referensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kandidat peserta diambil dari kelas moving yang sudah disusun di Data
 * Center: satu kelas moving = satu mata pelajaran dengan anggota lintas
 * rombel. Daftar inilah rombongan peserta sebenarnya, bukan seluruh isi
 * rombel yang kebetulan mengambil mapel yang sama.
 */
class KelasMovingDatacenterTest extends TestCase
{
    use RefreshDatabase;

    protected ?KelasMoving $kelasMoving = null;

    protected function setUp(): void
    {
        parent::setUp();

        $tahunAjaranId = TahunAjaran::aktif()?->id;

        $this->kelasMoving = KelasMoving::aktif()
            ->with('anggota')
            ->when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->get()
            ->first(fn ($km) => $km->anggota->isNotEmpty());

        if (! $this->kelasMoving) {
            $this->markTestSkipped('Data Center belum berisi kelas moving beranggota.');
        }

        $this->actingAs(User::create([
            'name' => 'Admin Moving DC', 'email' => 'movingdc@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]));
    }

    public function test_kandidat_hanya_anggota_kelas_moving_mapel_itu(): void
    {
        $ujian = $this->ujian($this->kelasMoving->mata_pelajaran_id);
        $anggota = Siswa::aktif()->whereIn('id', $this->kelasMoving->siswaIds())->get();

        $halaman = $this->get(route('ujian.peserta.pilih', $ujian))->assertOk();

        foreach ($anggota as $s) {
            $halaman->assertSee($s->nama_siswa);
        }

        $halaman->assertSee('Kandidat diambil dari');
        $halaman->assertSee($this->kelasMoving->nama_kelas_moving);

        // Teman sekelas asal yang BUKAN anggota kelas moving tidak ikut muncul
        // — inilah bedanya dengan menarik seluruh isi rombel.
        $bukanAnggota = $this->bukanAnggotaSekelas();

        if ($bukanAnggota) {
            $halaman->assertDontSee($bukanAnggota->nama_siswa);
        }
    }

    public function test_anggota_kelas_moving_bisa_langsung_didaftarkan(): void
    {
        $ujian = $this->ujian($this->kelasMoving->mata_pelajaran_id);
        $ids = Siswa::aktif()->whereIn('id', $this->kelasMoving->siswaIds())->pluck('id')->all();

        $this->post(route('ujian.peserta.tambah', $ujian), ['siswa_id' => $ids])
            ->assertSessionHasNoErrors();

        $this->assertSame(count($ids), $ujian->peserta()->count());

        // Rombel asal tetap tercatat supaya rekap nilai per kelas tetap benar.
        $tahunAjaranId = TahunAjaran::aktif()?->id;
        $asal = SiswaRombel::whereIn('siswa_id', $ids)
            ->when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->pluck('rombongan_belajar_id', 'siswa_id');

        foreach ($ujian->peserta()->get() as $p) {
            $this->assertSame(UjianPeserta::SUMBER_MANUAL, $p->sumber);
            $this->assertSame((int) $asal[$p->siswa_id], (int) $p->rombongan_belajar_id);
        }
    }

    public function test_penyaring_kelas_asal_bekerja_di_dalam_kelas_moving(): void
    {
        $ujian = $this->ujian($this->kelasMoving->mata_pelajaran_id);
        $tahunAjaranId = TahunAjaran::aktif()?->id;

        $asal = SiswaRombel::whereIn('siswa_id', $this->kelasMoving->siswaIds())
            ->when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->pluck('rombongan_belajar_id', 'siswa_id');

        if ($asal->unique()->count() < 2) {
            $this->markTestSkipped('Kelas moving ini anggotanya hanya dari satu rombel.');
        }

        $rombelId = $asal->first();
        $halaman = $this->get(route('ujian.peserta.pilih', $ujian).'?rombongan_belajar_id='.$rombelId)->assertOk();

        foreach ($asal as $siswaId => $rid) {
            $siswa = Siswa::aktif()->find($siswaId);

            if (! $siswa) {
                continue;
            }

            (int) $rid === (int) $rombelId
                ? $halaman->assertSee($siswa->nama_siswa)
                : $halaman->assertDontSee($siswa->nama_siswa);
        }
    }

    public function test_mapel_tanpa_kelas_moving_mundur_ke_kelas_pengambil_mapel(): void
    {
        // Mapel yang tidak punya kelas moving: daftarnya kembali ke seluruh
        // siswa rombel pengambil mapel, dengan keterangan yang jelas.
        $mapelLain = Referensi::mapel()
            ->first(fn ($m) => Referensi::kelasMoving($m->id)->isEmpty());

        if (! $mapelLain) {
            $this->markTestSkipped('Semua mapel sudah punya kelas moving.');
        }

        $this->get(route('ujian.peserta.pilih', $this->ujian($mapelLain->id)))
            ->assertOk()
            ->assertSee('belum punya kelas moving di Data Center')
            ->assertDontSee('Kandidat diambil dari');
    }

    /** Siswa aktif sekelas asal dengan seorang anggota, tapi bukan anggota. */
    protected function bukanAnggotaSekelas(): ?Siswa
    {
        $tahunAjaranId = TahunAjaran::aktif()?->id;
        $anggotaIds = $this->kelasMoving->siswaIds();

        $rombelIds = SiswaRombel::whereIn('siswa_id', $anggotaIds)
            ->when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->pluck('rombongan_belajar_id');

        $kandidat = SiswaRombel::whereIn('rombongan_belajar_id', $rombelIds)
            ->when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->whereNotIn('siswa_id', $anggotaIds)
            ->pluck('siswa_id');

        return Siswa::aktif()->whereIn('id', $kandidat)->first();
    }

    protected function ujian(int $mapelId): Ujian
    {
        $paket = PaketSoal::create([
            'kode_paket' => 'KMDC-'.uniqid(), 'nama_paket' => 'Paket Kelas Moving',
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
            'kode_ujian' => 'KMDC-'.uniqid(), 'nama_ujian' => 'Ujian Kelas Moving DC',
            'paket_soal_id' => $paket->id, 'mata_pelajaran_id' => $mapelId,
            'waktu_mulai' => now()->subHour(), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
            'mode_peserta' => Ujian::MODE_MANUAL,
        ]);
    }
}
