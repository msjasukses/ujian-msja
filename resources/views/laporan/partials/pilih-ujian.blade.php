{{--
    Daftar ujian sebagai pintu masuk tiap sub menu laporan. Dipakai bersama
    oleh Daftar Nilai, Statistik, Analisis Butir, Remidial dan Pengayaan.

    $items     : paginator berisi Ujian
    $routeShow : nama route detail, mis. "laporan.nilai.show"
    $kolomEkstra : closure(Ujian) => string HTML untuk satu kolom tambahan (opsional)
    $judulEkstra : judul kolom tambahan (opsional)
--}}
@php use App\Support\Referensi; @endphp

<div class="card">
    <div class="card-header">{{ $judul ?? 'Pilih Ujian' }}</div>

    <div class="card-body border-bottom bg-light-subtle py-2">
        <form class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Cari ujian</label>
                <input name="q" value="{{ request('q') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Mata pelajaran</label>
                <select name="mata_pelajaran_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach (Referensi::mapel() as $m)
                        <option value="{{ $m->id }}" @selected(request('mata_pelajaran_id') == $m->id)>{{ $m->nama_mapel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                {{-- Menyaring ujian yang kelas itu ikut serta, lalu terbawa ke
                     halaman detailnya sehingga daftar pesertanya langsung
                     tersaring pada kelas yang sama. --}}
                <label class="form-label">Kelas</label>
                <x-pilih-kelas :otomatis="false" />
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:3rem">#</th>
                    <th>Ujian</th>
                    <th>Pelaksanaan</th>
                    <th class="text-center">Peserta Selesai</th>
                    @isset($judulEkstra)<th class="text-center">{{ $judulEkstra }}</th>@endisset
                    <th style="width:8rem"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $i => $u)
                    <tr>
                        <td class="text-muted small">{{ $items->firstItem() + $i }}</td>
                        <td>
                            <a href="{{ route($routeShow, $u) }}" class="fw-semibold text-decoration-none">{{ $u->nama_ujian }}</a>
                            <div class="small text-muted">
                                {{ $u->kode_ujian }} &middot; {{ $u->mataPelajaran->nama_mapel ?? '-' }}
                                @if ($u->is_remidial)
                                    <span class="badge text-bg-warning-subtle text-warning border border-warning-subtle">Remidial</span>
                                @endif
                            </div>
                        </td>
                        <td class="small">{{ $u->waktu_mulai->format('d/m/Y H:i') }}</td>
                        <td class="text-center">
                            <span class="badge badge-soft">{{ $u->selesai_count ?? $u->peserta_selesai_count ?? 0 }}</span>
                        </td>
                        @isset($judulEkstra)
                            <td class="text-center">{!! $kolomEkstra($u) !!}</td>
                        @endisset
                        <td class="text-end">
                            <a href="{{ route($routeShow, [$u, 'rombongan_belajar_id' => request('rombongan_belajar_id')]) }}"
                               class="btn btn-sm btn-outline-primary">
                                Buka <i class="bi bi-chevron-right"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-kosong :kolom="isset($judulEkstra) ? 6 : 5" icon="bi-calendar-x"
                              pesan="Belum ada ujian yang bisa dilaporkan." />
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-body border-top">{{ $items->links() }}</div>
    @endif
</div>
