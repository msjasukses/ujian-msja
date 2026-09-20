<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\GuruMapel;
use App\Models\MataPelajaran;
use App\Models\Soal;
use App\Models\TingkatKelas;
use App\Models\Topik;
use App\Support\Referensi;
use Illuminate\Database\Seeder;

/**
 * Contoh topik dan bank soal untuk keperluan uji coba.
 *
 * Isinya materi Kelas 7 SMP pada empat mata pelajaran, memuat kelima jenis
 * soal yang didukung aplikasi. Mata pelajaran, tingkat kelas dan guru pengampu
 * dicari berdasarkan namanya di database Data Center — jadi seeder ini
 * menyesuaikan diri dengan id yang ada di sana, bukan menuliskannya keras.
 *
 * Dijalankan lewat ContohSeeder, bukan DatabaseSeeder, supaya data contoh
 * tidak pernah ikut masuk saat menyiapkan lingkungan sebenarnya.
 */
class ContohBankSoalSeeder extends Seeder
{
    /** Tingkat kelas yang dipakai seluruh data contoh. */
    protected const TINGKAT = 'Kelas 7';

    protected ?int $tingkatId = null;

    /** @var array<string, int> nama mapel => id */
    protected array $mapel = [];

    /** @var array<int, int|null> mata_pelajaran_id => guru_id pengampu */
    protected array $pengampu = [];

    /** Topik yang sedang diisi soalnya oleh helper di bawah. */
    protected ?Topik $topikAktif = null;

    protected int $nomorSoal = 0;

    public function run(): void
    {
        $this->tingkatId = TingkatKelas::where('nama', self::TINGKAT)->value('id');

        if (! $this->tingkatId) {
            $this->command->error('Tingkat "'.self::TINGKAT.'" tidak ditemukan di Data Center. Seeder contoh dibatalkan.');

            return;
        }

        $this->mapel = MataPelajaran::pluck('id', 'nama_mapel')->all();

        $this->matematika();
        $this->bahasaIndonesia();
        $this->ipa();
        $this->informatika();
        $this->pai();

        $this->command->info('Bank soal contoh: '.Topik::count().' topik, '.Soal::count().' butir soal.');
    }

    // =====================================================================
    // Matematika
    // =====================================================================

