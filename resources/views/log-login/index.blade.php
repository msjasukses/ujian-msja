@extends('layouts.app')
@section('title', 'Log Login')
@section('subtitle', 'Jejak percobaan masuk admin, guru, dan siswa')

@section('content')

@push('head')
<style>
    /* Dibiarkan satu baris terpotong; teks lengkapnya muncul saat ditunjuk,
       dan tetap ikut saat barisnya disalin. */
    .ua-mentah {
        font-size:.68rem; line-height:1.3; font-family:ui-monospace,Consolas,monospace;
        max-width:22rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        margin-top:.15rem; cursor:help;
    }
</style>
@endpush
@php use App\Models\LoginAttempt; @endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4"><x-stat label="Total percobaan" :value="$stat['total']" icon="bi-shield-lock" /></div>
    <div class="col-6 col-md-4"><x-stat label="Berhasil" :value="$stat['sukses']" icon="bi-check-circle" warna="success" /></div>
    <div class="col-6 col-md-4"><x-stat label="Gagal" :value="$stat['gagal']" icon="bi-x-circle" warna="danger" /></div>
    <div class="col-6 col-md-4"><x-stat label="Hari ini" :value="$stat['hari_ini']" icon="bi-calendar-day" warna="info" /></div>
    <div class="col-6 col-md-4">
        <a href="{{ route('log-login.index', ['guard' => 'siswa', 'status' => 'sukses', 'exambro' => 'tidak', 'dari' => now()->toDateString()]) }}"
           class="text-decoration-none">
            <x-stat label="Siswa tanpa ExamBro hari ini" :value="$stat['siswa_luar_exambro']"
                    icon="bi-shield-exclamation"
                    :warna="$stat['siswa_luar_exambro'] > 0 ? 'warning' : 'secondary'" />
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="{{ route('log-login.index', ['guard' => 'siswa', 'ganda' => 'ya', 'dari' => now()->toDateString()]) }}"
           class="text-decoration-none">
            <x-stat label="Siswa login ganda hari ini" :value="$stat['siswa_login_ganda']"
                    icon="bi-people-fill"
                    :warna="$stat['siswa_login_ganda'] > 0 ? 'danger' : 'secondary'" />
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span>Riwayat Login</span>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('log-login.export', request()->query()) }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
            </a>
            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#panelBersih">
                <i class="bi bi-trash me-1"></i>Bersihkan Log Lama
            </button>
        </div>
    </div>

    <div class="collapse" id="panelBersih">
        <form method="POST" action="{{ route('log-login.bersihkan') }}" class="card-body border-bottom bg-danger-subtle"
              data-konfirmasi="Hapus permanen semua log sebelum tanggal tersebut?">
            @csrf @method('DELETE')
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Hapus log sebelum tanggal</label>
                    <input type="date" name="sebelum" value="{{ now()->subMonths(3)->toDateString() }}"
                           class="form-control form-control-sm" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-sm btn-danger"><i class="bi bi-trash me-1"></i>Hapus</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Username</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="NISN / NIP / email">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Peran</label>
                <select name="guard" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach (LoginAttempt::GUARD as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(request('guard') === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Hasil</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="sukses" @selected(request('status') === 'sukses')>Berhasil</option>
                    <option value="gagal" @selected(request('status') === 'gagal')>Gagal</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Peramban ujian</label>
                <select name="exambro" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="ya" @selected(request('exambro') === 'ya')>Lewat ExamBro</option>
                    <option value="tidak" @selected(request('exambro') === 'tidak')>Bukan ExamBro</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Login ganda</label>
                <select name="ganda" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="ya" @selected(request('ganda') === 'ya')>Hanya login ganda</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Dari tanggal</label>
                <input type="date" name="dari" value="{{ request('dari') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Sampai</label>
                <input type="date" name="sampai" value="{{ request('sampai') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-1 d-flex gap-2">
                <button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i></button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:3rem">#</th>
                    <th>Waktu</th>
                    <th>Username</th>
                    <th>Peran</th>
                    <th class="text-center">Hasil</th>
                    <th>IP</th>
                    <th>Perangkat</th>
                    <th>Browser / OS</th>
                    <th class="text-center">Percobaan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $l)
                    <tr class="{{ ! $l->success ? 'table-danger-subtle' : ($l->login_ganda ? 'table-warning' : '') }}">
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td class="small">{{ $l->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="small">
                            <span class="font-monospace">{{ $l->username }}</span>
                            @if ($l->guard === 'siswa' && isset($namaSiswa[$l->username]))
                                <div class="text-muted">{{ $namaSiswa[$l->username] }}</div>
                            @endif
                        </td>
                        <td class="small">{{ $l->guard_label }}</td>
                        <td class="text-center">
                            <span class="badge {{ $l->success ? 'text-bg-success' : 'text-bg-danger' }}">
                                {{ $l->success ? 'Berhasil' : 'Gagal' }}
                            </span>
                            @if ($l->login_ganda)
                                <span class="badge text-bg-warning d-block mt-1"
                                      title="Akun ini sedang dipakai di perangkat lain saat login ini terjadi. Sesi di perangkat itu diakhiri.">
                                    <i class="bi bi-people-fill me-1"></i>Login ganda
                                </span>
                            @endif
                        </td>
                        <td class="small text-muted">
                            {{ $l->ip_address }}
                            @if ($l->login_ganda)
                                <div class="text-warning-emphasis fw-semibold"
                                     title="Peramban di perangkat yang tergeser: {{ $l->login_ganda_ua ?: 'tidak diketahui' }}">
                                    <i class="bi bi-arrow-left-right me-1"></i>menggeser {{ $l->login_ganda_ip ?: '?' }}
                                </div>
                            @endif
                        </td>
                        <td class="small">
                            <i class="bi {{ ['desktop' => 'bi-pc-display', 'mobile' => 'bi-phone', 'tablet' => 'bi-tablet'][$l->device_type] ?? 'bi-question-circle' }} me-1"></i>
                            {{ ucfirst($l->device_type ?? '-') }}
                        </td>
                        <td class="small">
                            <span class="text-muted">{{ $l->browser }} / {{ $l->os }}</span>
                            @if ($l->lewat_exambro)
                                <span class="badge text-bg-success-subtle text-success border border-success-subtle ms-1"
                                      title="Dikenali dari penanda WebView pada user agent — sesi dibuka lewat aplikasi peramban ujian, bukan peramban biasa.">
                                    <i class="bi bi-shield-check me-1"></i>ExamBro
                                </span>
                            @elseif ($l->guard === 'siswa' && $l->success)
                                <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1"
                                      title="Tidak ada penanda peramban ujian pada user agent sesi ini. Sebagian build ExamBro memang tidak mengirim penanda apa pun — periksa user agent di bawah sebelum menyimpulkan.">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Peramban biasa
                                </span>
                            @endif

                            {{-- User agent apa adanya. Inilah satu-satunya cara
                                 operator menemukan penanda khas peramban ujian
                                 sekolahnya, untuk diisikan ke UJIAN_PENANDA_EXAMBRO. --}}
                            @if ($l->user_agent)
                                <div class="ua-mentah text-muted" title="{{ $l->user_agent }}">{{ $l->user_agent }}</div>
                            @endif
                        </td>
                        <td class="text-center small">
                            <span class="badge {{ $l->attempt_no > 3 && ! $l->success ? 'text-bg-warning' : 'badge-soft' }}">
                                ke-{{ $l->attempt_no }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <x-kosong kolom="9" icon="bi-shield-check" pesan="Belum ada percobaan login tercatat." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-body border-top">{{ $items->links() }}</div>
    @endif
</div>
@endsection
