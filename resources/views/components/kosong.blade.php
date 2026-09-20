@props(['pesan' => 'Belum ada data.', 'icon' => 'bi-inbox', 'kolom' => 1])

<tr>
    <td colspan="{{ $kolom }}" class="text-center text-muted py-5">
        <i class="bi {{ $icon }} fs-2 d-block mb-2 opacity-50"></i>
        {{ $pesan }}
        @if (trim($slot) !== '')
            <div class="mt-3">{{ $slot }}</div>
        @endif
    </td>
</tr>
