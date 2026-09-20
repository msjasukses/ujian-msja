@props(['label', 'value', 'icon' => 'bi-graph-up', 'warna' => 'primary', 'keterangan' => null])

<div class="card stat-card h-100">
    <div class="card-body d-flex align-items-center gap-3 py-3">
        <div class="rounded-3 d-flex align-items-center justify-content-center bg-{{ $warna }}-subtle text-{{ $warna }}"
             style="width:2.75rem;height:2.75rem;flex:none">
            <i class="bi {{ $icon }} fs-5"></i>
        </div>
        <div class="min-width-0">
            <div class="stat-value">{{ $value }}</div>
            <div class="stat-label text-truncate">{{ $label }}</div>
            @if ($keterangan)
                <div class="small text-muted">{{ $keterangan }}</div>
            @endif
        </div>
    </div>
</div>