    protected function matematika(): void
    {
        $this->topik('MTK-7-01', 'Bilangan Bulat dan Operasinya', 'Matematika',
            elemen: 'Bilangan',
            cp: 'Peserta didik dapat membaca, menulis, dan membandingkan bilangan bulat, serta melakukan operasi hitung pada bilangan bulat.',
            tp: 'Melakukan operasi penjumlahan, pengurangan, perkalian, dan pembagian bilangan bulat termasuk penerapannya pada soal cerita.');

        $this->pg('Hasil dari (-8) + 15 - (-4) adalah ...',
            ['3', '11', '19', '27'], 'B', 1, 'C2', 'mudah',
            '(-8) + 15 = 7, lalu 7 - (-4) = 7 + 4 = 11.');

        $this->pg('Suhu di puncak gunung pagi hari -3 °C. Siang hari suhunya naik 12 °C. Suhu pada siang hari adalah ...',
            ['-15 °C', '-9 °C', '9 °C', '15 °C'], 'C', 1, 'C3', 'mudah',
            'Naik berarti ditambah: -3 + 12 = 9 °C.');

        $this->pg('Hasil dari (-6) × 7 : (-3) adalah ...',
            ['-14', '14', '-42', '42'], 'B', 2, 'C2', 'sedang',
            '(-6) × 7 = -42, lalu -42 : (-3) = 14. Bagi dua bilangan negatif hasilnya positif.');

        $this->pg('Urutan bilangan -5, 3, -12, 0, 8 dari yang terkecil adalah ...',
            ['-5, -12, 0, 3, 8', '-12, -5, 0, 3, 8', '0, -5, -12, 3, 8', '-12, -5, 3, 0, 8'],
            'B', 1, 'C2', 'mudah',
            'Makin ke kiri pada garis bilangan, makin kecil nilainya: -12 < -5 < 0 < 3 < 8.');

        $this->pgKompleks('Manakah pernyataan berikut yang bernilai benar? (jawaban boleh lebih dari satu)',
            ['-7 < -2', '(-3)² = -9', '|-6| = 6', '0 > -1', '(-2)³ = 8'],
            ['A', 'C', 'D'], 3, 'C4', 'sedang',
            '(-3)² = 9 bukan -9, dan (-2)³ = -8 bukan 8. Tiga pernyataan lainnya benar.');

        $this->benarSalah('Hasil bagi dua bilangan bulat negatif selalu bernilai positif.',
            'benar', 1, 'C2', 'sedang',
            'Tanda negatif dibagi negatif saling meniadakan, sehingga hasilnya positif.');

        $this->benarSalah('Setiap bilangan bulat negatif selalu lebih kecil daripada nol.',
            'benar', 1, 'C1', 'mudah');

        $this->penjodohan('Jodohkan operasi berikut dengan hasilnya!', [
            '(-4) + (-9)' => '-13',
            '(-20) : 5' => '-4',
            '6 × (-3)' => '-18',
            '(-15) - (-7)' => '-8',
        ], 4, 'C2', 'sedang');

        $this->essay('Seorang penyelam berada 18 meter di bawah permukaan laut. Ia naik 7 meter, lalu turun lagi 12 meter. '.
            'Tentukan posisi akhir penyelam terhadap permukaan laut. Tuliskan langkah pengerjaanmu!',
            'Posisi awal -18 m. Naik 7 m: -18 + 7 = -11 m. Turun 12 m: -11 - 12 = -23 m. '.
            'Jadi posisi akhir penyelam adalah 23 meter di bawah permukaan laut.',
            ['-18', 'naik', 'turun', '-23', 'di bawah permukaan'],
            6, 'C3', 'sedang',
            'Perhatikan kelengkapan langkah: posisi awal, operasi naik, operasi turun, dan kesimpulan.');

        // ------------------------------------------------------------------
        $this->topik('MTK-7-02', 'Himpunan', 'Matematika',
            elemen: 'Bilangan',
            cp: 'Peserta didik dapat menyatakan himpunan, menentukan anggota himpunan, dan melakukan operasi irisan serta gabungan.',
            tp: 'Menentukan irisan, gabungan, dan komplemen dari dua himpunan serta menyajikannya dalam diagram Venn.');

        $this->pg('Diketahui A = {1, 2, 3, 4, 5} dan B = {4, 5, 6, 7}. Hasil dari A ∩ B adalah ...',
            ['{1, 2, 3}', '{4, 5}', '{6, 7}', '{1, 2, 3, 4, 5, 6, 7}'], 'B', 2, 'C2', 'mudah',
            'Irisan memuat anggota yang ada di kedua himpunan, yaitu 4 dan 5.');

        $this->pg('Banyaknya himpunan bagian dari himpunan {a, b, c} adalah ...',
            ['3', '6', '8', '9'], 'C', 2, 'C3', 'sedang',
            'Banyak himpunan bagian = 2ⁿ dengan n = 3, sehingga 2³ = 8.');

        $this->pgKompleks('Diketahui S = {bilangan asli kurang dari 10} dan P = {2, 4, 6, 8}. '.
            'Manakah yang merupakan anggota komplemen P? (jawaban boleh lebih dari satu)',
            ['1', '4', '5', '8', '9'], ['A', 'C', 'E'], 3, 'C4', 'sukar',
            'Komplemen P memuat anggota S yang tidak ada di P, yaitu {1, 3, 5, 7, 9}.');

        // ------------------------------------------------------------------
        $this->topik('MTK-7-03', 'Persamaan Linear Satu Variabel', 'Matematika',
            elemen: 'Aljabar',
            cp: 'Peserta didik dapat menyelesaikan persamaan linear satu variabel dan menerapkannya dalam pemecahan masalah.',
            tp: 'Menentukan penyelesaian persamaan linear satu variabel serta menyusun model matematika dari soal cerita.');

        $this->pg('Penyelesaian dari 3x - 7 = 14 adalah ...',
            ['x = 5', 'x = 7', 'x = 9', 'x = 21'], 'B', 2, 'C3', 'mudah',
            '3x = 14 + 7 = 21, sehingga x = 21 : 3 = 7.');

        $this->pg('Jumlah tiga bilangan ganjil berurutan adalah 51. Bilangan terkecilnya adalah ...',
            ['15', '17', '19', '21'], 'A', 3, 'C4', 'sukar',
            'Misal bilangan terkecil x, maka x + (x+2) + (x+4) = 51 → 3x + 6 = 51 → x = 15.');

        $this->benarSalah('Persamaan 2x + 5 = 2x + 9 memiliki tepat satu penyelesaian.',
            'salah', 2, 'C4', 'sukar',
            'Kedua ruas memuat 2x sehingga tersisa 5 = 9 yang mustahil. Persamaan ini tidak punya penyelesaian.');

        $this->essay('Harga 3 buku tulis dan 2 pensil adalah Rp 23.000. Jika harga satu pensil Rp 4.000, '.
            'tentukan harga satu buku tulis! Tuliskan model matematikanya.',
            'Misal harga buku = x. Model: 3x + 2(4.000) = 23.000 → 3x + 8.000 = 23.000 → 3x = 15.000 → x = 5.000. '.
            'Jadi harga satu buku tulis adalah Rp 5.000.',
            ['3x', '8.000', '15.000', '5.000'],
            6, 'C3', 'sedang');

        // Butir peraga penanda matematika: pangkat, pecahan bertingkat,
        // dan akar — dipakai memeriksa tampilannya di seluruh halaman.
        $this->pg('Nilai dari <span class="pecahan"><span class="pembilang">2x + 6</span>'.
            '<span class="penyebut">2</span></span> untuk x = 3 adalah ...',
            ['4', '6', '8', '12'], 'B', 2, 'C3', 'sedang',
            '(2·3 + 6) : 2 = 12 : 2 = 6.');

        $this->pg('Hasil dari <span class="akar"><span class="radikan">144</span></span> × (−3)² ÷ 6 adalah ...',
            ['9', '18', '27', '36'], 'B', 3, 'C3', 'sedang',
            '√144 = 12, (−3)² = 9, sehingga 12 × 9 : 6 = 18.');

        $this->benarSalah('Untuk setiap bilangan real x berlaku x<sup>2</sup> ≥ 0.',
            'benar', 2, 'C4', 'sedang',
            'Kuadrat sebuah bilangan tidak pernah negatif, termasuk untuk x = 0.');
    }

