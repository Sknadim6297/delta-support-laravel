<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Root Admin setup | Delta Support</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; font-family: "Segoe UI", sans-serif; background: #f4f1ea; color: #1d1a16; }
        main { max-width: 32rem; margin: 4rem auto; padding: 2rem; background: #fff; border: 1px solid #e4dccb; border-radius: 16px; }
        h1 { font-size: 1.4rem; margin: 0 0 .5rem; }
        p, label { line-height: 1.5; }
        label { display: block; font-weight: 650; margin: 1rem 0 .35rem; }
        input { width: 100%; box-sizing: border-box; padding: .7rem .8rem; border: 1px solid #cfc4b0; border-radius: 8px; font: inherit; }
        button { margin-top: 1.25rem; width: 100%; padding: .8rem 1rem; border: 0; border-radius: 8px; background: #1d4f3a; color: #fff; font: inherit; font-weight: 700; }
        .note { color: #5c564c; font-size: .92rem; }
        .error { margin-top: .35rem; color: #8d1d18; font-size: .92rem; }
    </style>
</head>
<body>
    <main>
        <h1>Root Admin setup</h1>
        <p class="note">Login ID <strong>ST666666</strong>. This creates one administrator and does not insert a member. Income, withdrawals, transfers, and scheduled posting stay unchanged.</p>

        @if ($errors->any())
            <p class="error" role="alert">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('admin.bootstrap.store', ['gate' => $gate]) }}" autocomplete="off">
            @csrf
            <label for="bootstrap_token">Bootstrap token</label>
            <input id="bootstrap_token" name="bootstrap_token" type="password" autocomplete="off" spellcheck="false" required>

            <label for="email">Administrator email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="off" required>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

            <label for="backup_confirmation">Backup confirmation</label>
            <p class="note">Type <strong>{{ $confirmation }}</strong> only after a restorable backup of this database has been verified.</p>
            <input id="backup_confirmation" name="backup_confirmation" type="text" value="{{ old('backup_confirmation') }}" autocomplete="off" spellcheck="false" required>

            <button type="submit">Create Root Admin</button>
        </form>
    </main>
</body>
</html>
