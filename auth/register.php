<?php
require_once dirname(__DIR__) . '/includes/auth.php';
if (isLoggedIn()) redirectToDashboard();
$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    if (empty($name) || empty($email) || empty($password))
        $error = 'Name, email and password are required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $error = 'Please enter a valid email address.';
    elseif (strlen($password) < 6)
        $error = 'Password must be at least 6 characters.';
    elseif ($password !== $confirm)
        $error = 'Passwords do not match.';
    else {
        $db = getDB();
        $ee = $db->real_escape_string($email);
        $chk = $db->query("SELECT id FROM users WHERE email='$ee' LIMIT 1");
        if ($chk && $chk->num_rows > 0) {
            $error = 'This email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ne = $db->real_escape_string($name);
            $pe = $db->real_escape_string($phone);
            $he = $db->real_escape_string($hash);
            $db->query("INSERT INTO users (name,email,password,role,phone) VALUES ('$ne','$ee','$he','donor','$pe')");
            $uid = $db->insert_id;
            $db->query("INSERT INTO donor_profiles (user_id,donor_type) VALUES ($uid,'individual')");
            $success = 'Account created successfully!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Create Account - VCF Donation System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
/* ── Reset ─────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    min-height: 100%;
    display: flex;
}

/* ── Strict 50 / 50 ── */
.left, .right {
    width: 50%;
    min-height: 100vh;
}

/* ── LEFT ── (identical gradient/style to login) ─ */
.left {
    background: linear-gradient(155deg, #060e1f 0%, #0b1d44 40%, #1447c0 100%);
    display: flex;
    flex-direction: column;
    padding: 48px 52px;
    position: relative;
    overflow: hidden;
}
.left::before {
    content: '';
    position: absolute;
    width: 420px; height: 420px; border-radius: 50%;
    background: rgba(255,255,255,.04);
    top: -130px; right: -110px;
    pointer-events: none;
}
.left::after {
    content: '';
    position: absolute;
    width: 260px; height: 260px; border-radius: 50%;
    background: rgba(239,68,68,.07);
    bottom: -70px; left: -70px;
    pointer-events: none;
}

.logo {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 52px; position: relative; z-index: 1;
}
.logo-icon {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, #ef4444, #f97316);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem; color: white;
    box-shadow: 0 4px 12px rgba(239,68,68,.4);
    flex-shrink: 0;
}
.logo-name { font-size: .92rem; font-weight: 700; color: white; }

.headline { position: relative; z-index: 1; margin-bottom: 30px; }
.headline h2 {
    font-size: 2rem; font-weight: 800; color: white;
    letter-spacing: -.04em; line-height: 1.18; margin-bottom: 14px;
}
.headline h2 em { font-style: normal; color: #64b5f6; }
.headline p { font-size: .84rem; color: rgba(255,255,255,.52); line-height: 1.75; }

/* Steps */
.steps { position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column; gap: 16px; }
.step { display: flex; align-items: flex-start; gap: 14px; }
.step-num {
    width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
    background: rgba(255,255,255,.12);
    border: 1.5px solid rgba(255,255,255,.22);
    display: flex; align-items: center; justify-content: center;
    font-size: .74rem; font-weight: 800; color: white;
}
.step-body strong {
    display: block; font-size: .81rem; font-weight: 700;
    color: white; margin-bottom: 3px;
}
.step-body span { font-size: .73rem; color: rgba(255,255,255,.42); line-height: 1.55; }

/* Trust pills */
.trust-row {
    position: relative; z-index: 1;
    display: flex; gap: 10px; flex-wrap: wrap;
    margin-top: 24px; padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,.09);
}
.trust-pill {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: .71rem; font-weight: 500; color: rgba(255,255,255,.45);
}
.trust-pill i { color: #4ade80; font-size: .72rem; }

.left-footer {
    position: relative; z-index: 1; margin-top: 22px;
    display: flex; justify-content: space-between;
    font-size: .72rem; color: rgba(255,255,255,.25);
}
.left-footer a { color: rgba(255,255,255,.4); text-decoration: none; transition: color .15s; }
.left-footer a:hover { color: white; }

/* ── RIGHT ── */
.right {
    background: #f4f6fb;
    display: flex; align-items: center; justify-content: center;
    padding: 40px 32px;
    overflow-y: auto;
}

/* Card */
.card {
    background: white;
    border-radius: 20px;
    padding: 36px 36px;
    width: 100%; max-width: 480px;
    box-shadow:
        0 0 0 1px rgba(0,0,0,.05),
        0 4px 6px rgba(0,0,0,.04),
        0 10px 30px rgba(0,0,0,.07);
}

.card-logo {
    width: 64px; height: 64px;
    background: white; border: 2.5px solid #fee2e2; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
    box-shadow: 0 4px 16px rgba(239,68,68,.14);
}
.card-logo-inner {
    width: 52px; height: 52px;
    background: linear-gradient(135deg, #ef4444, #f97316);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; color: white;
}
.card-title { font-size: 1.2rem; font-weight: 800; color: #0f172a; text-align: center; letter-spacing: -.03em; margin-bottom: 6px; }
.card-sub   { font-size: .81rem; color: #94a3b8; text-align: center; margin-bottom: 26px; }

/* Fields */
.field      { margin-bottom: 14px; }
.field-label {
    display: block; font-size: .75rem; font-weight: 600;
    color: #374151; margin-bottom: 6px; letter-spacing: .01em;
}
.field-row  { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; align-items: start; }
.field-control {
    display: flex; align-items: center;
    border: 1.5px solid #e5e7eb; border-radius: 10px;
    background: white; overflow: hidden;
    transition: border-color .18s, box-shadow .18s;
    height: 44px;
}
.field-control:focus-within {
    border-color: #1447c0;
    box-shadow: 0 0 0 3px rgba(20,71,192,.1);
}
.field-prefix {
    padding: 0 12px; color: #9ca3af; font-size: .86rem;
    display: flex; align-items: center;
    flex-shrink: 0; border-right: 1.5px solid #f3f4f6;
    height: 44px; min-width: 42px; justify-content: center;
}
.field-input {
    flex: 1; border: none; outline: none;
    padding: 0 13px; height: 44px;
    font-size: .84rem; color: #111827; background: transparent;
}
.field-input::placeholder { color: #d1d5db; }

.btn-submit {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; height: 46px;
    background: linear-gradient(135deg, #0d2157, #1447c0);
    color: white; border: none; border-radius: 11px;
    font-size: .88rem; font-weight: 700; cursor: pointer;
    box-shadow: 0 4px 16px rgba(20,71,192,.3);
    transition: all .2s; letter-spacing: -.01em;
}
.btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 22px rgba(20,71,192,.42); }

.info-note {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 9px;
    padding: 11px 14px; margin-bottom: 16px;
    display: flex; align-items: flex-start; gap: 9px;
}
.info-note i  { color: #1447c0; flex-shrink: 0; margin-top: 1px; }
.info-note p  { font-size: .73rem; color: #64748b; line-height: 1.55; margin: 0; }

.error-box {
    background: #fef2f2; color: #b91c1c;
    border-left: 3px solid #ef4444;
    padding: 10px 14px; border-radius: 9px;
    font-size: .8rem; display: flex; align-items: center; gap: 8px;
    margin-bottom: 16px;
}
.success-box {
    background: #f0fdf4; border-left: 3px solid #22c55e;
    padding: 16px; border-radius: 10px; margin-bottom: 12px;
}
.success-box .st {
    font-size: .83rem; font-weight: 700; color: #15803d;
    display: flex; align-items: center; gap: 6px; margin-bottom: 12px;
}
.btn-login {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 20px;
    background: linear-gradient(135deg, #0d2157, #1447c0);
    color: white; border-radius: 9px;
    font-size: .8rem; font-weight: 700; text-decoration: none;
    transition: all .18s;
}
.btn-login:hover { transform: translateY(-1px); color: white; }

.card-footer-links {
    text-align: center; margin-top: 20px; padding-top: 18px;
    border-top: 1px solid #f9fafb;
    font-size: .78rem; color: #9ca3af;
}
.card-footer-links a { color: #1447c0; font-weight: 700; text-decoration: none; }
.card-footer-links a:hover { text-decoration: underline; }
.card-footer-links .sep { margin: 0 8px; color: #e5e7eb; }

/* Responsive */
@media (max-width: 900px) {
    body { flex-direction: column; }
    .left, .right { width: 100%; min-height: auto; }
    .left { padding: 32px 24px; }
    .steps { gap: 12px; }
    .right { padding: 28px 18px; }
    .card { padding: 32px 24px; max-width: 100%; }
}
</style>
</head>
<body>

<!-- ═══════════════════════════════════════════════
     LEFT PANEL
═══════════════════════════════════════════════ -->
<div class="left">

    <div class="logo">
        <div class="logo-icon"><i class="bi bi-heart-fill"></i></div>
        <span class="logo-name">VCF Donation System</span>
    </div>

    <div class="headline">
        <h2>Start Making a<br><em>Difference</em><br>Today</h2>
        <p>Create your free account and join donors supporting meaningful causes for education, healthcare, and community development across Vietnam.</p>
    </div>

    <div class="steps">
        <?php foreach ([
            ['Create your account',  'Fill in your basic info - takes under 2 minutes.'],
            ['Browse campaigns',     'Explore verified campaigns for education, healthcare and more.'],
            ['Make a donation',      'Choose an amount and submit your transaction details.'],
            ['Track your impact',    'Receive confirmation and see exactly how funds are used.'],
        ] as $i => [$title, $desc]): ?>
        <div class="step">
            <div class="step-num"><?= $i + 1 ?></div>
            <div class="step-body">
                <strong><?= $title ?></strong>
                <span><?= $desc ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="trust-row">
        <?php foreach (['Free to join', '100% transparent', 'Verified donations', 'Secure & private'] as $t): ?>
        <div class="trust-pill">
            <i class="bi bi-check2-circle"></i><?= $t ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="left-footer">
        <span>© 2026 VinaCapital Foundation</span>
        <a href="<?= SITE_URL ?>">Back to Home</a>
    </div>

</div>

<!-- ═══════════════════════════════════════════════
     RIGHT PANEL
═══════════════════════════════════════════════ -->
<div class="right">
    <div class="card">

        <div class="card-logo">
            <div class="card-logo-inner"><i class="bi bi-person-plus-fill"></i></div>
        </div>
        <div class="card-title">Create your account</div>
        <div class="card-sub">Join VCF and start making a difference</div>

        <?php if ($error): ?>
        <div class="error-box">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="success-box">
            <div class="st"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
            <a href="<?= SITE_URL ?>/auth/login.php" class="btn-login">
                <i class="bi bi-box-arrow-in-right"></i>Go to Sign In
            </a>
        </div>
        <?php else: ?>

        <form method="POST">
            <div class="field">
                <label class="field-label">Full Name <span style="color:#ef4444;">*</span></label>
                <div class="field-control">
                    <span class="field-prefix"><i class="bi bi-person"></i></span>
                    <input type="text" name="name" class="field-input"
                           placeholder="Nguyen Van A"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                           required autofocus>
                </div>
            </div>

            <div class="field">
                <label class="field-label">Email Address <span style="color:#ef4444;">*</span></label>
                <div class="field-control">
                    <span class="field-prefix"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="field-input"
                           placeholder="your@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required>
                </div>
            </div>

            <div class="field">
                <label class="field-label">
                    Phone
                    <span style="font-size:.68rem;color:#9ca3af;margin-left:4px;">(optional)</span>
                </label>
                <div class="field-control">
                    <span class="field-prefix"><i class="bi bi-telephone"></i></span>
                    <input type="text" name="phone" class="field-input"
                           placeholder="0901234567"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label class="field-label">Password <span style="color:#ef4444;">*</span></label>
                <div class="field-control">
                    <span class="field-prefix"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="field-input"
                           placeholder="Minimum 6 characters" required>
                </div>
            </div>

            <div class="field">
                <label class="field-label">Confirm Password <span style="color:#ef4444;">*</span></label>
                <div class="field-control">
                    <span class="field-prefix"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" name="confirm_password" class="field-input"
                           placeholder="Repeat your password" required>
                </div>
            </div>

            <div class="info-note">
                <i class="bi bi-info-circle"></i>
                <p>By creating an account, you agree to VCF's donation policy. Your information is protected and will not be shared without consent.</p>
            </div>

            <button type="submit" class="btn-submit">
                <i class="bi bi-person-check-fill"></i>
                Create Account
            </button>
        </form>

        <?php endif; ?>

        <div class="card-footer-links">
            Already have an account?
            <a href="<?= SITE_URL ?>/auth/login.php" style="margin-left:4px;">Sign In</a>
            <span class="sep">·</span>
            <a href="<?= SITE_URL ?>" style="color:#9ca3af;font-weight:500;">Home</a>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