    // =====================================================================
    // Bahasa Indonesia
    // =====================================================================

    protected function bahasaIndonesia(): void
    {
        $this->topik('BIN-7-01', 'Teks Deskripsi', 'Bahasa Indonesia',
            elemen: 'Membaca dan Memirsa',
            cp: 'Peserta didik memahami informasi dalam teks deskripsi serta mengidentifikasi struktur dan kaidah kebahasaannya.',
            tp: 'Mengidentifikasi struktur, ciri, dan kaidah kebahasaan teks deskripsi, serta menyusunnya secara tertulis.');

        $this->pg('Tujuan utama penulisan teks deskripsi adalah ...',
            [
                'meyakinkan pembaca agar mengikuti pendapat penulis',
                'menggambarkan objek sehingga pembaca seolah melihat sendiri',
                'menjelaskan langkah-langkah melakukan sesuatu',
                'menceritakan rangkaian peristiwa secara berurutan',
            ], 'B', 1, 'C1', 'mudah',
            'Teks deskripsi melukiskan objek secara rinci agar pembaca ikut merasakan dan membayangkannya.');

        $this->pg('Struktur teks deskripsi yang tepat adalah ...',
            [
                'identifikasi – deskripsi bagian – simpulan',
                'orientasi – komplikasi – resolusi',
                'tesis – argumen – penegasan ulang',
                'tujuan – bahan – langkah',
            ], 'A', 2, 'C2', 'sedang',
            'Pilihan B struktur teks narasi, C teks eksposisi, dan D teks prosedur.');

        $this->pg('Kalimat berikut yang menggunakan majas personifikasi adalah ...',
            [
                'Pantai itu berpasir putih dan sangat luas.',
                'Angin sore membelai lembut wajah para pengunjung.',
                'Air lautnya sebening kaca.',
                'Deburan ombak terdengar sampai kejauhan.',
            ], 'B', 2, 'C3', 'sedang',
            'Personifikasi memberikan sifat manusia kepada benda; angin digambarkan "membelai".');

        $this->pgKompleks('Manakah yang termasuk ciri kebahasaan teks deskripsi? (jawaban boleh lebih dari satu)',
            [
                'menggunakan kata sifat yang rinci',
                'menggunakan kata kerja imperatif',
                'melibatkan pancaindra pembaca',
                'menggunakan konjungsi urutan waktu seperti "kemudian"',
                'menggunakan majas untuk memperkuat gambaran',
            ], ['A', 'C', 'E'], 3, 'C4', 'sedang',
            'Kata kerja imperatif dan konjungsi urutan waktu adalah ciri teks prosedur, bukan deskripsi.');

        $this->benarSalah('Teks deskripsi selalu disusun berdasarkan urutan waktu kejadian.',
            'salah', 1, 'C2', 'sedang',
            'Urutan waktu adalah ciri teks narasi. Deskripsi disusun berdasarkan bagian-bagian objek.');

        $this->penjodohan('Jodohkan jenis teks berikut dengan tujuannya!', [
            'Teks deskripsi' => 'menggambarkan objek secara rinci',
            'Teks prosedur' => 'memandu langkah melakukan sesuatu',
            'Teks narasi' => 'menceritakan rangkaian peristiwa',
            'Teks eksposisi' => 'memaparkan pendapat disertai argumen',
        ], 4, 'C2', 'sedang');

        $this->essay('Tuliskan sebuah paragraf deskripsi minimal empat kalimat tentang ruang kelasmu. '.
            'Gunakan setidaknya dua kata sifat dan satu kalimat yang melibatkan pancaindra.',
            'Paragraf memuat identifikasi objek (ruang kelas), deskripsi bagian yang rinci, minimal dua kata sifat, '.
            'dan satu kalimat yang melibatkan pancaindra (penglihatan, pendengaran, atau penciuman).',
            ['kata sifat', 'pancaindra', 'rinci', 'minimal empat kalimat'],
            8, 'C6', 'sedang',
            'Nilai penuh bila keempat syarat terpenuhi; kurangi bila jumlah kalimat atau kata sifat belum cukup.');

        // ------------------------------------------------------------------
        $this->topik('BIN-7-02', 'Teks Prosedur', 'Bahasa Indonesia',
            elemen: 'Menulis',
            cp: 'Peserta didik dapat menulis teks prosedur dengan struktur dan kaidah kebahasaan yang tepat.',
            tp: 'Menyusun teks prosedur yang runtut dengan kalimat perintah yang jelas.');

        $this->pg('Kalimat berikut yang merupakan kalimat imperatif adalah ...',
            [
                'Adonan itu terasa sangat lembut.',
                'Tuangkan air panas ke dalam gelas secara perlahan.',
                'Ibu sedang membuat kue di dapur.',
                'Kue itu matang setelah dua puluh menit.',
            ], 'B', 1, 'C2', 'mudah',
            'Kalimat imperatif berisi perintah dan umumnya diawali kata kerja seperti "tuangkan".');

        $this->benarSalah('Teks prosedur menggunakan konjungsi urutan seperti "pertama", "kemudian", dan "terakhir".',
            'benar', 1, 'C1', 'mudah');

        $this->pgKompleks('Bagian yang wajib ada dalam teks prosedur adalah ... (jawaban boleh lebih dari satu)',
            ['tujuan', 'alat dan bahan', 'langkah-langkah', 'orientasi tokoh', 'komplikasi'],
            ['A', 'B', 'C'], 3, 'C3', 'sedang',
            'Orientasi tokoh dan komplikasi adalah bagian teks narasi.');
    }

