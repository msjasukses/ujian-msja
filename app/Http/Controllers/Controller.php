<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class Controller
{
    /**
     * Hapus banyak baris sekaligus dari kotak centang di halaman daftar.
     *
     * Baris diambil lewat $kueri, bukan langsung dari id kiriman, supaya guru
     * tidak bisa ikut menghapus milik guru lain dengan menyelipkan id. Setiap
     * baris tetap melewati aturan yang sama dengan hapus satuan ($alasanDitolak
     * mengembalikan alasan penolakan, atau null bila boleh dihapus), dan
     * dihapus satu per satu agar event model tetap berjalan.
     *
     * @param  callable(Model): ?string  $alasanDitolak
     */
    protected function hapusBanyak(Request $r, Builder $kueri, callable $alasanDitolak, string $label): RedirectResponse
    {
        $data = $r->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ], [
            'ids.required' => "Pilih minimal satu {$label} yang akan dihapus.",
        ]);

        $terhapus = 0;
        $ditolak = [];

        DB::transaction(function () use ($kueri, $data, $alasanDitolak, &$terhapus, &$ditolak) {
            foreach ($kueri->whereIn('id', $data['ids'])->get() as $model) {
                if ($alasan = $alasanDitolak($model)) {
                    $ditolak[] = $alasan;

                    continue;
                }

                $model->delete();
                $terhapus++;
            }
        });

        $balik = back();

        if ($terhapus) {
            $balik->with('success', "{$terhapus} {$label} dihapus.");
        }

        if ($ditolak) {
            $contoh = implode(' ', array_slice($ditolak, 0, 3));
            $sisa = count($ditolak) > 3 ? ' (dan '.(count($ditolak) - 3).' lainnya)' : '';

            $balik->with('error', count($ditolak)." {$label} tidak dihapus. {$contoh}{$sisa}");
        } elseif (! $terhapus) {
            $balik->with('error', "Tidak ada {$label} yang dihapus.");
        }

        return $balik;
    }
}
