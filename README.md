# Aplikasi Ujian Sekolah (CBT)

Aplikasi ujian berbasis Laravel 12 yang terintegrasi dengan dua aplikasi sekolah
yang sudah ada:

- **Data Center** (`C:\laragon\www\datacenter`) — sumber data siswa, guru, wali
  kelas, guru mata pelajaran, mata pelajaran, tingkat kelas, tahun ajaran dan
  rombongan belajar.
- **SIM Kurikulum** (`C:\laragon\www\kurikulum`) — sumber pemetaan CP-TP-ATP yang
  ditarik menjadi topik lewat fitur **Sinkron CP-TP-ATP**.

Kedua database itu diakses **read-only** lewat koneksi terpisah, jadi aplikasi
ini tidak pernah menulis ke sana dan satu sumber kebenaran tetap terjaga.

---

## Menu

| Menu | Isi |
| --- | --- |
| **Topik** | CRUD topik + **Sinkron CP-TP-ATP** dari database kurikulum, export Excel |
| **Bank Soal** | Input 5 jenis soal (teks Arab, simbol matematika, gambar), import **Word (.docx)** & **Excel (.xlsx/.csv)** berikut gambarnya, export Excel |
| **Pemilihan Soal Diujikan** | Paket soal: pilih butir manual atau ambil acak per tingkat kesukaran, atur urutan & bobot |
| **Registrasi Ujian** | Jadwal, durasi, token, KKM, pengacakan, proteksi kecurangan, kelas peserta, sinkron peserta, daftar hadir |
| **Hasil & Laporan Ujian** | Daftar Nilai (+koreksi essay), Statistik, Analisis Butir Soal, Remidial, Pengayaan — semuanya dengan export Excel |
| **Monitoring Ujian** | Pemantauan langsung (auto-refresh 15 detik), reset peserta, kumpul paksa, buka kunci pelanggaran, jejak aktivitas |
| **Log Login** | Riwayat percobaan masuk semua peran + export Excel |
| **Data Center** | Tampilan read-only data siswa, guru, wali kelas, guru mapel, mapel, tingkat kelas, tahun ajaran |

### Jenis soal yang didukung

| Jenis | Koreksi | Catatan |
| --- | --- | --- |
| Pilihan ganda | Otomatis | Satu kunci |
| Pilihan ganda kompleks | Otomatis, **skor parsial** | Benar menambah, salah mengurangi, minimal 0 |
| Benar / salah | Otomatis | — |
| Penjodohan | Otomatis, **skor parsial** per pasangan | Kolom kanan bisa diacak |
| Essay / uraian | Manual oleh guru | Punya jawaban model + kata kunci penilaian |

Nilai akhir = (total skor butir ÷ total bobot paket) × 100, sehingga bobot butir
yang berbeda-beda tetap menghasilkan skala 0–100.

---

## Instalasi

```bash
composer install
```

Salin `.env.example` menjadi `.env`, lalu isi tiga blok koneksi database:

```env
DB_DATABASE=ujian

DATACENTER_DB_DATABASE=datacenter_v2
KURIKULUM_DB_DATABASE=kurikulum
```

Kemudian:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Seeder hanya membuat akun pengelola:

| Email | Kata sandi | Peran |
| --- | --- | --- |
| `admin@ujian.test` | `password` | Admin |
| `operator@ujian.test` | `password` | Operator |

> Ganti kata sandi bawaan tersebut sebelum dipakai di lingkungan sebenarnya.

---

## Berjalan Tanpa Internet

Seluruh berkas gaya, ikon, dan font dilayani dari server aplikasi sendiri —
ada di `public/vendor/`, tidak ada satu pun rujukan ke CDN:

| Berkas | Isi |
| --- | --- |
| `vendor/bootstrap/` | Bootstrap 5.3.3 (CSS + bundle JS) |
| `vendor/bootstrap-icons/` | Bootstrap Icons 1.11.3 beserta berkas fontnya |
| `vendor/font/` | Font Amiri untuk teks Arab, beserta berkas gayanya |
| `vendor/select2/` | Select2 4.1.0-rc.0, tema Bootstrap 5 v1.3.0, bahasa Indonesia, dan jQuery 3.7.1 yang dibutuhkannya |

Ini bukan pilihan gaya. Ujian dijalankan lewat **ExamBro** dan peramban ujian
sejenis yang mengunci akses ke luar alamat server ujian, dan banyak sekolah
menjalankannya di jaringan lokal tanpa internet sama sekali. Satu rujukan CDN
saja membuat halaman siswa tampil **tanpa gaya apa pun**: tombol berubah
menjadi tautan bergaris bawah dan seluruh ikon hilang.

Berkas-berkas itu ikut tersalin saat aplikasi dipindahkan, jadi tidak ada
langkah pemasangan tambahan. Tes `AsetLokalTest` menjaga agar rujukan luar
tidak diam-diam kembali.

### Select2 pada seluruh pilihan

Setiap `<select>` di aplikasi — formulir, penyaring, sampai kotak jawaban
soal penjodohan di lembar ujian — tampil sebagai Select2 bertema Bootstrap 5.
Pemasangannya otomatis lewat `partials/select2.blade.php` di kedua tata
letak; halaman tidak perlu menandai select-nya. Select yang tidak ingin diubah
cukup diberi `data-select2="tidak"`, dan select yang ditambahkan skrip
belakangan ikut dipasang.

Tiga hal yang sengaja diatur, karena masing-masing merusak sesuatu bila
dibiarkan dengan bawaan Select2:

- **Jembatan kejadian `change`.** Select2 mengabarkan perubahan lewat jQuery,
  dan kabar itu tidak sampai ke pendengar `addEventListener('change')` —
  termasuk yang menyimpan jawaban soal penjodohan. Tanpa jembatan, jawaban
  penjodohan diam-diam berhenti tersimpan.
- **Tanpa placeholder.** Select2 menyembunyikan pilihan placeholder dari
  daftar, padahal pilihan kosong di sini kerap pilihan sungguhan — "Semua
  kelas", "— tanpa topik —". Dengan placeholder, sekali memilih satu kelas,
  pengawas tidak bisa kembali ke "Semua kelas".
- **`data-kirim-otomatis`, bukan `onchange="this.form.submit()"`.** Atribut
  `onchange` ikut terpanggil oleh jQuery lalu sekali lagi oleh jembatan, jadi
  formulirnya terkirim dua kali. Tes `Select2Test` menjaga agar atribut itu
  tidak kembali.

Kotak pencarian hanya muncul untuk daftar dengan delapan pilihan atau lebih —
di daftar pendek ia tidak membantu, dan di ponsel justru memunculkan papan
ketik. Di lembar ujian, Select2 juga tidak membuka dialog sistem Android
seperti select biasa, sehingga tidak memicu deteksi kehilangan fokus.

Bila skrip Select2 gagal dimuat, select tetap berfungsi sebagai select biasa.

### Peramban lama

Skrip halaman pengerjaan sengaja tidak memakai sintaks yang lebih baru dari
ES2017. ExamBro berjalan di atas WebView Android bawaan perangkat, yang pada
perangkat sekolah kerap jauh lebih tua daripada peramban di meja guru.
Operator `?.` dan `??` menjadi **galat sintaks** di sana — dan satu galat
sintaks membatalkan seluruh blok skrip: hitung mundur mati, jawaban tidak
tersimpan, tombol kumpulkan diam.

Keadaan opsi jawaban yang sedang dipilih juga ditandai dua kali — lewat CSS
`:has()` dan lewat kelas yang dipasang JavaScript — karena `:has()` baru
dikenal peramban terbitan 2022 ke atas.

### Bilah atas saat mengerjakan

Selama ujian berlangsung, bilah atas memuat **nama siswa dan kelasnya** di
kiri serta **sisa waktu** menempel di pojok kanan — sejajar dalam satu baris
agar tetap muat di layar ponsel, tempat sebagian besar siswa mengerjakan
lewat ExamBro.

