@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
        <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-circle me-1"></i>Periksa kembali isian berikut:</div>
        <ul class="mb-0 ps-3 small">
            @foreach ($errors->all() as $pesan)
                <li>{{ $pesan }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
