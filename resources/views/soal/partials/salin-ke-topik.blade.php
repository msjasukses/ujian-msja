{{--
    Jendela "Salin soal ke topik".

    Satu jendela melayani dua jalan masuk di halaman daftar: tombol salin pada
    satu baris, dan tombol "Salin terpilih" untuk butir yang dicentang. Daftar
    id-nya dituang sebagai input tersembunyi oleh skrip di bawah, jadi
    kotak centang baris tetap milik formulir hapus massal — satu kotak centang
    tidak bisa menempel pada dua formulir sekaligus.

    Yang mengerjakan penyalinan tetap server (SoalController::salin), termasuk
    pembatasan butir dan topik tujuan menurut kepemilikan guru.
--}}
<div class="modal fade" id="modalSalinSoal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('soal.salin') }}" id="formSalinSoal">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-files me-2"></i>Salin soal ke topik</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    {{-- Diisi skrip: satu <input name="ids[]"> per butir yang disalin. --}}
                    <div id="salinWadahId"></div>

                    <div class="alert alert-light border small d-flex gap-2 mb-3" id="salinRingkas">
                        <i class="bi bi-info-circle mt-1"></i><span></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="salinTopik">Topik tujuan <span class="text-danger">*</span></label>
                        <select name="topik_id" id="salinTopik" class="form-select" required>
                            <option value="">— pilih topik —</option>
                            @foreach ($daftarTopik as $t)
                                {{-- Penempatan topik ikut dibawa tiap pilihan, dipakai keterangan di bawah. --}}
                                <option value="{{ $t->id }}"
                                        data-mapel="{{ $t->mataPelajaran->nama_mapel ?? '' }}"
                                        data-tingkat="{{ $t->tingkatKelas->nama ?? '' }}"
                                        data-jurusan="{{ $t->jurusan->ringkas ?? '' }}">
                                    {{ Str::limit($t->nama_topik, 70) }}
                                </option>
                            @endforeach
                        </select>
                        @if ($daftarTopik->isEmpty())
                            <div class="form-text text-danger">
                                Belum ada topik. Buat topiknya dulu di menu
                                <a href="{{ route('topik.index') }}">Topik</a>.
                            </div>
                        @else
                            <div class="form-text" id="salinPenempatan">&nbsp;</div>
                        @endif
                    </div>

                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="ikut_penempatan"
                               id="salinIkut" value="1" checked>
                        <label class="form-check-label" for="salinIkut">
                            Sesuaikan mata pelajaran, tingkat, dan jurusan salinan dengan topik tujuan
                        </label>
                    </div>

                    <p class="small text-muted mb-0">
                        Soal aslinya tidak berubah. Pada salinan, kode soal dibiarkan kosong agar
                        tidak ada dua butir berkode sama; isian lainnya — opsi, kunci, bobot,
                        pembahasan, gambar, dan lampiran audio/video — disalin apa adanya.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary" @disabled($daftarTopik->isEmpty())>
                        <i class="bi bi-files me-1"></i>Salin ke topik ini
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    var jendela = document.getElementById('modalSalinSoal');
    if (! jendela || ! window.bootstrap) return;

    var modal = bootstrap.Modal.getOrCreateInstance(jendela);
    var formulir = document.getElementById('formSalinSoal');
    var wadah = document.getElementById('salinWadahId');
    var ringkas = document.querySelector('#salinRingkas span');
    var topik = document.getElementById('salinTopik');
    var penempatan = document.getElementById('salinPenempatan');

    function buka(ids, keterangan) {
        wadah.innerHTML = '';

        ids.forEach(function (id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            wadah.appendChild(input);
        });

        ringkas.textContent = keterangan;
        modal.show();
    }

    document.addEventListener('click', function (e) {
        var satu = e.target.closest('[data-salin-soal]');

        if (satu) {
            var teks = satu.dataset.salinRingkas || '';
            buka([satu.dataset.salinSoal], teks ? '1 butir disalin: “' + teks + '”' : '1 butir soal disalin.');
            return;
        }

        if (! e.target.closest('[data-salin-terpilih]')) return;

        var ids = [].slice.call(document.querySelectorAll('input[data-pilih]:checked'))
            .map(function (kotak) { return kotak.value; });

        if (! ids.length) {
            window.alert('Centang dulu soal yang akan disalin.');
            return;
        }

        buka(ids, ids.length + ' butir soal yang dicentang akan disalin.');
    });

    // Keterangan penempatan topik tujuan, supaya guru tahu mapel & tingkat
    // apa yang akan menempel pada salinannya bila pilihan di bawah dicentang.
    topik?.addEventListener('change', function () {
        if (! penempatan) return;

        var pilihan = topik.options[topik.selectedIndex];
        var bagian = pilihan && pilihan.value
            ? [pilihan.dataset.mapel, pilihan.dataset.tingkat, pilihan.dataset.jurusan].filter(Boolean)
            : [];

        penempatan.textContent = bagian.length ? 'Penempatan topik ini: ' + bagian.join(' · ') : ' ';
    });

    formulir.addEventListener('submit', function (e) {
        if (! wadah.querySelector('input')) {
            e.preventDefault();
            window.alert('Tidak ada soal yang dipilih untuk disalin.');
        }
    });
})();
</script>
@endpush