Nama ujian ikut tampil di sebelah kelas hanya bila layarnya cukup lebar. Di
ponsel nama ujian yang panjang akan menyita ruang sampai nama siswa dan
kelasnya sama-sama terpotong, padahal justru keduanya yang perlu terbaca —
pengawas memakainya untuk memastikan perangkat dipegang siswa yang benar.

Tombol Keluar sengaja tidak ada di halaman ini agar tidak tertekan tanpa
sengaja di tengah ujian.

### Menyegarkan halaman ujian

Halaman pengerjaan **tidak** memasang peringatan "Changes you made may not be
saved" saat disegarkan. Peringatan itu sempat ada dan keliru: pilihan ganda,
penjodohan, dan benar/salah dikirim ke server seketika, jadi tidak ada yang
hilang. Membiarkan peringatan yang tidak benar hanya melatih siswa untuk
mengabaikan peringatan.

Satu-satunya celah nyata adalah jawaban essay, yang ditunda 1,2 detik setelah
siswa berhenti mengetik. Celah itu ditutup: jawaban yang masih tertunda
dikirim lewat `sendBeacon` saat halaman ditinggalkan — berbeda dari `fetch`
yang dibatalkan peramban ketika halaman ditutup, `sendBeacon` dijamin tetap
terkirim.

### Menandai sesi ExamBro

Menu **Log Login** menandai sesi yang dibuka lewat peramban ujian:

- Lencana hijau **ExamBro** pada sesi yang memakai peramban ujian.
- Lencana kuning **Peramban biasa** pada sesi siswa yang berhasil masuk
  tanpa peramban ujian — inilah yang dicari pengawas.
- Kartu **Siswa tanpa ExamBro hari ini** menautkan langsung ke daftar
  tersaringnya, dan penyaring **Peramban ujian** memisahkan keduanya.
- Kolom **Lewat ExamBro** ikut pada berkas export.

Pengenalannya diturunkan dari `user_agent` yang memang sudah tersimpan,
bukan kolom tersendiri — dengan begitu seluruh baris lama ikut tertandai
tanpa migrasi maupun pengisian ulang.

> **Batas ketelitiannya.** Yang dikenali adalah penanda WebView Android
> (`wv` beserta `Version/4.0`), yaitu "aplikasi ber-WebView" — bukan ExamBro
> secara khusus. Aplikasi lain yang memuat halaman lewat WebView akan tampak
> sama, dan peramban ujian untuk komputer meja tidak memakai penanda ini sama
> sekali. Untuk sekolah yang memakai ExamBro di ponsel, penanda ini sudah
> memisahkan dengan tepat — tetapi jangan diperlakukan sebagai bukti mutlak.

---

## Ruang Ujian Peserta

Halaman pertama setelah siswa masuk. Kartu sapaannya memuat **jam berjalan**
dan tombol **Segarkan** tepat di sebelahnya.

Keduanya untuk satu keadaan yang sama: peserta sudah masuk sebelum ujiannya
dibuka, lalu menunggu. Di ExamBro tidak ada bilah alamat, sehingga tanpa
tombol itu satu-satunya cara memuat ulang daftar adalah keluar dari peramban
ujian — yang justru terhitung pelanggaran.

Jamnya dimulai dari jam server, bukan jam perangkat: yang menentukan jadwal
ujian adalah server, sedangkan jam ponsel siswa kerap meleset. Yang dikirim
ke halaman adalah detik sejak tengah malam, bukan cap waktu epoch — epoch
harus diterjemahkan kembali lewat zona waktu perangkat, dan perangkat yang
zonanya keliru akan menampilkan jam yang meleset berjam-jam.

Di layar ponsel tulisan pada tombolnya disembunyikan dan menyisakan ikon saja,
karena baris rincian di sana sudah penuh oleh NISN, kelas, dan tanggal.

---

## Lembar Pengerjaan

Bilah atasnya memuat identitas peserta di kiri, lalu sisa waktu dan tombol
**muat ulang** menempel di pojok kanan.

Tombol itu ada karena ExamBro tidak punya bilah alamat: tanpanya, satu-satunya
cara memuat ulang lembar yang tampilannya tersendat adalah keluar dari
peramban ujian — yang justru terhitung pelanggaran. Jawaban essay yang masih
menunggu jeda ketik dikirim lebih dulu sebelum halaman dimuat ulang, sehingga
menekannya tidak pernah menghilangkan ketikan yang belum sempat tersimpan.

Halaman **Ruang Ujian** juga punya jam berjalan dan tombolnya sendiri, untuk
peserta yang sudah masuk sebelum ujiannya dibuka.

---

## Satu Akun, Satu Perangkat

Akun siswa hanya boleh masuk di satu perangkat pada satu waktu. Bila akun yang
sama masuk dari perangkat kedua, **login terbaru yang menang**: sesi di
perangkat lama diakhiri, dan siswa di sana dibawa ke halaman login dengan
pesan *"Akun Anda baru saja masuk dari perangkat lain (IP …), jadi sesi di
perangkat ini diakhiri. Jika itu bukan Anda, segera lapor ke pengawas."*

Mengapa bukan kebalikannya — menolak login baru selama sesi lama hidup: siswa
jujur yang HP-nya mati di tengah ujian lalu pindah perangkat justru tidak akan
bisa masuk lagi sampai sesi lamanya kedaluwarsa sendiri.

Bila siswanya sedang mengerjakan ujian, kejadian itu tercatat di **jejak
aktivitas Monitoring** beserta IP kedua perangkat. Login ganda di tengah ujian
bisa berarti joki, atau jawaban yang dikerjakan bersama.

Cara kerjanya: setiap login siswa melahirkan token acak yang disimpan di sesi
peramban dan di tabel `sesi_siswa` (di database ujian — database Data Center
hanya boleh dibaca). Setiap permintaan membandingkan keduanya.

- **Lembar ujian tidak menunggu siswa menjawab.** Ia memeriksa sesinya tiap 15
  detik, dan seketika saat halamannya kembali terlihat. Tanpa itu, perangkat
  yang sudah dikalahkan tetap menampilkan soal selama siswanya diam — dan soal
  itulah yang dilindungi.
- **Memperbarui aplikasi di tengah ujian tidak mengeluarkan siapa pun.** Sesi
  yang lahir sebelum fitur ini terpasang tidak membawa token; sesi itu diakui
  dan diberi token — kecuali akunnya sudah punya sesi resmi di perangkat lain.
- **Keluar dari perangkat lama** hanya menghapus penanda miliknya sendiri,
  tidak pernah ikut mengakhiri sesi di perangkat yang baru.

### Login ganda di menu Log Login

Setiap login yang menggeser sesi di perangkat lain ditandai:

- kartu **Siswa login ganda hari ini** — dihitung per siswa, bukan per
  kejadian — yang bila diklik langsung menyaring daftarnya;
- penyaring **Login ganda**;
- pada barisnya: sorotan kuning, lencana **Login ganda**, dan keterangan
  **menggeser 192.168.x.x** — IP perangkat yang tergeser, dengan peramban
  perangkat itu pada tooltip-nya;
- nama siswa di bawah NISN, supaya pengawas tidak perlu mencocokkan nomor;
- kolom **Login Ganda** dan **Menggeser Sesi di IP** pada berkas export.

Yang dihitung "ganda" sengaja dibatasi. Penanda sesi tidak terhapus bila siswa
sekadar menutup peramban ujiannya — hanya bila ia menekan Keluar — jadi
keberadaan penanda lama saja akan menandai hampir setiap login pagi karena
sisa sesi kemarin. Login dihitung ganda hanya bila:

