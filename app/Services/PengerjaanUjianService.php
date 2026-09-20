<?php

namespace App\Services;

use App\Models\LoginAttempt;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\Models\UjianLog;
use App\Models\UjianPeserta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mesin pengerjaan ujian dari sisi peserta: memulai sesi, menyimpan jawaban
 * per butir, dan menutup sesi.
 *
 * Urutan soal dan urutan opsi dikunci saat peserta pertama kali memulai
 * (disimpan di ujian_jawaban.nomor_urut & urutan_opsi), supaya me-refresh
 * halaman tidak mengacak ulang tampilan dan jawaban yang sudah diisi tetap
 * menempel pada butir yang sama.
 */
class PengerjaanUjianService
{
    public function __construct(protected PenilaianService $penilaian) {}

    /**
     * Mulai atau lanjutkan pengerjaan.
     *
     * @throws \RuntimeException bila ujian belum/sudah lewat atau peserta ditolak.
     */
    public function mulai(UjianPeserta $peserta, ?string $token = null): UjianPeserta
    {
        $ujian = $peserta->ujian;

        $this->pastikanBolehMengerjakan($peserta, $ujian, $token);

        if ($peserta->status === UjianPeserta::TERDAFTAR) {
            DB::transaction(function () use ($peserta, $ujian) {
                $this->siapkanLembarJawaban($peserta, $ujian);

                $peserta->update([
                    'status' => UjianPeserta::MULAI,
                    'waktu_mulai' => now(),
                    'ip_address' => request()->ip(),
                    'browser' => LoginAttempt::parseUserAgent((string) request()->userAgent())['browser'],
                ]);
            });

            UjianLog::catat($ujian->id, $peserta->id, 'mulai');
        } else {
            UjianLog::catat($ujian->id, $peserta->id, 'lanjut');
        }

        return $peserta->refresh();
    }

    /**
     * Bolehkah ujian ini dikerjakan dari peramban permintaan sekarang?
     *
     * Pemeriksaannya memakai penanda yang sama dengan menu Log Login, supaya
     * lencana "lewat ExamBro" di sana dan keputusan boleh/tidak di sini tidak
     * pernah berbeda. Batasnya pun sama: yang dikenali adalah aplikasi
     * ber-WebView, bukan ExamBro secara khusus. Build peramban ujian yang
     * mengirim user agent Chrome apa adanya harus disetel menambahkan penanda
     * lewat UJIAN_PENANDA_EXAMBRO — tanpa itu, menyalakan kewajiban ini akan
     * menolak seluruh peserta.
     */
    public static function bolehDenganPeramban(Ujian $ujian, ?string $userAgent = null): bool
    {
        if (! $ujian->wajib_exambro) {
            return true;
        }

        return LoginAttempt::webview($userAgent ?? (string) request()->userAgent());
    }

    /** @throws \RuntimeException */
    protected function pastikanBolehMengerjakan(UjianPeserta $peserta, Ujian $ujian, ?string $token): void
    {
        if ($peserta->status === UjianPeserta::SELESAI) {
            throw new \RuntimeException('Anda sudah menyelesaikan ujian ini.');
        }
        if ($peserta->status === UjianPeserta::DIBATALKAN) {
            throw new \RuntimeException('Keikutsertaan Anda pada ujian ini dibatalkan. Hubungi pengawas.');
        }
        if ($ujian->status !== Ujian::AKTIF) {
            throw new \RuntimeException('Ujian belum diaktifkan oleh pengawas.');
        }
        if ($ujian->belum_mulai) {
            throw new \RuntimeException('Ujian baru dibuka pada '.$ujian->waktu_mulai->format('d/m/Y H:i').'.');
        }
        if ($ujian->sudah_lewat) {
            throw new \RuntimeException('Waktu ujian sudah berakhir.');
        }

        if (! static::bolehDenganPeramban($ujian)) {
            // User agent dicatat apa adanya: bila seluruh kelas tertolak,
            // pengawas butuh melihat penanda apa yang sebenarnya dikirim
            // peramban ujian sekolah (lihat config/ujian.php).
            UjianLog::catat($ujian->id, $peserta->id, 'tolak_non_exambro',
                Str::limit((string) request()->userAgent(), 180));

            throw new \RuntimeException(
                'Ujian ini hanya boleh dikerjakan lewat aplikasi ExamBro. '
                .'Tutup peramban ini, buka ExamBro, lalu masuk kembali.'
            );
        }

        if ($ujian->token && $peserta->status === UjianPeserta::TERDAFTAR) {
            if (strcasecmp((string) $token, $ujian->token) !== 0) {
                UjianLog::catat($ujian->id, $peserta->id, 'token_salah', 'Token dimasukkan: '.$token);
                throw new \RuntimeException('Token ujian salah.');
            }
        }
    }

    /**
     * Buat baris jawaban kosong untuk tiap butir di paket, sekaligus mengunci
     * urutan soal dan urutan opsi bila pengacakan diaktifkan.
     */
    protected function siapkanLembarJawaban(UjianPeserta $peserta, Ujian $ujian): void
    {
        $detail = $ujian->paketSoal->detail()->with('soal')->get();

        if ($ujian->acak_soal) {
            // Soal essay tetap ditaruh di bagian akhir supaya bentuk lembar
            // jawaban tidak berubah drastis antar peserta.
            [$essay, $objektif] = $detail->partition(fn ($d) => $d->soal?->jenis === Soal::ESSAY);
            $detail = $objektif->shuffle()->concat($essay->shuffle());
        }

        foreach ($detail->values() as $i => $d) {
            UjianJawaban::create([
                'ujian_peserta_id' => $peserta->id,
                'soal_id' => $d->soal_id,
                'nomor_urut' => $i + 1,
                'jawaban' => null,
                'urutan_opsi' => $ujian->acak_opsi ? $this->acakOpsi($d->soal) : null,
            ]);
        }
    }

