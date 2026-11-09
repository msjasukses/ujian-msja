<?php

namespace App\Services;

use App\Models\Soal;
use App\Support\RumusLatex;
use App\Support\TeksSoal;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;

/**
 * Import bank soal dari berkas Word (.docx).
 *
 * Naskah soal ditulis mengikuti kebiasaan penulisan soal di sekolah:
 *
 *   1. Ibu kota Provinsi Jawa Barat adalah ...
 *   A. Bandung
 *   B. Semarang
 *   C. Surabaya
 *   D. Serang
 *   JAWABAN: A
 *   PEMBAHASAN: Bandung merupakan ibu kota Jawa Barat.
 *
 * Jenis soal ditebak dari bentuk naskahnya:
 *   - ada opsi A–E, kunci satu huruf          -> pilihan ganda
 *   - ada opsi A–E, kunci lebih dari satu     -> pilihan ganda kompleks
 *   - tanpa opsi, kunci "benar"/"salah"       -> benar salah
 *   - tanpa opsi, kunci berupa kalimat        -> essay
 *   - baris "pernyataan ## jodohnya"          -> penjodohan
 *   - baris "pernyataan ## benar/salah"       -> benar salah kompleks
 *
 * Bila tebakan itu perlu dipaksa, tulis baris "JENIS: essay" (atau jenis
 * lain) tepat setelah nomor soal.
 *
 * Baris kunci boleh diawali JAWABAN / KUNCI / KUNCI JAWABAN, dan penanda
 * bobot ditulis "BOBOT: 5".
 *
 * Naskah bertabel juga diterima — satu tabel per butir, bentuk yang lazim
 * dipakai sekolah karena rapi saat dicetak:
 *
 *   +------------------------------------------+
 *   | JENIS SOAL : pilihan ganda               |
 *   +-----+------------------------------------+
 *   |  1  | Ibu kota Provinsi Jawa Barat ...   |
 *   |  A  | Bandung                            |
 *   |  B  | Semarang                           |
 *   +------------------------------------------+
 *   | JAWABAN : A                              |
 *   | BOBOT : 1                                |
 *   +------------------------------------------+
 *
 * Barisnya diterjemahkan lebih dulu ke bentuk naskah biasa di atas, jadi
 * kedua susunan itu dibaca oleh satu pengurai yang sama.
 */
class ImportSoalWordService
{
    protected const PEMISAH_JODOH = '##';

    /**
     * Kata kunci penanda di awal baris, mis. "JAWABAN : C".
     *
     * Dipakai dua kali: mengenali sel kiri sebuah tabel dua kolom
     * ("JAWABAN | C") dan menandai batas antar butir di dalam satu tabel
     * panjang yang memuat banyak soal sekaligus.
     */
    protected const POLA_PENANDA = 'JAWABAN|KUNCI(?:\s*JAWABAN)?|KATA\s*KUNCI|JENIS(?:\s*SOAL)?'
        .'|BOBOT|LEVEL|RANAH|(?:TINGKAT\s*)?KESUKARAN|PEMBAHASAN';

    /**
     * Penanda sementara untuk gambar yang ditemukan di dalam dokumen.
     *
     * Parser naskah bekerja baris demi baris atas teks polos, jadi gambar
     * dititipkan sebagai satu baris penanda agar tetap berada di urutan yang
     * benar — di bawah pertanyaannya, bukan tercecer di akhir dokumen.
     * Penanda diubah menjadi <img> saat butir dirakit.
     */
    protected const PENANDA_GAMBAR = '[[GAMBAR:%s]]';

    public function __construct(protected GambarSoalService $gambar) {}

    /**
     * @return array{soal: array<int, array<string, mixed>>, galat: array<int, string>}
     */
    public function baca(string $path): array
    {
        $baris = $this->bacaParagraf($path);

        return $this->uraikan($baris);
    }

    /**
     * Ubah dokumen Word menjadi daftar baris teks polos. Tabel ikut dibaca
     * (sel per sel) karena banyak naskah soal disusun dalam tabel.
     *
     * @return array<int, string>
     */
    protected function bacaParagraf(string $path): array
    {
        $dokumen = IOFactory::load($path);
        $baris = [];

        foreach ($dokumen->getSections() as $section) {
            foreach ($section->getElements() as $elemen) {
                $this->kumpulkanTeks($elemen, $baris);
            }
        }

        // Rapikan: buang spasi ganda dan baris kosong.
        return array_values(array_filter(
            array_map(fn ($t) => $this->rapikan((string) $t), $baris),
            fn ($t) => $t !== ''
        ));
    }