- sesi lama **masih dipakai dalam 10 menit terakhir** (lembar ujian berdenyut
  tiap 15 detik, jadi siswa yang sedang mengerjakan selalu tercakup); **dan**
- **perangkatnya lain**. ExamBro yang ditutup lalu dibuka lagi di HP yang sama
  masuk ulang dengan IP dan peramban yang persis sama — itu bukan dua
  perangkat, dan tidak boleh memenuhi daftar pengawas dengan alarm palsu.

Aturan yang sama dipakai untuk catatan di menu Monitoring.

### Login ganda di menu Monitoring Ujian

Login ganda yang terjadi saat peserta **sedang mengerjakan** ujian tampil di
halaman monitoring ujian itu:

- **bilah peringatan** di atas yang menyebut **nama** pesertanya, kelas, dan
  berapa kali — sebab yang harus dilakukan pengawas adalah mendatangi anak itu
  dan memeriksa siapa yang memegang perangkatnya;
- **baris peserta** disorot kuning dengan lencana **Login ganda 2x**; rincian
  terakhirnya — jam dan IP kedua perangkat — muncul saat lencana ditunjuk;
- **jejak aktivitas**: kejadiannya disorot, tidak tenggelam di antara jejak
  mulai-lanjut-selesai yang jauh lebih banyak;
- kolom **Login Ganda (kali)** pada berkas export.

Semuanya ikut tabel yang menyegarkan diri tiap 15 detik. Bila muncul peserta
login ganda baru, halamannya dimuat ulang sekali supaya bilah peringatannya
menyebut nama itu.

Login ganda bukan pelanggaran yang dihitung — tidak menambah hitungan dan
tidak mengunci lembar. Tindakannya diserahkan kepada pengawas, karena hanya
pengawas di ruangan yang bisa memastikan apakah itu joki atau sekadar siswa
yang berpindah HP.

---

## Proteksi Kecurangan

Diatur per ujian di **Registrasi Ujian → Proteksi Kecurangan**, bukan sekali
untuk seluruh aplikasi: ulangan harian yang diawasi langsung di kelas tidak
perlu diperlakukan seketat ujian sekolah.

### Yang ditegakkan

| Perbuatan | Dihitung | Akibatnya |
|---|---|---|
| Jendela sembulan (pop-up) | ya | **Langsung dikunci** |
| Jendela mengambang di perangkat sentuh | ya | **Langsung dikunci** |
| Membuka tab / jendela baru | ya | Diperingatkan, soal tertutup tirai |
| Membelah layar / jendela belum penuh | ya | Diperingatkan, soal tertutup tirai |
| Keluar peramban ujian / pindah aplikasi | ya | Diperingatkan, soal tertutup tirai |
| Lembar kehilangan fokus | ya | Diperingatkan, soal tertutup tirai |
| Keluar dari mode layar penuh | ya | Diperingatkan, soal tertutup tirai |
| Alat pengembang (F12, Ctrl+Shift+I) | ya | Diperingatkan, soal tertutup tirai |
| Menyalin isi soal, menempel, memotong | **tidak** | Dicegah dan dicatat saja |

Menyalin tulisan sendiri di kolom essay tetap diizinkan — tidak ada tempat
untuk menempelkannya di lembar ini. Memotong ikut dilarang di sana justru
demi siswanya: karena menempel diblokir, paragraf yang terlanjur dipotong
tidak akan pernah bisa dikembalikan.

Yang "diperingatkan" menambah hitungan; begitu mencapai batas — bawaannya
**3 kali** — lembarnya dikunci. Seluruh ketentuan di tabel ini dikunci oleh
satu pengujian bertabel, sehingga jenis pelanggaran baru tidak bisa
ditambahkan tanpa memutuskan bagaimana ia dihitung dan kapan ia mengunci.

### Mengapa dua di antaranya langsung dikunci

Berpindah aplikasi, keluar layar penuh, atau membelah layar masih bisa
terjadi karena kaget, salah tekan, atau telepon masuk — karena itu diberi
kesempatan.

Jendela kedua yang melayang di atas lembar ujian berbeda: ia tidak muncul
dengan sendirinya. Ia harus dibuka, dan satu-satunya gunanya selama ujian
adalah menaruh sesuatu untuk dibaca berdampingan dengan soal.

> **Batas "Tidak pernah" tetap dihormati.** Guru yang memilih setelan itu
> sudah memutuskan lembar tidak boleh dikunci sendiri oleh sistem, dan
> pelanggaran berat pun tidak melanggarnya — kalau tidak, setelan itu
> berbohong kepada yang memilihnya.

**Jendela mengambang hanya berlaku di perangkat sentuh.** Pengenalannya
berasal dari ukuran jendela yang menyusut di kedua sisi sekaligus. Di ponsel
dan tablet itu berarti jendela melayang, sebab jendela di sana selalu
memenuhi layar. Di komputer meja, jendela yang lebih kecil dari layar jauh
lebih mungkin sekadar belum dimaksimalkan — dan mengunci siswa seketika hanya
karena itu jelas tidak sebanding, jadi di sana diperlakukan sebagai layar
terbelah biasa.

### Layar blokir

Begitu pelanggaran terdeteksi, soal ditutup layar bertuliskan **UJIAN
TERBLOKIR**, disusul alasannya, nama dan kelas peserta, jam kejadian, serta
sisa kesempatan sebelum lembar dikunci. Identitas dicantumkan supaya pengawas
yang dipanggil langsung tahu lembar siapa yang dihadapinya.

Selama layar itu tampil, soal benar-benar tidak terbaca — bukan sekadar
diberi peringatan di atasnya. Tombol **Saya mengerti, lanjutkan** menolak
bekerja selama keadaannya belum pulih, jadi layar yang masih terbelah tidak
bisa dilewati begitu saja.

Pada pelanggaran terakhir, layar yang sama berubah merah: **Lembar jawaban
dikunci**. Tombol "Saya mengerti, lanjutkan" hilang, digantikan tautan
**Kembali ke Ruang Ujian** — tanpa itu peserta terpaku pada layar yang tidak
punya jalan keluar sama sekali, dan satu-satunya caranya pergi adalah menutup
peramban ujian, yang justru terhitung pelanggaran lagi.

Alasan penguncian disebutkan apa adanya. Lembar yang dikunci karena jendela
sembulan berbunyi demikian, bukan "pelanggaran sudah mencapai batas" — kalimat
yang membingungkan peserta yang baru sekali melanggar.

### Kehilangan fokus

Jendela lain naik ke depan, panel seperti asisten Gemini melayang di atas
lembar, atau peramban ujian menampilkan konfirmasi keluarnya sendiri —
layarnya masih menampilkan soal, tetapi peserta sudah tidak berada di sana.
Begitu kehilangan fokus itu bertahan 1,5 detik, tirainya dibuka tanpa menunggu
peserta kembali, dan hitungannya langsung ditanyakan ke server supaya sisa
kesempatan terbaca pada saat peringatan itu paling perlu. Rinciannya — dan
mengapa kotak pilihan penjodohan dikecualikan — ada di bagian *Kehilangan
fokus dan panel yang melayang* di bawah.

Satu kepergian kerap memicu dua kejadian sekaligus — fokus hilang lebih dulu,
lalu halamannya disembunyikan. Keduanya perbuatan yang sama, jadi peredamnya
satu ember bersama; kalau tidak, sekali berpindah aplikasi terhitung dua
pelanggaran.

### Mode layar penuh

Setelah siswa menekan **Mulai Ujian**, halaman soal terbuka dengan **gerbang
layar penuh**: soalnya tertutup sampai siswa menekan **Mulai dalam layar
penuh**. Gerbang yang sama muncul lagi setiap kali lembar dimuat tanpa layar
penuh — setelah tombol muat ulang, atau setelah kembali dari Ruang Ujian —
sebab memuat ulang selalu melepas layar penuh.

