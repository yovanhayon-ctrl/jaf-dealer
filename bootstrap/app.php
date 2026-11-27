<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'role' => EnsureUserHasRole::class,
        ]);

        // Staf yang dinonaktifkan owner langsung keluar.
        $middleware->web(append: [EnsureUserIsActive::class]);

        // Header keamanan (CSP, X-Frame-Options, dll.) untuk semua response, termasuk 404
        // (URL tanpa route tidak melewati grup middleware "web").
        $middleware->append(SecurityHeaders::class);

        // User yang sudah login dan membuka /login atau /register.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->homeRoute() ?? route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tautan verifikasi email yang kedaluwarsa/tidak valid diarahkan ramah ke halaman kirim ulang (bukan 403).
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->routeIs('verification.verify')) {
                if ($request->user()?->hasVerifiedEmail()) {
                    return redirect()->intended($request->user()->homeRoute())
                        ->with('success', 'Email Anda sudah terverifikasi sebelumnya.');
                }

                return redirect()->route('verification.notice')
                    ->with('error', 'Tautan verifikasi sudah kedaluwarsa atau tidak valid. Silakan klik Kirim Ulang untuk mendapatkan tautan baru.');
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
