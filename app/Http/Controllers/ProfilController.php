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
 * Menu "Profil Saya" di pojok kanan atas.
 *
 * Admin/operator tersimpan di database aplikasi sehingga nama, email, dan
 * kata sandinya bisa diubah di sini. Guru datang dari database Data Center
 * yang hanya bisa dibaca — datanya ditampilkan apa adanya, dan perubahannya
 * dilakukan di aplikasi Data Center.
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
     * Profil peserta. Data siswa milik Data Center, jadi halaman ini hanya
     * menampilkan — termasuk riwayat masuk, supaya siswa bisa mengenali bila
     * akunnya dipakai orang lain.
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

    public function ubahSandi(Request $r)
    {
        $user = $this->penggunaAplikasi();

        $r->validate([
            'password_lama' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'password_lama' => 'Kata sandi saat ini',
            'password' => 'Kata sandi baru',
        ]);

        if (! Hash::check($r->password_lama, $user->password)) {
            return back()->withErrors(['password_lama' => 'Kata sandi saat ini tidak cocok.']);
        }

        $user->update(['password' => $r->password]);

        // Sesi yang sedang dipakai diperbarui supaya tidak ikut terlempar ke
        // halaman login setelah kata sandinya berganti.
        $r->session()->regenerate();

        return redirect()->route('profil.index')->with('success', 'Kata sandi diperbarui.');
    }

    /** Hanya akun aplikasi (admin/operator) yang datanya bisa diubah di sini. */
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