    // =====================================================================
    // Ilmu Pengetahuan Alam
    // =====================================================================

    protected function ipa(): void
    {
        $this->topik('IPA-7-01', 'Klasifikasi Makhluk Hidup', 'Ilmu Pengetahuan Alam',
            elemen: 'Pemahaman IPA',
            cp: 'Peserta didik dapat mengklasifikasikan makhluk hidup berdasarkan ciri-ciri yang teramati.',
            tp: 'Mengelompokkan makhluk hidup berdasarkan ciri morfologi dan memahami kunci determinasi sederhana.');

        $this->pg('Ciri makhluk hidup yang membedakannya dengan benda mati adalah ...',
            ['memiliki massa', 'menempati ruang', 'dapat berkembang biak', 'dapat dipindahkan'],
            'C', 1, 'C1', 'mudah',
            'Berkembang biak (reproduksi) hanya dimiliki makhluk hidup.');

        $this->pg('Urutan takson dari yang paling luas ke paling sempit adalah ...',
            [
                'spesies – genus – famili – ordo',
                'kingdom – filum – kelas – ordo – famili – genus – spesies',
                'kingdom – kelas – filum – famili – genus – spesies',
                'genus – spesies – famili – kingdom',
            ], 'B', 2, 'C2', 'sedang',
            'Makin ke bawah, anggota takson makin sedikit dan makin mirip.');

        $this->pg('Penulisan nama ilmiah padi yang benar menurut aturan binomial nomenklatur adalah ...',
            ['oryza sativa', 'Oryza Sativa', 'Oryza sativa', 'ORYZA SATIVA'],
            'C', 2, 'C3', 'sedang',
            'Kata pertama (genus) diawali huruf kapital, kata kedua (penunjuk spesies) huruf kecil, keduanya dicetak miring.');

        $this->pgKompleks('Manakah yang termasuk ciri makhluk hidup? (jawaban boleh lebih dari satu)',
            ['bergerak', 'memiliki warna', 'peka terhadap rangsang', 'bernapas', 'memiliki bentuk tetap'],
            ['A', 'C', 'D'], 3, 'C2', 'sedang',
            'Warna dan bentuk tetap juga dimiliki benda mati, sehingga bukan ciri pembeda makhluk hidup.');

        $this->benarSalah('Semua makhluk hidup yang berada dalam satu genus pasti berada dalam famili yang sama.',
            'benar', 2, 'C4', 'sukar',
            'Genus adalah takson di bawah famili, sehingga anggota satu genus otomatis satu famili.');

        $this->penjodohan('Jodohkan kelompok hewan berikut dengan ciri khasnya!', [
            'Mamalia' => 'berkembang biak dengan melahirkan dan menyusui',
            'Aves' => 'tubuh ditutupi bulu dan berkembang biak dengan bertelur',
            'Pisces' => 'bernapas dengan insang dan hidup di air',
            'Amfibi' => 'hidup di dua alam, mengalami metamorfosis',
        ], 4, 'C2', 'sedang');

        // ------------------------------------------------------------------
        $this->topik('IPA-7-02', 'Zat dan Perubahannya', 'Ilmu Pengetahuan Alam',
            elemen: 'Pemahaman IPA',
            cp: 'Peserta didik dapat membedakan perubahan fisika dan kimia serta menjelaskan wujud zat.',
            tp: 'Membedakan perubahan fisika dan perubahan kimia berdasarkan contoh peristiwa sehari-hari.');

        $this->pg('Peristiwa berikut yang termasuk perubahan kimia adalah ...',
            ['es mencair', 'besi berkarat', 'air menguap', 'lilin meleleh'],
            'B', 2, 'C3', 'sedang',
            'Perkaratan menghasilkan zat baru (karat), sedangkan tiga peristiwa lainnya hanya mengubah wujud.');

        $this->pg('Perubahan wujud dari padat langsung menjadi gas disebut ...',
            ['mencair', 'menguap', 'menyublim', 'mengembun'],
            'C', 1, 'C1', 'mudah',
            'Contohnya kapur barus yang lama-lama habis tanpa mencair terlebih dahulu.');

        $this->pgKompleks('Manakah yang merupakan ciri terjadinya perubahan kimia? (jawaban boleh lebih dari satu)',
            ['terbentuk gas', 'terjadi perubahan warna', 'perubahan bersifat sementara', 'terbentuk endapan', 'wujudnya berubah lalu kembali'],
            ['A', 'B', 'D'], 3, 'C4', 'sukar',
            'Perubahan kimia bersifat tetap dan menghasilkan zat baru, ditandai gas, perubahan warna, endapan, atau perubahan suhu.');

        $this->essay('Jelaskan perbedaan perubahan fisika dan perubahan kimia, lalu berikan masing-masing dua contoh '.
            'peristiwa yang kamu temui sehari-hari!',
            'Perubahan fisika tidak menghasilkan zat baru dan umumnya dapat kembali ke wujud semula, '.
            'contohnya es mencair dan air menguap. Perubahan kimia menghasilkan zat baru dan bersifat tetap, '.
            'contohnya kertas dibakar dan besi berkarat.',
            ['zat baru', 'dapat kembali', 'tetap', 'dua contoh fisika', 'dua contoh kimia'],
            8, 'C4', 'sedang');
    }

