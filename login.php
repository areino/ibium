<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — <?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Band Manager', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>

<div class="login-wrap">
    <div class="login-card">

        <div class="login-logo">
            <div class="logo-icon">🎵</div>
            <h1><?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Band Manager', ENT_QUOTES, 'UTF-8') ?></h1>
            <p>Your band's command centre</p>
        </div>

        <?php if (!empty($error) && $error === 'invalid_token'): ?>
            <div class="alert alert-danger" role="alert">
                ⚠️ This login link is invalid or has already been used. Please request a new one.
            </div>
        <?php endif; ?>

        <?php if (!empty($error) && $error === 'invalid_email'): ?>
            <div class="alert alert-danger" role="alert">
                ⚠️ Please enter a valid email address.
            </div>
        <?php endif; ?>

        <?php if (!empty($sent)): ?>
            <!-- Success state -->
            <div class="alert alert-success" role="alert">
                ✅ Check your inbox! We've sent a magic login link to your email address.
            </div>
            <p class="text-muted text-sm mt-3" style="text-align:center;">
                The link expires in 1 hour. If you don't see it, check your spam folder.
            </p>
            <div style="text-align:center; margin-top:20px;">
                <a href="/login" style="font-size:0.85rem; color:var(--muted);">← Back to sign in</a>
            </div>

        <?php else: ?>
            <!-- Default login form -->
            <p class="text-muted text-sm mb-4" style="text-align:center;">
                Enter your email address and we'll send you a magic sign-in link.
            </p>

            <form method="post" action="/login" novalidate>
                <div class="form-group">
                    <label for="email" class="form-label">Email address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="you@example.com"
                        required
                        autofocus
                        autocomplete="email"
                    >
                </div>

                <button type="submit" class="btn btn-primary w-full" style="width:100%;">
                    Send magic link →
                </button>
            </form>

            <p class="text-muted text-xs mt-4" style="text-align:center;">
                No account? Contact your band admin to get added.
            </p>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
