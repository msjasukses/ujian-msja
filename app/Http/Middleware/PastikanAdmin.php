<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Menu yang hanya untuk admin/operator, mis. Log Login dan Pengguna. */
class PastikanAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check()) {
            return $next($request);
        }

        if (Auth::guard('guru')->check()) {
            abort(403, 'Menu ini hanya dapat diakses admin.');
        }

        return redirect()->route('login');
    }
}