Mengapa tidak langsung dari tombol Mulai Ujian: peramban selalu melepas layar
penuh begitu halaman berpindah, dan di halaman baru permintaan layar penuh
hanya diterima bila lahir dari tindakan pengguna. Tombol gerbang itulah
tindakan tersebut.

> **Gerbangnya tidak bisa menjebak.** Bila peramban menolak — atau, seperti
> yang ketahuan saat pengujian, janji `requestFullscreen()` tidak pernah
> selesai sama sekali — gerbang tetap lepas paling lambat 2 detik. Gerbang
> yang tak bisa dilewati berarti ujian yang tak bisa dikerjakan, dan itu jauh
> lebih merugikan daripada satu lembar yang dikerjakan di luar layar penuh.

Memuat ulang dan mengumpulkan lembar sama-sama melepas layar penuh; keduanya
sah dan tidak terhitung pelanggaran.

Layar penuh menutup bilah alamat, bilah tab, dan bilah tugas sekaligus —
ketiganya jalan keluar termudah dari lembar ujian. Permintaannya dikirim pada
sentuhan atau ketukan tombol pertama peserta, sebab peramban menolak
permintaan layar penuh yang tidak lahir dari tindakan pengguna.

> **Penegakannya bersyarat.** Yang dituntut hanya peserta yang layar penuhnya
> pernah benar-benar aktif. Fullscreen API tidak tersedia di semua WebView,
> dan ExamBro sendiri sudah berjalan sebagai kios tanpa melewati API itu —
> menuntutnya di sana hanya akan memunculkan tirai yang tidak pernah bisa
> dipenuhi siswa. Keluar-masuk yang terjadi dalam 1,5 detik juga diabaikan:
> sebagian WebView menerima permintaan layar penuh lalu membatalkannya
> sendiri, dan pantulan seperti itu bukan perbuatan peserta.

### Keluar dari peramban ujian

Menekan tombol keluar ExamBro, menekan tombol Beranda, menarik panel
notifikasi, atau berpindah ke aplikasi lain sama-sama menyembunyikan halaman
ujian. Tidak satu pun dapat dicegah dari dalam halaman, jadi semuanya
dicatat dan dihitung, lalu soal diblokir begitu peserta kembali.

Laporannya dikirim lewat `sendBeacon` karena pada saat itu halaman sedang
ditinggalkan dan permintaan `fetch` yang belum selesai akan dibatalkan
peramban. Konsekuensinya tidak ada balasan yang bisa dibaca, jadi hitungan
dan status kuncinya ditanyakan ulang begitu halaman aktif lagi.

Bila peramban ujian benar-benar ditutup lalu dibuka lagi, halaman dimuat dari
awal dan tirainya tidak pernah sempat tampil. Karena itu server ikut
mengabarkan pelanggaran terakhir beserta umurnya; yang berumur di bawah 45
detik ditampilkan menyusul saat halaman terbuka kembali.

> **Menyegarkan halaman tidak terhitung pelanggaran.** Memuat ulang juga
> menyembunyikan halaman sesaat sebelum ditutup, sehingga tanpa pembeda tiga
> kali menyegarkan sudah cukup mengunci lembar siswa yang tidak berbuat
> apa-apa. Pembedanya urutan kejadian: memuat ulang selalu didahului
> `beforeunload`, sedangkan berpindah aplikasi dan menekan tombol keluar
> ExamBro tidak — di sana halamannya hanya dijeda, bukan ditinggalkan
> alamatnya. Penyimak `beforeunload`-nya pasif, jadi dialog "Reload site?"
> tetap tidak muncul.

Mengumpulkan lembar jawaban, dan pengumpulan otomatis saat waktu habis, juga
dikecualikan — keduanya meninggalkan halaman secara sah.

### Daftar ujian di menu Monitoring

Halaman pertama menu Monitoring Ujian memuat kolom **Siswa Melanggar** dan
penyaring **Mata pelajaran** serta **Kelas / rombel**.

**Siswa Melanggar** menghitung siswa, bukan kejadian — siswa yang melanggar
tiga kali tetap satu. Di bawahnya tercantum berapa lembar yang sedang
**terkunci**, sebab hanya merekalah yang menunggu tindakan pengawas saat itu
juga. Angkanya diambil dari jejak pelanggaran, bukan dari penghitung
pelanggaran peserta: penghitung itu kembali ke nol setiap kali pengawas
membuka kunci atau mereset, sehingga siswa yang sudah melanggar akan tampak
bersih. Percobaan menyalin tidak ikut dihitung, sama seperti di tempat lain.

Pilihan pada kedua penyaring hanya berisi mapel dan kelas yang memang punya
ujian, jadi tidak ada pilihan yang berujung pada daftar kosong. Bila disaring
per kelas, **setiap angka di tabel ikut menyempit** ke peserta kelas itu —
ditandai keterangan di atas tabel — dan tombol **Pantau** langsung membuka
monitoring kelas tersebut.

### Kartu angka dan penyaring status di halaman detail

Halaman monitoring sebuah ujian memuat lima kartu: **Terdaftar**, **Belum
mulai**, **Sedang mengerjakan**, **Selesai**, dan **Melanggar**. Setiap kartu
bisa diklik untuk menampilkan daftar siswanya di tabel — itulah rincian tiap
angka. Penyaring **status** di kepala tabel melakukan hal yang sama, dan bisa
digabung dengan penyaring kelas.

- **Angka kartu tidak ikut tersaring.** Kalau ikut, memilih "Selesai" membuat
  kartu lain menjadi nol. Peringatan lembar terkunci dan login ganda juga tetap
  tampil, walau siswanya sedang tersaring keluar dari tabel.
- **Rincian pelanggaran** tampil di kolom Pelanggaran, misalnya *Hilang fokus
  2× · Tab baru 1×*. Rinciannya diambil dari jejak, jadi tetap terbaca walau
  hitungannya sudah dinolkan karena kunci dibuka atau direset.
- "Melanggar" di halaman ini dan kolom **Siswa Melanggar** di halaman daftar
  memakai satu definisi yang sama, sehingga angkanya selalu cocok.
- Saringannya bertahan saat tabel menyegarkan diri, dan tombol **Export** ikut
  mengekspor apa yang sedang tampil, lengkap dengan kolom rincian pelanggaran.

### Aksi pengawas di menu Monitoring

Kolom **Aksi** pada tabel peserta menyediakan tiga tombol, sesuai keadaan
barisnya:

| Tombol | Muncul saat | Akibatnya |
| --- | --- | --- |
| 🔓 **Aktifkan kembali** | lembar terblokir | Kunci dibuka, hitungan pelanggaran dinolkan, peserta melanjutkan dari jawaban terakhirnya |
| ⏹ **Kumpulkan paksa** | sedang mengerjakan | Lembar dikumpulkan dan langsung dikoreksi |
| ↺ **Reset pengerjaan** | sedang mengerjakan / selesai | Seluruh jawaban dihapus, peserta mengulang dari awal — kunci dan hitungan pelanggaran ikut dibersihkan |

Ketiganya tetap ada pada tabel yang menyegarkan dirinya tiap 15 detik.

**Izinkan semua lanjut.** Bila ada lembar yang terkunci, bilah merah di atas
menyebut nama-nama pesertanya dan menyediakan tombol untuk mengizinkan mereka
semua melanjutkan ujian sekaligus — tanpa menekan *Aktifkan kembali* satu per
satu. Nama disebut lebih dulu karena izin serentak tidak boleh diberikan
tanpa tahu kepada siapa.

- Yang disentuh hanya peserta yang **terkunci**. Peserta yang pernah melanggar
  tetapi belum mencapai batas memang sudah boleh melanjutkan, dan hitungannya
  tidak dinolkan — mengampuni pelanggaran yang belum mengunci apa pun bukan
  maksud tombol ini.
