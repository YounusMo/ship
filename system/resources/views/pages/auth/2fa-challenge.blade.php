<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Two-factor — Ship Flow</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; max-width: 380px; margin: 4rem auto; padding: 0 1rem; color: #1f2937; }
        h1 { font-size: 1.25rem; }
        p.muted { color: #6b7280; font-size: 0.9rem; }
        label { display: block; font-size: 0.875rem; margin: 1rem 0 0.25rem; }
        input { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; box-sizing: border-box; font-family: ui-monospace, monospace; }
        button { margin-top: 1rem; padding: 0.6rem 1rem; background: #1f2937; color: #fff; border: 0; border-radius: 6px; font-size: 0.95rem; cursor: pointer; }
        .error { color: #b91c1c; font-size: 0.85rem; margin-top: 0.25rem; }
    </style>
</head>
<body>
    <h1>Two-factor verification</h1>
    <p class="muted">Open your authenticator app and enter the 6-digit code for ShipFlow.</p>

    <form method="POST" action="{{ route('two-factor.challenge.verify') }}">
        @csrf
        <label for="code">Code</label>
        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus>
        @error('code') <div class="error">{{ $message }}</div> @enderror
        <button type="submit">Verify</button>
    </form>
</body>
</html>
