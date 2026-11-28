<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Preview butir soal dari menu Pemilihan Soal dan Registrasi Ujian. */
class PreviewSoalTest extends TestCase
{
    use RefreshDatabase;

    protected PaketSoal $paket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Admin Uji', 'email' => 'preview@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]));

        $this->paket = PaketSoal::create(['kode_paket' => 'PRV-1', 'nama_paket' => 'Paket Preview', 'is_aktif' => true]);

        $butir = [
            Soal::create([
                'jenis' => Soal::PG, 'pertanyaan' => 'Ibu kota Indonesia?', 'bobot' => 2,
                'opsi' => [['key' => 'A', 'text' => 'Jakarta'], ['key' => 'B', 'text' => 'Bandung']], 'kunci' => ['A'],
                'pembahasan' => 'Jakarta adalah ibu kota.',
            ]),
            Soal::create([
                'jenis' => Soal::ESSAY, 'pertanyaan' => 'Jelaskan fotosintesis.', 'bobot' => 3,
                'kunci' => ['jawaban' => 'Proses tumbuhan membuat makanan'],
            ]),
        ];

        // Nomor urut dibalik untuk memastikan preview mengikuti urutan paket.
        foreach ($butir as $i => $s) {
            PaketSoalDetail::create([
                'paket_soal_id' => $this->paket->id, 'soal_id' => $s->id,
                'nomor_urut' => 2 - $i, 'bobot' => $s->bobot,
            ]);
        }
    }

    public function test_preview_paket_menampilkan_semua_butir_sesuai_urutan(): void
    {
        $this->get(route('paket-soal.preview', $this->paket))
            ->assertOk()
            ->assertSeeInOrder(['Soal 1', 'Jelaskan fotosintesis.', 'Soal 2', 'Ibu kota Indonesia?'])
            ->assertSee('Jakarta adalah ibu kota.')
            ->assertSee('Proses tumbuhan membuat makanan')
            ->assertSee('2 butir');
    }

    public function test_preview_soal_dari_registrasi_ujian(): void
    {
        $ujian = Ujian::create([
            'kode_ujian' => 'PRV-UJN', 'nama_ujian' => 'Ujian Preview', 'paket_soal_id' => $this->paket->id,
            'waktu_mulai' => now(), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::DRAFT,
        ]);

        $this->get(route('ujian.preview-soal', $ujian))
            ->assertOk()
            ->assertSee('Ujian Preview')
            ->assertSee('Ibu kota Indonesia?')
            ->assertSee('Jelaskan fotosintesis.');

        $this->get(route('ujian.index'))->assertSee(route('ujian.preview-soal', $ujian));
        $this->get(route('ujian.edit', $ujian))->assertSee(route('paket-soal.preview', $this->paket));
    }

    public function test_tombol_preview_ada_di_menu_pemilihan_soal(): void
    {
        $url = route('paket-soal.preview', $this->paket);

        $this->get(route('paket-soal.index'))->assertSee($url);
        $this->get(route('paket-soal.kelola', $this->paket))->assertSee($url);
    }

    public function test_gambar_pertanyaan_dan_opsi_ikut_tampil(): void
    {
        $bergambar = Soal::create([
            'jenis' => Soal::PG, 'bobot' => 1,
            'pertanyaan' => 'Perhatikan bangun berikut.<img src="/storage/soal-gambar/bangun.png" alt="bangun">',
            'opsi' => [
                ['key' => 'A', 'text' => '<img src="/storage/soal-gambar/opsi-a.png" alt="a">'],
                ['key' => 'B', 'text' => 'Persegi'],
            ],
            'kunci' => ['A'],
        ]);
        PaketSoalDetail::create([
            'paket_soal_id' => $this->paket->id, 'soal_id' => $bergambar->id, 'nomor_urut' => 3, 'bobot' => 1,
        ]);

        $this->get(route('paket-soal.preview', $this->paket))
            ->assertOk()
            ->assertSee('soal-gambar/bangun.png', false)
            ->assertSee('soal-gambar/opsi-a.png', false);

        // Halaman pemilihan soal: butir dalam paket bisa dilihat lengkap.
        $this->get(route('paket-soal.kelola', $this->paket))
            ->assertOk()
            ->assertSee('pratinjau-soal-'.$bergambar->id, false)
            ->assertSee('soal-gambar/bangun.png', false)
            ->assertSee('soal-gambar/opsi-a.png', false)
            ->assertSee('Soal ini memuat gambar');
    }

    public function test_butir_bank_soal_tersedia_bisa_dilihat_lengkap(): void
    {
        $tersedia = Soal::create([
            'jenis' => Soal::PG, 'bobot' => 1, 'pertanyaan' => 'Soal di bank belum dipilih',
            'opsi' => [['key' => 'A', 'text' => 'Opsi bank pertama'], ['key' => 'B', 'text' => '<img src="/storage/soal-gambar/bank-b.png" alt="b">']],
            'kunci' => ['B'],
        ]);

        $this->get(route('paket-soal.kelola', $this->paket))
            ->assertOk()
            ->assertSee('pratinjau-soal-'.$tersedia->id, false)
            ->assertSee('Opsi bank pertama')
            ->assertSee('soal-gambar/bank-b.png', false);
    }

    public function test_preview_paket_kosong_tetap_terbuka(): void
    {
        $kosong = PaketSoal::create(['kode_paket' => 'PRV-0', 'nama_paket' => 'Kosong', 'is_aktif' => true]);

        $this->get(route('paket-soal.preview', $kosong))
            ->assertOk()
            ->assertSee('Paket ini belum berisi soal.');
    }
}
