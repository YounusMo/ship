@php
    use App\Http\Controllers\langController;
    $lang = new langController();
@endphp
@extends('layout')
@section('content')
@if (Auth::check())
    <script>window.location = '/'</script>
@endif

<style>
    .content { margin: 0 !important; padding: 0 !important; }
    .main    { margin: 0 !important; padding: 0 !important; }
    body     { background: var(--color-surface-1); }
</style>

<div class="login-shell">
    {{-- ============ Brand panel (LTR: left, RTL: right) ============ --}}
    <aside class="login-brand-panel">
        <div>
            <div class="d-flex align-items-center gap-3 mb-4">
                <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,var(--color-gold-600),var(--color-gold-500));display:flex;align-items:center;justify-content:center;color:var(--color-navy-900);font-weight:800;font-size:20px;">M</div>
                <div style="font-size:14px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--color-gold-500);">MATAZ TRADING</div>
            </div>
            <h1 class="brand-headline">
                {{ $lang->write('Trusted shipping & treasury operations.', $selected_lang) }}
            </h1>
            <p class="brand-sub">
                {{ $lang->write('Multi-branch, multi-currency. Built for serious trading.', $selected_lang) }}
            </p>
        </div>

        <div class="brand-foot">
            <div><strong>{{ date('Y') }}</strong> &middot; {{ $lang->write('Internal staff & client portal', $selected_lang) }}</div>
            <div>Tripoli &middot; Misrata &middot; Benghazi &middot; Guangzhou</div>
        </div>
    </aside>

    {{-- ============ Form panel ============ --}}
    <main class="login-form-panel">
        <div class="login-form-card">
            <form action="{{ url('auth/user/login') }}" method="post" autocomplete="on">
                @csrf
                <div class="login-logo">M</div>
                <h2>{{ $lang->write('Sign in', $selected_lang) }}</h2>
                <p class="login-tagline">{{ $lang->write('Use your email or code to access your account', $selected_lang) }}</p>

                @if (session()->has('err'))
                    <div class="alert alert-danger py-2 mb-3" style="font-size:13px">
                        {{ session()->get('err') }}
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label">{{ $lang->write('Email or code', $selected_lang) }}</label>
                    <div class="search-input">
                        <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <input type="text" class="form-control" name="email" autofocus required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">{{ $lang->write('Password', $selected_lang) }}</label>
                    <div class="search-input">
                        <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg">
                    {{ $lang->write('Sign in', $selected_lang) }}
                </button>

                <div class="divider-labeled mt-5">{{ $lang->write('Need help?', $selected_lang) }}</div>
                <p class="text-center text-muted" style="font-size:13px;line-height:1.5;">
                    {{ $lang->write('Contact your branch administrator for credentials or a password reset.', $selected_lang) }}
                </p>
            </form>
        </div>
    </main>
</div>
@endsection
