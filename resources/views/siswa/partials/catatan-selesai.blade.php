{{-- Keterangan tombol "Selesai & Kumpulkan" yang masih terkunci ambang waktu.

     Hitungannya ikut berjalan sendiri: skrip lembar ujian memperbarui setiap
     elemen .teks-tunggu tiap detik dan menyembunyikan catatan ini begitu
     ambangnya tercapai, jadi siswa tidak perlu memuat ulang halaman untuk
     tahu tombolnya sudah terbuka. Dipakai di dua tempat — di bawah butir
     terakhir dan di panel navigasi — supaya keterangannya selalu ada di
     dekat tombol mana pun yang sedang dilihat siswa.

     $kelas: kelas pembungkus dari pemanggil, karena kedua tempat itu butuh
     jarak yang berbeda. --}}
<div class="catatan-selesai {{ $kelas ?? '' }}" style="{{ $bolehKumpul ? 'display:none' : '' }}">
    <i class="bi bi-hourglass-split"></i>
    <div>
        Tombol <strong>Selesai &amp; Kumpulkan</strong> terbuka pada
        {{ $peserta->ujian->menit_boleh_kumpul }} menit terakhir masa pengerjaan Anda —
        <span class="teks-tunggu fw-semibold">{{ gmdate('H:i:s', $tungguKumpulDetik) }}</span> lagi.
    </div>
</div>