    /**
     * Urutan tampil opsi setelah diacak. Untuk penjodohan yang diacak adalah
     * kolom kanan saja — kolom kiri tetap urut supaya pernyataan mudah dibaca.
     *
     * @return array<int, string>|null
     */
    protected function acakOpsi(?Soal $soal): ?array
    {
        if (! $soal) {
            return null;
        }

        return match ($soal->jenis) {
            Soal::PG, Soal::PG_KOMPLEKS => collect($soal->opsiPilihan())
                ->pluck('key')->shuffle()->values()->all(),
            Soal::PENJODOHAN => collect($soal->opsiKanan())
                ->pluck('key')->shuffle()->values()->all(),
            default => null,
        };
    }

    /**
     * Simpan jawaban satu butir. Mengembalikan false bila sesi sudah tidak
     * boleh menerima jawaban lagi (waktu habis / sudah dikumpulkan).
     */
    public function simpanJawaban(UjianPeserta $peserta, int $soalId, mixed $jawaban, bool $ragu = false): bool
    {
        if ($peserta->status !== UjianPeserta::MULAI || $peserta->sisaWaktuDetik() <= 0) {
            return false;
        }

        $baris = $peserta->jawaban()->where('soal_id', $soalId)->first();

        if (! $baris) {
            return false;
        }

        $baris->update([
            'jawaban' => $this->bersihkanJawaban($baris->soal, $jawaban),
            'ragu' => $ragu,
        ]);

        $peserta->update(['sisa_detik' => $peserta->sisaWaktuDetik()]);

        return true;
    }

    /**
     * Normalkan bentuk jawaban dari form agar selalu sesuai struktur yang
     * dipahami Soal::koreksi(), apa pun yang dikirim browser.
     */
    protected function bersihkanJawaban(?Soal $soal, mixed $jawaban): mixed
    {
        if (! $soal) {
            return null;
        }

        return match ($soal->jenis) {
            Soal::ESSAY => ['teks' => is_array($jawaban) ? (string) ($jawaban['teks'] ?? '') : (string) $jawaban],

            Soal::PG, Soal::BENAR_SALAH => (function () use ($jawaban) {
                $v = is_array($jawaban) ? ($jawaban[0] ?? null) : $jawaban;

                return ($v === null || $v === '') ? null : [(string) $v];
            })(),

            Soal::PG_KOMPLEKS => (function () use ($jawaban) {
                $v = array_values(array_filter((array) $jawaban, fn ($x) => $x !== '' && $x !== null));

                return $v === [] ? null : array_map('strval', $v);
            })(),

            Soal::PENJODOHAN => (function () use ($jawaban) {
                $v = array_filter((array) $jawaban, fn ($x) => $x !== '' && $x !== null);

                return $v === [] ? null : array_map('strval', $v);
            })(),

            default => $jawaban,
        };
    }

    /** Kumpulkan lembar jawaban dan koreksi otomatis. */
    public function selesaikan(UjianPeserta $peserta, string $event = 'selesai'): UjianPeserta
    {
        if ($peserta->status === UjianPeserta::SELESAI) {
            return $peserta;
        }

        $peserta->update([
            'status' => UjianPeserta::SELESAI,
            'waktu_selesai' => now(),
            'sisa_detik' => max(0, $peserta->sisaWaktuDetik()),
        ]);

        $peserta = $this->penilaian->koreksi($peserta);

        UjianLog::catat($peserta->ujian_id, $peserta->id, $event);

        return $peserta;
    }

    /**
     * Tutup paksa semua peserta yang waktunya sudah habis tetapi lembar
     * jawabannya belum dikumpulkan (mis. browser ditutup mendadak).
     */
    public function tutupYangKedaluwarsa(Ujian $ujian): int
    {
        $jumlah = 0;

        $ujian->peserta()->where('status', UjianPeserta::MULAI)->get()
            ->each(function (UjianPeserta $p) use (&$jumlah) {
                if ($p->sisaWaktuDetik() <= 0) {
                    $this->selesaikan($p, 'auto_selesai');
                    $jumlah++;
                }
            });

        return $jumlah;
    }

    /**
     * Reset pengerjaan seorang peserta — dipakai pengawas saat siswa terputus
     * atau salah mulai. Seluruh jawabannya dihapus dan sesi dibuka lagi.
     */
    public function reset(UjianPeserta $peserta, ?string $alasan = null): UjianPeserta
    {
        DB::transaction(function () use ($peserta) {
            $peserta->jawaban()->delete();
            $peserta->update([
                'status' => UjianPeserta::TERDAFTAR,
                'waktu_mulai' => null,
                'waktu_selesai' => null,
                'sisa_detik' => null,
                'nilai' => null,
                'skor_objektif' => 0,
                'skor_essay' => 0,
                'jumlah_benar' => 0,
                'jumlah_salah' => 0,
                'jumlah_kosong' => 0,
                'essay_dinilai' => false,
                'reset_count' => $peserta->reset_count + 1,

                // Kunci pengawasan ikut dibuka. Reset berarti mengulang dari
                // awal; tanpa ini peserta memulai lagi dengan lembar yang masih
                // terblokir dan langsung berhenti di layar yang sama.
                'pelanggaran' => 0,
                'dikunci_at' => null,
            ]);
        });

        UjianLog::catat($peserta->ujian_id, $peserta->id, 'reset', $alasan);

        return $peserta->refresh();
    }
}
