<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\HtmlString;

/**
 * Penyaji teks soal.
 *
 * Isi butir soal ditulis guru, boleh memuat penanda sederhana: cetak tebal,
 * pangkat, teks Arab, pecahan bertingkat, dan akar. Semuanya perlu lolos ke
 * halaman apa adanya — kalau di-escape, pangkat pada "x²" atau arah kanan-ke-
 * kiri pada teks Arab akan hilang.
 *
 * Karena itu isinya disaring memakai daftar tag yang diizinkan, bukan
 * ditampilkan mentah. Guru memang pengguna tepercaya, tetapi soal yang mereka
 * tulis dibaca ulang di peramban admin dan seluruh siswa saat ujian
 * berlangsung — satu <script> yang lolos akan berjalan di semua layar itu.
 * Menyaring di sini sekaligus membuat isi tempelan dari Word tidak membawa
 * gaya dan atribut yang merusak tata letak halaman.
 */
final class TeksSoal
{
    /** Tag yang boleh muncul pada teks soal. */
    public const TAG = [
        'b', 'strong', 'i', 'em', 'u', 's', 'sup', 'sub', 'br', 'span', 'small',
        'mark', 'code', 'p', 'div', 'ul', 'ol', 'li', 'table', 'thead', 'tbody',
        'tr', 'td', 'th', 'img',
    ];

    /** Atribut yang boleh menyertai tag di atas. */
    public const ATRIBUT = ['dir', 'lang', 'class', 'colspan', 'rowspan', 'src', 'alt'];

    /**
     * Nama kelas yang dikenali gaya bawaan aplikasi. Kelas di luar daftar ini
     * dibuang supaya teks soal tidak bisa meniru atau merusak komponen
     * halaman lain — misalnya menempelkan kelas "position-fixed".
     */
    public const KELAS = [
        'teks-arab', 'pecahan', 'pembilang', 'penyebut', 'akar', 'radikan',
        'text-center', 'text-end', 'table', 'table-bordered', 'table-sm',
    ];

    /** Tag blok yang membuat penambahan <br> otomatis tidak lagi diperlukan. */
    private const TAG_BLOK = ['p', 'div', 'ul', 'ol', 'table'];

    /**
     * Teks soal siap tampil: sudah disaring dan pergantian barisnya diubah
     * menjadi <br>.
     */
    public static function html(?string $teks): HtmlString
    {
        $bersih = self::bersihkan($teks);

        // Isi yang sudah memakai tag blok mengatur jaraknya sendiri; menambah
        // <br> di sana justru menyisipkan baris kosong di antara sel tabel.
        if (self::memuatTagBlok($bersih)) {
            return new HtmlString($bersih);
        }

        return new HtmlString(nl2br($bersih, false));
    }