- Bila halaman sedang disaring per kelas, izinnya **hanya untuk kelas itu**.
- Hitungan pelanggaran mereka dinolkan, sama seperti membuka kunci satu per
  satu; **rincian pelanggarannya tetap tercatat** dan tetap terhitung di kartu
  Melanggar.
Sebelumnya kolom itu berganti menjadi tulisan "muat ulang untuk aksi" begitu
penyegaran pertama berjalan — padahal justru di saat itulah tombolnya
dibutuhkan: peserta yang terblokir atau harus direset sedang berhenti
mengerjakan sementara hitung mundurnya terus berjalan.

Kunci sengaja tidak dapat dibuka siswa dengan token ujian: token itu
diumumkan ke seluruh kelas saat ujian dimulai, jadi siswa yang melanggar
pasti mengetahuinya — kunci yang bisa dibuka sendiri sama saja dengan tidak
ada kunci.

Selain tombolnya, kolom **Pelanggaran** menampilkan hitungan tiap peserta,
lembar yang terkunci diberi lencana merah, jejak aktivitas menyorot baris
pelanggaran, dan kedua angkanya ikut pada berkas export.

### Cara layar terbelah dan jendela mengambang dikenali

Dari ukuran jendela. Layar terbelah menyusutkan satu sumbu; jendela mengambang
menyusutkan keduanya sekaligus.

**Ambangnya relatif terhadap ukuran istirahat perangkat, bukan angka tetap.**
Ini pelajaran dari lapangan. Versi pertama memakai ambang tetap 0,62 terhadap
tinggi layar. Perangkat sekolah yang diuji 10 September 2026 menyisakan
viewport setinggi **0,61** layar dalam keadaan diam — bilah ExamBro dan bilah
sistem Android memakan sisanya. Perangkat itu dinyatakan "layar terbelah"
hanya karena duduk diam, selisih satu perseratus.

Yang dipakai sekarang: seberapa jauh jendela menyusut dari ukuran terbesar
yang pernah teramati di perangkat itu sendiri (80% masih dianggap wajar).
Ditambah lantai mutlak — 0,60 lebar dan 0,45 tinggi — untuk peserta yang sudah
membelah layar sebelum lembar ujian dibuka, yang ukuran istirahatnya terlanjur
terukur kecil.

**Lebar dibandingkan dengan lebar, tinggi dengan tinggi.** Versi sebelumnya
membandingkan sisi panjang jendela dengan sisi panjang layar. Cara itu gugur
justru pada kasus paling sering: layar ponsel yang dibelah ke bawah menyisakan
jendela 392×260 — lebih lebar daripada tinggi, padahal layarnya tetap potret.
Sisi "panjang" jendela lalu diadu dengan tinggi layar, kedua sumbu terbaca
menyusut, dan layar terbelah biasa salah dikenali sebagai jendela mengambang
yang mengunci seketika.

Orientasi layarnya tidak diambil dari `screen.orientation`: nilainya tidak
selalu sepakat dengan `screen.width/height` di WebView, dan sekali keduanya
bertentangan seluruh perhitungan ikut salah — jendela pernah terukur setinggi
1,32 layar, yang mustahil. Sebagai gantinya kedua pemetaan sumbu dicoba, lalu
diambil yang masuk akal; bila keduanya masuk akal, dipilih yang lebarnya
paling pas, sebab jendela peramban praktis selalu memenuhi lebar wadahnya.

Tiga hal sengaja dikecualikan:

- **Papan ketik di layar.** Papan ketik memendekkan jendela persis seperti
  layar yang dibelah ke bawah. Selama kursor berada di kolom isian,
  pemeriksaan tinggi ditangguhkan. Lebarnya tetap diperiksa, karena papan
  ketik tidak pernah menyempitkan lebar.
- **Perbesaran halaman.** Memperbesar halaman menyusutkan `innerWidth` tanpa
  mengubah `screen.width`. `devicePixelRatio` bergerak seiring perbesaran itu,
  jadi dipakai mengembalikan ukurannya ke skala semula.
- **Memutar layar.** Rasio yang bisa dicapai tiap sumbu ikut berubah, sehingga
  acuan lama menjadi mustahil dipenuhi. Acuannya dipelajari ulang.

### Kehilangan fokus dan panel yang melayang

Panel yang melayang di atas lembar ujian — asisten **Gemini**, gelembung
obrolan, jendela kecil aplikasi lain — tidak menyembunyikan halaman dan tidak
mengubah ukurannya. Pemeriksaan "keluar aplikasi" dan "layar terbelah" tidak
melihatnya. Satu-satunya jejaknya: **fokus jendela pindah ke panel itu**.

Pemeriksaan kehilangan fokus sempat dimatikan di HP. Versi pertamanya
memastikan lewat `document.hasFocus()`, yang di WebView Android mengembalikan
`false` walaupun halamannya tampil penuh dan sedang disentuh — pengujian
lapangan 10 September 2026 mencatat 10 pelanggaran palsu karenanya. Akibat
dimatikan, panel Gemini lolos.

Sekarang keadaan fokus dilacak dari pasangan kejadian `blur`/`focus` sendiri,
dan kehilangan fokus baru dilaporkan bila **bertahan**:

- fokus tidak kembali dalam **1,5 detik** — kedipan fokus sesaat, yang lazim
  di WebView, tidak pernah selama itu;
- siswa **tidak menyentuh halaman** selama itu — siapa pun yang sedang
  menyentuh lembar ujian jelas sedang berada di lembar ujian;
- fokusnya **tidak sedang dipegang kotak pilihan**. Di Android, kotak pilihan
  soal penjodohan dibuka sebagai dialog sistem yang ikut mengambil fokus — itu
  bagian dari mengerjakan soal, bukan kecurangan.

Pelanggaran ini dihitung (tiga kali mengunci lembar), tetapi **tidak mengunci
seketika** seperti jendela mengambang. Dari dalam halaman, panel Gemini tidak
dapat dibedakan dari panel notifikasi yang ditarik turun atau pemberitahuan
telepon masuk — mengunci seketika berarti mengunci siswa yang ditelepon.
## Data Contoh untuk Uji Coba

`migrate --seed` hanya membuat akun pengelola. Untuk mengisi aplikasi dengan
data yang bisa langsung diklik-klik:

```bash
php artisan db:seed --class=ContohSeeder
```

Perintah ini aman diulang — topik & soal diperbarui berdasarkan kodenya,
sedangkan paket dan jadwal ujian contoh (berawalan `CTH-`) dibuat ulang dari
awal. Prasyaratnya database Data Center sudah berisi siswa, guru, mata
pelajaran, tingkat kelas, dan rombongan belajar pada tahun ajaran aktif.

Yang dihasilkan:

| | Isi |
| --- | --- |
| **Bank soal** | 10 topik pada 5 mapel Kelas 7 (Matematika, Bahasa Indonesia, IPA, Informatika, PAI), 52 butir mencakup kelima jenis soal, lengkap dengan pembahasan, level kognitif, dan tingkat kesukaran |
| **Contoh Arab & matematika** | Topik PAI berisi butir berbahasa Arab berharakat; topik PLSV berisi butir dengan pecahan bertingkat, akar, dan pangkat — untuk memeriksa tampilannya di seluruh halaman |
| **Paket soal** | 4 paket: PAS Matematika, UH Bahasa Indonesia, PTS IPA, dan satu Try Out Informatika tanpa jadwal sebagai bahan latihan memilih butir |
| **Ujian selesai** | PAS Matematika, 60 peserta 3 kelas, seluruh lembar sudah dikoreksi — 4 di antaranya sengaja masih menunggu penilaian essay |
| **Ujian berlangsung** | UH Bahasa Indonesia yang jendela waktunya sedang terbuka: sebagian siswa mengerjakan, sebagian sudah mengumpulkan, sebagian belum mulai — untuk mencoba menu Monitoring |
| **Ujian terjadwal** | PTS IPA berstatus draft minggu depan |
| **Tindak lanjut** | Rencana remidial & pengayaan, sebagian sudah punya nilai akhir dan sebagian masih direncanakan |
| **Log login** | 60 baris percobaan masuk dari ketiga peran, sekitar 20% gagal |

