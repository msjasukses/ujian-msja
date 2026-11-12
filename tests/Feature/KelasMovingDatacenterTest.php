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
use App\Models\UjianKelasMoving;
use App\Models\UjianPeserta;
use App\Models\User;
use App\Services\RegistrasiPesertaService;
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

    public function test_kelas_moving_dipasang_dari_form_mendaftarkan_anggotanya(): void
    {
        $mapelId = $this->kelasMoving->mata_pelajaran_id;
        $paket = $this->paket($mapelId);

        $this->post('/ujian', $this->formUjian($paket->id, [
            'mode_peserta' => Ujian::MODE_MANUAL,
            'kelas_moving_id' => [$this->kelasMoving->id],
        ]))->assertSessionHasNoErrors();

        $ujian = Ujian::where('kode_ujian', 'KMF-1')->firstOrFail();
        $anggotaAktif = Siswa::aktif()->whereIn('id', $this->kelasMoving->siswaIds())->pluck('id');

        $this->assertSame([$this->kelasMoving->id], $ujian->kelasMovingIds());
        $this->assertEqualsCanonicalizing(
            $anggotaAktif->all(),
            $ujian->peserta()->pluck('siswa_id')->map('intval')->all()
        );
    }

    public function test_kelas_moving_mapel_lain_tidak_bisa_dipasang(): void
    {
        // Rombongan harus sesuai naskahnya: kelas moving mapel lain ditolak.
        $mapelLain = Referensi::mapel()->first(fn ($m) => (int) $m->id !== (int) $this->kelasMoving->mata_pelajaran_id);
        $paket = $this->paket((int) $mapelLain->id);

        $this->post('/ujian', $this->formUjian($paket->id, [
            'mode_peserta' => Ujian::MODE_MANUAL,
            'kelas_moving_id' => [$this->kelasMoving->id],
        ]))->assertSessionHasNoErrors();

        $ujian = Ujian::where('kode_ujian', 'KMF-1')->firstOrFail();

        $this->assertSame([], $ujian->kelasMovingIds());
    }

    public function test_sinkron_mengikuti_susunan_kelas_moving(): void
    {
        $ujian = $this->ujian($this->kelasMoving->mata_pelajaran_id);
        UjianKelasMoving::create(['ujian_id' => $ujian->id, 'kelas_moving_id' => $this->kelasMoving->id]);

        $hasil = app(RegistrasiPesertaService::class)->sinkronkan($ujian);
        $anggotaAktif = Siswa::aktif()->whereIn('id', $this->kelasMoving->siswaIds())->pluck('id');

        $this->assertSame($anggotaAktif->count(), $hasil['ditambah']);

        // Dua siswa di luar kelas moving: yang bawaan kelas dicabut saat
        // sinkron, yang ditambahkan guru satu per satu tetap bertahan.
        $luar = Siswa::aktif()->whereNotIn('id', $this->kelasMoving->siswaIds())->take(2)->get();

        if ($luar->count() < 2) {
            $this->markTestSkipped('Butuh dua siswa aktif di luar kelas moving.');
        }

        $registrasi = app(RegistrasiPesertaService::class);
        $registrasi->daftarkanSiswa($ujian, [$luar[0]->id], UjianPeserta::SUMBER_MANUAL);
        $registrasi->daftarkanSiswa($ujian, [$luar[1]->id], UjianPeserta::SUMBER_KELAS);

        $registrasi->sinkronkan($ujian);

        $this->assertDatabaseHas('ujian_peserta', [
            'ujian_id' => $ujian->id, 'siswa_id' => $luar[0]->id, 'sumber' => UjianPeserta::SUMBER_MANUAL,
        ]);
        $this->assertDatabaseMissing('ujian_peserta', [
            'ujian_id' => $ujian->id, 'siswa_id' => $luar[1]->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ganti
     * @return array<string, mixed>
     */
    protected function formUjian(int $paketId, array $ganti = []): array
    {
        return $ganti + [
            'kode_ujian' => 'KMF-1',
            'nama_ujian' => 'Ujian Kelas Moving dari Form',
            'paket_soal_id' => $paketId,
            'tahun_ajaran' => TahunAjaran::aktif()?->nama_tahun_ajaran,
            'waktu_mulai' => now()->addDay()->format('Y-m-d H:i'),
            'waktu_selesai' => now()->addDay()->addHours(2)->format('Y-m-d H:i'),
            'durasi_menit' => 60,
            'kkm' => 75,
            'maks_pelanggaran' => 3,
            'status' => Ujian::DRAFT,
        ];
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

    protected function paket(int $mapelId): PaketSoal
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

        return $paket;
    }

    protected function ujian(int $mapelId): Ujian
    {
        $paket = $this->paket($mapelId);

        return Ujian::create([
            'kode_ujian' => 'KMDC-'.uniqid(), 'nama_ujian' => 'Ujian Kelas Moving DC',
            'paket_soal_id' => $paket->id, 'mata_pelajaran_id' => $mapelId,
            'waktu_mulai' => now()->subHour(), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
            'mode_peserta' => Ujian::MODE_MANUAL,
        ]);
    }
}