    // =====================================================================
    // Informatika
    // =====================================================================

    protected function informatika(): void
    {
        $this->topik('INF-7-01', 'Berpikir Komputasional', 'Informatika',
            elemen: 'Berpikir Komputasional',
            cp: 'Peserta didik mampu menerapkan berpikir komputasional untuk menyelesaikan persoalan sehari-hari.',
            tp: 'Menerapkan dekomposisi, pengenalan pola, abstraksi, dan algoritma pada persoalan sederhana.');

        $this->pg('Memecah masalah besar menjadi bagian-bagian kecil yang lebih mudah diselesaikan disebut ...',
            ['abstraksi', 'dekomposisi', 'pengenalan pola', 'algoritma'],
            'B', 1, 'C1', 'mudah',
            'Dekomposisi adalah langkah pertama berpikir komputasional.');

        $this->pg('Urutan langkah yang logis dan terbatas untuk menyelesaikan suatu masalah disebut ...',
            ['algoritma', 'abstraksi', 'dekomposisi', 'simulasi'],
            'A', 1, 'C1', 'mudah');

        $this->pgKompleks('Manakah yang termasuk empat pilar berpikir komputasional? (jawaban boleh lebih dari satu)',
            ['dekomposisi', 'pengenalan pola', 'kompilasi', 'abstraksi', 'algoritma'],
            ['A', 'B', 'D', 'E'], 4, 'C2', 'sedang',
            'Kompilasi adalah proses menerjemahkan kode program, bukan pilar berpikir komputasional.');

        $this->penjodohan('Jodohkan pilar berpikir komputasional dengan pengertiannya!', [
            'Dekomposisi' => 'memecah masalah menjadi bagian kecil',
            'Pengenalan pola' => 'mencari kemiripan antar masalah',
            'Abstraksi' => 'mengabaikan detail yang tidak penting',
            'Algoritma' => 'menyusun langkah penyelesaian yang runtut',
        ], 4, 'C2', 'sedang');

        // ------------------------------------------------------------------
        $this->topik('INF-7-02', 'Perangkat Keras dan Perangkat Lunak', 'Informatika',
            elemen: 'Sistem Komputer',
            cp: 'Peserta didik memahami komponen sistem komputer dan fungsi masing-masing.',
            tp: 'Mengelompokkan perangkat masukan, pemroses, keluaran, dan penyimpanan pada sistem komputer.');

        $this->pg('Perangkat berikut yang termasuk perangkat masukan (input device) adalah ...',
            ['monitor', 'printer', 'papan ketik', 'pengeras suara'],
            'C', 1, 'C1', 'mudah',
            'Papan ketik (keyboard) memasukkan data ke komputer; tiga lainnya menampilkan keluaran.');

        $this->pg('Komponen yang berperan sebagai pusat pemroses data pada komputer adalah ...',
            ['RAM', 'CPU', 'hard disk', 'GPU'],
            'B', 2, 'C2', 'mudah',
            'CPU (Central Processing Unit) mengolah seluruh instruksi program.');

        $this->benarSalah('RAM merupakan media penyimpanan yang isinya tetap tersimpan meskipun komputer dimatikan.',
            'salah', 2, 'C3', 'sedang',
            'RAM bersifat volatil — isinya hilang saat daya diputus. Penyimpanan tetap dilakukan oleh hard disk atau SSD.');

        $this->penjodohan('Jodohkan perangkat berikut dengan kelompoknya!', [
            'Pemindai (scanner)' => 'perangkat masukan',
            'Proyektor' => 'perangkat keluaran',
            'SSD' => 'perangkat penyimpanan',
            'Prosesor' => 'perangkat pemroses',
        ], 4, 'C2', 'sedang');
    }

