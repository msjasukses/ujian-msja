@props([
    'action' => null,
    'label' => 'data',
    'aksi' => 'Hapus',
    'catatan' => 'Data yang terhapus tidak bisa dikembalikan.',
    // semua | form | tombol — pisahkan bila tombolnya berada di dalam form lain.
    'bagian' => 'semua',
])

{{--
    Tombol "Hapus terpilih" untuk halaman daftar. Kotak centang baris
    (komponen pilih) dan tombolnya terhubung ke form #hapusMassal lewat
    atribut form=, jadi keduanya boleh berada di luar form ini — form tidak
    boleh bersarang, sedangkan tiap baris sudah punya form hapus satuan
    sendiri. Tombol disembunyikan sampai ada baris yang dicentang;
    perilakunya ada di layouts.app.
--}}
@if ($bagian !== 'tombol')
    <form id="hapusMassal" method="POST" action="{{ $action }}" class="d-none"
          data-label="{{ $label }}" data-aksi="{{ $aksi }}" data-catatan="{{ $catatan }}">
        @csrf @method('DELETE')
    </form>
@endif

@if ($bagian !== 'form')
    <button type="submit" form="hapusMassal" class="btn btn-sm btn-danger d-none" data-hapus-massal-tombol>
        <i class="bi bi-{{ $aksi === 'Hapus' ? 'trash' : 'x-lg' }} me-1"></i>{{ $aksi }} terpilih (<span data-jumlah>0</span>)
    </button>
@endif