Token ujian contoh: `MTK7PAS`, `BIN7UH`, `IPA7PTS`.

> Ujian yang "sedang berlangsung" ditambatkan pada waktu seeding — jendelanya
> terbuka 120 menit ke depan. Setelah lewat, peserta yang belum mengumpulkan
> akan tertutup otomatis dan menu Monitoring kembali sepi. Jalankan ulang
> seeder untuk memperagakannya lagi.

### Bagaimana jawaban peserta dibangkitkan

Jawaban tidak diacak seragam — kalau begitu, daya pembeda tiap butir akan
mendekati nol dan menu Analisis Butir Soal tidak memberi pelajaran apa pun.

Seeder memakai model sederhana: tiap siswa punya **kemampuan** (0,35–1,00,
diundi dari rata-rata dua bilangan acak agar menumpuk di tengah) dan tiap butir
punya **kemudahan** sesuai tingkat kesukarannya. Peluang menjawab benar =
kemampuan × kemudahan. Soal pilihan ganda kompleks dan penjodohan mendapat
jawaban yang benar sebagian, dan panjang jawaban essay ikut mengikuti kemampuan
siswa sehingga guru punya bahan nyata saat mencoba menilainya.

Hasilnya pada PAS Matematika: rata-rata ±67, median ±68, rentang 30–100,
simpangan baku ±17, dan sebaran nilai berbentuk lonceng.

**Butir nomor 1 sengaja dibuat bermasalah** — siswa berkemampuan tinggi justru
memilih opsi lain, meniru soal yang kuncinya keliru. Butir itu muncul di halaman
Analisis Butir Soal dengan daya pembeda negatif (±−0,50) dan keputusan
*"Buang / perbaiki kunci"*, sehingga cara membaca laporan itu bisa langsung
diperagakan.

Pemisahannya dipatok pada ambang kemampuan, bukan diundi. Cara undian sempat
dipakai dan ternyata rapuh: menambah satu butir saja ke bank soal menggeser
aliran bilangan acak dan daya pembedanya bisa berbalik positif. Karena alasan
serupa, lembar yang dibiarkan menunggu penilaian essay dijaga sedikit — lembar
tanpa nilai essay kehilangan sekitar sepertiga bobot, sehingga pemiliknya
terlempar ke peringkat bawah betapapun tinggi kemampuannya dan kelompok 27%
atas–bawah pada analisis butir jadi tercampur.

Bilangan acaknya diberi benih tetap, jadi hasil seeding selalu sama.

---

## Cara Masuk

Halaman login punya tiga pilihan peran dengan pengenal yang berbeda:

| Peran | Username | Sumber akun |
| --- | --- | --- |
| Admin / Operator | Email | tabel `users` database `ujian` |
| Guru | NIP | tabel `guru` database Data Center |
| Siswa | NISN | tabel `siswa` database Data Center |

Guru dan siswa memakai kata sandi yang sudah tersimpan di Data Center — aplikasi
ini tidak pernah mengubahnya.

**Hak akses:** guru hanya melihat bank soal, paket, dan ujian miliknya sendiri.
Menu Log Login dan Pengguna khusus admin.

---

## Menulis Soal: Teks Arab, Simbol Matematika & Gambar

Kolom teks pada formulir soal bukan kotak teks polos, melainkan **editor yang
menampilkan hasilnya langsung** — pangkat tampil naik, pecahan tampil bersusun,
teks Arab tampil kanan-ke-kiri, gambar tampil sebagai gambar. Yang terlihat di
sana sama persis dengan yang nanti dilihat siswa.

> Kolom aslinya tidak dibuang, hanya disembunyikan dan tetap ikut terkirim.
> Bila JavaScript gagal berjalan, kolom itu muncul kembali dan formulir tetap
> terpakai.

Di bawahnya ada **papan sisip** yang melayani seluruh kolom pada formulir —
pertanyaan, tiap opsi jawaban, pasangan penjodohan, kunci essay, dan
pembahasan — dengan menyisipkan ke kolom yang sedang disunting. Empat
kelompoknya:

| Tab | Isi |
| --- | --- |
| **Simbol** | operasi, perbandingan, himpunan & logika, pangkat/indeks satuan (⁰¹²³…₀₁₂₃), akar & kalkulus, geometri, pecahan siap pakai (½ ⅓ ¾ …), panah |
| **Rumus & Format** | tebal, miring, garis bawah, **pangkat**, **indeks**, **pecahan bertingkat**, **akar**, sisip gambar, baris baru |
| **Yunani** | α β γ … ω dan Γ Δ Θ Λ Ξ Π Σ Φ Ψ Ω |
| **Hijaiyah** | huruf hijaiyah, bentuk lain (أ إ آ ء ة ى), harakat, angka Arab ٠–٩, tanda baca ، ؛ ؟, dan lafaz ﷲ ﷺ ﷻ ﷽ |

**Cara memakai tombol rumus.** Menandai teks lebih dulu akan membungkus teks
itu; tanpa menandai, kursor diletakkan di bagian yang perlu diisi.

| Tombol | Cara pakai |
| --- | --- |
| **Pangkat / Indeks** | ketik `s`, tekan Pangkat, ketik `2`, tekan Pangkat sekali lagi untuk kembali menulis normal |
| **Pecahan bertingkat** | tekan tombolnya, ketik pembilang, <kbd>Tab</kbd>, ketik penyebut, <kbd>Tab</kbd> untuk keluar |
| **Akar** | tandai `225` lalu tekan Akar — atau tekan Akar lalu ketik isinya, <kbd>Tab</kbd> untuk keluar |

<kbd>Tab</kbd> itu penting: tanpa jalan keluar, kursor terperangkap di dalam
pecahan dan seluruh kalimat berikutnya ikut tertulis sebagai penyebut.

### Teks Arab

Huruf Arab bisa langsung diketik dari papan ketik Arab bawaan sistem, atau
disisipkan lewat tab Hijaiyah. Untuk soal berbahasa Indonesia yang memuat
kutipan Arab, tekan **Blok teks Arab** lebih dulu — tombol itu membungkus
kutipan dengan penanda arah kanan-ke-kiri:

```html
Lafaz <span class="teks-arab" lang="ar" dir="rtl">الْحَمْدُ لِلَّهِ</span> artinya segala puji bagi Allah.
```

Tanpa pembungkus itu, tanda baca dan angka di sekitar kutipan akan melompat ke
sisi yang salah — gejala khas teks campuran dua arah. Seluruh kolom teks dan
tempat penampilan soal memakai `dir="auto"`, jadi butir yang **seluruhnya**
berbahasa Arab otomatis rata kanan tanpa penanda tambahan.

Fontnya memakai tumpukan yang diawali Amiri lalu font Arab bawaan sistem, dan
ukuran serta tinggi barisnya dinaikkan agar harakat tidak berdesakan. Bila
sekolah menjalankan aplikasi ini tanpa internet dan font CDN gagal diunduh,
huruf Arab tetap terbaca memakai font sistem.

### Simbol & rumus matematika

Sebagian besar kebutuhan cukup dengan karakter Unicode dari tab Simbol:
`√144 × (−3)² ÷ 6`, `x² ≥ 0`, `H₂O`, `∠ABC = 90°`, `A ∩ B ⊆ C`. Untuk susunan
bertingkat tersedia dua penanda:

```html
<span class="pecahan"><span class="pembilang">2x + 6</span><span class="penyebut">2</span></span>
<span class="akar"><span class="radikan">144</span></span>
```

