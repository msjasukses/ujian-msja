<?php

namespace App\Support;

/**
 * Pengurai rumus LaTeX untuk naskah yang diimpor.
 *
 * Banyak naskah soal ditulis dengan rumus dalam bentuk LaTeX — "\(\frac{11}{4}\)"
 * — karena itulah bentuk yang dihasilkan penulis rumus di web maupun keluaran
 * asisten AI. Tanpa penguraian, kode mentahnya yang masuk ke bank soal dan
 * terbaca siswa saat ujian.
 *
 * Kembaran dari pengurai di sisi peramban yang menangani tempelan papan klip.
 * Keduanya perlu ada: yang di peramban melayani guru yang menempel langsung ke
 * formulir, yang di sini melayani berkas Word dan Excel yang diunggah.
 *
 * Cakupannya sengaja dibatasi pada yang benar-benar dipakai di soal sekolah —
 * pecahan, akar, pangkat, indeks, dan lambang. Perintah di luar daftar ditulis
 * apa adanya, bukan dibuang, supaya guru bisa melihat bagian mana yang perlu
 * dirapikan sendiri.
 */
final class RumusLatex
{
    /** Lambang LaTeX beserta padanan Unicode-nya. */
    public const LAMBANG = [
        'times' => '×', 'div' => '÷', 'pm' => '±', 'mp' => '∓', 'cdot' => '·',
        'ast' => '∗', 'star' => '⋆',
        'neq' => '≠', 'ne' => '≠', 'leq' => '≤', 'le' => '≤', 'geq' => '≥', 'ge' => '≥',
        'approx' => '≈', 'equiv' => '≡', 'sim' => '∼', 'cong' => '≅',
        'll' => '≪', 'gg' => '≫', 'propto' => '∝',
        'in' => '∈', 'notin' => '∉', 'subset' => '⊂', 'subseteq' => '⊆',
        'supset' => '⊃', 'supseteq' => '⊇', 'cup' => '∪', 'cap' => '∩',
        'emptyset' => '∅', 'varnothing' => '∅', 'forall' => '∀', 'exists' => '∃',
        'therefore' => '∴', 'because' => '∵', 'neg' => '¬', 'land' => '∧', 'lor' => '∨',
        'infty' => '∞', 'partial' => '∂', 'nabla' => '∇', 'sum' => '∑', 'prod' => '∏',
        'int' => '∫', 'iint' => '∬', 'oint' => '∮',
        'Delta' => 'Δ', 'Gamma' => 'Γ', 'Theta' => 'Θ', 'Lambda' => 'Λ', 'Xi' => 'Ξ',
        'Pi' => 'Π', 'Sigma' => 'Σ', 'Phi' => 'Φ', 'Psi' => 'Ψ', 'Omega' => 'Ω',
        'alpha' => 'α', 'beta' => 'β', 'gamma' => 'γ', 'delta' => 'δ', 'epsilon' => 'ε',
        'varepsilon' => 'ε', 'zeta' => 'ζ', 'eta' => 'η', 'theta' => 'θ', 'iota' => 'ι',
        'kappa' => 'κ', 'lambda' => 'λ', 'mu' => 'μ', 'nu' => 'ν', 'xi' => 'ξ',
        'pi' => 'π', 'rho' => 'ρ', 'sigma' => 'σ', 'tau' => 'τ', 'upsilon' => 'υ',
        'phi' => 'φ', 'varphi' => 'φ', 'chi' => 'χ', 'psi' => 'ψ', 'omega' => 'ω',
        'rightarrow' => '→', 'to' => '→', 'leftarrow' => '←', 'leftrightarrow' => '↔',
        'Rightarrow' => '⇒', 'Leftarrow' => '⇐', 'Leftrightarrow' => '⇔', 'mapsto' => '↦',
        'circ' => '°', 'degree' => '°', 'angle' => '∠', 'perp' => '⊥',
        'parallel' => '∥', 'triangle' => '△',
        'ldots' => '…', 'dots' => '…', 'cdots' => '⋯',
        'quad' => '  ', 'qquad' => '    ',
    ];

