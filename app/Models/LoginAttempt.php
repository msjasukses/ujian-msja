<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Catatan percobaan login untuk menu Log Login. */
class LoginAttempt extends Model
{
    protected $table = 'login_attempts';

    protected $guarded = ['id'];

    protected $casts = [
        'success' => 'boolean',
        'login_ganda' => 'boolean',
    ];

    /** @var array<string, string> */
    public const GUARD = [
        'web' => 'Admin / Operator',
        'guru' => 'Guru',
        'siswa' => 'Siswa',
    ];

    /**
     * Penanda bahwa sesi berjalan di dalam aplikasi ber-WebView, bukan
     * peramban biasa.
     *
     * Android menyisipkan token "wv" pada user agent WebView, dan menyertakan
     * "Version/4.0" berdampingan dengan "Chrome/". ExamBro dibangun di atas
     * WebView itu, sehingga sesinya dikenali dari penanda tersebut.
     *
     * Perlu jujur soal batasnya: yang terdeteksi adalah "aplikasi ber-WebView",
     * bukan ExamBro secara khusus — aplikasi lain yang memuat halaman lewat
     * WebView akan tampak sama, dan peramban ujian untuk komputer meja tidak
     * memakai penanda ini sama sekali. Untuk pengawasan ujian di sekolah yang
     * memakai ExamBro, penanda ini sudah memisahkan dengan tepat antara siswa
     * yang memakai peramban ujian dan yang membuka lewat peramban biasa.
     */
    /**
     * Penanda WebView Android klasik.
     *
     * Tidak semua peramban ujian memakainya. Build yang diuji 10 September
     * 2026 mengirim user agent Chrome tereduksi yang tidak dapat dibedakan
     * dari Chrome Android biasa; untuk itu tersedia penanda tambahan yang
     * dapat disetel sekolah, lihat config/ujian.php.
     */
    public const POLA_WEBVIEW = '/;\s*wv\)|Version\/4\.0.*Chrome\//i';

    /**
     * Penanda tambahan yang disetel sekolah, dari UJIAN_PENANDA_EXAMBRO.
     *
     * @return list<string>
     */
    public static function penandaTambahan(): array
    {
        return config('ujian.penanda_exambro', []);
    }

    public function getGuardLabelAttribute(): string
    {
        return self::GUARD[$this->guard] ?? $this->guard;
    }

    /** Apakah sesi ini dibuka lewat ExamBro (aplikasi ber-WebView)? */
    public function getLewatExambroAttribute(): bool
    {
        return static::webview((string) $this->user_agent);
    }

    public static function webview(string $ua): bool
    {
        if (preg_match(self::POLA_WEBVIEW, $ua)) {
            return true;
        }

        foreach (static::penandaTambahan() as $penanda) {
            if ($penanda !== '' && stripos($ua, $penanda) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Saring menurut pemakaian ExamBro.
     *
     * Disaring lewat user_agent yang memang sudah tersimpan, bukan kolom
     * tersendiri — dengan begitu seluruh baris lama ikut tersaring benar tanpa
     * perlu migrasi maupun pengisian ulang.
     */
    public function scopeExambro($query, bool $ya = true)
    {
        // Penanda bawaan berikut yang disetel sekolah, dijaga tetap seiring
        // dengan webview() supaya penyaring dan lencana tidak pernah berbeda
        // pendapat tentang baris yang sama.
        $pola = array_merge(['; wv)', 'Version/4.0'], static::penandaTambahan());

        return $ya
            ? $query->where(function ($w) use ($pola) {
                foreach ($pola as $p) {
                    $w->orWhere('user_agent', 'like', '%'.$p.'%');
                }
            })
            : $query->where(function ($w) use ($pola) {
                foreach ($pola as $p) {
                    $w->where('user_agent', 'not like', '%'.$p.'%');
                }
            });
    }

    /** Simpan satu percobaan login lengkap dengan hasil parsing user agent. */
    public static function catat(Request $r, string $username, string $guard, bool $success): static
    {
        $ua = (string) $r->userAgent();
        $parsed = static::parseUserAgent($ua);

        $attemptNo = static::where('username', $username)
            ->whereDate('created_at', today())
            ->count() + 1;

        return static::create([
            'username' => $username,
            'guard' => $guard,
            'success' => $success,
            'ip_address' => $r->ip(),
            'user_agent' => mb_substr($ua, 0, 500),
            'device_type' => $parsed['device'],
            'browser' => $parsed['browser'],
            'os' => $parsed['os'],
            'attempt_no' => $attemptNo,
        ]);
    }

    /**
     * Parser sederhana user agent -> device / browser / OS.
     *
     * @return array{device:string, browser:string, os:string, webview:bool}
     */
    public static function parseUserAgent(string $ua): array
    {
        $device = 'desktop';
        if (preg_match('/iPad|Tablet/i', $ua)) {
            $device = 'tablet';
        } elseif (preg_match('/Mobi|Android|iPhone|iPod|BlackBerry|Opera Mini/i', $ua)) {
            $device = 'mobile';
        }

        $browser = 'Lainnya';
        if (preg_match('/Edg\//i', $ua)) {
            $browser = 'Edge';
        } elseif (preg_match('/OPR\/|Opera/i', $ua)) {
            $browser = 'Opera';
        } elseif (preg_match('/Chrome\//i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox\//i', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari\//i', $ua)) {
            $browser = 'Safari';
        }

        $os = 'Lainnya';
        if (preg_match('/Windows NT 10/i', $ua)) {
            $os = 'Windows 10/11';
        } elseif (preg_match('/Windows NT/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS X/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/Android/i', $ua)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            $os = 'iOS';
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
        }

        $webview = static::webview($ua);

        return compact('device', 'browser', 'os', 'webview');
    }
}
