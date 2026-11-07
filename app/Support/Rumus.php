<?php

namespace App\Support;

/**
 * Penulisan rumus kimia.
 *
 * Rumus ditulis dengan penanda: "_" menurunkan angka sesudahnya dan "^"
 * menaikkannya — H_2O, Fe_2O_3, SO_4^2-. Kedudukan khusus itu hanya berlaku
 * selama angka dan tanda muatan, jadi huruf berikutnya kembali sejajar
 * dengan sendirinya dan rumus panjang tidak perlu ditutup penanda.
 *
 * Penanda dipakai, bukan huruf indeks Unicode (H₂O), karena tidak semua
 * font yang dipakai peramban memuatnya. Huruf yang hilang tampil sebagai
 * kotak kosong, dan itu terjadi justru pada berkas PNG yang sudah tersimpan
 * di bank soal — tempat kekeliruannya paling mahal diperbaiki.
 */
class Rumus
{
    /**
     * Pecah rumus menjadi potongan beserta kedudukannya.
     *
     * @return array<int, array{0: string, 1: string}> [teks, biasa|bawah|atas]
     */
    public static function potong(string $teks): array
    {
        $potongan = [];
        $kedudukan = 'biasa';
        $kini = '';
        $baru = false;

        foreach (mb_str_split($teks) as $huruf) {
            if ($huruf === '_' || $huruf === '^') {
                [$potongan, $kini] = [self::simpan($potongan, $kini, $kedudukan), ''];
                $kedudukan = $huruf === '_' ? 'bawah' : 'atas';
                $baru = true;

                continue;
            }

            // Satu huruf besar tepat setelah penanda ikut naik atau turun:
            // itu dipakai lambang alel golongan darah I^A, I^B, dan I^O.
            // Sesudah huruf itu kedudukannya kembali biasa, sehingga rumus
            // kimia seperti SO_4^2- tetap berperilaku seperti semula.
            $ikut = preg_match('/[0-9+-]/', $huruf) === 1
                || ($baru && preg_match('/\p{Lu}/u', $huruf) === 1);

            if ($kedudukan !== 'biasa' && ! $ikut) {
                [$potongan, $kini] = [self::simpan($potongan, $kini, $kedudukan), ''];
                $kedudukan = 'biasa';
            }

            $kini .= $huruf;
            $baru = false;
        }

        return self::simpan($potongan, $kini, $kedudukan);
    }

    /** Rumus sebagai HTML, untuk teks pertanyaan, opsi, dan pembahasan. */
    public static function html(string $teks): string
    {
        return implode('', array_map(
            fn ($p) => match ($p[1]) {
                'bawah' => '<sub>'.e($p[0]).'</sub>',
                'atas' => '<sup>'.e($p[0]).'</sup>',
                default => e($p[0]),
            },
            self::potong($teks),
        ));
    }

    /** Rumus tanpa penanda, untuk kunci jawaban isian singkat. */
    public static function polos(string $teks): string
    {
        return implode('', array_column(self::potong($teks), 0));
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $potongan
     * @return array<int, array{0: string, 1: string}>
     */
    protected static function simpan(array $potongan, string $teks, string $kedudukan): array
    {
        if ($teks !== '') {
            $potongan[] = [$teks, $kedudukan];
        }

        return $potongan;
    }
}