    /**
     * Satu baris naskah siap diurai.
     *
     * PhpWord mengembalikan teks apa adanya dari XML dokumen, jadi masih
     * dalam bentuk ter-escape: tanda kutip terbaca &quot; dan tanda &
     * terbaca &amp;. Entitasnya dipulihkan lebih dulu — kalau tidak,
     * kalimat sesederhana Bacalah teks "Banjir" berikut! akan masuk ke
     * bank soal dengan &quot; di badan pertanyaannya. Pemulihan dilakukan
     * sebelum spasi dirapikan supaya &nbsp; ikut tertangkap aturan
     * \x{00A0} di bawah.
     */
    protected function rapikan(string $teks): string
    {
        $teks = html_entity_decode($teks, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\x{00A0}|\s+/u', ' ', $teks));
    }

    /** @param array<int, string> $baris */
    protected function kumpulkanTeks(object $elemen, array &$baris): void
    {
        if ($elemen instanceof Image) {
            // getImageString() memberi isi biner apa adanya. Jangan tergoda
            // memakai getImageStringData(): dengan argumen true ia memberi
            // base64 dan dengan false ia memberi heksadesimal — keduanya
            // bukan isi berkas, dan keduanya tetap "berhasil" tanpa galat.
            $alamat = $this->gambar->dariBiner($elemen->getImageString());

            // Gambar yang gagal dibaca dilewati diam-diam: satu berkas rusak
            // di tengah naskah tidak sepadan dengan menggagalkan seluruh impor.
            if ($alamat !== null) {
                $baris[] = sprintf(self::PENANDA_GAMBAR, $alamat);
            }

            return;
        }

        if ($elemen instanceof Text) {
            $baris[] = (string) $elemen->getText();

            return;
        }

        if ($elemen instanceof TextRun) {
            $gabung = '';

            foreach ($elemen->getElements() as $anak) {
                // Word menaruh gambar sebagai anak di dalam paragraf, bukan
                // sebagai elemen tersendiri — termasuk ketika gambar itu
                // berdiri sendiri satu baris.
                if ($anak instanceof Image) {
                    // Teks yang sudah terkumpul ditutup lebih dulu supaya
                    // gambar tetap berada sesudahnya, bukan mendahului.
                    if (trim($gabung) !== '') {
                        $baris[] = $gabung;
                        $gabung = '';
                    }

                    $this->kumpulkanTeks($anak, $baris);

                    continue;
                }

                if (method_exists($anak, 'getText')) {
                    $teks = $anak->getText();
                    $gabung .= is_string($teks) ? $teks : '';
                }
            }

            $baris[] = $gabung;

            return;
        }

        if ($elemen instanceof Table) {
            $this->kumpulkanTabel($elemen, $baris);

            return;
        }

        if (method_exists($elemen, 'getElements')) {
            foreach ($elemen->getElements() as $anak) {
                $this->kumpulkanTeks($anak, $baris);
            }

            return;
        }

        if (method_exists($elemen, 'getText')) {
            $teks = $elemen->getText();
            if (is_string($teks)) {
                $baris[] = $teks;
            }
        }
    }

    /**
     * Terjemahkan satu tabel menjadi baris-baris naskah biasa.
     *
     * Hanya tabel dua kolom yang diterjemahkan, dan itu pun bukan semuanya:
     * sebagian naskah memakai tabel sekadar sebagai tata letak, mis. opsi
     * dipasang dua-dua per baris. Sel yang isinya sudah menerangkan dirinya
     * sendiri ("A. Bandung") karena itu dibiarkan berdiri sebagai barisnya
     * masing-masing, persis seperti pembacaan sebelum tabel dikenali.
     *
     * @param  array<int, string>  $baris
     */
    protected function kumpulkanTabel(Table $tabel, array &$baris): void
    {
        // Nomor butir hanya diakui sekali per butir. Tanpa pembatasan ini
        // pasangan penjodohan yang sisi kirinya kebetulan angka — "1945 |
        // Proklamasi" — akan dibaca sebagai awal soal baru.
        $nomorTerpakai = false;

        foreach ($tabel->getRows() as $row) {
            $sel = [];

            foreach ($row->getCells() as $cell) {
                $isi = [];

                foreach ($cell->getElements() as $anak) {
                    $this->kumpulkanTeks($anak, $isi);
                }

                $isi = array_values(array_filter(
                    array_map(fn ($t) => $this->rapikan((string) $t), $isi),
                    fn ($t) => $t !== ''
                ));

                if ($isi !== []) {
                    $sel[] = $isi;
                }
            }

            if ($sel === []) {
                continue;
            }

            if (count($sel) !== 2 || $this->selMenerangkanDiri($sel)) {
                foreach ($sel as $isi) {
                    foreach ($isi as $t) {
                        $baris[] = $t;
                    }
                }

                if ($this->barisPenanda($sel[0][0])) {
                    $nomorTerpakai = false;
                }

                continue;
            }

            // Gambar dipisahkan dari teks: ia tetap menyusul sebagai barisnya
            // sendiri supaya pengurai bisa menempelkannya ke opsi atau
            // pertanyaan yang tepat.
            $kiri = $ekor = [];

            foreach ($sel[0] as $t) {
                $this->barisGambar($t) ? $ekor[] = $t : $kiri[] = $t;
            }

            $kepala = '';
            $sisa = [];

            foreach ($sel[1] as $t) {
                if ($kepala === '' && ! $this->barisGambar($t)) {
                    $kepala = $t;

                    continue;
                }

                $sisa[] = $t;
            }

            $teksKiri = implode(' ', $kiri);

            if ($teksKiri === '') {
                // Sel kiri kosong — lazimnya baris sambungan pada sel yang
                // digabung. Isinya dipakai apa adanya.
                $baris[] = $kepala;
            } elseif (preg_match('/^(?:'.self::POLA_PENANDA.')$/iu', rtrim($teksKiri, ' :.'))) {
                $baris[] = rtrim($teksKiri, ' :.').': '.$kepala;
                $nomorTerpakai = false;
            } elseif (preg_match('/^([A-Ha-h])[.)]?$/u', $teksKiri, $m)) {
                $baris[] = strtoupper($m[1]).'. '.$kepala;
            } elseif (! $nomorTerpakai && $kepala !== '' && preg_match('/^(\d{1,3})[.)]?$/u', $teksKiri, $m)) {
                $baris[] = $m[1].'. '.$kepala;
                $nomorTerpakai = true;
            } else {
                $baris[] = $teksKiri.' '.self::PEMISAH_JODOH.' '.$kepala;
            }

            foreach (array_merge($sisa, $ekor) as $t) {
                $baris[] = $t;
            }
        }
    }

    /**
     * Baris tabel yang selnya sudah berbentuk naskah utuh — "A. Bandung",
     * "1. Ibu kota ..." — tidak perlu, dan tidak boleh, dipasangkan.
     *
     * @param  array<int, array<int, string>>  $sel
     */
    protected function selMenerangkanDiri(array $sel): bool
    {
        foreach ($sel as $isi) {
            foreach ($isi as $t) {
                if (preg_match('/^(?:[A-Ha-h]|\d{1,3})[.)]\s*\S/u', $t)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function barisGambar(string $teks): bool
    {
        return (bool) preg_match('/^\[\[GAMBAR:[^\]]+\]\]$/', $teks);
    }

    protected function barisPenanda(string $teks): bool
    {
        return (bool) preg_match('/^(?:'.self::POLA_PENANDA.')\s*[:.]/iu', $teks);
    }

    /**
     * @param  array<int, string>  $baris
     * @return array{soal: array<int, array<string, mixed>>, galat: array<int, string>}
     */
    protected function uraikan(array $baris): array
    {
        $soal = [];
        $galat = [];
        $blok = null;

        // Pada naskah bertabel, "JENIS SOAL : ..." berdiri di baris kepala
        // tabel — sebelum nomor butirnya. Penanda itu disimpan dulu, lalu
        // dipasangkan ke butir yang menyusul.
        $jenisTertunda = null;

        foreach ($baris as $teks) {
            // --- Awal butir soal baru: "1." / "1)" di awal baris ---
            if (preg_match('/^(\d{1,3})[.)]\s*(.*)$/u', $teks, $m)) {
                // Baris "1. Tokyo" di dalam daftar jodoh bukan soal baru,
                // melainkan pasangan — dikenali dari adanya pemisah ##.
                if (! str_contains($teks, self::PEMISAH_JODOH)) {
                    if ($blok !== null) {
                        $this->tutupBlok($blok, $soal, $galat);
                    }
                    $blok = $this->blokBaru((int) $m[1], $m[2], $jenisTertunda);
                    $jenisTertunda = null;

                    continue;
                }
            }

            // --- Jenis soal: boleh menyusul maupun mendahului nomor butirnya ---
            //
            // Naskah biasa menulis "JENIS: essay" tepat di bawah nomor soal,
            // sedangkan naskah bertabel menaruh "JENIS SOAL : ..." di baris
            // kepala tabel — yaitu sesudah butir sebelumnya selesai. Keduanya
            // dibedakan dari keadaan butir yang sedang dibaca: selama butir itu
            // belum punya opsi, pasangan, maupun kunci, penanda jenis masih
            // miliknya; sesudah itu, penanda jenis sudah milik butir berikutnya.
            if (preg_match('/^JENIS(?:\s*SOAL)?\s*[:.]\s*(.+)$/iu', $teks, $m)) {
                $masihKosong = $blok !== null
                    && $blok['opsi'] === []
                    && $blok['jodoh'] === []
                    && $blok['kunci'] === '';

                if ($masihKosong) {
                    $blok['jenis_paksa'] = trim($m[1]);
                } else {
                    $jenisTertunda = trim($m[1]);
                }

                continue;
            }

            if ($blok === null) {
                continue; // teks pembuka dokumen sebelum soal pertama
            }

            // --- Penanda eksplisit ---
            if (preg_match('/^KATA\s*KUNCI\s*[:.]\s*(.*)$/iu', $teks, $m)) {
                $blok['kata_kunci'] = array_values(array_filter(
                    array_map('trim', explode(',', $m[1])),
                    fn ($k) => $k !== ''
                ));

                continue;
            }
            if (preg_match('/^(?:KUNCI(?:\s*JAWABAN)?|JAWABAN)\s*[:.]\s*(.*)$/iu', $teks, $m)) {
                $blok['kunci'] = trim($m[1]);

                continue;
            }
            if (preg_match('/^PEMBAHASAN\s*[:.]\s*(.*)$/iu', $teks, $m)) {
                $blok['pembahasan'] = trim($m[1]);

                continue;
            }
            if (preg_match('/^BOBOT\s*[:.]\s*([\d.,]+)/iu', $teks, $m)) {
                $blok['bobot'] = (float) str_replace(',', '.', $m[1]);

                continue;
            }
            if (preg_match('/^(?:LEVEL|RANAH)\s*[:.]\s*(C[1-6])/iu', $teks, $m)) {
                $blok['level_kognitif'] = strtoupper($m[1]);

                continue;
            }
            if (preg_match('/^(?:KESUKARAN|TINGKAT\s*KESUKARAN)\s*[:.]\s*(\w+)/iu', $teks, $m)) {
                $blok['tingkat_kesukaran'] = strtolower($m[1]);

                continue;
            }

            // --- Gambar ---
            // Ditempelkan pada bagian yang paling terakhir ditulis: bila baris
            // sebelumnya sebuah opsi, gambar itu milik opsi tersebut (soal
            // pilihan ganda bergambar), selain itu milik pertanyaannya.
            if (preg_match('/^\[\[GAMBAR:([^\]]+)\]\]$/', $teks, $m)) {
                $tag = $this->tagGambar($m[1]);

                if ($blok['opsi_terakhir'] !== null) {
                    $blok['opsi'][$blok['opsi_terakhir']] = trim($blok['opsi'][$blok['opsi_terakhir']].' '.$tag);
                } else {
                    $blok['pertanyaan'] = trim($blok['pertanyaan'].' '.$tag);
                }

                continue;
            }

            // --- Pasangan penjodohan: "Jepang ## Tokyo" ---
            if (str_contains($teks, self::PEMISAH_JODOH)) {
                $bersih = preg_replace('/^\d{1,3}[.)]\s*/u', '', $teks);
                [$kiri, $kanan] = array_map('trim', explode(self::PEMISAH_JODOH, $bersih, 2));
                $blok['jodoh'][] = ['kiri' => $kiri, 'kanan' => $kanan];

                continue;
            }

            // --- Opsi jawaban: "A. Bandung" / "a) Bandung" ---
            // Teksnya boleh kosong — opsi yang isinya cuma gambar ditulis
            // sebagai "A." saja, gambarnya menyusul di baris berikutnya.
            if (preg_match('/^([A-Ea-e])[.)]\s*(.*)$/u', $teks, $m)) {
                $huruf = strtoupper($m[1]);
                $blok['opsi'][$huruf] = trim($m[2]);
                $blok['opsi_terakhir'] = $huruf;

                continue;
            }

            // --- Daftar pilihan bawaan soal benar/salah ---
            // Naskah bertabel mencantumkan "benar" dan "salah" sebagai dua
            // baris tersendiri, sekadar memperlihatkan pilihan yang akan
            // dilihat siswa. Keduanya bukan lanjutan pertanyaan, dan kuncinya
            // tetap diambil dari baris JAWABAN.
            if ($this->kodeJenis((string) $blok['jenis_paksa']) === Soal::BENAR_SALAH
                && $this->nilaiBenarSalah($teks) !== null) {
                continue;
            }

            // --- Selain itu: lanjutan teks pertanyaan ---
            $blok['opsi_terakhir'] = null;
            $blok['pertanyaan'] = trim($blok['pertanyaan'].' '.$teks);
        }

        if ($blok !== null) {
            $this->tutupBlok($blok, $soal, $galat);
        }

        if ($soal === [] && $galat === []) {
            $galat[] = 'Tidak ada butir soal yang terbaca. Pastikan tiap soal diawali nomor, mis. "1. Isi pertanyaan".';
        }

        return compact('soal', 'galat');
    }

    /** @return array<string, mixed> */
    protected function blokBaru(int $nomor, string $pertanyaan, ?string $jenisPaksa = null): array
    {
        return [
            'nomor' => $nomor,
            'pertanyaan' => trim($pertanyaan),
            'opsi' => [],
            'opsi_terakhir' => null,
            'jodoh' => [],
            'kunci' => '',
            'kata_kunci' => [],
            'pembahasan' => null,
            'bobot' => 1.0,
            'level_kognitif' => null,
            'tingkat_kesukaran' => null,
            'jenis_paksa' => $jenisPaksa,
        ];
    }

    /**
     * @param  array<string, mixed>  $blok
     * @param  array<int, array<string, mixed>>  $soal
     * @param  array<int, string>  $galat
     */
    protected function tutupBlok(array $blok, array &$soal, array &$galat): void
    {
        try {
            $soal[] = $this->susun($blok);
        } catch (\InvalidArgumentException $e) {
            $galat[] = "Soal nomor {$blok['nomor']}: {$e->getMessage()}";
        }
    }

    /**
     * @param  array<string, mixed>  $blok
     * @return array<string, mixed>
     */
    /** Ubah alamat gambar menjadi tag siap tampil. */
    protected function tagGambar(string $alamat): string
    {
        return '<img src="'.e($alamat).'" alt="Gambar soal">';
    }

    protected function susun(array $blok): array
    {
        if ($blok['pertanyaan'] === '') {
            throw new \InvalidArgumentException('teks pertanyaan kosong.');
        }

        $jenis = $this->tentukanJenis($blok);
        [$opsi, $kunci] = $this->susunOpsiDanKunci($jenis, $blok);

        return [
            'jenis' => $jenis,
            // Naskah kerap menuliskan rumusnya sebagai LaTeX; tanpa diurai,
            // kode mentahnya yang terbaca siswa saat ujian.
            'pertanyaan' => RumusLatex::keHtml($blok['pertanyaan']),
            'opsi' => $opsi,
            'kunci' => $kunci,
            'bobot' => (float) $blok['bobot'],
            'level_kognitif' => $blok['level_kognitif'],
            'tingkat_kesukaran' => $blok['tingkat_kesukaran'],
            'pembahasan' => RumusLatex::keHtml($blok['pembahasan']),
        ];
    }

    /**
     * Terjemahkan nama jenis yang ditulis guru menjadi kode jenis soal.
     *
     * Penulisannya dibiarkan bebas, karena begitulah naskah datang dari
     * sekolah: "PILIHAN GANDA KOMPLEKS (JAWABAN LEBIH DARI 1)",
     * "SALAH/BENAR (BIASA)", "Essay / Uraian", "pg_kompleks". Yang dibaca
     * karena itu bukan seluruh kalimatnya, melainkan kata-kata penandanya —
     * keterangan tambahan dalam kurung boleh ada, boleh tidak, dan urutan
     * "benar/salah" maupun "salah/benar" sama-sama diterima.
     */
    protected function kodeJenis(string $teks): ?string
    {
        $kata = ' '.trim(preg_replace('/[^a-z]+/', ' ', mb_strtolower(trim($teks)))).' ';

        $ada = fn (string ...$cari) => array_reduce(
            $cari,
            fn ($hasil, $k) => $hasil || str_contains($kata, " {$k} "),
            false
        );

        // Kata "kompleks" diperiksa lebih dulu: tanpa itu "BENAR/SALAH
        // (KOMPLEKS)" terbaca sebagai benar/salah biasa, dan seluruh daftar
        // pernyataannya hilang menjadi lanjutan teks pertanyaan.
        $kompleks = $ada('kompleks', 'komplek');

        return match (true) {
            $ada('jodoh', 'penjodohan', 'menjodohkan') => Soal::PENJODOHAN,
            $ada('essay', 'esai', 'uraian') => Soal::ESSAY,
            // "pilihan ganda" diperiksa sebelum "benar/salah" supaya
            // keterangan seperti "(pilih jawaban yang benar)" tidak membelokkan
            // soal pilihan ganda menjadi soal benar/salah.
            $kompleks && $ada('pg', 'ganda') => Soal::PG_KOMPLEKS,
            $kompleks && $ada('benar', 'salah', 'bs') => Soal::BENAR_SALAH_KOMPLEKS,
            $ada('pg', 'ganda') => Soal::PG,
            $ada('benar', 'salah', 'bs') => Soal::BENAR_SALAH,
            default => null,
        };
    }

    /** @param array<string, mixed> $blok */
    protected function tentukanJenis(array $blok): string
    {
        if ($blok['jenis_paksa']) {
            $jenis = $this->kodeJenis((string) $blok['jenis_paksa']);

            if ($jenis === null) {
                throw new \InvalidArgumentException("penanda JENIS \"{$blok['jenis_paksa']}\" tidak dikenali.");
            }

            return $jenis;
        }

        if ($blok['jodoh'] !== []) {
            // Baris "pernyataan ## jodohnya" melayani dua jenis soal. Bila
            // seluruh sisi kanannya hanya berisi benar/salah, yang dimaksud
            // penulis naskah adalah benar/salah kompleks — pasangan seperti
            // itu tidak masuk akal sebagai soal penjodohan, karena kolom
            // kanannya cuma punya dua isi yang berulang.
            $semuaBenarSalah = collect($blok['jodoh'])
                ->every(fn ($p) => $this->nilaiBenarSalah($p['kanan']) !== null);

            return $semuaBenarSalah ? Soal::BENAR_SALAH_KOMPLEKS : Soal::PENJODOHAN;
        }

        $kunciMentah = strtolower(trim((string) $blok['kunci']));

        if ($blok['opsi'] === []) {
            return $this->nilaiBenarSalah($kunciMentah) !== null
                ? Soal::BENAR_SALAH
                : Soal::ESSAY;
        }

        // Kunci "A, C" atau "AC" berarti soal pilihan ganda kompleks.
        $huruf = array_filter(preg_split('/[^A-Za-z]+/', $kunciMentah) ?: []);
        $jumlahHuruf = $huruf === []
            ? 0
            : array_sum(array_map(fn ($h) => strlen($h), $huruf));

        return $jumlahHuruf > 1 ? Soal::PG_KOMPLEKS : Soal::PG;
    }

    /**
     * Baca tulisan kunci benar/salah apa adanya dari naskah: "Benar", "b",
     * "SALAH", "false", dan seterusnya. Mengembalikan null bila teksnya bukan
     * salah satu dari keduanya — dipakai juga untuk menebak jenis soal.
     */
    protected function nilaiBenarSalah(string $teks): ?string
    {
        $t = strtolower(trim($teks));

        return match (true) {
            in_array($t, ['benar', 'b', 'true'], true) => 'benar',
            in_array($t, ['salah', 's', 'false'], true) => 'salah',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $blok
     * @return array{0: array|null, 1: array}
     */
    protected function susunOpsiDanKunci(string $jenis, array $blok): array
    {
        $kunciMentah = trim((string) $blok['kunci']);

        if ($jenis === Soal::BENAR_SALAH) {
            return [null, [$this->nilaiBenarSalah($kunciMentah) ?? 'salah']];
        }

        if ($jenis === Soal::BENAR_SALAH_KOMPLEKS) {
            if (count($blok['jodoh']) < 2) {
                throw new \InvalidArgumentException(
                    'soal benar/salah kompleks minimal 2 pernyataan, tiap baris ditulis '
                    .'"pernyataan '.self::PEMISAH_JODOH.' benar" atau "pernyataan '.self::PEMISAH_JODOH.' salah".'
                );
            }

            $opsi = $kunci = [];

            foreach (array_values($blok['jodoh']) as $i => $pernyataan) {
                $nilai = $this->nilaiBenarSalah($pernyataan['kanan']);

                if ($nilai === null) {
                    throw new \InvalidArgumentException(
                        'kunci pernyataan "'.TeksSoal::polos($pernyataan['kiri']).'" harus "benar" atau "salah".'
                    );
                }

                $nomor = (string) ($i + 1);
                $opsi[] = ['key' => $nomor, 'text' => RumusLatex::keHtml($pernyataan['kiri'])];
                $kunci[$nomor] = $nilai;
            }

            return [$opsi, $kunci];
        }

        if ($jenis === Soal::ESSAY) {
            if ($kunciMentah === '') {
                throw new \InvalidArgumentException('soal essay belum punya baris JAWABAN sebagai kunci penilaian.');
            }

            return [null, [
                'jawaban' => RumusLatex::keHtml($kunciMentah),
                // Kata kunci membantu guru menilai cepat: bagian jawaban yang
                // wajib muncul ditulis "KATA KUNCI: evaporasi, kondensasi".
                'kata_kunci' => array_values((array) $blok['kata_kunci']),
            ]];
        }

        if ($jenis === Soal::PENJODOHAN) {
            if (count($blok['jodoh']) < 2) {
                throw new \InvalidArgumentException(
                    'soal penjodohan minimal 2 pasangan, ditulis "pernyataan '.self::PEMISAH_JODOH.' jodohnya".'
                );
            }

            $huruf = range('A', 'Z');
            $kiri = $kanan = $kunci = [];

            foreach (array_values($blok['jodoh']) as $i => $pasangan) {
                $nomor = (string) ($i + 1);
                $kiri[] = ['key' => $nomor, 'text' => RumusLatex::keHtml($pasangan['kiri'])];
                $kanan[] = ['key' => $huruf[$i], 'text' => RumusLatex::keHtml($pasangan['kanan'])];
                $kunci[$nomor] = $huruf[$i];
            }

            return [['kiri' => $kiri, 'kanan' => $kanan], $kunci];
        }

        // pg & pg_kompleks
        if (count($blok['opsi']) < 2) {
            throw new \InvalidArgumentException('pilihan ganda minimal punya 2 opsi (A, B, ...).');
        }

        ksort($blok['opsi']);
        $opsi = [];

        foreach ($blok['opsi'] as $key => $teks) {
            $isi = RumusLatex::keHtml($teks);

            // Naskah boleh menuliskan "A." tanpa isi — bentuk yang dipakai
            // untuk opsi bergambar, gambarnya menyusul di baris berikutnya.
            // Bila ternyata tidak ada gambar yang menyusul, opsinya diisi
            // hurufnya sendiri, sama seperti pada formulir input.
            $opsi[] = ['key' => $key, 'text' => TeksSoal::kosong($isi) ? $key : $isi];
        }

        // "A, C" maupun "AC" sama-sama dipecah menjadi ['A','C'].
        $kunci = array_values(array_unique(array_filter(
            preg_split('//u', strtoupper(preg_replace('/[^A-Za-z]/', '', $kunciMentah)), -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));

        if ($kunci === []) {
            throw new \InvalidArgumentException('tidak ditemukan baris JAWABAN / KUNCI.');
        }

        $tersedia = array_column($opsi, 'key');
        foreach ($kunci as $k) {
            if (! in_array($k, $tersedia, true)) {
                throw new \InvalidArgumentException("kunci \"{$k}\" tidak ada di daftar opsi.");
            }
        }

        if ($jenis === Soal::PG && count($kunci) > 1) {
            $jenis = Soal::PG_KOMPLEKS;
        }

        return [$opsi, $kunci];
    }
}