    /**
     * Lambang yang perlu diberi jarak di kiri-kanannya.
     *
     * Di LaTeX jarak di sekitar tanda operasi dibuat oleh penata huruf, bukan
     * oleh spasi yang diketik — "3\times4" tetap tampil "3 × 4". Karena
     * keluaran di sini berupa teks biasa, jaraknya dibangkitkan sendiri.
     * Lambang yang berperilaku seperti huruf, misalnya \pi, sengaja tidak
     * diberi jarak supaya "\pi r" tetap menjadi "πr".
     */
    private const BERJARAK = [
        'times', 'div', 'pm', 'mp', 'cdot', 'ast', 'star',
        'neq', 'ne', 'leq', 'le', 'geq', 'ge', 'approx', 'equiv', 'sim', 'cong',
        'll', 'gg', 'propto',
        'in', 'notin', 'subset', 'subseteq', 'supset', 'supseteq', 'cup', 'cap',
        'land', 'lor',
        'rightarrow', 'to', 'leftarrow', 'leftrightarrow',
        'Rightarrow', 'Leftarrow', 'Leftrightarrow', 'mapsto',
        'perp', 'parallel',
    ];

    /** Perintah yang isinya ditampilkan apa adanya. */
    private const BUNGKUS = ['text', 'mathrm', 'mathbf', 'mathit', 'operatorname', 'textrm', 'mbox'];

    /**
     * Pola pembatas rumus.
     *
     * Pembatas $...$ hanya diperlakukan sebagai rumus bila isinya memuat
     * perintah LaTeX. Tanpa syarat itu, tulisan harga seperti "$5 dan $10"
     * akan terbaca sebagai rumus.
     */
    private const PEMBATAS = '/\\\\\((.+?)\\\\\)|\\\\\[(.+?)\\\\\]|\$\$(.+?)\$\$|\$([^$\n]*[\\\\^_{][^$\n]*)\$/su';

    /** Apakah sepotong teks memuat rumus LaTeX? */
    public static function punya(?string $teks): bool
    {
        return (bool) preg_match(
            '/\\\\\(|\\\\\[|\$\$|\\\\frac|\\\\dfrac|\\\\tfrac|\\\\sqrt|\\\\times|\\\\div/',
            (string) $teks
        );
    }

