<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\PaketSoal;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Models\UjianLog;
use App\Models\UjianPeserta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Halaman daftar Monitoring Ujian: jumlah siswa yang melanggar, serta
 * penyaring mata pelajaran dan kelas.
 *
 * Susunannya: ujian A untuk kelas 1 dan kelas 2, ujian B hanya untuk kelas 2,
 * masing-masing dengan mata pelajaran berbeda.
 */
class MonitoringDaftarTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    /** @var Collection<int, MataPelajaran> */
    protected Collection $mapel;

    /** @var Collection<int, RombonganBelajar> */
    protected Collection $rombel;

    /** @var Collection<int, int> */
    protected Collection $siswa;

    protected Ujian $ujianA;

    protected Ujian $ujianB;

    /** @var array<string, UjianPeserta> */
    protected array $peserta = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapel = MataPelajaran::orderBy('id')->take(2)->get();
        $this->rombel = RombonganBelajar::orderBy('id')->take(3)->get();
        $this->siswa = Siswa::orderBy('id')->take(4)->pluck('id');

        if ($this->mapel->count() < 2 || $this->rombel->count() < 3 || $this->siswa->count() < 4) {
            $this->markTestSkipped('Database datacenter belum berisi cukup mapel, rombel, dan siswa.');
        }

        $this->admin = User::create([
            'name' => 'Pengawas', 'email' => 'pengawas@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        [$kelas1, $kelas2] = [$this->rombel[0]->id, $this->rombel[1]->id];

        $this->ujianA = $this->ujian('MON-A', 'Ujian A', $this->mapel[0]->id, [$kelas1, $kelas2]);
        $this->ujianB = $this->ujian('MON-B', 'Ujian B', $this->mapel[1]->id, [$kelas2]);

        // Ujian A: dua anak kelas 1, satu anak kelas 2.
        $this->peserta['berulang'] = $this->peserta($this->ujianA, 0, $kelas1);   // melanggar tiga kali
        $this->peserta['menyalin'] = $this->peserta($this->ujianA, 1, $kelas1);   // hanya menyalin
        $this->peserta['kelas2'] = $this->peserta($this->ujianA, 2, $kelas2);     // sekali, lalu direset

        foreach (['layar_terbelah', 'hilang_fokus', 'tab_baru'] as $jenis) {
            $this->langgar($this->peserta['berulang'], $jenis);
        }
        $this->langgar($this->peserta['berulang'], 'dikunci');
        $this->peserta['berulang']->forceFill(['pelanggaran' => 3, 'dikunci_at' => now()])->save();

        $this->langgar($this->peserta['menyalin'], 'salin_tempel');

        $this->langgar($this->peserta['kelas2'], 'layar_mengambang');
        // Pengawas mereset: penghitungnya kembali nol, jejaknya tetap.
        $this->peserta['kelas2']->forceFill(['pelanggaran' => 0, 'dikunci_at' => null])->save();

        // Ujian B: satu anak kelas 2, bersih.
        $this->peserta['bersih'] = $this->peserta($this->ujianB, 3, $kelas2);
    }

    // =====================================================================
    // Jumlah siswa melanggar
    // =====================================================================

    public function test_jumlah_siswa_melanggar_dihitung_per_siswa(): void
    {
        $items = $this->daftar();

        // "berulang" tiga kali tetap satu siswa; "kelas2" tetap terhitung
        // walau penghitungnya sudah direset; "menyalin" tidak dihitung.
        $this->assertSame(2, (int) $items->firstWhere('id', $this->ujianA->id)->melanggar_count);
        $this->assertSame(0, (int) $items->firstWhere('id', $this->ujianB->id)->melanggar_count);
    }

    public function test_lembar_terkunci_dihitung_tersendiri(): void
    {
        $items = $this->daftar();

        $this->assertSame(1, (int) $items->firstWhere('id', $this->ujianA->id)->terkunci_count);

        $this->actingAs($this->admin)
            ->get(route('monitoring.index'))
            ->assertSee('Siswa Melanggar')
            ->assertSee('1 terkunci');
    }

    // =====================================================================
    // Penyaring
    // =====================================================================

    public function test_penyaring_mata_pelajaran(): void
    {
        $items = $this->daftar(['mata_pelajaran_id' => $this->mapel[0]->id]);

        $this->assertSame([$this->ujianA->id], $items->pluck('id')->all());
    }

    public function test_penyaring_kelas_memilih_ujian_kelas_itu(): void
    {
        $this->assertSame([$this->ujianA->id],
            $this->daftar(['rombongan_belajar_id' => $this->rombel[0]->id])->pluck('id')->all());

        $this->assertEqualsCanonicalizing([$this->ujianA->id, $this->ujianB->id],
            $this->daftar(['rombongan_belajar_id' => $this->rombel[1]->id])->pluck('id')->all());
    }

    /** Disaring per kelas, setiap angka hanya menghitung anak kelas itu. */
    public function test_penyaring_kelas_menyempitkan_angka(): void
    {
        $kelas1 = $this->daftar(['rombongan_belajar_id' => $this->rombel[0]->id])
            ->firstWhere('id', $this->ujianA->id);

        $this->assertSame(2, (int) $kelas1->peserta_count);
        $this->assertSame(1, (int) $kelas1->melanggar_count);
        $this->assertSame(1, (int) $kelas1->terkunci_count);

        $kelas2 = $this->daftar(['rombongan_belajar_id' => $this->rombel[1]->id])
            ->firstWhere('id', $this->ujianA->id);

        $this->assertSame(1, (int) $kelas2->peserta_count);
        $this->assertSame(1, (int) $kelas2->melanggar_count);
        $this->assertSame(0, (int) $kelas2->terkunci_count);
    }

    public function test_kedua_penyaring_bisa_digabung(): void
    {
        $this->assertSame([], $this->daftar([
            'mata_pelajaran_id' => $this->mapel[1]->id,
            'rombongan_belajar_id' => $this->rombel[0]->id,
        ])->pluck('id')->all());
    }

    /** Pilihan penyaring hanya berisi mapel dan kelas yang memang punya ujian. */
    public function test_pilihan_penyaring_hanya_yang_punya_ujian(): void
    {
        $respons = $this->actingAs($this->admin)->get(route('monitoring.index'))->assertOk();

        $this->assertEqualsCanonicalizing(
            $this->mapel->pluck('id')->all(),
            $respons->viewData('pilihanMapel')->pluck('id')->all()
        );

        $this->assertEqualsCanonicalizing(
            [$this->rombel[0]->id, $this->rombel[1]->id],
            $respons->viewData('pilihanRombel')->pluck('id')->all(),
            'Rombel ketiga tidak dipakai ujian mana pun dan tidak boleh muncul.'
        );
    }

    /** Disaring per kelas: ada keterangannya, dan tombol Pantau membuka kelas itu. */
    public function test_saring_kelas_diteruskan_ke_halaman_pantau(): void
    {
        $kelas = $this->rombel[0];

        $this->actingAs($this->admin)
            ->get(route('monitoring.index', ['rombongan_belajar_id' => $kelas->id]))
            ->assertOk()
            ->assertSee('hanya menghitung peserta kelas')
            ->assertSee($kelas->nama_rombel)
            ->assertSee(route('monitoring.show', [$this->ujianA, 'rombongan_belajar_id' => $kelas->id]), false);
    }

    // =====================================================================

    /** @return Collection<int, Ujian> */
    private function daftar(array $saring = []): Collection
    {
        return collect($this->actingAs($this->admin)
            ->get(route('monitoring.index', $saring))
            ->assertOk()
            ->viewData('items')
            ->items());
    }

    private function ujian(string $kode, string $nama, int $mapelId, array $kelas): Ujian
    {
        $paket = PaketSoal::create(['kode_paket' => $kode.'-P', 'nama_paket' => 'Paket', 'is_aktif' => true]);

        $ujian = Ujian::create([
            'kode_ujian' => $kode, 'nama_ujian' => $nama, 'paket_soal_id' => $paket->id,
            'mata_pelajaran_id' => $mapelId,
            'waktu_mulai' => now()->subMinutes(10), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
        ]);

        foreach ($kelas as $k) {
            UjianKelas::create(['ujian_id' => $ujian->id, 'rombongan_belajar_id' => $k]);
        }

        return $ujian;
    }

    private function peserta(Ujian $ujian, int $urutSiswa, int $kelas): UjianPeserta
    {
        return UjianPeserta::create([
            'ujian_id' => $ujian->id, 'siswa_id' => $this->siswa[$urutSiswa],
            'rombongan_belajar_id' => $kelas, 'status' => UjianPeserta::MULAI, 'waktu_mulai' => now(),
        ]);
    }

    private function langgar(UjianPeserta $p, string $jenis): void
    {
        UjianLog::create(['ujian_id' => $p->ujian_id, 'ujian_peserta_id' => $p->id, 'event' => $jenis]);
    }
}
