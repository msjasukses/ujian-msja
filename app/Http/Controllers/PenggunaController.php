<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/** Menu "Pengguna" — akun admin/operator aplikasi ujian. */
class PenggunaController extends Controller
{
    public function index(Request $r)
    {
        $items = User::query()
            ->when($r->q, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")->orWhere('email', 'like', "%{$v}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('pengguna.index', compact('items'));
    }

    public function create()
    {
        return view('pengguna.form', ['item' => new User(['role' => User::ROLE_OPERATOR, 'is_aktif' => true])]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'role' => 'required|in:admin,operator',
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['name' => 'Nama', 'password' => 'Kata sandi']);

        User::create($data + ['is_aktif' => $r->boolean('is_aktif', true)]);

        return redirect()->route('pengguna.index')->with('success', 'Pengguna ditambahkan.');
    }

    public function edit(User $pengguna)
    {
        return view('pengguna.form', ['item' => $pengguna]);
    }

    public function update(Request $r, User $pengguna)
    {
        $data = $r->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email,'.$pengguna->id,
            'role' => 'required|in:admin,operator',
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], [], ['name' => 'Nama', 'password' => 'Kata sandi']);

        if (blank($data['password'])) {
            unset($data['password']);
        }

        $pengguna->update($data + ['is_aktif' => $r->boolean('is_aktif')]);

        return redirect()->route('pengguna.index')->with('success', 'Pengguna diperbarui.');
    }

    public function destroy(User $pengguna)
    {
        if ($alasan = $this->alasanTidakBisaDihapus($pengguna)) {
            return back()->with('error', $alasan);
        }

        $pengguna->delete();

        return back()->with('success', 'Pengguna dihapus.');
    }

    public function hapusMassal(Request $r)
    {
        return $this->hapusBanyak($r, User::query(), fn (User $u) => $this->alasanTidakBisaDihapus($u), 'pengguna');
    }

    // Jumlah admin dihitung ulang tiap kali dipanggil, jadi hapus massal yang
    // mencentang semua admin tetap menyisakan satu.
    protected function alasanTidakBisaDihapus(User $pengguna): ?string
    {
        if ($pengguna->id === Auth::guard('web')->id()) {
            return 'Anda tidak bisa menghapus akun yang sedang dipakai.';
        }

        if ($pengguna->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            return "Minimal harus tersisa satu akun admin ({$pengguna->name}).";
        }

        return null;
    }
}
