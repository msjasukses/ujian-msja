<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RedisCacheService;
use App\Support\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Menu "Kinerja & Cache".
 *
 * Satu-satunya setelan di sini: ke mana tembolok dan sesi login disimpan —
 * database (bawaan) atau Redis. Pada hari ujian, pilihan ini yang paling
 * terasa: tabel sessions berhenti menjadi titik rebutan tulisan antar ratusan
 * peserta yang lembarnya berdenyut bersamaan.
 *
 * Khusus peran admin, tidak termasuk operator. Setelan yang salah di sini
 * membuat seluruh aplikasi tidak bisa diakses siapa pun, dan memperbaikinya
 * butuh akses ke berkas .env di server.
 */
class KinerjaController extends Controller
{
    public function __construct(protected RedisCacheService $redis) {}

    public function index()
    {
        $this->pastikanAdmin();

        return view('kinerja.index', [
            'keadaan' => $this->redis->keadaan(),
            'prefixSaran' => $this->redis->prefixSaran(),
        ]);
    }

    /** Uji koneksi saja — tidak ada yang berubah, apa pun hasilnya. */
    public function uji(Request $r)
    {
        $this->pastikanAdmin();

        $hasil = $this->redis->uji($this->parameter($r));

        if (! $hasil['ok']) {
            return back()->withInput()->with('error', $hasil['pesan']);
        }

        $pesan = $hasil['pesan'];

        if ($hasil['versi']) {
            $pesan .= ' Redis '.$hasil['versi'];
            $pesan .= $hasil['memori'] ? ', memori terpakai '.$hasil['memori'].'.' : '.';
        }

        return back()->withInput()->with('success', $pesan);
    }

    public function simpan(Request $r)
    {
        $this->pastikanAdmin();

        $data = $this->parameter($r);
        $sesiBaru = $r->input('sesi');
        $sesiBerganti = $sesiBaru !== config('session.driver');

        try {
            $this->redis->terapkan($data, $r->input('tembolok'), $sesiBaru);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        /*
         * Pesan berhasil dititipkan di sesi — dan sesi itu masih milik penyimpan
         * LAMA, karena konfigurasi permintaan ini sudah terlanjur dimuat. Saat
         * driver sesinya ikut berganti, titipan itu tidak akan pernah terbaca:
         * permintaan berikutnya membaca penyimpan baru yang masih kosong, dan
         * admin mendarat di halaman masuk. Itu sudah diberitahukan di halaman
         * sebelum tombolnya ditekan, jadi di sini cukup tidak berpura-pura.
         */
        if ($sesiBerganti) {
            return redirect()->route('kinerja.index');
        }

        return redirect()->route('kinerja.index')->with(
            'success',
            'Penyimpan tembolok sekarang: '.$r->input('tembolok').'. Konfigurasi sudah dimuat ulang.'
        );
    }

    /**
     * @return array<string, string|null>
     */
    protected function parameter(Request $r): array
    {
        $data = $r->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'password' => ['nullable', 'string', 'max:255'],
            'database' => ['required', 'integer', 'between:0,15'],
            // Tembolok dan sesi wajib di nomor database yang berbeda: perintah
            // cache:clear mengosongkan seluruh isi database temboloknya, dan
            // kalau sesi ikut di sana, seluruh peserta ikut terlempar keluar.
            'cache_database' => ['required', 'integer', 'between:0,15', 'different:database'],
            'prefix' => ['required', 'string', 'max:64', 'regex:/\A[A-Za-z0-9_:.\-]+\z/'],
            'tembolok' => ['required', Rule::in(RedisCacheService::PENYIMPAN)],
            'sesi' => ['required', Rule::in(RedisCacheService::PENYIMPAN)],
        ], [
            'cache_database.different' => 'Nomor database tembolok harus berbeda dari database sesi.',
            'prefix.regex' => 'Awalan kunci hanya boleh berisi huruf, angka, titik, garis bawah, titik dua, dan tanda hubung.',
        ], [
            'host' => 'Alamat Redis',
            'port' => 'Porta',
            'password' => 'Kata sandi',
            'database' => 'Nomor database sesi',
            'cache_database' => 'Nomor database tembolok',
            'prefix' => 'Awalan kunci',
            'tembolok' => 'Penyimpan tembolok',
            'sesi' => 'Penyimpan sesi',
        ]);

        return $data;
    }

    protected function pastikanAdmin(): void
    {
        $pengguna = Pengguna::user();

        abort_unless($pengguna instanceof User && $pengguna->isAdmin(), 403, 'Menu kinerja hanya untuk peran admin.');
    }
}