    // =====================================================================
    // Pendidikan Agama Islam — contoh soal berbahasa Arab
    // =====================================================================

    /**
     * Butir-butir di sini dipakai untuk memastikan tulisan Arab berharakat
     * dan simbol matematika tampil benar di seluruh halaman: badan soal,
     * opsi jawaban, kunci essay, sampai berkas export.
     */
    protected function pai(): void
    {
        $this->topik('PAI-7-01', 'Membaca Surah Pendek dan Artinya', 'Pendidikan Agama Islam dan Budi Pekerti',
            elemen: 'Al-Qur\'an dan Hadis',
            cp: 'Peserta didik dapat membaca, menghafal, dan memahami arti surah-surah pendek dengan tartil.',
            tp: 'Membaca surah pendek sesuai kaidah tajwid serta menjelaskan kandungan maknanya.');

        $this->pg('Arti dari lafaz <span class="teks-arab" lang="ar" dir="rtl">الرَّحْمَٰنِ الرَّحِيمِ</span> adalah ...',
            [
                'Yang Maha Pengasih lagi Maha Penyayang',
                'Yang Maha Kuasa lagi Maha Perkasa',
                'Yang Maha Mendengar lagi Maha Melihat',
                'Yang Maha Pengampun lagi Maha Penerima Tobat',
            ], 'A', 2, 'C2', 'mudah',
            'Ar-Rahman berarti Maha Pengasih, Ar-Rahim berarti Maha Penyayang.');

        $this->pg('<span class="teks-arab" lang="ar" dir="rtl">مَا مَعْنَى كَلِمَةِ "الْمَدْرَسَةُ"؟</span>',
            ['Rumah', 'Sekolah', 'Masjid', 'Pasar'], 'B', 2, 'C1', 'mudah',
            'Al-madrasah bermakna sekolah atau tempat belajar.');

        $this->penjodohan('Jodohkan lafaz berikut dengan artinya!', [
            '<span class="teks-arab" lang="ar" dir="rtl">الْحَمْدُ لِلَّهِ</span>' => 'Segala puji bagi Allah',
            '<span class="teks-arab" lang="ar" dir="rtl">سُبْحَانَ اللَّهِ</span>' => 'Maha Suci Allah',
            '<span class="teks-arab" lang="ar" dir="rtl">اللَّهُ أَكْبَرُ</span>' => 'Allah Maha Besar',
            '<span class="teks-arab" lang="ar" dir="rtl">أَسْتَغْفِرُ اللَّهَ</span>' => 'Aku memohon ampun kepada Allah',
        ], 4, 'C2', 'sedang');

        $this->benarSalah('Lafaz <span class="teks-arab" lang="ar" dir="rtl">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</span> disebut basmalah.',
            'benar', 1, 'C1', 'mudah');

        $this->essay('Tuliskan lafaz basmalah beserta artinya, lalu jelaskan kapan sebaiknya dibaca!',
            'Lafaz basmalah adalah <span class="teks-arab" lang="ar" dir="rtl">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</span> '.
            'yang artinya "Dengan nama Allah Yang Maha Pengasih lagi Maha Penyayang". '.
            'Dibaca ketika hendak memulai pekerjaan yang baik, seperti makan, belajar, dan bepergian.',
            ['basmalah', 'Maha Pengasih', 'Maha Penyayang', 'memulai pekerjaan baik'],
            8, 'C3', 'sedang');
    }
    // =====================================================================
    // Helper penulisan data
    // =====================================================================

