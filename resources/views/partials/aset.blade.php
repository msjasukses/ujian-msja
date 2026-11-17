{{--
    Berkas gaya dan ikon, dilayani dari server ini sendiri.

    Sebelumnya ketiganya diambil dari CDN. Itu berjalan mulus di meja
    pengembang, tetapi runtuh di tempat aplikasi ini sebenarnya dipakai:
    ExamBro dan peramban ujian sejenis mengunci akses ke luar alamat server
    ujian, dan banyak sekolah menjalankan ujian di jaringan lokal tanpa
    internet sama sekali. Yang terjadi bukan tampilan yang kurang cantik,
    melainkan halaman tanpa gaya sepenuhnya — tombol berubah menjadi tautan
    bergaris bawah dan tidak ada satu pun ikon.

    Karena itu tidak boleh ada satu pun rujukan ke alamat luar di seluruh
    aplikasi. Berkasnya ada di public/vendor/ dan ikut tersalin saat aplikasi
    dipindahkan.
--}}
{{-- Ikon tab peramban: logo sekolah dari Data Center, dilayani server ini
     sendiri lewat rute logo-sekolah. Tanpa logo di sana, peramban jatuh ke
     public/favicon.ico seperti sebelumnya. --}}
@php $urlLogoSekolah = \App\Support\Referensi::urlLogoSekolah(); @endphp
@if ($urlLogoSekolah)
    <link rel="icon" href="{{ $urlLogoSekolah }}">
@endif

<link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/select2/select2.min.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/select2/select2-bootstrap-5-theme.min.css') }}" rel="stylesheet">
