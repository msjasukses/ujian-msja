<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\LoginAttempt;
use App\Models\Siswa;
use App\Models\User;
use App\Support\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Menu "Profil Saya" di pojok kanan atas, dan profil peserta di ruang ujian.
 *
 * Admin/operator tersimpan di database aplikasi sehingga nama, email, dan
 * kata sandinya bisa diubah di sini. Identitas guru dan siswa datang dari
 * database Data Center dan ditampilkan apa adanya — perubahannya dilakukan di
 * aplikasi Data Center — tetapi kata sandinya boleh diganti sendiri dari
 * halaman ini, karena hanya pemilik akun yang mengetahui yang lama.
 */
class ProfilController extends Controller
{
    public function index()
    {
        $pengguna = Pengguna::user();

        return view('profil.index', [
            'item' => $pengguna,
            'bisaDiubah' => $pengguna instanceof User,
            'riwayat' => $this->riwayatLogin($pengguna),
        ]);
    }

    /**
     * Profil peserta. Identitas siswa milik Data Center, jadi halaman ini
     * hanya menampilkannya — termasuk riwayat masuk, supaya siswa bisa
     * mengenali bila akunnya dipakai orang lain, lalu mengganti kata sandinya
     * sendiri di situ juga.
     */
    public function siswa()
    {
        $siswa = Pengguna::siswa();

        return view('siswa.profil', [
            'siswa' => $siswa,
            'kelas' => $siswa?->rombelPada(),
            'riwayat' => $this->riwayatLogin($siswa),
        ]);
    }

    public function update(Request $r)
    {
        $user = $this->penggunaAplikasi();

        $data = $r->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email,'.$user->id,
        ], [], ['name' => 'Nama']);

        $user->update($data);

        return redirect()->route('profil.index')->with('success', 'Profil diperbarui.');
    }

    /** Kata sandi admin/operator maupun guru, sesuai siapa yang sedang masuk. */
    public function ubahSandi(Request $r)
    {
        $pengguna = Pengguna::user();

        abort_unless($pengguna instanceof User || $pengguna instanceof Guru, 403);

        return $this->gantiSandi($r, $pengguna, 'profil.index');
    }

    /** Kata sandi peserta, dari halaman profil di ruang ujian. */
    public function ubahSandiSiswa(Request $r)
    {
        $siswa = Pengguna::siswa();

        abort_unless($siswa instanceof Siswa, 403);

        return $this->gantiSandi($r, $siswa, 'siswa.profil');
    }

    /**
     * Ganti kata sandi akun yang sedang masuk, untuk ketiga guard.
     *
     * Kata sandi lama selalu diminta: itulah yang membedakan pemilik akun dari
     * orang yang kebetulan menemukan perangkatnya belum terkunci.
     */
    protected function gantiSandi(Request $r, User|Guru|Siswa $pengguna, string $kembali)
    {
        // Pesannya ditulis sendiri, tidak memakai bawaan Laravel yang berbahasa
        // Inggris: formulir ini dipakai siswa, bukan hanya pengelola aplikasi.
        $r->validate([
            'password_lama' => 'required|string',
            'password' => ['required', 'confirmed', 'different:password_lama', Password::min(8)],
        ], [
            'password_lama.required' => 'Kata sandi saat ini wajib diisi.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Ulangan kata sandi baru tidak sama.',
            'password.different' => 'Kata sandi baru harus berbeda dari yang sekarang.',
            'password.min' => 'Kata sandi baru minimal :min karakter.',
        ], [
            'password_lama' => 'Kata sandi saat ini',
            'password' => 'Kata sandi baru',
        ]);

        // Akun datacenter yang kolom password-nya masih kosong tidak bisa masuk
        // sejak awal, jadi blank() di sini hanya menjaga Hash::check.
        $cocok = $pengguna instanceof User
            ? filled($pengguna->password) && Hash::check($r->password_lama, $pengguna->password)
            : $pengguna->kataSandiCocok($r->password_lama);

        if (! $cocok) {
            return back()->withErrors(['password_lama' => 'Kata sandi saat ini tidak cocok.']);
        }

        // Admin/operator ada di database aplikasi ini; guru dan siswa di
        // datacenter, tempat kata sandinya dibaca saat masuk.
        if ($pengguna instanceof User) {
            $pengguna->update(['password' => $r->password]);
        } else {
            $pengguna->gantiKataSandi($r->password);
        }

        // Sesi yang sedang dipakai diperbarui supaya tidak ikut terlempar ke
        // halaman login setelah kata sandinya berganti. Isi sesi ikut pindah,
        // jadi penanda satu-perangkat milik peserta tetap utuh.
        $r->session()->regenerate();

        return redirect()->route($kembali)->with('success', 'Kata sandi diperbarui.');
    }

    /** Hanya akun aplikasi (admin/operator) yang identitasnya bisa diubah di sini. */
    protected function penggunaAplikasi(): User
    {
        $user = Auth::guard('web')->user();

        abort_unless($user instanceof User, 403, 'Akun guru diubah melalui aplikasi Data Center.');

        return $user;
    }

    /** Lima percobaan login terakhir atas nama pengguna ini. */
    protected function riwayatLogin(User|Guru|Siswa|null $pengguna)
    {
        $username = match (true) {
            $pengguna instanceof User => $pengguna->email,
            $pengguna instanceof Guru => $pengguna->nip,
            $pengguna instanceof Siswa => $pengguna->nisn,
            default => null,
        };

        if (blank($username)) {
            return collect();
        }

        return LoginAttempt::where('username', $username)
            ->where('guard', Pengguna::guard())
            ->latest('id')
            ->limit(5)
            ->get();
    }
}