    /** Buat topik baru dan jadikan acuan soal-soal berikutnya. */
    protected function topik(
        string $kode,
        string $nama,
        string $namaMapel,
        string $elemen,
        string $cp,
        string $tp,
    ): void {
        $mapelId = $this->mapel[$namaMapel] ?? null;

        $this->topikAktif = Topik::updateOrCreate(
            ['kode_topik' => $kode],
            [
                'nama_topik' => $nama,
                'mata_pelajaran_id' => $mapelId,
                'tingkat_kelas_id' => $this->tingkatId,
                'fase' => 'D',
                'semester' => 'Ganjil',
                'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
                'elemen' => $elemen,
                'capaian_pembelajaran' => $cp,
                'tujuan_pembelajaran' => $tp,
                'sumber' => Topik::SUMBER_MANUAL,
                'is_aktif' => true,
            ]
        );
    }

    /** @param array<int, string> $opsi */
    protected function pg(
        string $pertanyaan,
        array $opsi,
        string $kunci,
        float $bobot = 1,
        ?string $level = null,
        ?string $kesukaran = null,
        ?string $pembahasan = null,
    ): Soal {
        return $this->simpan(Soal::PG, $pertanyaan, $this->opsiHuruf($opsi), [$kunci],
            $bobot, $level, $kesukaran, $pembahasan);
    }

