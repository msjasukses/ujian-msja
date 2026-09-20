@php use App\Support\Pengguna; use App\Support\Referensi; @endphp
<header class="topbar px-3 px-lg-4 py-2 d-flex align-items-center gap-3 sticky-top">
    <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="bukaSidebar()" aria-label="Buka menu">
        <i class="bi bi-list"></i>
    </button>

    <div class="flex-grow-1 min-width-0">
        <div class="fw-semibold text-truncate">@yield('title', 'Dashboard')</div>
        @hasSection('subtitle')
            <div class="small text-muted text-truncate">@yield('subtitle')</div>
        @endif
    </div>

    <span class="badge badge-soft d-none d-md-inline" title="Tahun ajaran aktif di Data Center">
        <i class="bi bi-calendar-range me-1"></i>{{ Referensi::namaTahunAjaranAktif() ?? 'Tahun ajaran belum diset' }}
    </span>

    <div class="dropdown">
        <button class="btn btn-sm btn-light border d-flex align-items-center gap-2" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i>
            <span class="d-none d-sm-inline text-truncate" style="max-width:12rem">{{ Pengguna::nama() }}</span>
            <span class="badge badge-soft d-none d-lg-inline">{{ Pengguna::peran() }}</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li class="px-3 py-2 small text-muted border-bottom">
                Masuk sebagai <strong>{{ Pengguna::peran() }}</strong>
            </li>
            <li>
                <a class="dropdown-item {{ request()->routeIs('profil.*') ? 'active' : '' }}" href="{{ route('profil.index') }}">
                    <i class="bi bi-person-gear me-2"></i>Profil Saya
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                </form>
            </li>
        </ul>
    </div>
</header>