Keduanya dirender sebagai pecahan bersusun dengan garis pembagi dan akar dengan
garis di atas radikan, memakai CSS biasa — tanpa pustaka rumus tambahan, jadi
tetap tampil benar saat luring. Guru tidak perlu mengetik penanda itu sendiri;
tombol pada tab Rumus & Format yang menyusunnya.

### Gambar

Kolom pertanyaan menerima gambar lewat tiga jalan, semuanya bermuara ke tempat
yang sama:

- **Tempel** — Ctrl+V pada kolom teks. Tangkapan layar dari Word, Excel, atau
  tombol PrintScreen langsung terpasang.
- **Seret-lepas** — jatuhkan berkas gambar ke kolom teks.
- **Tombol Sisipkan gambar** pada tab Format papan sisip.

Gambar diunggah lebih dulu, lalu yang masuk ke kolom hanyalah
`<img src="/storage/soal/…">` — sehingga pratinjau bisa langsung
menampilkannya. Diterima JPG, PNG, GIF, dan WebP sampai 5 MB.

Yang perlu diketahui tentang penyimpanannya:

- Gambar disimpan sebagai **berkas**, bukan ditanam sebagai data URI di dalam
  kolom pertanyaan. Satu foto ponsel 2 MB sebagai data URI membengkak
  sepertiga lagi, ikut terbawa pada setiap query bank soal, dan ikut tersalin
  ke tiap lembar jawaban saat naskah ditampilkan.
- Nama berkas diambil dari **sidik jari isinya**, jadi satu peta yang dipakai
  lima butir sekaligus — atau naskah Word yang diimpor dua kali — hanya
  menempati ruang sekali.
- Gambar selebar lebih dari 1280 px **diperkecil**; lebar badan soal di layar
  hanya sekitar 700 px, sehingga foto 4000 px cuma memperlambat pemuatan.
- Jenis berkas ditentukan dari **isinya**, bukan dari nama atau header
  unggahan — keduanya ditentukan pengirim dan gampang dipalsukan.

Gambar yang tak lagi dirujuk butir mana pun (guru mengunggah lalu membatalkan,
atau mengganti gambar saat menyunting) bisa dibereskan sewaktu-waktu:

```bash
php artisan soal:bersihkan-gambar
```

Tanpa `--hapus` perintah itu hanya mendaftar temuannya. Jalankan ulang dengan
`--hapus` untuk benar-benar menghapusnya.

> Instalasi baru perlu `php artisan storage:link` sekali agar gambar bisa
> diakses peramban.

### Menempel dari Word, Google Dokumen, atau halaman web

Tempelan dari luar dikenali dan diubah menjadi penanda yang dipakai aplikasi
ini, jadi hasilnya tampil sama seperti hasil ketikan sendiri:

| Sumber | Bentuk aslinya | Menjadi |
| --- | --- | --- |
| Word | pangkat sebagai `style="vertical-align:super"` | `<sup>` |
| Google Dokumen | pangkat & indeks sebagai `vertical-align` | `<sup>` / `<sub>` |
| Word 365, Wikipedia | MathML `<msup>` `<msub>` `<mfrac>` `<msqrt>` `<mroot>` | pangkat, indeks, pecahan bertingkat, akar |
| Situs ber-KaTeX / MathJax | MathML **dan** tumpukan `<span>` sekaligus | satu rumus saja |
| Situs, ChatGPT, catatan | kode LaTeX apa adanya: `\(4\sqrt{3}\)`, `\[…\]`, `$$…$$` | pangkat, indeks, pecahan bertingkat, akar |

Tiga hal yang ditangani karena tanpa itu hasilnya keliru:

- **Rumus tertulis dua kali.** KaTeX dan MathJax mengirim dua salinan — MathML
  untuk pembaca layar, tumpukan `<span>` untuk mata. Yang diambil MathML-nya,
  kembaran visualnya dibuang.
- **Seluruh tempelan menjadi tebal.** Google Dokumen membungkus salinannya
  dengan `<b style="font-weight:normal">` — tag tebal yang dinetralkan oleh
  gayanya sendiri. Begitu `style` dibuang, yang tersisa hanya `<b>`. Bungkus
  semacam itu kini dilepas.
- **Tempelan beralinea di kolom satu baris.** Opsi jawaban dan pasangan
  penjodohan tingginya satu baris; alinea dari Word diratakan agar daftar opsi
  tidak berantakan.

#### Rumus LaTeX

Inilah bentuk tempelan yang paling sering ditemui, dan yang paling mudah
terlewat: banyak situs — juga keluaran asisten AI — menuliskan rumusnya
sebagai kode LaTeX lalu hanya mengirim teks itu ke papan klip, tanpa MathML
sama sekali. Yang tertempel pun berupa tulisan `\(4\sqrt{3} - 2\sqrt{3}\)`.

Kode semacam itu diurai menjadi rumus sungguhan. Yang dikenali:

| Perintah | Hasil |
| --- | --- |
| `\frac{a}{b}`, `\dfrac`, `\tfrac` | pecahan bertingkat |
| `\sqrt{x}`, `\sqrt[3]{27}` | akar, akar berderajat |
| `x^{2}`, `H_{2}O` | pangkat, indeks |
| `\times \div \pm \le \ge \ne \approx` | × ÷ ± ≤ ≥ ≠ ≈ |
| `\pi \alpha \theta \Delta \sum \int \infty` | π α θ Δ ∑ ∫ ∞ |
| `\text{...}`, `\left(`, `\right)` | isinya saja |

Pembatas `\(…\)`, `\[…\]`, dan `$$…$$` selalu diperlakukan sebagai rumus.
Pembatas `$…$` hanya bila isinya memang memuat perintah LaTeX — kalau tidak,
tulisan harga seperti `$5` ikut terbaca sebagai rumus. Perintah di luar daftar
ditulis apa adanya, bukan dibuang, supaya guru bisa melihat bagian mana yang
perlu dirapikan sendiri.

Untuk soal yang **terlanjur** tersimpan atau tertempel sebagai kode mentah,
tandai bagian rumusnya lalu tekan **Ubah LaTeX terpilih** pada tab Rumus &
Format — tanpa itu satu-satunya jalan adalah mengetik ulang seluruh rumus.

> Rumus Word yang disalin sebagai **gambar** tetap masuk sebagai gambar — itu
> memang bentuk yang dikirim Word, dan ditangani jalur penyisipan gambar.

### Penanda yang diizinkan

Isi butir soal disaring memakai daftar tag yang diizinkan, bukan ditampilkan
mentah: `b strong i em u s sup sub br span small mark code p div ul ol li table
thead tbody tr td th img`, dengan atribut `dir lang class colspan rowspan src
alt`. Nama kelas dibatasi pada kelas bawaan aplikasi, dan `src` gambar hanya
boleh menunjuk berkas di server sendiri atau `data:image/`.

Penyaringan ini bukan sekadar kerapian. Soal ditulis guru, tetapi dibaca ulang
di peramban admin dan seluruh siswa saat ujian berlangsung — satu `<script>`
yang lolos akan berjalan di semua layar itu. Tag di luar daftar dibuang namun
tulisannya dipertahankan, sehingga kalimat guru tidak hilang hanya karena satu
tag yang keliru.

---

## Format Import Soal

