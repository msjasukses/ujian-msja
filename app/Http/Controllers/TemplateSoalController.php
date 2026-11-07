<?php

namespace App\Http\Controllers;

use App\Services\TemplateSoalService;
use App\Support\Template\Daftar;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pratinjau butir soal yang dirakit dari template.
 *
 * Perakitannya tetap di server walau yang menampilkan peramban: rumus,
 * kunci, dan pengecoh soal cukup rumit untuk ditulis dua kali, dan satu
 * salinan yang bisa diuji lebih dipercaya daripada dua salinan yang mirip.
 * Peramban hanya menggambar apa yang dikembalikan dari sini.
 */
class TemplateSoalController extends Controller
{
    public function rakit(Request $r, TemplateSoalService $template)
    {
        $r->validate(['kode' => ['required', Rule::in(array_keys(Daftar::semua()))]]);

        $kode = (string) $r->input('kode');
        $isi = (array) $r->input('isi', []);

        [$aturan, $namaKolom] = $template->aturan($kode, $isi);

        $data = $r->validate([
            'jenis' => ['required', Rule::in(array_keys(TemplateSoalService::JENIS))],
            'benar' => ['nullable', 'boolean'],
            ...$aturan,
        ], [], $namaKolom);

        if ($galat = $template->periksa($kode, $data['isi'] ?? [])) {
            throw ValidationException::withMessages($galat);
        }

        return response()->json($template->rakit(
            $kode,
            $data['isi'] ?? [],
            $data['jenis'],
            (bool) ($data['benar'] ?? true),
        ));
    }
}
