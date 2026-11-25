@extends('layouts.auth')

@section('title', 'Verifikasi Email')

@section('content')
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10 text-danger mb-3"
             style="width: 56px; height: 56px;">
            <i class="bi bi-envelope-check fs-3" aria-hidden="true"></i>
        </div>
        <h1 class="h4 mb-1">Verifikasi Email Anda</h1>
        <p class="text-muted small mb-0">
            Kami telah mengirimkan tautan verifikasi ke <strong>{{ auth()->user()->email }}</strong>.
        </p>
    </div>

    <div class="alert alert-light border small text-muted mb-4">
        Silakan buka kotak masuk (atau folder <em>Spam</em>) pada email Anda dan klik tautan verifikasi untuk mulai mengajukan pembelian, booking test drive, atau booking servis bengkel.
    </div>

    <form method="POST" action="{{ route('verification.send') }}" data-disable-on-submit>
        @csrf
        <button type="submit" class="btn btn-accent w-100 mb-3">
            <i class="bi bi-arrow-repeat"></i> Kirim Ulang Tautan Verifikasi
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="btn btn-link btn-sm text-muted text-decoration-none">
            <i class="bi bi-box-arrow-right"></i> Keluar / Ganti Akun
        </button>
    </form>
@endsection
