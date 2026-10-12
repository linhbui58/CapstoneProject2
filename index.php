<?php
// Bật hiển thị lỗi để dễ sửa
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once __DIR__ . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/auth.php';
    redirectToDashboard();
}
$db = getDB();
$campaigns  = $db->query("SELECT c.*, DATEDIFF(c.end_date,CURDATE()) as days_left, COUNT(d.id) as donor_count FROM campaigns c LEFT JOIN donations d ON d.campaign_id=c.id AND d.status='verified' WHERE c.status='active' GROUP BY c.id ORDER BY c.raised_amount DESC LIMIT 3");
$t_raised   = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM donations WHERE status='verified'")->fetch_assoc()['t'];
$t_donors   = $db->query("SELECT COUNT(DISTINCT donor_id) as t FROM donations WHERE status='verified'")->fetch_assoc()['t'];
$t_camps    = $db->query("SELECT COUNT(*) as t FROM campaigns WHERE status='active'")->fetch_assoc()['t'];
$t_verif    = $db->query("SELECT COUNT(*) as t FROM transactions WHERE verification_status='verified'")->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>VCF — Donation &amp; Fundraising Management System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;background:#fff;}
a{text-decoration:none;color:inherit;}
.w{max-width:1200px;margin:0 auto;padding:0 40px;}

