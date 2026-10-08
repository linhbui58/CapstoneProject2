<?php
require_once dirname(__DIR__) . '/includes/auth.php';
if (isLoggedIn()) redirectToDashboard();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $result = loginUser($email, $password);
        if ($result['success']) {
            if (isset($_SESSION['redirect_after_login'])) {
                $r = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
                redirect($r);
            } else { redirectToDashboard(); }
        } else { $error = $result['message']; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Sign In - VCF Donation System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
/* ── Reset ─────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    height: 100%;
    display: flex;
    overflow: hidden;
}

/* ── Layout: strict 50 / 50 ────────────────────── */
.left, .right {
    width: 50%;
    height: 100vh;
    overflow-y: auto;
}

/* ── LEFT ───────────────────────────────────────── */
.left {
    background: linear-gradient(155deg, #060e1f 0%, #0b1d44 40%, #1447c0 100%);
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    padding: 48px 52px;
    position: relative;
    overflow: hidden;
    gap: 28px;
}

/* Decorative circles */
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

/* Logo */
.logo {
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    z-index: 1;
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
.logo-name {
    font-size: .92rem;
    font-weight: 700;
    color: white;
    letter-spacing: -.01em;
}

/* Headline */
.headline {
    position: relative;
    z-index: 1;
}
.headline h2 {
    font-size: 2.1rem;
    font-weight: 800;
    color: white;
    letter-spacing: -.04em;
    line-height: 1.18;
    margin-bottom: 12px;
}
.headline h2 em {
    font-style: normal;
    color: #64b5f6;
}
.headline p {
    font-size: .84rem;
    color: rgba(255,255,255,.52);
    line-height: 1.7;
}

/* Feature grid */
.feat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    position: relative;
    z-index: 1;
    width: 100%;
}
.feat-card {
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 12px;
    padding: 16px;
    transition: background .2s;
}
.feat-card:hover { background: rgba(255,255,255,.13); }
.feat-card-top {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 7px;
}
.feat-icon {
    width: 28px; height: 28px;
    border-radius: 7px;
    background: rgba(255,255,255,.13);
    display: flex; align-items: center; justify-content: center;
    font-size: .82rem;
    color: rgba(255,255,255,.85);
    flex-shrink: 0;
}
.feat-card-top strong {
    font-size: .79rem;
    font-weight: 700;
    color: white;
    line-height: 1.3;
}
.feat-card p {
    font-size: .71rem;
    color: rgba(255,255,255,.42);
    line-height: 1.55;
    margin: 0;
}

/* Left footer */
.left-footer {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .72rem;
    color: rgba(255,255,255,.25);
    width: 100%;
    padding-top: 16px;
    border-top: 1px solid rgba(255,255,255,.08);
}
.left-footer a {
    color: rgba(255,255,255,.4);
    text-decoration: none;
    transition: color .15s;
}
.left-footer a:hover { color: white; }

/* ── RIGHT ──────────────────────────────────────── */
.right {
    background: #f4f6fb;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 32px;
}

/* Form card */
.card {
    background: white;
    border-radius: 18px;
    padding: 32px 32px;
    width: 100%;
    max-width: 380px;
    box-shadow:
        0 0 0 1px rgba(0,0,0,.05),
        0 4px 6px rgba(0,0,0,.04),
        0 10px 30px rgba(0,0,0,.07);
}

/* Card header */
.card-logo {
    width: 54px; height: 54px;
    background: white;
    border: 2px solid #fee2e2;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px;
    box-shadow: 0 4px 14px rgba(239,68,68,.13);
}
.card-logo-inner {
    width: 44px; height: 44px;
    background: linear-gradient(135deg, #ef4444, #f97316);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem;
    color: white;
}
.card-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: #0f172a;
    text-align: center;
    letter-spacing: -.03em;
    margin-bottom: 4px;
}
.card-sub {
    font-size: .78rem;
    color: #94a3b8;
    text-align: center;
    margin-bottom: 22px;
}

/* Field */
.field { margin-bottom: 16px; }
.field-label {
    display: block;
    font-size: .75rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
    letter-spacing: .01em;
}
.field-control {
    display: flex;
    align-items: center;
    border: 1.5px solid #e5e7eb;
    border-radius: 9px;
    background: white;
    overflow: hidden;
    transition: border-color .18s, box-shadow .18s;
}
.field-control:focus-within {
    border-color: #1447c0;
    box-shadow: 0 0 0 3px rgba(20,71,192,.1);
}
.field-prefix {
    padding: 0 11px;
    color: #9ca3af;
    font-size: .84rem;
    display: flex; align-items: center;
    flex-shrink: 0;
    border-right: 1.5px solid #f3f4f6;
    height: 40px;
}
.field-input {
    flex: 1;
    border: none;
    outline: none;
    padding: 0 11px;
    height: 40px;
    font-size: .83rem;
    color: #111827;
    background: transparent;
}
.field-input::placeholder { color: #d1d5db; }
.field-suffix {
    padding: 0 11px;
    color: #9ca3af;
    font-size: .84rem;
    background: transparent;
    border: none;
    cursor: pointer;
    height: 40px;
    display: flex; align-items: center;
    transition: color .15s;
}
.field-suffix:hover { color: #374151; }

/* Submit */
.btn-submit {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    height: 46px;
    background: linear-gradient(135deg, #0d2157, #1447c0);
    color: white;
    border: none;
    border-radius: 11px;
    font-size: .88rem;
    font-weight: 700;
    cursor: pointer;
    margin-top: 4px;
    box-shadow: 0 4px 16px rgba(20,71,192,.3);
    transition: all .2s;
    letter-spacing: -.01em;
}
.btn-submit:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 22px rgba(20,71,192,.42);
}

/* Divider */
.divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 22px 0 16px;
}
.divider-line { flex: 1; height: 1px; background: #f3f4f6; }
.divider-label {
    font-size: .67rem;
    font-weight: 700;
    color: #d1d5db;
    letter-spacing: .07em;
    white-space: nowrap;
}

/* Demo buttons */
.demo-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px;
}
.demo-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 10px 12px;
    border-radius: 10px;
    font-size: .77rem;
    font-weight: 700;
    cursor: pointer;
    border: 1.5px solid;
    transition: all .15s;
}
.demo-btn:hover { transform: translateY(-1px); filter: brightness(.97); }
.demo-mgr  { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
.demo-fin  { background: #fffbeb; color: #854d0e; border-color: #fde68a; }
.demo-camp { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.demo-don  { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }

/* Error */
.error-box {
    background: #fef2f2;
    color: #b91c1c;
    border-left: 3px solid #ef4444;
    padding: 10px 14px;
    border-radius: 9px;
    font-size: .8rem;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}

/* Card footer */
.card-footer-links {
    text-align: center;
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #f9fafb;
    font-size: .78rem;
    color: #9ca3af;
}
.card-footer-links a { color: #1447c0; font-weight: 700; text-decoration: none; }
.card-footer-links a:hover { text-decoration: underline; }
.card-footer-links .sep { margin: 0 8px; color: #e5e7eb; }

/* Password hint */
.pw-hint {
    text-align: center;
    font-size: .71rem;
    color: #d1d5db;
    margin-top: 12px;
}
.pw-hint code {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    color: #1447c0;
    padding: 1px 7px;
    border-radius: 5px;
    font-size: .69rem;
}

/* Responsive */
@media (max-width: 900px) {
    body { flex-direction: column; overflow: auto; }
    .left, .right { width: 100%; height: auto; overflow: visible; }
    .left { min-height: auto; }
    .feat-grid { display: none; }
    .right { padding: 32px 20px; }
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
        <h2>Empowering<br><em>Communities</em><br>Through Giving</h2>
        <p>Join VinaCapital Foundation's platform to support meaningful campaigns for education, healthcare, and community development across Vietnam.</p>
    </div>

    <div class="feat-grid">
        <?php
        $features = [
            ['bi-shield-check-fill',  'Verified Donations',   'Every transaction reviewed by Finance Staff before recording'],
            ['bi-search',             'Full Transparency',     'Track exactly how every VND is allocated and used'],
            ['bi-megaphone-fill',     'Campaign Management',   'Real campaigns with clear goals and transparent progress'],
            ['bi-bar-chart-line-fill','Live Analytics',        'Managers monitor fundraising performance in real time'],
        ];
        foreach ($features as [$icon, $title, $desc]):
        ?>
        <div class="feat-card">
            <div class="feat-card-top">
                <div class="feat-icon"><i class="bi <?= $icon ?>"></i></div>
                <strong><?= $title ?></strong>
            </div>
            <p><?= $desc ?></p>
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
            <div class="card-logo-inner"><i class="bi bi-heart-fill"></i></div>
        </div>
        <div class="card-title">Welcome back</div>
        <div class="card-sub">Sign in to your VCF account</div>

        <?php if ($error): ?>
        <div class="error-box">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label class="field-label">Email Address</label>
                <div class="field-control">
                    <span class="field-prefix"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="field-input"
                           placeholder="your@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required autofocus>
                </div>
            </div>

            <div class="field">
                <label class="field-label">Password</label>
                <div class="field-control">
                    <span class="field-prefix"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="pw" class="field-input"
                           placeholder="••••••••" required>
                    <button type="button" class="field-suffix" onclick="togglePw()">
                        <i class="bi bi-eye-slash" id="eyeIco"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="bi bi-box-arrow-in-right"></i>
                Sign In
            </button>
        </form>

        <div class="divider">
            <div class="divider-line"></div>
            <span class="divider-label">DEMO ACCOUNTS</span>
            <div class="divider-line"></div>
        </div>

        <div class="demo-grid">
            <button class="demo-btn demo-mgr"  onclick="fill('manager@vcf.org')">
                <i class="bi bi-shield-fill"></i> Manager
            </button>
            <button class="demo-btn demo-fin"  onclick="fill('finance@vcf.org')">
                <i class="bi bi-cash-coin"></i> Finance Staff
            </button>
            <button class="demo-btn demo-camp" onclick="fill('campaign@vcf.org')">
                <i class="bi bi-megaphone-fill"></i> Campaign Staff
            </button>
            <button class="demo-btn demo-don"  onclick="fill('donor@vcf.org')">
                <i class="bi bi-heart-fill"></i> Donor
            </button>
        </div>

        <p class="pw-hint">Password for all accounts: <code>password</code></p>

        <div class="card-footer-links">
            New to VCF?
            <a href="<?= SITE_URL ?>/auth/register.php" style="margin-left:4px;">Create account</a>
            <span class="sep">·</span>
            <a href="<?= SITE_URL ?>" style="color:#9ca3af;font-weight:500;">Home</a>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePw() {
    const i = document.getElementById('pw');
    const e = document.getElementById('eyeIco');
    i.type = i.type === 'password' ? 'text' : 'password';
    e.className = 'bi ' + (i.type === 'password' ? 'bi-eye-slash' : 'bi-eye');
}
function fill(email) {
    document.querySelector('[name=email]').value = email;
    document.getElementById('pw').value = 'password';
}
</script>
</body>
</html>