> Naskah Word maupun Excel boleh memuat huruf Arab, simbol matematika, dan
> **gambar**; ketiganya masuk apa adanya. Simbol sebaiknya diketik sebagai
> karakter Unicode (`×`, `√`, `²`), bukan sebagai objek Equation bawaan Word —
> objek Equation tersimpan sebagai gambar di dalam dokumen dan tidak terbaca
> sebagai teks.
>
> **Gambar pada naskah Word** menempel pada bagian yang ditulis tepat
> sebelumnya: bila baris sebelumnya sebuah opsi, gambar itu menjadi isi opsi
> tersebut — tulis `A.` lalu tempel gambarnya di baris berikutnya untuk
> membuat opsi bergambar. Selain itu gambar menjadi bagian pertanyaan.
>
> **Gambar pada berkas Excel** mengikuti sel tempatnya ditempel: gambar di
> kolom `pertanyaan` menjadi bagian pertanyaan, gambar di kolom `opsi_a`
> menjadi isi opsi A.

### Word (.docx)

Naskah ditulis seperti biasa; jenis soal ditebak dari bentuknya.

```
1. Ibu kota Provinsi Jawa Barat adalah ...
A. Bandung
B. Semarang
C. Surabaya
D. Serang
JAWABAN: A
BOBOT: 1
LEVEL: C1
KESUKARAN: mudah
PEMBAHASAN: Bandung merupakan ibu kota Jawa Barat.
```

| Bentuk naskah | Jenis yang dihasilkan |
| --- | --- |
| Ada opsi A–E, `JAWABAN: A` | Pilihan ganda |
| Ada opsi A–E, `JAWABAN: A, C` | Pilihan ganda kompleks |
| Tanpa opsi, `JAWABAN: benar` | Benar / salah |
| Tanpa opsi, kunci berupa kalimat | Essay |
| Baris `Jepang ## Tokyo` | Penjodohan |

Tambahkan baris `JENIS: essay` bila perlu memaksa jenis tertentu. Baris
`KUNCI:` boleh dipakai menggantikan `JAWABAN:`.

### Excel (.xlsx / .xls / .csv)

Satu baris = satu butir, dengan urutan kolom:

```
jenis | pertanyaan | opsi_a..opsi_e | kunci | bobot | level_kognitif | tingkat_kesukaran | pembahasan
```

- **pg** → kunci satu huruf (`A`)
- **pg_kompleks** → beberapa huruf dipisah koma (`A,C`)
- **benar_salah** → opsi dikosongkan, kunci `benar` / `salah`
- **essay** → opsi dikosongkan, kunci = jawaban model; kata kunci penilaian
  ditulis di `opsi_a` dipisah koma
- **penjodohan** → tiap opsi ditulis `pernyataan ## jodohnya`, kolom kunci kosong

Template kedua format bisa diunduh dari menu **Bank Soal → Import Word / Excel**.
Hasil **Export Excel** bank soal memakai kolom yang sama persis, jadi bisa
disunting lalu diimpor kembali.

Import berjalan dua langkah: berkas diurai menjadi pratinjau (lengkap dengan
daftar baris yang gagal dibaca), baru butir yang dicentang disimpan.

---

## Analisis Butir Soal

| Ukuran | Rumus | Tafsir |
| --- | --- | --- |
| Tingkat kesukaran (P) | rata-rata skor butir ÷ skor maksimal | ≤0,30 sukar · 0,31–0,70 sedang · >0,70 mudah |
| Daya pembeda (D) | (rata-rata 27% atas − rata-rata 27% bawah) ÷ skor maksimal | <0,20 jelek · 0,20–0,29 cukup · 0,30–0,39 baik · ≥0,40 sangat baik |
| Efektivitas pengecoh | proporsi pemilih tiap opsi | berfungsi bila dipilih ≥5% peserta |

Daya pembeda **negatif** berarti siswa berkemampuan tinggi justru lebih banyak
salah — kunci jawabannya perlu diperiksa.

---

## Catatan Teknis

- **Tanpa langkah build.** Antarmuka memakai Bootstrap 5.3 + Bootstrap Icons dari
  CDN, mengikuti pola aplikasi SIM Kurikulum. Tidak ada npm/Vite.
- **Relasi lintas database tidak memakai foreign key.** Kolom `siswa_id`,
  `guru_id`, `mata_pelajaran_id`, `rombongan_belajar_id` dan sejenisnya menunjuk
  ke database Data Center, sehingga hanya diberi indeks.
- **Urutan soal & opsi dikunci saat peserta memulai** (`ujian_jawaban.nomor_urut`
  dan `urutan_opsi`), supaya me-refresh halaman tidak mengacak ulang tampilan.
- **Sinkron bersifat idempoten.** Baris pemetaan CP-TP-ATP yang sudah pernah
  ditarik dikenali lewat pasangan (`sumber`, `sumber_ref_id`) dan hanya
  diperbarui. Topik hasil input manual tidak pernah tersentuh sinkron.
- **`getAuthIdentifierName()` tidak dioverride** pada model Siswa/Guru. Login
  memakai NISN/NIP ditangani lewat kredensial `Auth::attempt`; mengganti kolom
  identitas akan membuat `Auth::id()` mengembalikan NISN dan merusak setiap
  perbandingan `siswa_id`.
- **Hindari bentuk singkat `@php(...)` di Blade.** Blade menyimpan blok mentah
  dengan regex `/(?<!@)@php(.*?)@endphp/s`, sehingga `@php(...)` sebaris yang
  muncul sebelum blok `@php ... @endphp` di berkas yang sama akan ikut tertelan.
  Seluruh view di proyek ini memakai bentuk blok.

---

## Pengujian

```bash
php artisan test
```

Tes memakai database terpisah `ujian_test` (dibuat otomatis lewat `phpunit.xml`),
sedangkan koneksi `datacenter` dan `kurikulum` tetap menunjuk database asli
karena keduanya hanya dibaca. Tes yang bergantung pada data referensi akan
di-*skip* dengan pesan jelas bila database itu belum tersedia.

Cakupannya: seluruh halaman pengelola, penyimpanan kelima jenis soal, aturan
koreksi tiap jenis, alur pengerjaan siswa sampai nilai keluar, pemilihan butir ke
paket, registrasi peserta dari kelas, idempotensi sinkron CP-TP-ATP, parser
import Word & Excel (termasuk pelaporan baris cacat), keabsahan seluruh berkas
export Excel, serta pembatasan akses antar peran.

Kemandirian dari internet ikut dijaga: berkas gaya, ikon, dan font harus ada
di dalam `public/`, berkas gayanya harus menunjuk ke dalam, tidak boleh ada
rujukan CDN tersisa di tampilan mana pun, dan skrip halaman pengerjaan harus
bebas dari sintaks yang tidak dikenal WebView lama.

Gambar diuji pada seluruh jalannya: unggahan formulir, penolakan berkas yang
bukan gambar, penentuan akhiran dari isi berkas, pengecilan gambar lebar,
penggabungan berkas kembar, penarikan gambar dari naskah Word dan lembar Excel,
sampai perintah pembersih yang harus menyisakan gambar yang masih dirujuk lewat
kolom opsi.

Pengenalan rumus dari tempelan luar ikut diuji — kode LaTeX berpembatas,
MathML, pangkat bergaya `vertical-align`, salinan ganda KaTeX/MathJax, dan
bungkus `<b>` dari Google Dokumen — beserta seluruh jalur penyisipan pangkat,
akar, dan pecahan pada formulir.

Penulisan soal berbahasa Arab dan bersimbol matematika diuji dari ujung ke
ujung: tersimpan lewat formulir, tampil di halaman guru dan di lembar ujian
siswa, masuk lewat impor Word & Excel, dan keluar lewat export — sebab karakter
di luar ASCII gampang rusak di titik mana pun. Penyaring teks soal diuji dua
arah: penanda yang sah harus lolos, sedangkan `<script>`, penangan kejadian,
dan gambar dari alamat luar harus tertahan.

Data contoh juga ikut diuji — bukan sekadar "ada isinya", melainkan sebaran
nilainya wajar (rata-rata, rentang, dan simpangan baku dalam batas masuk akal),
analisis butirnya berdaya pembeda positif, dan butir bermasalah bawaannya benar-
benar terdeteksi negatif.
