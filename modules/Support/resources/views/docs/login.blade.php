<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} API docs · Sign in</title>
    <style>
        :root { color-scheme: light dark; --bg: #f5f5f7; --card: #fff; --text: #1d1d1f; --muted: #6e6e73; --border: #d2d2d7; --accent: #f53003; --error: #c62828; }
        @media (prefers-color-scheme: dark) { :root { --bg: #111; --card: #1c1c1e; --text: #f5f5f7; --muted: #a1a1a6; --border: #3a3a3c; --error: #ef5350; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--text); font: 15px/1.5 system-ui, -apple-system, sans-serif; padding: 16px; }
        form { width: 100%; max-width: 360px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 28px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        p { margin: 0 0 20px; color: var(--muted); }
        label { display: block; font-weight: 600; margin: 14px 0 6px; }
        input { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; background: transparent; color: inherit; font: inherit; }
        button { width: 100%; margin-top: 20px; padding: 11px; border: 0; border-radius: 8px; background: var(--accent); color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .error { color: var(--error); font-size: 13px; margin-top: 6px; }
    </style>
</head>
<body>
<form method="POST" action="{{ route('docs.login.attempt') }}">
    @csrf
    <h1>API documentation</h1>
    <p>Sign in with your system user account.</p>

    <label for="email">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
    @error('email')<div class="error">{{ $message }}</div>@enderror

    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    @error('password')<div class="error">{{ $message }}</div>@enderror

    <button type="submit">Sign in</button>
</form>
</body>
</html>
