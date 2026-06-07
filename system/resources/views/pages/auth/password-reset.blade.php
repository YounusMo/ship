<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose new password — Ship Flow</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; max-width: 420px; margin: 4rem auto; padding: 0 1rem; color: #1f2937; }
        h1 { font-size: 1.25rem; margin-bottom: 0.25rem; }
        p.muted { color: #6b7280; font-size: 0.9rem; margin-top: 0; }
        label { display: block; font-size: 0.875rem; margin: 1rem 0 0.25rem; }
        input { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
        button { margin-top: 1rem; padding: 0.6rem 1rem; background: #1f2937; color: #fff; border: 0; border-radius: 6px; font-size: 0.95rem; cursor: pointer; }
        button:hover { background: #111827; }
        .error { color: #b91c1c; font-size: 0.85rem; margin-top: 0.25rem; }
    </style>
</head>
<body>
    <h1>Choose a new password</h1>
    <p class="muted">Minimum 8 characters.</p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <label for="password">New password</label>
        <input id="password" type="password" name="password" required autofocus>
        @error('password') <div class="error">{{ $message }}</div> @enderror

        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required>

        <button type="submit">Update password</button>
    </form>
</body>
</html>
