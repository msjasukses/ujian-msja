@props(['soal' => null, 'url' => null, 'tipe' => null, 'judul' => null])

@php
    $alamat = $url ?? $soal?->media_url;
    $jenis = $tipe ?? $soal?->media_tipe;
@endphp

@if ($alamat && $jenis)
    {{--
        Pemutar audio/video butir soal.

        Berkasnya dilayani server ujian sendiri, jadi tetap berjalan di
        jaringan sekolah tanpa internet.

        preload="metadata": durasi langsung tampil, tetapi isinya baru diunduh
        saat peserta menekan putar — satu kelas yang membuka lembar ujian
        serentak tidak menarik seluruh rekaman sekaligus.

        Video sengaja dimatikan gambar-dalam-gambarnya: proteksi kecurangan
        menghitung jendela mengambang sebagai pelanggaran, dan peserta yang
        tidak sengaja menekan tombol itu tidak boleh ikut tercatat curang.
    --}}
    <div {{ $attributes->class(['media-soal mb-3']) }}>
        <div class="small text-muted mb-1">
            <i class="bi bi-{{ $jenis === 'video' ? 'camera-video' : 'volume-up' }} me-1"></i>
            {{ $judul ?? ($jenis === 'video' ? 'Tayangan soal' : 'Rekaman soal') }}
            &middot; boleh diputar ulang sebanyak yang diperlukan
        </div>

        @if ($jenis === 'video')
            <video src="{{ $alamat }}" class="w-100 rounded border" style="max-height:22rem"
                   controls playsinline preload="metadata"
                   controlsList="nodownload noremoteplayback" disablePictureInPicture>
                Peramban ini tidak dapat memutar video soal. Laporkan kepada pengawas.
            </video>
        @else
            <audio src="{{ $alamat }}" class="w-100" controls preload="metadata"
                   controlsList="nodownload noremoteplayback">
                Peramban ini tidak dapat memutar audio soal. Laporkan kepada pengawas.
            </audio>
        @endif
    </div>
@endif
