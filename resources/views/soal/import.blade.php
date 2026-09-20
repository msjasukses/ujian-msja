@extends('layouts.app')
@section('title', 'Import Soal')
@section('subtitle', 'Unggah naskah soal dari Microsoft Word (.docx) atau Excel (.xlsx / .csv)')

@section('content')
@php use App\Support\Referensi; @endphp

@if ($pratinjau)
    {{-- =================== LANGKAH 2: PRATINJAU =================== --}}
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span>
                Pratinjau hasil pembacaan
                <span class="badge badge-soft ms-1">{{ $pratinjau['nama_berkas'] }}</span>
            </span>
            <form method="POST" action="{{ route('soal.import.batal') }}">
                @csrf
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Batal &amp; unggah ulang</button>
            </form>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <x-stat label="Butir terbaca" :value="count($pratinjau['soal'])" icon="bi-check2-circle" warna="success" />
                </div>
                <div class="col-6 col-lg-3">
                    <x-stat label="Baris bermasalah" :value="count($pratinjau['galat'])" icon="bi-exclamation-triangle" warna="danger" />
                </div>
            </div>

            @if ($pratinjau['galat'])
                <div class="alert alert-warning mt-3 mb-0">
                    <div class="fw-semibold small mb-1">Bagian berikut tidak bisa dibaca dan tidak akan diimpor:</div>
                    <ul class="small mb-0 ps-3">
                        @foreach ($pratinjau['galat'] as $g)
                            <li>{{ $g }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    @if ($pratinjau['soal'])
        <form method="POST" action="{{ route('soal.import.simpan') }}">
            @csrf
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Butir yang akan disimpan</span>
                    <button class="btn btn-sm btn-primary">
                        <i class="bi bi-download me-1"></i>Simpan ke Bank Soal
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:2.5rem"><input type="checkbox" class="form-check-input" id="pilihSemua" checked></th>
                                <th style="width:3rem">No</th>
                                <th>Pertanyaan</th>
                                <th>Jenis</th>
                                <th>Opsi</th>
                                <th>Kunci</th>
                                <th class="text-center">Bobot</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pratinjau['soal'] as $i => $s)
                                <tr>
                                    <td><input type="checkbox" name="pilih[]" value="{{ $i }}" class="form-check-input pilih-baris" checked></td>
                                    <td class="text-muted small">{{ $i + 1 }}</td>
                                    <td class="text-wrap-2" style="min-width:16rem">{{ Str::limit($s['pertanyaan'], 180) }}</td>
                                    <td><x-jenis-soal :jenis="$s['jenis']" /></td>
                                    <td class="small text-muted" style="min-width:12rem">
                                        @if ($s['jenis'] === 'penjodohan')
                                            {{ collect($s['opsi']['kiri'] ?? [])->pluck('text')->implode(' / ') }}
                                        @elseif (is_array($s['opsi']))
                                            {{ collect($s['opsi'])->map(fn ($o) => $o['key'].'. '.Str::limit($o['text'], 25))->implode(' | ') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="small">
                                        @if ($s['jenis'] === 'essay')
                                            {{ Str::limit($s['kunci']['jawaban'] ?? '', 40) }}
                                        @elseif ($s['jenis'] === 'penjodohan')
                                            {{-- Kurung kurawal wajib: tanpa itu "→" terserap menjadi bagian nama variabel. --}}
                                            {{ collect($s['kunci'])->map(fn ($v, $k) => "{$k}→{$v}")->implode(', ') }}
                                        @else
                                            <strong>{{ implode(', ', (array) $s['kunci']) }}</strong>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $s['bobot'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top small text-muted">
                    Semua butir akan disimpan dengan topik
                    <strong>{{ optional($daftarTopik->firstWhere('id', $pratinjau['topik_id']))->nama_topik ?? 'tanpa topik' }}</strong>
                    dan tahun ajaran <strong>{{ Referensi::namaTahunAjaranAktif() ?? '-' }}</strong>.
                </div>
            </div>
        </form>

        @push('scripts')
        <script>
            document.getElementById('pilihSemua')?.addEventListener('change', function () {
                document.querySelectorAll('.pilih-baris').forEach(cb => cb.checked = this.checked);
            });
        </script>
        @endpush
    @endif

@else
    {{-- =================== LANGKAH 1: UNGGAH =================== --}}
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">Unggah Berkas</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('soal.import.pratinjau') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Sumber berkas</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="sumber" id="sumber-word" value="word"
                                           @checked($sumber === 'word') onchange="gantiSumber()">
                                    <label class="btn btn-outline-primary w-100 py-2" for="sumber-word">
                                        <i class="bi bi-file-earmark-word me-1"></i>Word (.docx)
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="sumber" id="sumber-excel" value="excel"
                                           @checked($sumber === 'excel') onchange="gantiSumber()">
                                    <label class="btn btn-outline-success w-100 py-2" for="sumber-excel">
                                        <i class="bi bi-file-earmark-excel me-1"></i>Excel (.xlsx / .csv)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Berkas <span class="text-danger">*</span></label>
                            <input type="file" name="berkas" id="berkas" required
                                   class="form-control @error('berkas') is-invalid @enderror">
                            @error('berkas')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Ukuran maksimal 20 MB.</div>
                        </div>

                        <hr>
                        <p class="small text-muted">
                            Nilai berikut dipasang ke <em>semua</em> butir hasil import, jadi tidak perlu ditulis di berkas.
                        </p>

                        <div class="mb-3">
                            <label class="form-label">Topik</label>
                            <select name="topik_id" class="form-select">
                                <option value="">— tanpa topik —</option>
                                @foreach ($daftarTopik as $t)
                                    <option value="{{ $t->id }}" @selected(old('topik_id') == $t->id)>
                                        {{ Str::limit($t->nama_topik, 70) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-7">
                                <label class="form-label">Mata pelajaran</label>
                                <x-pilih-mapel />
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Tingkat kelas</label>
                                <x-pilih-tingkat />
                            </div>
                        </div>

                        <button class="btn btn-primary w-100">
                            <i class="bi bi-eye me-1"></i>Baca &amp; Tampilkan Pratinjau
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3" id="panduan-word">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-earmark-word text-primary me-1"></i>Format naskah Word</span>
                    <a href="{{ route('soal.import.template-word') }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-download me-1"></i>Contoh
                    </a>
                </div>
                <div class="card-body small">
                    <p class="mb-2">Tulis soal seperti naskah biasa. Parser mengenali pola berikut:</p>
<pre class="bg-light border rounded p-2 mb-2" style="font-size:.78rem">1. Ibu kota Jawa Barat adalah ...
A. Bandung
B. Semarang
C. Surabaya
D. Serang
JAWABAN: A
BOBOT: 1
PEMBAHASAN: Bandung ibu kota Jabar.</pre>
                    <ul class="mb-0 ps-3">
                        <li>Kunci lebih dari satu (mis. <code>JAWABAN: A, C</code>) otomatis jadi <strong>PG kompleks</strong>.</li>
                        <li>Tanpa opsi + kunci <code>benar</code>/<code>salah</code> jadi soal <strong>benar salah</strong>.</li>
                        <li>Tanpa opsi + kunci berupa kalimat jadi soal <strong>essay</strong>.</li>
                        <li>Baris <code>Jepang ## Tokyo</code> membentuk soal <strong>penjodohan</strong>.</li>
                        <li>Untuk memaksa jenis, tambahkan baris <code>JENIS: essay</code>.</li>
                        <li>
                            <strong>Gambar</strong> yang tertanam di naskah ikut terbawa. Gambar menempel pada
                            bagian yang ditulis tepat sebelumnya: bila baris sebelumnya sebuah opsi, gambar itu
                            menjadi isi opsi tersebut — tulis <code>A.</code> lalu tempel gambarnya di baris
                            berikutnya untuk membuat opsi bergambar; selain itu gambar menjadi bagian pertanyaan.
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card" id="panduan-excel">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-earmark-excel text-success me-1"></i>Format berkas Excel</span>
                    <a href="{{ route('soal.import.template-excel') }}" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-download me-1"></i>Template
                    </a>
                </div>
                <div class="card-body small">
                    <p class="mb-2">Satu baris = satu butir, dengan urutan kolom:</p>
                    <ol class="mb-2 ps-3">
                        <li><code>jenis</code> — pg / pg_kompleks / essay / penjodohan / benar_salah</li>
                        <li><code>pertanyaan</code></li>
                        <li>–7. <code>opsi_a</code> … <code>opsi_e</code></li>
                        <li class="list-unstyled">8. <code>kunci</code></li>
                        <li class="list-unstyled">9. <code>bobot</code></li>
                        <li class="list-unstyled">10–12. <code>level_kognitif</code>, <code>tingkat_kesukaran</code>, <code>pembahasan</code></li>
                    </ol>
                    <ul class="mb-0 ps-3">
                        <li>PG kompleks: kunci ditulis <code>A,C</code>.</li>
                        <li>Essay: kunci = jawaban model, kata kunci ditulis di <code>opsi_a</code>.</li>
                        <li>Penjodohan: tiap opsi ditulis <code>pernyataan ## jodohnya</code>, kunci dikosongkan.</li>
                        <li>
                            <strong>Gambar</strong> yang ditempel di lembar kerja ikut terbawa, mengikuti sel
                            tempatnya diletakkan: gambar di kolom <code>pertanyaan</code> menjadi bagian
                            pertanyaan, gambar di kolom <code>opsi_a</code> menjadi isi opsi A.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Kotak panduan mengikuti sumber berkas yang dipilih, dan filter tipe
        // berkas di dialog unggah ikut menyesuaikan.
        function gantiSumber() {
            const word = document.getElementById('sumber-word').checked;
            document.getElementById('panduan-word').style.display  = word ? '' : 'none';
            document.getElementById('panduan-excel').style.display = word ? 'none' : '';
            document.getElementById('berkas').accept = word ? '.docx' : '.xlsx,.xls,.csv';
        }
        gantiSumber();
    </script>
    @endpush
@endif
@endsection