    /**
     * @param  array<int, string>  $opsi
     * @param  array<int, string>  $kunci
     */
    protected function pgKompleks(
        string $pertanyaan,
        array $opsi,
        array $kunci,
        float $bobot = 2,
        ?string $level = null,
        ?string $kesukaran = null,
        ?string $pembahasan = null,
    ): Soal {
        return $this->simpan(Soal::PG_KOMPLEKS, $pertanyaan, $this->opsiHuruf($opsi), $kunci,
            $bobot, $level, $kesukaran, $pembahasan);
    }

    protected function benarSalah(
        string $pernyataan,
        string $kunci,
        float $bobot = 1,
        ?string $level = null,
        ?string $kesukaran = null,
        ?string $pembahasan = null,
    ): Soal {
        return $this->simpan(Soal::BENAR_SALAH, $pernyataan, null, [$kunci],
            $bobot, $level, $kesukaran, $pembahasan);
    }

    /** @param array<string, string> $pasangan pernyataan => jodohnya */
    protected function penjodohan(
        string $pertanyaan,
        array $pasangan,
        float $bobot = 4,
        ?string $level = null,
        ?string $kesukaran = null,
        ?string $pembahasan = null,
    ): Soal {
        $huruf = range('A', 'Z');
        $kiri = $kanan = $kunci = [];

        foreach (array_values($pasangan) as $i => $jodoh) {
            $nomor = (string) ($i + 1);
            $kiri[] = ['key' => $nomor, 'text' => array_keys($pasangan)[$i]];
            $kanan[] = ['key' => $huruf[$i], 'text' => $jodoh];
            $kunci[$nomor] = $huruf[$i];
        }

        return $this->simpan(Soal::PENJODOHAN, $pertanyaan, ['kiri' => $kiri, 'kanan' => $kanan], $kunci,
            $bobot, $level, $kesukaran, $pembahasan);
    }

    /** @param array<int, string> $kataKunci */
    protected function essay(
        string $pertanyaan,
        string $jawaban,
        array $kataKunci = [],
        float $bobot = 5,
        ?string $level = null,
        ?string $kesukaran = null,
        ?string $pembahasan = null,
    ): Soal {
        return $this->simpan(Soal::ESSAY, $pertanyaan, null,
            ['jawaban' => $jawaban, 'kata_kunci' => $kataKunci],
            $bobot, $level, $kesukaran, $pembahasan);
    }

    /**
     * @param  array<int, string>  $opsi
     * @return array<int, array{key:string, text:string}>
     */
    protected function opsiHuruf(array $opsi): array
    {
        $huruf = range('A', 'Z');

        return array_map(
            fn ($teks, $i) => ['key' => $huruf[$i], 'text' => $teks],
            $opsi,
            array_keys(array_values($opsi))
        );
    }

    protected function simpan(
        string $jenis,
        string $pertanyaan,
        ?array $opsi,
        array $kunci,
        float $bobot,
        ?string $level,
        ?string $kesukaran,
        ?string $pembahasan,
    ): Soal {
        $mapelId = $this->topikAktif?->mata_pelajaran_id;

        return Soal::updateOrCreate(
            ['kode_soal' => 'CTH-'.str_pad((string) (++$this->nomorSoal), 3, '0', STR_PAD_LEFT)],
            [
                'topik_id' => $this->topikAktif?->id,
                'mata_pelajaran_id' => $mapelId,
                'tingkat_kelas_id' => $this->tingkatId,
                'tahun_ajaran' => Referensi::namaTahunAjaranAktif(),
                'jenis' => $jenis,
                'pertanyaan' => $pertanyaan,
                'opsi' => $opsi,
                'kunci' => $kunci,
                'bobot' => $bobot,
                'level_kognitif' => $level,
                'tingkat_kesukaran' => $kesukaran,
                'pembahasan' => $pembahasan,
                'guru_id' => $this->guruPengampu($mapelId),
                'is_aktif' => true,
            ]
        );
    }

    /**
     * Guru pengampu mapel, dipakai supaya penyaringan "guru hanya melihat
     * soal miliknya" bisa langsung dicoba dengan login guru.
     */
    protected function guruPengampu(?int $mapelId): ?int
    {
        if (! $mapelId) {
            return null;
        }

        return $this->pengampu[$mapelId] ??= Guru::where('mata_pelajaran_id', $mapelId)->value('id')
            ?? GuruMapel::where('mata_pelajaran_id', $mapelId)->value('guru_id');
    }
}