    /**
     * Teks soal tanpa penanda apa pun, untuk daftar ringkas, hasil pencarian,
     * dan berkas export yang tidak bisa menampilkan HTML.
     */
    public static function polos(?string $teks): string
    {
        $polos = html_entity_decode(
            strip_tags(self::bersihkan($teks)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        // Spasi tanpa pemisah — datang dari &nbsp; yang ditinggalkan editor
        // maupun tempelan Word — disamakan dengan spasi biasa. Pada layar
        // keduanya serupa, tetapi pada berkas export dan kotak pencarian
        // U+00A0 tidak cocok dengan spasi yang diketik pengguna.
        $polos = str_replace("\u{00A0}", ' ', $polos);

        // Rapikan sisa spasi ganda bekas tag yang dibuang.
        return trim((string) preg_replace('/[ \t]+/u', ' ', $polos));
    }

    /**
     * Apakah sepotong teks soal benar-benar tanpa isi?
     *
     * Bukan sekadar pemeriksaan string kosong: editor menyisakan &nbsp;, <br>,
     * dan span kosong pada kolom yang baru dibersihkan, sehingga kolom yang
     * bagi guru tampak kosong sebenarnya tidak.
     *
     * Gambar diperlakukan sebagai isi meski tidak menyumbang satu huruf pun —
     * opsi jawaban yang isinya hanya gambar adalah bentuk yang lazim pada soal
     * bangun datar dan grafik.
     */
    public static function kosong(?string $teks): bool
    {
        $teks = (string) $teks;

        if (stripos($teks, '<img') !== false) {
            return false;
        }

        // Entitas diuraikan langsung di sini, tidak lewat polos(). Jalur cepat
        // polos() memperlakukan isi tanpa tag sebagai teks biasa dan justru
        // meng-escape-nya, sehingga "&nbsp;" — sisa yang paling sering
        // ditinggalkan editor pada kolom yang baru dikosongkan — terbaca
        // sebagai isi sungguhan.
        $polos = html_entity_decode(strip_tags($teks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $polos)) === '';
    }

    /** Buang seluruh tag dan atribut di luar daftar yang diizinkan. */
    public static function bersihkan(?string $teks): string
    {
        $teks = (string) $teks;

        if (trim($teks) === '') {
            return '';
        }

        // Tanpa tag sama sekali — jalur tercepat, dan yang paling sering
        // terjadi karena kebanyakan soal ditulis sebagai teks biasa.
        if (! str_contains($teks, '<')) {
            return e($teks);
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $sebelumnya = libxml_use_internal_errors(true);

        // Deklarasi encoding di depan membuat libxml membaca bita UTF-8 apa
        // adanya, sehingga huruf Arab dan simbol matematika tidak berubah
        // menjadi karakter rusak.
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="akar-teks-soal">'.$teks.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($sebelumnya);

        $akar = $dom->getElementById('akar-teks-soal');

        if (! $akar) {
            return e(strip_tags($teks));
        }

        self::saring($akar);

        $hasil = '';

        foreach (iterator_to_array($akar->childNodes) as $anak) {
            $hasil .= $dom->saveHTML($anak);
        }

        return $hasil;
    }

    /** Telusuri pohon simpul dan singkirkan yang tidak diizinkan. */
    private static function saring(DOMNode $induk): void
    {
        // Disalin lebih dulu karena daftar anak berubah saat ada yang dihapus.
        foreach (iterator_to_array($induk->childNodes) as $simpul) {
            if ($simpul instanceof DOMElement) {
                self::saringElemen($simpul);

                continue;
            }

            // Sisakan hanya teks biasa; komentar dan blok CDATA dibuang.
            if ($simpul->nodeType !== XML_TEXT_NODE) {
                $induk->removeChild($simpul);
            }
        }
    }

    private static function saringElemen(DOMElement $elemen): void
    {
        $nama = strtolower($elemen->nodeName);

        if (! in_array($nama, self::TAG, true)) {
            // Isi tag terlarang tetap dipertahankan sebagai teks — kalimat
            // guru tidak ikut hilang hanya karena satu tag yang keliru. Namun
            // <script> dan <style> dibuang berikut isinya, sebab isinya kode,
            // bukan kalimat.
            if (in_array($nama, ['script', 'style'], true)) {
                $elemen->parentNode?->removeChild($elemen);

                return;
            }

            self::saring($elemen);
            self::gantiDenganIsinya($elemen);

            return;
        }

        foreach (iterator_to_array($elemen->attributes) as $atribut) {
            $namaAtribut = strtolower($atribut->nodeName);

            if (! in_array($namaAtribut, self::ATRIBUT, true)) {
                $elemen->removeAttribute($atribut->nodeName);

                continue;
            }

            if ($namaAtribut === 'class') {
                self::saringKelas($elemen);
            }

            if ($namaAtribut === 'src' && ! self::sumberGambarAman($atribut->nodeValue)) {
                $elemen->parentNode?->removeChild($elemen);

                return;
            }
        }

        self::saring($elemen);
    }

    private static function saringKelas(DOMElement $elemen): void
    {
        $kelas = array_values(array_intersect(
            preg_split('/\s+/', trim((string) $elemen->getAttribute('class'))) ?: [],
            self::KELAS
        ));

        $kelas === []
            ? $elemen->removeAttribute('class')
            : $elemen->setAttribute('class', implode(' ', $kelas));
    }

    /**
     * Gambar hanya boleh menunjuk berkas di server ini atau data URI gambar —
     * menutup "javascript:" sekaligus alamat luar yang bisa dipakai melacak
     * siapa saja yang sedang membuka soal.
     */
    private static function sumberGambarAman(?string $src): bool
    {
        $src = trim((string) $src);

        return str_starts_with($src, '/')
            || str_starts_with($src, 'data:image/');
    }

    /** Ganti sebuah elemen dengan anak-anaknya, elemennya sendiri hilang. */
    private static function gantiDenganIsinya(DOMElement $elemen): void
    {
        $induk = $elemen->parentNode;

        if (! $induk) {
            return;
        }

        while ($elemen->firstChild) {
            $induk->insertBefore($elemen->firstChild, $elemen);
        }

        $induk->removeChild($elemen);
    }

    private static function memuatTagBlok(string $html): bool
    {
        foreach (self::TAG_BLOK as $tag) {
            if (stripos($html, '<'.$tag) !== false) {
                return true;
            }
        }

        return false;
    }
}
