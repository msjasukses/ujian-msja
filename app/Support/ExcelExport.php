<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pembungkus tipis PhpSpreadsheet untuk semua tombol "Export Excel" di
 * aplikasi ini, supaya tiap laporan tidak menulis ulang kode styling yang
 * sama. Pemakaian:
 *
 *   return ExcelExport::make('Daftar Nilai')
 *       ->judul('DAFTAR NILAI UJIAN', 'Matematika — X TKJ 1')
 *       ->header(['No', 'NISN', 'Nama', 'Nilai'])
 *       ->rows($rows)
 *       ->unduh('daftar-nilai.xlsx');
 */
class ExcelExport
{
    protected Spreadsheet $book;

    protected Worksheet $sheet;

    /** Baris berikutnya yang akan ditulis. */
    protected int $baris = 1;

    /** Baris tempat header tabel berada, dipakai untuk styling & autofilter. */
    protected ?int $barisHeader = null;

    protected int $jumlahKolom = 0;

    public function __construct(string $namaSheet = 'Sheet1')
    {
        $this->book = new Spreadsheet;
        $this->sheet = $this->book->getActiveSheet();
        // Nama sheet Excel maksimal 31 karakter dan tidak boleh memuat : \ / ? * [ ]
        $this->sheet->setTitle(mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', '-', $namaSheet), 0, 31));
    }

    public static function make(string $namaSheet = 'Sheet1'): self
    {
        return new self($namaSheet);
    }

    /** Judul besar + baris-baris keterangan di atas tabel. */
    public function judul(string $judul, string ...$keterangan): self
    {
        $this->sheet->setCellValue('A'.$this->baris, $judul);
        $this->sheet->getStyle('A'.$this->baris)->getFont()->setBold(true)->setSize(14);
        $this->baris++;

        foreach ($keterangan as $ket) {
            if ($ket === '') {
                continue;
            }
            $this->sheet->setCellValue('A'.$this->baris, $ket);
            $this->sheet->getStyle('A'.$this->baris)->getFont()->setSize(10);
            $this->baris++;
        }

        $this->baris++; // satu baris kosong pemisah

        return $this;
    }

    /** @param array<int, string> $kolom */
    public function header(array $kolom): self
    {
        $this->barisHeader = $this->baris;
        $this->jumlahKolom = max($this->jumlahKolom, count($kolom));

        foreach (array_values($kolom) as $i => $judul) {
            $this->sheet->setCellValue([$i + 1, $this->baris], $judul);
        }

        $range = $this->rangeBaris($this->baris, count($kolom));
        $this->sheet->getStyle($range)->getFont()->setBold(true);
        $this->sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E8EEF7');
        $this->sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $this->baris++;

        return $this;
    }

    /** @param iterable<int, array<int, mixed>> $rows */
    public function rows(iterable $rows): self
    {
        foreach ($rows as $row) {
            $row = array_values((array) $row);
            $this->jumlahKolom = max($this->jumlahKolom, count($row));

            foreach ($row as $i => $nilai) {
                if ($nilai instanceof \DateTimeInterface) {
                    $nilai = $nilai->format('d/m/Y H:i');
                }
                // Teks yang diawali "=" akan dianggap rumus oleh Excel; tulis
                // sebagai string eksplisit supaya tidak memicu error.
                $this->sheet->setCellValueExplicit(
                    [$i + 1, $this->baris],
                    is_scalar($nilai) || $nilai === null ? $nilai : (string) $nilai,
                    is_numeric($nilai) && ! is_bool($nilai)
                        ? DataType::TYPE_NUMERIC
                        : DataType::TYPE_STRING
                );
            }
            $this->baris++;
        }

        return $this;
    }

    /** Baris ringkasan (dicetak tebal), mis. rata-rata / total. */
    public function ringkasan(array $row): self
    {
        $awal = $this->baris;
        $this->rows([$row]);
        $this->sheet->getStyle($this->rangeBaris($awal, count($row)))->getFont()->setBold(true);

        return $this;
    }

    /** Satu baris kosong. */
    public function spasi(int $jumlah = 1): self
    {
        $this->baris += $jumlah;

        return $this;
    }

    protected function rangeBaris(int $baris, int $jumlahKolom): string
    {
        $akhir = Coordinate::stringFromColumnIndex(max(1, $jumlahKolom));

        return "A{$baris}:{$akhir}{$baris}";
    }

    /** Garis tabel + auto width + freeze pane, dipanggil sebelum menulis file. */
    protected function rapikan(): void
    {
        $kolomAkhir = Coordinate::stringFromColumnIndex(max(1, $this->jumlahKolom));

        if ($this->barisHeader !== null && $this->baris > $this->barisHeader) {
            $range = "A{$this->barisHeader}:{$kolomAkhir}".($this->baris - 1);
            $this->sheet->getStyle($range)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setRGB('B7C3D4');
            $this->sheet->setAutoFilter("A{$this->barisHeader}:{$kolomAkhir}{$this->barisHeader}");
            $this->sheet->freezePane('A'.($this->barisHeader + 1));
        }

        for ($i = 1; $i <= max(1, $this->jumlahKolom); $i++) {
            $huruf = Coordinate::stringFromColumnIndex($i);
            $this->sheet->getColumnDimension($huruf)->setAutoSize(true);
        }
    }

    /** Kirim file sebagai unduhan ke browser. */
    public function unduh(string $namaFile): StreamedResponse
    {
        $this->rapikan();
        $writer = new Xlsx($this->book);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $namaFile, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-store',
        ]);
    }

    /** Simpan ke path lokal (dipakai pembuatan file template import). */
    public function simpan(string $path): string
    {
        $this->rapikan();
        (new Xlsx($this->book))->save($path);

        return $path;
    }
}