/* ── NAVBAR ── */
.nav{
    background:#fff;
    border-bottom:1px solid #f1f5f9;
    position:sticky;top:0;z-index:100;
    box-shadow:0 1px 16px rgba(0,0,0,.05);
}
.nav-in{
    display:flex;align-items:center;
    justify-content:space-between;
    height:64px;
    max-width:1200px;
    margin:0 auto;
    padding:0 40px;
}
.nav-logo{display:flex;align-items:center;gap:11px;flex-shrink:0;}
.nav-logo-ico{
    width:36px;height:36px;
    background:linear-gradient(135deg,#ef4444,#f97316);
    border-radius:10px;display:flex;align-items:center;justify-content:center;
    font-size:.88rem;color:#fff;box-shadow:0 3px 12px rgba(239,68,68,.35);
    flex-shrink:0;
}
.nav-logo span{font-weight:700;font-size:.94rem;color:#0f172a;letter-spacing:-.02em;white-space:nowrap;}
.nav-links{
    display:flex;align-items:center;gap:4px;
    position:absolute;left:50%;transform:translateX(-50%);
}
.nav-links a{font-size:.85rem;font-weight:500;color:#475569;padding:8px 16px;border-radius:8px;transition:all .15s;white-space:nowrap;}
.nav-links a:hover{color:#1447c0;background:#eff6ff;}
.nav-actions{display:flex;align-items:center;gap:10px;flex-shrink:0;}
.btn-in{color:#1447c0;font-size:.83rem;font-weight:600;padding:8px 18px;border:1.5px solid #dbeafe;border-radius:9px;background:#eff6ff;transition:all .15s;white-space:nowrap;}
.btn-in:hover{background:#dbeafe;color:#0d2157;}
.btn-dn{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-size:.83rem;font-weight:700;padding:9px 20px;border-radius:9px;box-shadow:0 3px 12px rgba(239,68,68,.3);transition:all .18s;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;}
.btn-dn:hover{transform:translateY(-1px);box-shadow:0 5px 18px rgba(239,68,68,.42);color:#fff;}

/* ── HERO ── */
.hero{
    background:
        radial-gradient(ellipse at 75% 35%, rgba(59,130,246,.28) 0%, transparent 52%),
        radial-gradient(ellipse at 12% 65%, rgba(99,102,241,.2) 0%, transparent 48%),
        linear-gradient(160deg,#060e1f 0%,#0a1833 45%,#0d2157 100%);
    min-height:560px;
    padding:90px 0 100px;
    position:relative;overflow:hidden;
}
.hero::before{content:'';position:absolute;width:560px;height:560px;border-radius:50%;background:rgba(59,130,246,.07);top:-160px;right:-100px;pointer-events:none;}
.hero::after{content:'';position:absolute;width:340px;height:340px;border-radius:50%;background:rgba(99,102,241,.07);bottom:-90px;left:6%;pointer-events:none;}
.orb3{position:absolute;width:240px;height:240px;border-radius:50%;background:rgba(239,68,68,.05);top:18%;right:30%;pointer-events:none;}

.hero-grid{display:grid;grid-template-columns:1fr 400px;gap:64px;align-items:center;position:relative;z-index:1;}

/* LEFT */
.hero-badge{display:inline-flex;align-items:center;gap:7px;background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.25);color:#fbbf24;padding:6px 15px;border-radius:20px;font-size:.71rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;margin-bottom:22px;}
.hero-title{font-size:3.2rem;font-weight:800;color:#fff;letter-spacing:-.04em;line-height:1.12;margin-bottom:18px;}
.hero-title .hl{background:linear-gradient(135deg,#f97316,#fbbf24);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.hero-sub{color:rgba(255,255,255,.6);font-size:.96rem;line-height:1.78;margin-bottom:32px;max-width:420px;}
.hero-btns{display:flex;gap:13px;flex-wrap:wrap;}
.btn-hp{display:inline-flex;align-items:center;gap:8px;padding:13px 26px;border-radius:10px;font-weight:700;font-size:.88rem;transition:all .2s;letter-spacing:-.01em;}
.btn-hp.red{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;box-shadow:0 4px 18px rgba(239,68,68,.42);}
.btn-hp.red:hover{transform:translateY(-2px);box-shadow:0 7px 24px rgba(239,68,68,.52);color:#fff;}
.btn-hp.ghost{background:rgba(255,255,255,.1);backdrop-filter:blur(8px);border:1.5px solid rgba(255,255,255,.2);color:rgba(255,255,255,.85);}
.btn-hp.ghost:hover{background:rgba(255,255,255,.18);color:#fff;}

/* RIGHT — glassmorphism card */
.glass-card{
    background:rgba(255,255,255,.07);
    backdrop-filter:blur(24px);
    -webkit-backdrop-filter:blur(24px);
    border:1px solid rgba(255,255,255,.13);
    border-radius:22px;
    padding:30px 32px;
}
.glass-stats{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;margin-bottom:24px;}
.gs-item{text-align:center;padding:0 10px;}
.gs-item+.gs-item{border-left:1px solid rgba(255,255,255,.1);}
.gs-num{display:block;font-size:1.65rem;font-weight:800;color:#fff;letter-spacing:-.03em;line-height:1.1;}
.gs-lbl{display:block;font-size:.63rem;color:rgba(255,255,255,.38);text-transform:uppercase;letter-spacing:.09em;margin-top:4px;font-weight:600;}
.glass-div{height:1px;background:rgba(255,255,255,.09);margin:0 0 20px;}
.glass-title{font-size:.63rem;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.1em;font-weight:700;margin-bottom:14px;}
.step-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:11px;}
.step-left{display:flex;align-items:center;gap:10px;}
.step-n{width:21px;height:21px;border-radius:50%;background:rgba(255,255,255,.11);border:1px solid rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:.63rem;font-weight:800;color:rgba(255,255,255,.7);flex-shrink:0;}
.step-t{font-size:.78rem;color:rgba(255,255,255,.62);line-height:1.4;}
.step-ico{font-size:.88rem;color:rgba(255,255,255,.28);}
.glass-pills{display:flex;gap:8px;margin-top:18px;flex-wrap:wrap;}
.gpill{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:.69rem;font-weight:600;}
.gpill.v{background:rgba(251,191,36,.14);color:#fbbf24;border:1px solid rgba(251,191,36,.25);}
.gpill.t{background:rgba(74,222,128,.1);color:#4ade80;border:1px solid rgba(74,222,128,.2);}
.gpill.s{background:rgba(147,197,253,.1);color:#93c5fd;border:1px solid rgba(147,197,253,.18);}

/* ── STATS BAR ── */
.stats-bar{background:#fff;border-bottom:1px solid #f1f5f9;box-shadow:0 2px 12px rgba(0,0,0,.04);}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);}
.sbar{
    display:flex;align-items:center;gap:16px;
    padding:26px 32px;border-right:1px solid #f1f5f9;
    transition:background .15s;
}
.sbar:hover{background:#fafbff;}
.sbar:last-child{border-right:none;}
.sbar-icon{
    width:50px;height:50px;border-radius:14px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.2rem;flex-shrink:0;
}
.sbar-num{font-size:1.45rem;font-weight:800;letter-spacing:-.03em;line-height:1.1;margin-bottom:3px;}
.sbar-lbl{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;}

/* ── FEATURES ── */
.feat-sec{background:#f8fafc;padding:64px 0;}
.sec-hdr{text-align:center;margin-bottom:44px;}
.sec-tag{display:inline-block;background:#eff6ff;color:#1447c0;border:1px solid #dbeafe;padding:4px 14px;border-radius:20px;font-size:.7rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:10px;}
.sec-title{font-size:1.75rem;font-weight:800;color:#0f172a;letter-spacing:-.03em;margin-bottom:8px;}
.sec-sub{color:#64748b;font-size:.88rem;}
.feat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;}
.feat-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:22px 18px;text-align:center;transition:all .2s;}
.feat-card:hover{box-shadow:0 8px 28px rgba(0,0,0,.08);transform:translateY(-3px);border-color:#dbeafe;}
.feat-icon{width:48px;height:48px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;margin:0 auto 13px;}
.feat-t{font-size:.85rem;font-weight:700;color:#0f172a;margin-bottom:7px;}
.feat-d{font-size:.76rem;color:#64748b;line-height:1.65;}

/* ── CAMPAIGNS ── */
.camp-sec{background:#fff;padding:64px 0;}
.camps-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;margin-top:38px;}
.cc{background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;transition:all .25s;display:flex;flex-direction:column;}
.cc:hover{box-shadow:0 12px 36px rgba(0,0,0,.1);transform:translateY(-4px);border-color:#bfdbfe;}
.cc-img{height:170px;position:relative;overflow:hidden;background:#dbeafe;}
.cc-img img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s;}
.cc:hover .cc-img img{transform:scale(1.05);}
.cc-img-ov{position:absolute;inset:0;background:linear-gradient(180deg,transparent 30%,rgba(0,0,0,.5));}
.cc-badge{position:absolute;top:11px;left:11px;z-index:2;background:rgba(22,163,74,.88);backdrop-filter:blur(4px);color:#fff;padding:3px 10px;border-radius:20px;font-size:.64rem;font-weight:700;}
.cc-days{position:absolute;top:11px;right:11px;z-index:2;background:rgba(0,0,0,.42);backdrop-filter:blur(4px);color:rgba(255,255,255,.85);padding:3px 10px;border-radius:20px;font-size:.64rem;font-weight:600;}
.cc-days.urgent{background:rgba(239,68,68,.82);color:#fff;}
.cc-body{padding:17px 19px;flex:1;display:flex;flex-direction:column;}
.cc-meta{font-size:.7rem;color:#94a3b8;margin-bottom:6px;display:flex;align-items:center;gap:4px;}
.cc-title{font-size:.9rem;font-weight:700;color:#0f172a;margin-bottom:7px;line-height:1.4;}
.cc-desc{font-size:.75rem;color:#64748b;flex:1;margin-bottom:12px;line-height:1.62;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.prog-hdr{display:flex;justify-content:space-between;margin-bottom:5px;}
.prog-raised{font-size:.71rem;font-weight:600;color:#334155;}
.prog-pct{font-size:.76rem;font-weight:800;color:#1447c0;}
.prog-bar{height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden;margin-bottom:4px;}
.prog-fill{height:100%;background:linear-gradient(90deg,#3b82f6,#1447c0);border-radius:3px;}
.prog-fill.done{background:linear-gradient(90deg,#22c55e,#16a34a);}
.prog-goal{font-size:.67rem;color:#94a3b8;}
.cc-btn{display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 14px;margin-top:12px;background:#eff6ff;border:1.5px solid #dbeafe;color:#1447c0;border-radius:9px;font-size:.78rem;font-weight:700;transition:all .18s;}
.cc-btn:hover{background:#dbeafe;border-color:#93c5fd;color:#0d2157;}

/* ── HOW IT WORKS ── */
.how-sec{background:linear-gradient(135deg,#eff6ff 0%,#f0f9ff 100%);padding:64px 0;border-top:1px solid #e0f2fe;border-bottom:1px solid #e0f2fe;}
.how-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-top:38px;}
.how-card{background:#fff;border:1px solid #e0f2fe;border-radius:14px;padding:24px 18px;text-align:center;}
.how-num{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#1447c0,#0d2157);color:#fff;font-size:.88rem;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 13px;box-shadow:0 4px 12px rgba(20,71,192,.3);}
.how-ico{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin:0 auto 11px;}
.how-t{font-size:.85rem;font-weight:700;color:#0f172a;margin-bottom:6px;}
.how-d{font-size:.75rem;color:#64748b;line-height:1.65;}

/* ── CTA ── */
.cta-sec{background:linear-gradient(135deg,#060e1f 0%,#0d2157 55%,#1447c0 100%);padding:68px 0;position:relative;overflow:hidden;}
.cta-sec::before{content:'';position:absolute;width:350px;height:350px;border-radius:50%;background:rgba(255,255,255,.04);top:-100px;right:-60px;}
.cta-in{text-align:center;max-width:560px;margin:0 auto;position:relative;z-index:1;}
.cta-title{font-size:1.85rem;font-weight:800;color:#fff;letter-spacing:-.03em;margin-bottom:10px;}
.cta-sub{color:rgba(255,255,255,.58);font-size:.9rem;margin-bottom:28px;line-height:1.72;}
.cta-btns{display:flex;gap:13px;justify-content:center;flex-wrap:wrap;}

/* ── FOOTER ── */
.footer{background:#0a1628;padding:56px 0 0;}
.foot-top{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:48px;padding-bottom:48px;border-bottom:1px solid rgba(255,255,255,.07);}
.foot-brand{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
.foot-logo-i{width:34px;height:34px;background:linear-gradient(135deg,#ef4444,#f97316);border-radius:9px;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 10px rgba(239,68,68,.35);}
.foot-brand-name{font-weight:700;font-size:.9rem;color:#fff;}
.foot-about{font-size:.79rem;color:rgba(255,255,255,.4);line-height:1.75;max-width:270px;margin-bottom:18px;}
.foot-socials{display:flex;gap:8px;}
.social-btn{width:33px;height:33px;border-radius:8px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:.83rem;transition:all .18s;}
.social-btn:hover{background:rgba(255,255,255,.13);color:#fff;}
.foot-col-title{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.28);margin-bottom:14px;}
.foot-link{display:block;font-size:.79rem;color:rgba(255,255,255,.42);margin-bottom:9px;transition:color .14s;}
.foot-link:hover{color:#fff;}
.foot-link i{font-size:.62rem;margin-right:5px;}
.foot-contact-item{display:flex;align-items:flex-start;gap:9px;margin-bottom:11px;}
.foot-contact-item i{color:rgba(239,68,68,.7);font-size:.78rem;flex-shrink:0;margin-top:2px;}
.foot-contact-item span{font-size:.76rem;color:rgba(255,255,255,.4);line-height:1.55;}
.foot-nl{margin-top:16px;}
.foot-nl-label{font-size:.72rem;font-weight:600;color:rgba(255,255,255,.45);margin-bottom:7px;}
.foot-nl-row{display:flex;border:1px solid rgba(255,255,255,.12);border-radius:9px;overflow:hidden;background:rgba(255,255,255,.05);}
.foot-nl-row input{flex:1;border:none;background:transparent;padding:8px 11px;font-size:.76rem;color:#fff;outline:none;}
.foot-nl-row input::placeholder{color:rgba(255,255,255,.3);}
.foot-nl-row button{padding:8px 12px;background:linear-gradient(135deg,#ef4444,#dc2626);border:none;color:#fff;font-size:.74rem;font-weight:700;cursor:pointer;transition:all .15s;}
.foot-nl-row button:hover{background:#dc2626;}
/* stats row in footer */
.foot-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:0;padding:20px 0;border-bottom:1px solid rgba(255,255,255,.06);}
.fstat{text-align:center;padding:4px 0;border-right:1px solid rgba(255,255,255,.06);}
.fstat:last-child{border-right:none;}
.fstat-n{font-size:1.3rem;font-weight:800;letter-spacing:-.02em;display:block;}
.fstat-l{font-size:.64rem;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.07em;font-weight:600;margin-top:2px;display:block;}
.foot-bottom{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:16px 0;}
.foot-copy{font-size:.7rem;color:rgba(255,255,255,.2);}
.foot-bottom-links{display:flex;align-items:center;gap:14px;}
.foot-bottom-links a{font-size:.7rem;color:rgba(255,255,255,.22);transition:color .14s;}
.foot-bottom-links a:hover{color:rgba(255,255,255,.6);}

/* ── RESPONSIVE ── */
@media(max-width:1024px){.hero-grid{grid-template-columns:1fr;gap:32px;}.glass-card{display:none;}.feat-grid,.how-grid{grid-template-columns:1fr 1fr;}.camps-grid{grid-template-columns:1fr 1fr;}.foot-top{grid-template-columns:1fr 1fr;gap:28px;}.stats-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:640px){.camps-grid,.feat-grid,.how-grid{grid-template-columns:1fr;}.hero-title{font-size:2rem;}.w{padding:0 16px;}.nav-links{display:none;}.stats-grid{grid-template-columns:1fr 1fr;}}
</style>
</head>
<body>

<!-- ═══ NAVBAR ══════════════════════════════════════ -->
<nav style="background:#fff;border-bottom:1px solid #f1f5f9;position:sticky;top:0;z-index:100;box-shadow:0 1px 16px rgba(0,0,0,.05);">
    <div style="max-width:1200px;margin:0 auto;padding:0 40px;display:grid;grid-template-columns:1fr auto 1fr;align-items:center;height:64px;">

        <!-- LEFT: Logo -->
        <a href="<?= SITE_URL ?>" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
            <div style="width:36px;height:36px;background:linear-gradient(135deg,#ef4444,#f97316);border-radius:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 12px rgba(239,68,68,.35);flex-shrink:0;">
                <i class="bi bi-heart-fill" style="color:white;font-size:.88rem;"></i>
            </div>
            <span style="font-weight:700;font-size:.94rem;color:#0f172a;letter-spacing:-.02em;white-space:nowrap;">VCF Donation System</span>
        </a>

        <!-- CENTER: Links -->
        <div style="display:flex;align-items:center;gap:2px;">
            <a href="#campaigns" style="font-size:.85rem;font-weight:500;color:#475569;padding:8px 16px;border-radius:8px;transition:all .15s;white-space:nowrap;text-decoration:none;" onmouseover="this.style.color='#1447c0';this.style.background='#eff6ff'" onmouseout="this.style.color='#475569';this.style.background='transparent'">Campaigns</a>
            <a href="#how"       style="font-size:.85rem;font-weight:500;color:#475569;padding:8px 16px;border-radius:8px;transition:all .15s;white-space:nowrap;text-decoration:none;" onmouseover="this.style.color='#1447c0';this.style.background='#eff6ff'" onmouseout="this.style.color='#475569';this.style.background='transparent'">How it Works</a>
            <a href="#impact"    style="font-size:.85rem;font-weight:500;color:#475569;padding:8px 16px;border-radius:8px;transition:all .15s;white-space:nowrap;text-decoration:none;" onmouseover="this.style.color='#1447c0';this.style.background='#eff6ff'" onmouseout="this.style.color='#475569';this.style.background='transparent'">Our Impact</a>
        </div>

        <!-- RIGHT: Buttons -->
        <div style="display:flex;align-items:center;gap:9px;justify-content:flex-end;">
            <a href="<?= SITE_URL ?>/auth/login.php"
               style="color:#1447c0;font-size:.83rem;font-weight:600;padding:8px 18px;border:1.5px solid #dbeafe;border-radius:9px;background:#eff6ff;transition:all .15s;white-space:nowrap;text-decoration:none;"
               onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                Sign In
            </a>
            <a href="<?= SITE_URL ?>/auth/register.php"
               style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-size:.83rem;font-weight:700;padding:9px 20px;border-radius:9px;box-shadow:0 3px 12px rgba(239,68,68,.3);transition:all .18s;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;text-decoration:none;"
               onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 5px 18px rgba(239,68,68,.42)'"
               onmouseout="this.style.transform='none';this.style.boxShadow='0 3px 12px rgba(239,68,68,.3)'">
                <i class="bi bi-heart-fill" style="font-size:.76rem;"></i>Donate Now
            </a>
        </div>
    </div>
</nav>

<!-- ═══ HERO ════════════════════════════════════════ -->
<section class="hero">
    <div class="orb3"></div>
    <div class="w">
        <div class="hero-grid">
            <!-- Left -->
            <div>
                <div class="hero-badge">
                    <i class="bi bi-award-fill" style="font-size:.76rem;"></i>
                    VinaCapital Foundation
                </div>
                <h1 class="hero-title">
                    Making a <span class="hl">Difference</span>,<br>
                    One Donation at a Time.
                </h1>
                <p class="hero-sub">
                    Support education, healthcare, and community programs across Vietnam through transparent, verified fundraising campaigns.
                </p>
                <div class="hero-btns">
                    <a href="<?= SITE_URL ?>/auth/register.php" class="btn-hp red">
                        <i class="bi bi-heart-fill" style="font-size:.82rem;"></i>Donate Now
                    </a>
                    <a href="#campaigns" class="btn-hp ghost">
                        <i class="bi bi-collection" style="font-size:.82rem;"></i>Explore Campaigns
                    </a>
                </div>
            </div>

            <!-- Right — glassmorphism -->
            <div class="glass-card">
                <!-- Stats -->
                <div class="glass-stats">
                    <div class="gs-item">
                        <span class="gs-num"><?= number_format($t_raised/1000000,1) ?>M+</span>
                        <span class="gs-lbl">VND Raised</span>
                    </div>
                    <div class="gs-item">
                        <span class="gs-num" style="color:#fbbf24;"><?= $t_donors ?></span>
                        <span class="gs-lbl">Donors</span>
                    </div>
                    <div class="gs-item">
                        <span class="gs-num" style="color:#4ade80;"><?= $t_camps ?></span>
                        <span class="gs-lbl">Campaigns</span>
                    </div>
                </div>
                <div class="glass-div"></div>
                <div class="glass-title">HOW IT WORKS</div>
                <?php
                $steps = [
                    ['Register &amp; choose a campaign',   'bi-calendar-check'],
                    ['Submit donation + transaction info',  'bi-shield-check'],
                    ['Finance staff verifies transaction',  'bi-file-earmark-check'],
                    ['Receive confirmation &amp; track impact','bi-check2-circle'],
                ];
                foreach($steps as $i => [$s,$ico]): ?>
                <div class="step-row">
                    <div class="step-left">
                        <div class="step-n"><?= $i+1 ?></div>
                        <span class="step-t"><?= $s ?></span>
                    </div>
                    <i class="bi <?= $ico ?> step-ico"></i>
                </div>
                <?php endforeach; ?>
                <div class="glass-pills">
                    <span class="gpill v"><i class="bi bi-check-circle-fill" style="font-size:.72rem;"></i>Verified</span>
                    <span class="gpill t"><i class="bi bi-eye-fill" style="font-size:.72rem;"></i>Transparent</span>
                    <span class="gpill s"><i class="bi bi-lock-fill" style="font-size:.72rem;"></i>Secure</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══ STATS BAR ════════════════════════════════════ -->
<section class="stats-bar" id="impact">
    <div class="w">
        <div class="stats-grid">
            <?php
            $sbars = [
                [number_format($t_raised/1000000,1).'M VND','Total Raised',         'bi-bag-fill',        '#dbeafe','#1447c0'],
                [$t_donors,                                   'Total Donors',         'bi-people-fill',     '#dcfce7','#16a34a'],
                [$t_camps,                                    'Active Campaigns',     'bi-megaphone-fill',  '#ede9fe','#7c3aed'],
                [$t_verif,                                    'Verified Transactions','bi-file-check-fill', '#fff7ed','#ea580c'],
            ];
            foreach($sbars as [$v,$l,$ic,$bg,$co]): ?>
            <div class="sbar">
                <div class="sbar-icon" style="background:<?= $bg ?>;color:<?= $co ?>;">
                    <i class="bi <?= $ic ?>"></i>
                </div>
                <div>
                    <div class="sbar-num" style="color:<?= $co ?>;"><?= $v ?></div>
                    <div class="sbar-lbl"><?= $l ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══ FEATURES ════════════════════════════════════ -->
<section class="feat-sec">
    <div class="w">
        <div class="sec-hdr">
            <div class="sec-tag">Why Choose VCF</div>
            <h2 class="sec-title">A Platform You Can Trust</h2>
            <p class="sec-sub">Built for transparency, verified by staff, tracked end-to-end</p>
        </div>
        <div class="feat-grid">
            <?php foreach([
                ['bi-shield-check-fill','#dbeafe','#1447c0','Verified Donations',    'Every transaction is reviewed by Finance Staff before any donation is recorded.'],
                ['bi-pie-chart-fill',   '#dcfce7','#16a34a','Fund Transparency',     'Track exactly how every donated VND is allocated and used across all programs.'],
                ['bi-megaphone-fill',   '#ede9fe','#7c3aed','Campaign Management',   'Create campaigns with clear goals, deadlines, and real-time progress tracking.'],
                ['bi-graph-up-arrow',   '#fff7ed','#ea580c','Real-time Analytics',   'Managers monitor fundraising performance live with charts and donor analytics.'],
            ] as [$ic,$bg,$co,$ti,$de]): ?>
            <div class="feat-card">
                <div class="feat-icon" style="background:<?= $bg ?>;color:<?= $co ?>;"><i class="bi <?= $ic ?>"></i></div>
                <div class="feat-t"><?= $ti ?></div>
                <div class="feat-d"><?= $de ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══ CAMPAIGNS ════════════════════════════════════ -->
<section class="camp-sec" id="campaigns">
    <div class="w">
        <div class="sec-hdr">
            <div class="sec-tag">Featured Campaigns</div>
            <h2 class="sec-title">Support a Cause Today</h2>
            <p class="sec-sub">Every contribution makes a real difference in someone's life</p>
        </div>
        <div class="camps-grid">
            <?php
            $grads=['linear-gradient(135deg,#1e3a8a,#3b82f6)','linear-gradient(135deg,#065f46,#059669)','linear-gradient(135deg,#7c2d12,#f97316)'];
            $gi=0;
            if($campaigns && $campaigns->num_rows>0): while($c=$campaigns->fetch_assoc()):
                $pct=min(100,$c['target_amount']>0?round(($c['raised_amount']/$c['target_amount'])*100,1):0);
                $done=$pct>=100;$urgent=$c['days_left']>0&&$c['days_left']<=7;
            ?>
            <div class="cc">
                <div class="cc-img" style="<?= !$c['image_url']?'background:'.$grads[$gi%3]:'' ?>">
                    <?php if($c['image_url']): ?>
                    <img src="<?= htmlspecialchars($c['image_url']) ?>" alt="<?= htmlspecialchars($c['title']) ?>"
                         onerror="this.style.display='none';this.parentElement.style.background='<?= $grads[$gi%3] ?>'">
                    <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-heart-pulse" style="font-size:3rem;color:rgba(255,255,255,.22);"></i>
                    </div>
                    <?php endif; ?>
                    <div class="cc-img-ov"></div>
                    <div class="cc-badge"><?= $done?'Goal Reached':'Active' ?></div>
                    <div class="cc-days <?= $urgent?'urgent':'' ?>">
                        <?= $c['days_left']>0 ? '<i class="bi bi-clock" style="font-size:.6rem;margin-right:2px;"></i>'.$c['days_left'].'d' : 'Ended' ?>
                    </div>
                </div>
                <div class="cc-body">
                    <div class="cc-meta"><i class="bi bi-people" style="font-size:.76rem;"></i><?= $c['donor_count'] ?> donors</div>
                    <div class="cc-title"><?= htmlspecialchars($c['title']) ?></div>
                    <div class="cc-desc"><?= htmlspecialchars($c['description']) ?></div>
                    <div class="prog-hdr">
                        <span class="prog-raised"><?= number_format($c['raised_amount']/1000000,1) ?>M VND raised</span>
                        <span class="prog-pct" style="<?= $done?'color:#16a34a':'' ?>"><?= $pct ?>%</span>
                    </div>
                    <div class="prog-bar"><div class="prog-fill <?= $done?'done':'' ?>" style="width:<?= $pct ?>%"></div></div>
                    <div class="prog-goal">Goal: <?= number_format($c['target_amount']/1000000,0) ?>M VND</div>
                    <a href="<?= SITE_URL ?>/auth/login.php" class="cc-btn">
                        <i class="bi bi-heart" style="font-size:.78rem;"></i>View Project
                    </a>
                </div>
            </div>
            <?php $gi++; endwhile; endif; ?>
        </div>
        <div style="text-align:center;margin-top:32px;">
            <a href="<?= SITE_URL ?>/auth/login.php"
               style="display:inline-flex;align-items:center;gap:7px;padding:10px 26px;border:1.5px solid #dbeafe;border-radius:10px;color:#1447c0;font-size:.82rem;font-weight:600;background:#eff6ff;transition:all .18s;"
               onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                View All Campaigns <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- ═══ HOW IT WORKS ════════════════════════════════ -->
<section class="how-sec" id="how">
    <div class="w">
        <div class="sec-hdr">
            <div class="sec-tag">How It Works</div>
            <h2 class="sec-title">Simple, Transparent Process</h2>
            <p class="sec-sub">From donation to impact — every step is verified and tracked</p>
        </div>
        <div class="how-grid">
            <?php foreach([
                ['bi-person-plus-fill','#eff6ff','#1447c0','Register',          'Create your free donor account in under 2 minutes.'],
                ['bi-collection-fill', '#f0fdf4','#16a34a','Choose Campaign',   'Browse active campaigns and select a cause you care about.'],
                ['bi-cash-coin',       '#fffbeb','#d97706','Donate',            'Submit your donation with transaction details for verification.'],
                ['bi-shield-check-fill','#faf5ff','#7c3aed','Verified & Track','Finance Staff verifies your transaction. Track your impact live.'],
            ] as $i=>[$ic,$bg,$co,$ti,$de]): ?>
            <div class="how-card">
                <div class="how-num"><?= $i+1 ?></div>
                <div class="how-ico" style="background:<?= $bg ?>;color:<?= $co ?>;"><i class="bi <?= $ic ?>"></i></div>
                <div class="how-t"><?= $ti ?></div>
                <div class="how-d"><?= $de ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══ CTA ══════════════════════════════════════════ -->
<section class="cta-sec">
    <div class="w">
        <div class="cta-in">
            <div style="font-size:.68rem;color:rgba(255,255,255,.3);font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:10px;">Ready to make an impact?</div>
            <h2 class="cta-title">Join Our Community of Donors</h2>
            <p class="cta-sub">Create a free account and start supporting campaigns that matter. Every donation is verified and tracked transparently.</p>
            <div class="cta-btns">
                <a href="<?= SITE_URL ?>/auth/register.php" class="btn-hp red">
                    <i class="bi bi-person-plus-fill"></i>Create Account
                </a>
                <a href="<?= SITE_URL ?>/auth/login.php" class="btn-hp ghost">
                    <i class="bi bi-box-arrow-in-right"></i>Sign In
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ═══ FOOTER ═══════════════════════════════════════ -->
<footer class="footer">
    <div class="w">
        <div class="foot-top">
            <!-- Brand -->
            <div>
                <div class="foot-brand">
                    <div class="foot-logo-i"><i class="bi bi-heart-fill" style="color:white;font-size:.8rem;"></i></div>
                    <span class="foot-brand-name">VCF Donation System</span>
                </div>
                <p class="foot-about">Supporting education, healthcare, and community development programs across Vietnam through transparent, verified fundraising campaigns.</p>
                <div class="foot-socials">
                    <?php foreach([['bi-facebook','#'],['bi-youtube','#'],['bi-linkedin','#'],['bi-envelope-fill','mailto:info@vcf.org']] as [$ic,$u]): ?>
                    <a href="<?= $u ?>" class="social-btn"><i class="bi <?= $ic ?>"></i></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Quick Links -->
            <div>
                <div class="foot-col-title">Quick Links</div>
                <?php foreach([['Home',SITE_URL.'/'],['Campaigns','#campaigns'],['How It Works','#how'],['Our Impact','#impact'],['Sign In',SITE_URL.'/auth/login.php'],['Register',SITE_URL.'/auth/register.php']] as [$l,$u]): ?>
                <a href="<?= $u ?>" class="foot-link"><i class="bi bi-chevron-right"></i><?= $l ?></a>
                <?php endforeach; ?>
            </div>
            <!-- Programs -->
            <div>
                <div class="foot-col-title">Our Programs</div>
                <?php foreach(['Education for Children','Healthcare Access','Clean Water Initiative','Scholarship Program','Elderly Care Support','Disaster Relief Fund'] as $p): ?>
                <div style="font-size:.78rem;color:rgba(255,255,255,.4);margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                    <div style="width:5px;height:5px;border-radius:50%;background:rgba(239,68,68,.6);flex-shrink:0;"></div><?= $p ?>
                </div>
                <?php endforeach; ?>
            </div>
            <!-- Contact -->
            <div>
                <div class="foot-col-title">Contact</div>
                <?php foreach([
                    ['bi-geo-alt-fill',  '3rd Floor, Viet Dragon Tower, 141 Nguyen Du, D1, HCMC'],
                    ['bi-telephone-fill','(+84) 28 3827 2255'],
                    ['bi-envelope-fill', 'info@vinacapitalfoundation.org'],
                    ['bi-globe',         'vinacapitalfoundation.org'],
                ] as [$ic,$txt]): ?>
                <div class="foot-contact-item">
                    <i class="bi <?= $ic ?>"></i>
                    <span><?= $txt ?></span>
                </div>
                <?php endforeach; ?>
                <div class="foot-nl">
                    <div class="foot-nl-label">Stay updated</div>
                    <div class="foot-nl-row">
                        <input type="email" placeholder="your@email.com">
                        <button><i class="bi bi-arrow-right"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="foot-stats">
            <?php foreach([
                [number_format($t_raised/1000000,1).'M VND','Total Raised',   '#fbbf24'],
                [$t_donors,                                   'Donors',         '#4ade80'],
                [$t_camps,                                    'Campaigns',      '#60a5fa'],
                [$t_verif,                                    'Verified Txns',  '#f472b6'],
            ] as [$v,$l,$c]): ?>
            <div class="fstat">
                <span class="fstat-n" style="color:<?= $c ?>;"><?= $v ?></span>
                <span class="fstat-l"><?= $l ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Bottom -->
        <div class="foot-bottom">
            <span class="foot-copy">© 2026 VinaCapital Foundation. All rights reserved. · Capstone Project II · INS3282</span>
            <div class="foot-bottom-links">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Use</a>
                <a href="#">Transparency Report</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
