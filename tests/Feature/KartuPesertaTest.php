<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\MataPelajaran;
use App\Models\PaketSoal;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Cetak kartu peserta: satu kartu per siswa memuat seluruh jadwal terpilih
 * yang diikutinya, tersaring menurut kelas, dan tidak pernah membocorkan token.
 */
class KartuPesertaTest extends TestCase
{
    use RefreshDatabase;

    /** @var Collection<int, Siswa> */
    protected Collection $siswa;

    /** @var Collection<int, RombonganBelajar> */
    protected Collection $kelas;

    /** @var Collection<int, MataPelajaran> */
    protected Collection $mapel;

    protected Ujian $ujianSatu;

    protected Ujian $ujianDua;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siswa = Siswa::orderBy('id')->take(2)->get();
        $this->kelas = RombonganBelajar::orderBy('id')->take(2)->get();
        $this->mapel = MataPelajaran::orderBy('id')->take(3)->get();

        if ($this->siswa->count() < 2 || $this->kelas->count() < 2 || $this->mapel->count() < 3) {
            $this->markTestSkipped('Database datacenter belum berisi dua siswa, dua kelas, dan tiga mapel.');
        }

        $this->actingAs(User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]));

        // Siswa pertama ikut dua jadwal mendatang, siswa kedua satu jadwal.
        $this->ujianSatu = $this->ujian('KP-1', $this->mapel[0], now()->addDay());
        $this->ujianDua = $this->ujian('KP-2', $this->mapel[1], now()->addDays(2));

        $this->daftar($this->ujianSatu, 0);
        $this->daftar($this->ujianDua, 0);
        $this->daftar($this->ujianSatu, 1);

        // Jadwal yang sudah selesai tidak ikut tercetak secara bawaan.
        $this->daftar($this->ujian('KP-3', $this->mapel[2], now()->subWeek(), Ujian::SELESAI), 0);
    }

    public function test_satu_kartu_per_siswa_memuat_seluruh_jadwalnya(): void
    {
        $halaman = $this->get('/kartu-peserta')->assertOk();

        $this->assertSame(2, $this->jumlahKartu($halaman));
        // Dua baris jadwal di kartu siswa pertama, satu di kartu siswa kedua;
        // jadwal yang sudah selesai tidak ikut.
        $this->assertSame(3, substr_count($halaman->getContent(), '<td class="tengah">'));

        $halaman->assertSee($this->siswa[0]->nama_siswa)
            ->assertSee($this->siswa[0]->nisn)
            ->assertSee($this->siswa[0]->nis)
            ->assertSee($this->mapel[0]->nama_mapel)
            ->assertSee($this->mapel[1]->nama_mapel)
            ->assertSee('KARTU PESERTA UJIAN');
    }

    /** Token dibacakan pengawas saat ujian; kartu yang dibawa pulang tidak boleh memuatnya. */
    public function test_token_ujian_tidak_tercetak(): void
    {
        $halaman = $this->get('/kartu-peserta')->assertOk();

        foreach (Ujian::pluck('token') as $token) {
            $halaman->assertDontSee($token, false);
        }
    }

    public function test_tersaring_menurut_kelas_dan_jadwal(): void
    {
        $halaman = $this->get('/kartu-peserta?rombongan_belajar_id='.$this->kelas[1]->id)->assertOk();
        $this->assertSame(1, $this->jumlahKartu($halaman));
        $halaman->assertSee($this->siswa[1]->nama_siswa)->assertDontSee($this->siswa[0]->nama_siswa);

        $halaman = $this->get('/kartu-peserta?ujian_id[]='.$this->ujianDua->id)->assertOk();
        $this->assertSame(1, $this->jumlahKartu($halaman));
        $halaman->assertSee($this->siswa[0]->nama_siswa)->assertDontSee($this->siswa[1]->nama_siswa);
    }

    public function test_guru_hanya_mencetak_kartu_jadwalnya_sendiri(): void
    {
        $guru = Guru::first();

        if (! $guru) {
            $this->markTestSkipped('Database datacenter belum berisi data guru.');
        }

        $this->ujianSatu->update(['guru_id' => $guru->id]);

        auth('web')->logout();
        $this->actingAs($guru, 'guru');

        // Jadwal milik orang lain diabaikan walau id-nya dikirim langsung.
        $this->assertSame(0, $this->jumlahKartu(
            $this->get('/kartu-peserta?ujian_id[]='.$this->ujianDua->id)->assertOk()
        ));

        $this->assertSame(2, $this->jumlahKartu(
            $this->get('/kartu-peserta?ujian_id[]='.$this->ujianSatu->id)->assertOk()
        ));
    }

    public function test_halaman_peserta_menautkan_cetak_kartu(): void
    {
        $this->get("/ujian/{$this->ujianSatu->id}/peserta")
            ->assertOk()
            ->assertSee('Cetak Kartu Peserta')
            ->assertSee(route('kartu-peserta.index', ['ujian_id' => [$this->ujianSatu->id]]), false);
    }

    protected function ujian(string $kode, MataPelajaran $mapel, $mulai, string $status = Ujian::AKTIF): Ujian
    {
        $paket = PaketSoal::create(['kode_paket' => $kode, 'nama_paket' => 'Paket '.$kode, 'is_aktif' => true]);

        return Ujian::create([
            'kode_ujian' => $kode, 'nama_ujian' => 'Ujian '.$kode, 'paket_soal_id' => $paket->id,
            'mata_pelajaran_id' => $mapel->id, 'semester' => 'Ganjil',
            'waktu_mulai' => $mulai, 'waktu_selesai' => $mulai->copy()->addHours(2),
            'durasi_menit' => 90, 'kkm' => 75, 'status' => $status, 'token' => 'RHS'.substr($kode, -1).'XQ',
        ]);
    }

    protected function daftar(Ujian $ujian, int $urutan): void
    {
        UjianPeserta::create([
            'ujian_id' => $ujian->id,
            'siswa_id' => $this->siswa[$urutan]->id,
            'rombongan_belajar_id' => $this->kelas[$urutan]->id,
            'nomor_peserta' => $this->siswa[$urutan]->nis,
            'status' => UjianPeserta::TERDAFTAR,
        ]);
    }

    protected function jumlahKartu(TestResponse $halaman): int
    {
        return substr_count($halaman->getContent(), '<article class="kartu">');
    }
}