    /**
     * Ubah rumus LaTeX yang tersisip di dalam kalimat menjadi penanda tampil.
     *
     * Teks tanpa rumus dikembalikan apa adanya — tidak ikut di-escape — supaya
     * naskah biasa tersimpan persis seperti sebelum penguraian ini ada.
     */
    public static function keHtml(?string $teks): string
    {
        $teks = (string) $teks;

        if (! self::punya($teks)) {
            return $teks;
        }

        $keluar = '';
        $akhir = 0;

        preg_match_all(self::PEMBATAS, $teks, $cocok, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($cocok as $satu) {
            [$penuh, $posisi] = $satu[0];

            $keluar .= e(substr($teks, $akhir, $posisi - $akhir));

            // Hanya satu dari empat kelompok pembatas yang terisi.
            $isi = '';
            foreach ([1, 2, 3, 4] as $i) {
                if (isset($satu[$i]) && $satu[$i][1] !== -1) {
                    $isi = $satu[$i][0];
                }
            }

            $keluar .= self::urai($isi);
            $akhir = $posisi + strlen($penuh);
        }

        return $keluar.e(substr($teks, $akhir));
    }

    /** Ubah satu potongan LaTeX (tanpa pembatas) menjadi penanda tampil. */
    public static function urai(string $sumber): string
    {
        $i = 0;
        $hasil = self::baca($sumber, $i, strlen($sumber));

        // LaTeX merapatkan spasi berturut-turut menjadi satu. Tanpa ini,
        // "5 \text{ cm}" tampil dengan dua spasi karena spasi pemisah dan
        // spasi di dalam \text sama-sama ikut terbawa.
        return (string) preg_replace('/ {2,}/', ' ', $hasil);
    }

    // =====================================================================

    /** Baca sumber mulai dari posisi $i sampai $batas. */
    private static function baca(string $src, int &$i, int $batas): string
    {
        $keluar = '';

        while ($i < $batas) {
            $c = $src[$i];

            if ($c === '\\') {
                if (preg_match('/^\\\\([a-zA-Z]+)[ ]*/', substr($src, $i), $m)) {
                    $i += strlen($m[0]);
                    $keluar .= self::perintah($src, $i, $batas, $m[1]);

                    continue;
                }

                // \, \; \! \: mengatur jarak; \{ \} \$ adalah huruf biasa.
                $berikut = $src[$i + 1] ?? '';
                $i += 2;
                $keluar .= str_contains(',;:! ', $berikut) && $berikut !== '' ? ' ' : e($berikut);

                continue;
            }

            if ($c === '^' || $c === '_') {
                $i++;
                $satuan = self::satuan($src, $i, $batas);
                $keluar .= $c === '^' ? "<sup>{$satuan}</sup>" : "<sub>{$satuan}</sub>";

                continue;
            }

            if ($c === '{') {
                $tutup = self::tutupKurung($src, $i, $batas);
                $dalam = $i + 1;
                $keluar .= self::baca($src, $dalam, $tutup);
                $i = $tutup + 1;

                continue;
            }

            if ($c === '}') {
                $i++;

                continue;
            }

            $i++;
            $keluar .= e($c);
        }

        return $keluar;
    }

    /**
     * Ambil satu satuan berikutnya: sebuah {kelompok}, satu perintah, atau
     * satu huruf. Dipakai sebagai isi pangkat, akar, dan pecahan.
     */
    private static function satuan(string $src, int &$i, int $batas): string
    {
        while ($i < $batas && $src[$i] === ' ') {
            $i++;
        }

        if ($i >= $batas) {
            return '';
        }

        if ($src[$i] === '{') {
            $tutup = self::tutupKurung($src, $i, $batas);
            $dalam = $i + 1;
            $isi = self::baca($src, $dalam, $tutup);
            $i = $tutup + 1;

            return $isi;
        }

        if ($src[$i] === '\\') {
            if (preg_match('/^\\\\([a-zA-Z]+)[ ]*/', substr($src, $i), $m)) {
                $i += strlen($m[0]);

                return self::perintah($src, $i, $batas, $m[1]);
            }

            $i += 2;

            return e($src[$i - 1] ?? '');
        }

        return e($src[$i++]);
    }

    private static function perintah(string $src, int &$i, int $batas, string $nama): string
    {
        if (in_array($nama, ['frac', 'dfrac', 'tfrac'], true)) {
            $atas = self::satuan($src, $i, $batas);
            $bawah = self::satuan($src, $i, $batas);

            return '<span class="pecahan"><span class="pembilang">'.$atas
                .'</span><span class="penyebut">'.$bawah.'</span></span>';
        }

        if ($nama === 'sqrt') {
            $derajat = '';

            // Akar berderajat ditulis \sqrt[3]{27}.
            if (($src[$i] ?? '') === '[') {
                $tutup = strpos($src, ']', $i);

                if ($tutup !== false) {
                    $derajat = '<sup>'.self::urai(substr($src, $i + 1, $tutup - $i - 1)).'</sup>';
                    $i = $tutup + 1;
                }
            }

            return $derajat.'<span class="akar"><span class="radikan">'
                .self::satuan($src, $i, $batas).'</span></span>';
        }

        if (in_array($nama, self::BUNGKUS, true)) {
            return self::satuan($src, $i, $batas);
        }

        // \left( dan \right) hanya penanda ukuran; kurungnya sendiri tetap.
        if (in_array($nama, ['left', 'right', 'big', 'Big'], true)) {
            return '';
        }

        if (! isset(self::LAMBANG[$nama])) {
            return e('\\'.$nama);
        }

        $lambang = e(self::LAMBANG[$nama]);

        return in_array($nama, self::BERJARAK, true) ? ' '.$lambang.' ' : $lambang;
    }

    /** Cari '}' pasangan dari '{' pada posisi $mulai, menghitung sarangnya. */
    private static function tutupKurung(string $src, int $mulai, int $batas): int
    {
        $dalam = 0;

        for ($j = $mulai; $j < $batas; $j++) {
            if ($src[$j] === '\\') {
                $j++;

                continue;
            }

            if ($src[$j] === '{') {
                $dalam++;
            } elseif ($src[$j] === '}' && --$dalam === 0) {
                return $j;
            }
        }

        return $batas;
    }
}
