<?php

namespace App\Http\Controllers;

use App\Models\Jurusan;
use App\Models\Pengaturan;
use App\Support\Referensi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Menu "Pengaturan" (khusus admin/operator).
 *
 * Saat ini berisi jenjang sekolah. Pada SMA dan SMK, setiap isian tingkat
 * kelas — topik, bank soal, import soal, dan paket soal — disertai pilihan
 * jurusan.
 */
class PengaturanController extends Controller
{
    public function index()
    {
        return view('pengaturan.index', [
            'jenjang' => Referensi::jenjang(),
            'jenjangDataCenter' => Referensi::jenjangDataCenter(),
            'tersimpan' => filled(Pengaturan::ambil(Pengaturan::JENJANG)),
            'jurusan' => Jurusan::orderBy('nama_jurusan')->get(),
        ]);
    }

    public function update(Request $r)
    {
        $data = $r->validate([
            'jenjang' => ['required', Rule::in(array_keys(Referensi::daftarJenjang()))],
        ], [], ['jenjang' => 'Jenjang sekolah']);

        Pengaturan::simpan(Pengaturan::JENJANG, $data['jenjang']);

        $pesan = 'Jenjang sekolah disimpan: '.Referensi::daftarJenjang()[$data['jenjang']].'.';

        if (Referensi::pakaiJurusan()) {
            $pesan .= ' Pilihan jurusan kini muncul di menu Topik, Bank Soal, dan Pemilihan Soal Diujikan.';
        }

        return redirect()->route('pengaturan.index')->with('success', $pesan);
    }
}
