<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password — Ship Flow</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; max-width: 420px; margin: 4rem auto; padding: 0 1rem; color: #1f2937; }
        h1 { font-size: 1.25rem; margin-bottom: 0.25rem; }
        p.muted { color: #6b7280; font-size: 0.9rem; margin-top: 0; }
        label { display: block; font-size: 0.875rem; margin: 1rem 0 0.25rem; }
        input[type=email] { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
        button { margin-top: 1rem; padding: 0.6rem 1rem; background: #1f2937; color: #fff; border: 0; border-radius: 6px; font-size: 0.95rem; cursor: pointer; }
        button:hover { background: #111827; }
        .alert { padding: 0.75rem 1rem; background: #f3f4f6; border-radius: 6px; font-size: 0.875rem; margin-bottom: 1rem; }
        .error { color: #b91c1c; font-size: 0.85rem; margin-top: 0.25rem; }
        a { color: #2563eb; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>
    <h1>Reset password</h1>
    <p class="muted">Enter your staff email address. If it exists, a reset link will be sent.</p>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <button type="submit">Send reset link</button>
    </form>

    <p style="margin-top:1.5rem"><a href="/login">Back to sign in</a></p>
</body>
</html>
