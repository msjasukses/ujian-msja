<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
    @include('partials.aset')
    <style>
        :root { --sb-bg:#16233a; --sb-hover:#22334f; --sb-text:#c8d3e4; }
        body { background:#f2f5f9; font-size:.925rem; }

        .sidebar { width:264px; height:100vh; background:var(--sb-bg); color:var(--sb-text);
                   position:fixed; top:0; left:0; overflow-y:auto; z-index:1040; }
        .sidebar a, .sidebar .menu-toggle { color:var(--sb-text); text-decoration:none; }
        .sidebar .nav-link { border-radius:.35rem; margin:1px .5rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background:var(--sb-hover); color:#fff; }
        .sidebar .nav-link.active { box-shadow:inset 3px 0 0 #4dabf7; }
        .sidebar .brand { color:#fff; font-weight:600; padding:1rem; border-bottom:1px solid var(--sb-hover); }
        .menu-toggle { width:100%; text-align:left; background:transparent; border:0; border-radius:.35rem; margin:1px .5rem; }
        .menu-toggle:hover { background:var(--sb-hover); color:#fff; }
        .menu-toggle .chev { transition:transform .2s; font-size:.7rem; }
        .menu-toggle.collapsed .chev { transform:rotate(-90deg); }
        .menu-sub .nav-link { padding-left:2.5rem !important; font-size:.875rem; }
        .menu-group-title { font-size:.68rem; text-transform:uppercase; letter-spacing:.08em;
                            color:#7186a5; padding:.9rem 1rem .25rem; }

        .content-wrapper { margin-left:264px; min-height:100vh; display:flex; flex-direction:column; }
        .topbar { background:#fff; border-bottom:1px solid #e4e9f0; }
        .sidebar-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1039; }
        .sidebar-backdrop.show { display:block; }
        @media (max-width: 991.98px) {
            .sidebar { left:-264px; transition:left .2s; }
            .sidebar.show { left:0; }
            .content-wrapper { margin-left:0; }
        }

        .card { border:1px solid #e4e9f0; border-radius:.6rem; box-shadow:0 1px 2px rgba(16,24,40,.04); }
        .card-header { background:#fff; border-bottom:1px solid #eef1f6; font-weight:600; }
        .table > :not(caption) > * > * { padding:.55rem .6rem; }
        .table thead th { background:#f7f9fc; font-size:.78rem; text-transform:uppercase;
                          letter-spacing:.03em; color:#5b6b83; font-weight:600; white-space:nowrap; }
        .stat-card .stat-value { font-size:1.6rem; font-weight:600; line-height:1.1; }
        .stat-card .stat-label { font-size:.78rem; color:#6b7a90; }
        .badge-soft { background:#eef2f7; color:#41506a; font-weight:500; }
        .form-label { font-weight:500; font-size:.86rem; margin-bottom:.25rem; }
        .table-sticky thead th { position:sticky; top:0; z-index:2; }
        .text-wrap-2 { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .soal-body img { max-width:100%; height:auto; }
    </style>
    @include('partials.gaya-soal')
    @include('partials.editor-kaya')
    @stack('head')
</head>
<body>

@include('partials.sidebar')
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="tutupSidebar()"></div>

<div class="content-wrapper">
    @include('partials.header')

    <main class="flex-grow-1 p-3 p-lg-4">
        @include('partials.flash')
        @yield('content')
    </main>

    <footer class="px-4 py-3 text-muted small border-top bg-white">
        &copy; {{ date('Y') }} {{ config('app.name') }}
        &middot; data siswa &amp; guru bersumber dari Data Center, CP-TP-ATP dari SIM Kurikulum
    </footer>
</div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@include('partials.select2')
<script>
    function bukaSidebar()  { document.getElementById('sidebar').classList.add('show');
                              document.getElementById('sidebarBackdrop').classList.add('show'); }
    function tutupSidebar() { document.getElementById('sidebar').classList.remove('show');
                              document.getElementById('sidebarBackdrop').classList.remove('show'); }

    // Konfirmasi untuk tombol/form yang menghapus atau mengubah keadaan penting.
    document.addEventListener('submit', function (e) {
        const pesan = e.target.dataset.konfirmasi;
        if (pesan && !window.confirm(pesan)) { e.preventDefault(); }
    });

    // Hapus massal (komponen hapus-massal + pilih): hitung baris yang dicentang,
    // tampilkan tombolnya, dan siapkan pesan konfirmasi untuk listener di atas.
    (function () {
        const form = document.getElementById('hapusMassal');
        if (!form) return;

        const semua = document.querySelector('[data-pilih-semua]');
        const baris = () => [...document.querySelectorAll('input[data-pilih]')];

        function segarkan() {
            const kotak = baris();
            const n = kotak.filter(c => c.checked).length;

            document.querySelectorAll('[data-hapus-massal-tombol]').forEach(t => {
                t.querySelector('[data-jumlah]').textContent = n;
                t.classList.toggle('d-none', n === 0);
            });
            form.dataset.konfirmasi = form.dataset.aksi + ' ' + n + ' ' + form.dataset.label
                + ' yang dicentang? ' + form.dataset.catatan;
            kotak.forEach(c => c.closest('tr')?.classList.toggle('table-active', c.checked));

            if (semua) {
                semua.checked = kotak.length > 0 && n === kotak.length;
                semua.indeterminate = n > 0 && n < kotak.length;
            }
        }

        semua?.addEventListener('change', () => {
            baris().forEach(c => { c.checked = semua.checked; });
            segarkan();
        });
        document.addEventListener('change', e => { if (e.target.matches('input[data-pilih]')) segarkan(); });
        // Peramban bisa memulihkan centang saat kembali ke halaman ini.
        window.addEventListener('pageshow', segarkan);
        segarkan();
    })();
</script>
@stack('scripts')
</body>
</html>
