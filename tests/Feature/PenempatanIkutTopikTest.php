<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\TingkatKelas;
use App\Models\Topik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Memilih topik ikut mengisi mata pelajaran, tingkat kelas, dan jurusan —
 * ketiganya memang sudah melekat pada topiknya sendiri. Berlaku di Input Soal
 * maupun di Import Word/Excel, dua halaman yang meminta isian yang sama.
 *
 * Pengisiannya dikerjakan di peramban, jadi yang bisa dijaga dari sini adalah
 * bahannya: tiap pilihan topik harus membawa penempatannya, dan skrip yang
 * memakainya harus benar-benar ikut dimuat. Justru dua hal itu yang paling
 * mudah hilang tanpa disadari — satu @include yang terlupa tidak menimbulkan
 * galat apa pun, hanya formulir yang diam-diam berhenti mengisi dirinya.
 */
class PenempatanIkutTopikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Admin Topik', 'email' => 'penempatan@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]));
    }

    /**
     * Satu topik berpenempatan lengkap, beserta potongan atribut yang harus
     * muncul di halaman mana pun yang menampilkannya.
     */
    protected function topikLengkap(): string
    {
        $mapel = MataPelajaran::aktif()->first();
        $tingkat = TingkatKelas::aktif()->first();

        if (! $mapel || ! $tingkat) {
            $this->markTestSkipped('Database datacenter belum berisi mata pelajaran dan tingkat kelas.');
        }

        Topik::create([
            'nama_topik' => 'Trigonometri Dasar',
            'mata_pelajaran_id' => $mapel->id,
            'tingkat_kelas_id' => $tingkat->id,
            'sumber' => Topik::SUMBER_MANUAL,
            'is_aktif' => true,
        ]);

        return 'data-mapel="'.$mapel->id.'" data-tingkat="'.$tingkat->id.'" data-jurusan=""';
    }

    public function test_input_soal_membawa_penempatan_tiap_topik(): void
    {
        $atribut = $this->topikLengkap();

        $this->get(route('soal.create'))
            ->assertOk()
            ->assertSee($atribut, false)
            ->assertSee('data-penempatan-ikut-topik', false);
    }

    /**
     * Di halaman import, isian ini dipasang ke seluruh butir hasil unggahan
     * sekaligus — satu saja yang keliru menyeret satu berkas penuh.
     */
    public function test_import_membawa_penempatan_yang_sama(): void
    {
        $atribut = $this->topikLengkap();

        $this->get(route('soal.import.form'))
            ->assertOk()
            ->assertSee($atribut, false)
            ->assertSee('data-penempatan-ikut-topik', false);
    }

    public function test_topik_tanpa_penempatan_tidak_membawa_apa_pun(): void
    {
        // Atributnya tetap ditulis, hanya kosong. Skripnya membaca kekosongan
        // itu sebagai "jangan ubah apa pun", bukan sebagai "kosongkan" —
        // topik yang belum lengkap tidak boleh menghapus pilihan guru.
        Topik::create([
            'nama_topik' => 'Topik Tanpa Penempatan',
            'sumber' => Topik::SUMBER_MANUAL,
            'is_aktif' => true,
        ]);

        $kosong = 'data-mapel="" data-tingkat="" data-jurusan=""';

        $this->get(route('soal.create'))->assertOk()->assertSee($kosong, false);
        $this->get(route('soal.import.form'))->assertOk()->assertSee($kosong, false);
    }
}
