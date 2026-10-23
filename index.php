<?php
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
<title>VCF &mdash; Donation &amp; Fundraising Management System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
/* ─── RESET & BASE ─────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    color: #0f172a;
    background: #fff;
    -webkit-font-smoothing: antialiased;
    overflow-x: hidden;
}
a { text-decoration: none; color: inherit; }
.w { max-width: 1200px; margin: 0 auto; padding: 0 40px; }

/* ─── NAVBAR ──────────────────────────────────────────────── */
.pub-nav {
    background: #fff;
    border-bottom: 1px solid #f1f5f9;
    position: sticky;
    top: 0;
    z-index: 999;
    box-shadow: 0 1px 20px rgba(0,0,0,.06);
}
.pub-nav-in {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    height: 66px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 40px;
    gap: 16px;
}
.nav-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    flex-shrink: 0;
}
.nav-logo-ico {
    width: 38px; height: 38px;
    background: linear-gradient(135deg, #ef4444, #f97316);
    border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem; color: #fff;
    box-shadow: 0 4px 14px rgba(239,68,68,.38);
    flex-shrink: 0;
    transition: transform .2s;
}
.nav-logo:hover .nav-logo-ico { transform: scale(1.07); }
.nav-logo-text {
    font-weight: 800;
    font-size: .95rem;
    color: #0f172a;
    letter-spacing: -.025em;
    line-height: 1.1;
}
.nav-logo-sub {
    display: block;
    font-size: .62rem;
    font-weight: 500;
    color: #94a3b8;
    letter-spacing: .01em;
}
.nav-links-center {
    display: flex;
    align-items: center;
    gap: 2px;
    justify-content: center;
}
.nav-link-item {
    font-size: .84rem;
    font-weight: 500;
    color: #475569;
    padding: 7px 15px;
    border-radius: 8px;
    transition: all .15s;
    white-space: nowrap;
}
.nav-link-item:hover { color: #1447c0; background: #eff6ff; }
.nav-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: flex-end;
    flex-shrink: 0;
}
.btn-signin {
    color: #1447c0;
    font-size: .82rem;
    font-weight: 600;
    padding: 8px 18px;
    border: 1.5px solid #dbeafe;
    border-radius: 9px;
    background: #eff6ff;
    transition: all .15s;
    white-space: nowrap;
}
.btn-signin:hover { background: #dbeafe; color: #0d2157; }
.btn-donate-nav {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    font-size: .82rem;
    font-weight: 700;
    padding: 9px 20px;
    border-radius: 9px;
    box-shadow: 0 3px 14px rgba(239,68,68,.32);
    transition: all .2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}
.btn-donate-nav:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239,68,68,.46);
    color: #fff;
}

/* ─── HERO ────────────────────────────────────────────────── */
.hero-section {
    display: block;
    background:
        radial-gradient(ellipse at 78% 30%, rgba(59,130,246,.3) 0%, transparent 55%),
        radial-gradient(ellipse at 10% 70%, rgba(99,102,241,.22) 0%, transparent 50%),
        linear-gradient(160deg, #05091a 0%, #0a1833 40%, #0d2157 100%);
    min-height: 580px;
    padding: 96px 0 108px;
    position: relative;
    overflow: hidden;
}
.hero-orb-1 {
    position: absolute;
    width: 580px; height: 580px;
    border-radius: 50%;
    background: rgba(59,130,246,.07);
    top: -180px; right: -100px;
    pointer-events: none;
}
.hero-orb-2 {
    position: absolute;
    width: 360px; height: 360px;
    border-radius: 50%;
    background: rgba(99,102,241,.07);
    bottom: -100px; left: 4%;
    pointer-events: none;
}
.hero-orb-3 {
    position: absolute;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: rgba(239,68,68,.05);
    top: 22%; right: 33%;
    pointer-events: none;
}
.hero-grid {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 60px;
    align-items: center;
    position: relative;
    z-index: 2;
}
/* Hero Left */
.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: rgba(251,191,36,.12);
    border: 1px solid rgba(251,191,36,.28);
    color: #fbbf24;
    padding: 6px 15px;
    border-radius: 20px;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin-bottom: 22px;
}
.hero-badge i { font-size: .76rem; }
.hero-title {
    font-size: 3.3rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.045em;
    line-height: 1.1;
    margin-bottom: 20px;
}
.hero-title .hl {
    background: linear-gradient(135deg, #f97316 0%, #fbbf24 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.hero-sub {
    color: rgba(255,255,255,.58);
    font-size: .97rem;
    line-height: 1.82;
    margin-bottom: 34px;
    max-width: 430px;
}
.hero-btns { display: flex; gap: 12px; flex-wrap: wrap; }
.btn-hero {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 13px 26px;
    border-radius: 10px;
    font-weight: 700;
    font-size: .88rem;
    transition: all .2s;
    letter-spacing: -.01em;
    cursor: pointer;
}
.btn-hero.primary {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    box-shadow: 0 4px 20px rgba(239,68,68,.45);
}
.btn-hero.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 26px rgba(239,68,68,.56);
    color: #fff;
}
.btn-hero.outline {
    background: rgba(255,255,255,.09);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1.5px solid rgba(255,255,255,.2);
    color: rgba(255,255,255,.85);
}
.btn-hero.outline:hover {
    background: rgba(255,255,255,.17);
    color: #fff;
    border-color: rgba(255,255,255,.35);
}
/* Hero trust strip */
.hero-trust {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-top: 32px;
    flex-wrap: wrap;
}
.hero-trust-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .74rem;
    color: rgba(255,255,255,.42);
    font-weight: 500;
}
.hero-trust-item i { font-size: .8rem; color: rgba(74,222,128,.7); }
.hero-trust-sep { width: 1px; height: 16px; background: rgba(255,255,255,.12); }

/* Hero Right — glass card */
.glass-card {
    background: rgba(255,255,255,.07);
    backdrop-filter: blur(28px);
    -webkit-backdrop-filter: blur(28px);
    border: 1px solid rgba(255,255,255,.14);
    border-radius: 22px;
    padding: 28px 30px 26px;
}
.glass-stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0;
    margin-bottom: 22px;
}
.gs-item { text-align: center; padding: 0 8px; }
.gs-item + .gs-item { border-left: 1px solid rgba(255,255,255,.1); }
.gs-num {
    display: block;
    font-size: 1.7rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.04em;
    line-height: 1.1;
}
.gs-lbl {
    display: block;
    font-size: .61rem;
    color: rgba(255,255,255,.36);
    text-transform: uppercase;
    letter-spacing: .09em;
    margin-top: 4px;
    font-weight: 600;
}
.glass-hr { height: 1px; background: rgba(255,255,255,.09); margin: 0 0 18px; }
.glass-section-label {
    font-size: .6rem;
    color: rgba(255,255,255,.28);
    text-transform: uppercase;
    letter-spacing: .12em;
    font-weight: 700;
    margin-bottom: 13px;
}
.glass-step-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.glass-step-left { display: flex; align-items: center; gap: 10px; }
.glass-step-num {
    width: 22px; height: 22px;
    border-radius: 50%;
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.18);
    display: flex; align-items: center; justify-content: center;
    font-size: .62rem; font-weight: 800;
    color: rgba(255,255,255,.68);
    flex-shrink: 0;
}
.glass-step-text {
    font-size: .77rem;
    color: rgba(255,255,255,.6);
    line-height: 1.4;
}
.glass-step-icon {
    font-size: .85rem;
    color: rgba(255,255,255,.26);
}
.glass-pills {
    display: flex;
    gap: 7px;
    margin-top: 16px;
    flex-wrap: wrap;
}
.gpill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 11px;
    border-radius: 20px;
    font-size: .68rem;
    font-weight: 600;
}
.gpill.verified  { background: rgba(251,191,36,.14); color: #fbbf24; border: 1px solid rgba(251,191,36,.28); }
.gpill.transp    { background: rgba(74,222,128,.1);  color: #4ade80; border: 1px solid rgba(74,222,128,.22); }
.gpill.secure    { background: rgba(147,197,253,.1); color: #93c5fd; border: 1px solid rgba(147,197,253,.2); }
.gpill i { font-size: .7rem; }

/* ─── STATS BAR ───────────────────────────────────────────── */
.stats-bar {
    display: block;
    background: #fff;
    border-bottom: 1px solid #f1f5f9;
    box-shadow: 0 2px 14px rgba(0,0,0,.05);
}
.stats-bar-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
}
.sbar-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 24px 30px;
    border-right: 1px solid #f1f5f9;
    transition: background .15s;
    cursor: default;
}
.sbar-item:last-child { border-right: none; }
.sbar-item:hover { background: #fafbff; }
.sbar-icon-wrap {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.22rem;
    flex-shrink: 0;
    transition: transform .2s;
}
.sbar-item:hover .sbar-icon-wrap { transform: scale(1.06); }
.sbar-value {
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: -.035em;
    line-height: 1.1;
    margin-bottom: 3px;
}
.sbar-label {
    font-size: .67rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #94a3b8;
}

/* ─── SECTION COMMONS ─────────────────────────────────────── */
.sec-tag {
    display: inline-block;
    background: #eff6ff;
    color: #1447c0;
    border: 1px solid #dbeafe;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: .69rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    margin-bottom: 10px;
}
.sec-title {
    font-size: 1.8rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -.035em;
    margin-bottom: 8px;
}
.sec-sub {
    color: #64748b;
    font-size: .88rem;
    line-height: 1.7;
}
.sec-header { text-align: center; margin-bottom: 48px; }

/* ─── FEATURES SECTION ────────────────────────────────────── */
.features-section {
    display: block;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
    padding: 76px 0 80px;
}
.feat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 22px;
}
.feat-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 28px 22px 22px;
    text-align: center;
    transition: all .28s cubic-bezier(.25,.46,.45,.94);
    position: relative;
    overflow: hidden;
}
.feat-card::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 18px;
    opacity: 0;
    transition: opacity .28s;
    pointer-events: none;
}
.feat-card:hover {
    transform: translateY(-7px);
    border-color: transparent;
}
.feat-card:hover::after { opacity: 1; }
.feat-card-blue:hover  { box-shadow: 0 16px 42px rgba(20,71,192,.14);  }
.feat-card-green:hover { box-shadow: 0 16px 42px rgba(22,163,74,.13);  }
.feat-card-purple:hover{ box-shadow: 0 16px 42px rgba(124,58,237,.12); }
.feat-card-orange:hover{ box-shadow: 0 16px 42px rgba(234,88,12,.12);  }
.feat-icon-wrap {
    width: 56px; height: 56px;
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem;
    margin: 0 auto 18px;
    transition: transform .25s;
}
.feat-card:hover .feat-icon-wrap { transform: scale(1.1) rotate(-3deg); }
.feat-card-title {
    font-size: .9rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 10px;
    letter-spacing: -.01em;
}
.feat-card-desc {
    font-size: .76rem;
    color: #64748b;
    line-height: 1.7;
    margin-bottom: 18px;
}
.feat-card-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 13px;
    border-radius: 20px;
    font-size: .68rem;
    font-weight: 700;
    margin-top: auto;
}

/* ─── CAMPAIGNS SECTION ───────────────────────────────────── */
.campaigns-section {
    display: block;
    background: #fff;
    padding: 68px 0 72px;
}
.camps-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    margin-top: 0;
}
.camp-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    overflow: hidden;
    transition: all .28s cubic-bezier(.25,.46,.45,.94);
    display: flex;
    flex-direction: column;
}
.camp-card:hover {
    box-shadow: 0 16px 42px rgba(0,0,0,.1);
    transform: translateY(-5px);
    border-color: #bfdbfe;
}
.camp-img {
    height: 175px;
    position: relative;
    overflow: hidden;
}
.camp-img img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .45s;
}
.camp-card:hover .camp-img img { transform: scale(1.07); }
.camp-img-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, transparent 25%, rgba(0,0,0,.52) 100%);
}
.camp-badge {
    position: absolute;
    top: 12px; left: 12px;
    z-index: 2;
    background: rgba(22,163,74,.88);
    backdrop-filter: blur(6px);
    color: #fff;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .63rem;
    font-weight: 700;
    letter-spacing: .03em;
}
.camp-badge.done { background: rgba(99,102,241,.88); }
.camp-days {
    position: absolute;
    top: 12px; right: 12px;
    z-index: 2;
    background: rgba(0,0,0,.42);
    backdrop-filter: blur(6px);
    color: rgba(255,255,255,.85);
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .63rem;
    font-weight: 600;
}
.camp-days.urgent { background: rgba(239,68,68,.84); color: #fff; }
.camp-body {
    padding: 18px 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.camp-meta {
    font-size: .7rem;
    color: #94a3b8;
    margin-bottom: 7px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.camp-title {
    font-size: .9rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 7px;
    line-height: 1.4;
    letter-spacing: -.01em;
}
.camp-desc {
    font-size: .75rem;
    color: #64748b;
    flex: 1;
    margin-bottom: 14px;
    line-height: 1.65;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.prog-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 6px;
}
.prog-raised { font-size: .71rem; font-weight: 600; color: #334155; }
.prog-pct    { font-size: .78rem; font-weight: 800; color: #1447c0; }
.prog-pct.done { color: #16a34a; }
.prog-track  { height: 7px; background: #f1f5f9; border-radius: 4px; overflow: hidden; margin-bottom: 5px; }
.prog-fill   { height: 100%; background: linear-gradient(90deg, #3b82f6, #1447c0); border-radius: 4px; transition: width .6s; }
.prog-fill.done { background: linear-gradient(90deg, #22c55e, #16a34a); }
.prog-goal   { font-size: .67rem; color: #94a3b8; }
.camp-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 10px 16px;
    margin-top: 14px;
    background: linear-gradient(135deg, #1447c0, #0d2157);
    color: #fff;
    border-radius: 10px;
    font-size: .79rem;
    font-weight: 700;
    transition: all .2s;
    letter-spacing: -.01em;
}
.camp-btn:hover {
    background: linear-gradient(135deg, #1a56db, #1447c0);
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 5px 16px rgba(20,71,192,.35);
}
.camp-placeholder {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
}

/* ─── HOW IT WORKS ────────────────────────────────────────── */
.how-section {
    display: block;
    background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%);
    padding: 68px 0 72px;
    border-top: 1px solid #e0f2fe;
    border-bottom: 1px solid #e0f2fe;
}
.how-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
    position: relative;
}
/* Dotted connector line between steps on desktop */
.how-grid::before {
    content: '';
    position: absolute;
    top: 38px;
    left: calc(12.5% + 28px);
    right: calc(12.5% + 28px);
    height: 2px;
    background: repeating-linear-gradient(90deg, #bfdbfe 0, #bfdbfe 6px, transparent 6px, transparent 14px);
    z-index: 0;
    pointer-events: none;
}
.how-card {
    background: #fff;
    border: 1px solid #e0f2fe;
    border-radius: 16px;
    padding: 26px 20px 22px;
    text-align: center;
    position: relative;
    z-index: 1;
    transition: all .25s;
}
.how-card:hover {
    box-shadow: 0 10px 30px rgba(0,0,0,.08);
    transform: translateY(-4px);
    border-color: #93c5fd;
}
.how-step-num {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1447c0, #0d2157);
    color: #fff;
    font-size: .9rem;
    font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px;
    box-shadow: 0 5px 14px rgba(20,71,192,.34);
    position: relative;
    z-index: 1;
}
.how-icon-wrap {
    width: 44px; height: 44px;
    border-radius: 13px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.12rem;
    margin: 0 auto 12px;
    transition: transform .22s;
}
.how-card:hover .how-icon-wrap { transform: scale(1.12); }
.how-card-title {
    font-size: .86rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 7px;
    letter-spacing: -.01em;
}
.how-card-desc {
    font-size: .75rem;
    color: #64748b;
    line-height: 1.68;
}

/* ─── CTA ─────────────────────────────────────────────────── */
.cta-section {
    display: block;
    background:
        radial-gradient(ellipse at 20% 50%, rgba(59,130,246,.18) 0%, transparent 55%),
        radial-gradient(ellipse at 80% 30%, rgba(239,68,68,.12) 0%, transparent 50%),
        linear-gradient(135deg, #05091a 0%, #0d2157 55%, #1447c0 100%);
    padding: 72px 0;
    position: relative;
    overflow: hidden;
}
.cta-section::before {
    content: '';
    position: absolute;
    width: 380px; height: 380px;
    border-radius: 50%;
    background: rgba(255,255,255,.035);
    top: -110px; right: -70px;
    pointer-events: none;
}
.cta-section::after {
    content: '';
    position: absolute;
    width: 240px; height: 240px;
    border-radius: 50%;
    background: rgba(255,255,255,.025);
    bottom: -80px; left: 8%;
    pointer-events: none;
}
.cta-inner {
    text-align: center;
    max-width: 580px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}
.cta-eyebrow {
    font-size: .67rem;
    color: rgba(255,255,255,.3);
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    margin-bottom: 12px;
}
.cta-title {
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.04em;
    margin-bottom: 12px;
    line-height: 1.18;
}
.cta-sub {
    color: rgba(255,255,255,.56);
    font-size: .9rem;
    margin-bottom: 30px;
    line-height: 1.75;
}
.cta-btns {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}

/* ─── FOOTER ──────────────────────────────────────────────── */
.pub-footer {
    background: #0a1628;
    padding: 56px 0 0;
}
.foot-top-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 48px;
    padding-bottom: 48px;
    border-bottom: 1px solid rgba(255,255,255,.07);
}
.foot-brand-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}
.foot-logo-ico {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, #ef4444, #f97316);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 3px 10px rgba(239,68,68,.35);
    flex-shrink: 0;
}
.foot-brand-name {
    font-weight: 700;
    font-size: .9rem;
    color: #fff;
}
.foot-about {
    font-size: .78rem;
    color: rgba(255,255,255,.38);
    line-height: 1.78;
    max-width: 275px;
    margin-bottom: 18px;
}
.foot-socials { display: flex; gap: 8px; }
.social-btn {
    width: 34px; height: 34px;
    border-radius: 9px;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.1);
    display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,.48);
    font-size: .84rem;
    transition: all .18s;
}
.social-btn:hover { background: rgba(255,255,255,.14); color: #fff; border-color: rgba(255,255,255,.2); }
.foot-col-title {
    font-size: .67rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: rgba(255,255,255,.26);
    margin-bottom: 15px;
}
.foot-link {
    display: block;
    font-size: .78rem;
    color: rgba(255,255,255,.4);
    margin-bottom: 9px;
    transition: color .15s;
}
.foot-link:hover { color: #fff; }
.foot-link i { font-size: .6rem; margin-right: 5px; }
.foot-contact-item {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-bottom: 11px;
}
.foot-contact-item i { color: rgba(239,68,68,.7); font-size: .78rem; flex-shrink: 0; margin-top: 2px; }
.foot-contact-item span { font-size: .75rem; color: rgba(255,255,255,.38); line-height: 1.58; }
.foot-nl-label { font-size: .71rem; font-weight: 600; color: rgba(255,255,255,.4); margin-bottom: 7px; margin-top: 16px; }
.foot-nl-row {
    display: flex;
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 9px;
    overflow: hidden;
    background: rgba(255,255,255,.05);
}
.foot-nl-row input {
    flex: 1;
    border: none;
    background: transparent;
    padding: 8px 11px;
    font-size: .75rem;
    color: #fff;
    outline: none;
}
.foot-nl-row input::placeholder { color: rgba(255,255,255,.28); }
.foot-nl-row button {
    padding: 8px 13px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    border: none;
    color: #fff;
    font-size: .75rem;
    font-weight: 700;
    cursor: pointer;
    transition: opacity .15s;
}
.foot-nl-row button:hover { opacity: .85; }
.foot-stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    padding: 20px 0;
    border-bottom: 1px solid rgba(255,255,255,.06);
}
.fstat-item { text-align: center; padding: 4px 0; border-right: 1px solid rgba(255,255,255,.06); }
.fstat-item:last-child { border-right: none; }
.fstat-val { font-size: 1.3rem; font-weight: 800; letter-spacing: -.025em; display: block; }
.fstat-lbl { font-size: .63rem; color: rgba(255,255,255,.28); text-transform: uppercase; letter-spacing: .07em; font-weight: 600; margin-top: 2px; display: block; }
.foot-bottom-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    padding: 16px 0;
}
.foot-copy { font-size: .69rem; color: rgba(255,255,255,.2); }
.foot-legal-links { display: flex; align-items: center; gap: 16px; }
.foot-legal-links a { font-size: .69rem; color: rgba(255,255,255,.22); transition: color .14s; }
.foot-legal-links a:hover { color: rgba(255,255,255,.58); }

/* ─── RESPONSIVE ──────────────────────────────────────────── */
@media (max-width: 1024px) {
    .hero-grid { grid-template-columns: 1fr; gap: 36px; }
    .glass-card { display: none; }
    .feat-grid, .how-grid { grid-template-columns: 1fr 1fr; }
    .camps-grid { grid-template-columns: 1fr 1fr; }
    .foot-top-grid { grid-template-columns: 1fr 1fr; gap: 30px; }
    .stats-bar-grid { grid-template-columns: 1fr 1fr; }
    .how-grid::before { display: none; }
}
@media (max-width: 640px) {
    .camps-grid, .feat-grid, .how-grid { grid-template-columns: 1fr; }
    .hero-title { font-size: 2.1rem; }
    .w { padding: 0 18px; }
    .nav-links-center { display: none; }
    .pub-nav-in { grid-template-columns: 1fr auto; padding: 0 18px; }
    .stats-bar-grid { grid-template-columns: 1fr 1fr; }
    .how-grid::before { display: none; }
}
</style>
</head>
<body>

<!-- ═══════════════════════════════════════════════════════════
     NAVBAR
═══════════════════════════════════════════════════════════ -->
<nav class="pub-nav">
    <div class="pub-nav-in">

        <!-- Logo -->
        <a href="<?= SITE_URL ?>" class="nav-logo">
            <div class="nav-logo-ico">
                <i class="bi bi-heart-fill"></i>
            </div>
            <div>
                <span class="nav-logo-text">VCF Donation System</span>
                <span class="nav-logo-sub">VinaCapital Foundation</span>
            </div>
        </a>

        <!-- Center links -->
        <div class="nav-links-center">
            <a href="#campaigns" class="nav-link-item">Campaigns</a>
            <a href="#how"       class="nav-link-item">How It Works</a>
            <a href="#impact"    class="nav-link-item">Our Impact</a>
        </div>

        <!-- Actions -->
        <div class="nav-actions">
            <a href="<?= SITE_URL ?>/auth/login.php"    class="btn-signin">Sign In</a>
            <a href="<?= SITE_URL ?>/auth/register.php" class="btn-donate-nav">
                <i class="bi bi-heart-fill" style="font-size:.76rem;"></i>Donate Now
            </a>
        </div>

    </div>
</nav>

<!-- ═══════════════════════════════════════════════════════════
     HERO
═══════════════════════════════════════════════════════════ -->
<section class="hero-section">
    <div class="hero-orb-1"></div>
    <div class="hero-orb-2"></div>
    <div class="hero-orb-3"></div>

    <div class="w">
        <div class="hero-grid">

            <!-- Left -->
            <div>
                <div class="hero-badge">
                    <i class="bi bi-award-fill"></i>
                    VinaCapital Foundation
                </div>
                <h1 class="hero-title">
                    Making a <span class="hl">Difference</span>,<br>
                    One Donation<br>at a Time.
                </h1>
                <p class="hero-sub">
                    Support education, healthcare, and community programs across Vietnam through transparent, fully verified fundraising campaigns.
                </p>
                <div class="hero-btns">
                    <a href="<?= SITE_URL ?>/auth/register.php" class="btn-hero primary">
                        <i class="bi bi-heart-fill" style="font-size:.8rem;"></i>Donate Now
                    </a>
                    <a href="#campaigns" class="btn-hero outline">
                        <i class="bi bi-collection" style="font-size:.8rem;"></i>Explore Campaigns
                    </a>
                </div>
                <div class="hero-trust">
                    <div class="hero-trust-item"><i class="bi bi-shield-check-fill"></i>Staff-verified donations</div>
                    <div class="hero-trust-sep"></div>
                    <div class="hero-trust-item"><i class="bi bi-eye-fill"></i>Full transparency</div>
                    <div class="hero-trust-sep"></div>
                    <div class="hero-trust-item"><i class="bi bi-lock-fill"></i>Secure process</div>
                </div>
            </div>

            <!-- Right — glassmorphism card -->
            <div class="glass-card">
                <div class="glass-stats-row">
                    <div class="gs-item">
                        <span class="gs-num"><?= number_format($t_raised / 1000000, 1) ?>M+</span>
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
                <div class="glass-hr"></div>
                <div class="glass-section-label">HOW IT WORKS</div>
                <?php
                $steps = [
                    ['Register &amp; choose a campaign',         'bi-calendar-check'],
                    ['Submit donation + transaction info',        'bi-shield-check'],
                    ['Finance staff verifies transaction',        'bi-file-earmark-check'],
                    ['Receive confirmation &amp; track impact',   'bi-check2-circle'],
                ];
                foreach ($steps as $i => [$s, $ico]):
                ?>
                <div class="glass-step-row">
                    <div class="glass-step-left">
                        <div class="glass-step-num"><?= $i + 1 ?></div>
                        <span class="glass-step-text"><?= $s ?></span>
                    </div>
                    <i class="bi <?= $ico ?> glass-step-icon"></i>
                </div>
                <?php endforeach; ?>
                <div class="glass-pills">
                    <span class="gpill verified"><i class="bi bi-check-circle-fill"></i>Verified</span>
                    <span class="gpill transp"><i class="bi bi-eye-fill"></i>Transparent</span>
                    <span class="gpill secure"><i class="bi bi-lock-fill"></i>Secure</span>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     STATS BAR
═══════════════════════════════════════════════════════════ -->
<section class="stats-bar" id="impact">
    <div class="w" style="padding-left:0;padding-right:0;max-width:100%;">
        <div class="stats-bar-grid">
            <?php
            $sbars = [
                [number_format($t_raised / 1000000, 1) . 'M VND', 'Total Raised',          'bi-bag-heart-fill',   '#dbeafe', '#1447c0'],
                [$t_donors,                                         'Unique Donors',          'bi-people-fill',      '#dcfce7', '#16a34a'],
                [$t_camps,                                          'Active Campaigns',       'bi-megaphone-fill',   '#ede9fe', '#7c3aed'],
                [$t_verif,                                          'Verified Transactions',  'bi-patch-check-fill', '#fff7ed', '#ea580c'],
            ];
            foreach ($sbars as [$v, $l, $ic, $bg, $co]):
            ?>
            <div class="sbar-item">
                <div class="sbar-icon-wrap" style="background:<?= $bg ?>;color:<?= $co ?>;">
                    <i class="bi <?= $ic ?>"></i>
                </div>
                <div>
                    <div class="sbar-value" style="color:<?= $co ?>;"><?= $v ?></div>
                    <div class="sbar-label"><?= $l ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     FEATURES
═══════════════════════════════════════════════════════════ -->
<section class="features-section">
    <div class="w">
        <div class="sec-header">
            <div class="sec-tag">Why Choose VCF</div>
            <h2 class="sec-title">A Platform You Can Trust</h2>
            <p class="sec-sub">Built for transparency, verified by staff, tracked end-to-end across every campaign.</p>
        </div>

        <div class="feat-grid">
            <?php
            $feats = [
                [
                    'bi-shield-check-fill',
                    'linear-gradient(135deg,#dbeafe,#eff6ff)', '#1447c0',
                    'feat-card-blue',
                    'Verified Donations',
                    'Every transaction is reviewed by Finance Staff before any donation is recorded — no exceptions.',
                    '#eff6ff', '#1447c0', 'bi-check2-circle', 'Staff-verified'
                ],
                [
                    'bi-pie-chart-fill',
                    'linear-gradient(135deg,#dcfce7,#f0fdf4)', '#16a34a',
                    'feat-card-green',
                    'Fund Transparency',
                    'Track exactly how every donated VND is allocated and used across all programs and campaigns.',
                    '#f0fdf4', '#16a34a', 'bi-eye-fill', 'Fully audited'
                ],
                [
                    'bi-megaphone-fill',
                    'linear-gradient(135deg,#ede9fe,#f5f3ff)', '#7c3aed',
                    'feat-card-purple',
                    'Campaign Management',
                    'Create campaigns with clear goals, deadlines, and real-time contribution progress tracking.',
                    '#f5f3ff', '#7c3aed', 'bi-flag-fill', 'Goal-driven'
                ],
                [
                    'bi-graph-up-arrow',
                    'linear-gradient(135deg,#fff7ed,#ffedd5)', '#ea580c',
                    'feat-card-orange',
                    'Real-time Analytics',
                    'Managers monitor fundraising performance live with rich charts, reports, and donor analytics.',
                    '#fff7ed', '#ea580c', 'bi-bar-chart-fill', 'Live dashboard'
                ],
            ];
            foreach ($feats as [$ic, $iconBg, $iconColor, $cardClass, $title, $desc, $badgeBg, $badgeColor, $badgeIc, $badgeLabel]):
            ?>
            <div class="feat-card <?= $cardClass ?>" style="border-top: 3px solid <?= $iconColor ?>;">
                <div class="feat-icon-wrap" style="background:<?= $iconBg ?>;color:<?= $iconColor ?>;box-shadow: 0 5px 16px <?= $iconColor ?>28;">
                    <i class="bi <?= $ic ?>"></i>
                </div>
                <div class="feat-card-title"><?= $title ?></div>
                <div class="feat-card-desc"><?= $desc ?></div>
                <div class="feat-card-badge" style="background:<?= $badgeBg ?>;color:<?= $badgeColor ?>;border:1px solid <?= $badgeColor ?>22;">
                    <i class="bi <?= $badgeIc ?>" style="font-size:.7rem;"></i>
                    <?= $badgeLabel ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     CAMPAIGNS
═══════════════════════════════════════════════════════════ -->
<section class="campaigns-section" id="campaigns">
    <div class="w">
        <div class="sec-header">
            <div class="sec-tag">Featured Campaigns</div>
            <h2 class="sec-title">Support a Cause Today</h2>
            <p class="sec-sub">Every contribution makes a real, measurable difference in someone's life.</p>
        </div>

        <div class="camps-grid">
            <?php
            $grads = [
                'linear-gradient(135deg,#1e3a8a,#3b82f6)',
                'linear-gradient(135deg,#065f46,#059669)',
                'linear-gradient(135deg,#7c2d12,#f97316)',
            ];
            $gi = 0;
            if ($campaigns && $campaigns->num_rows > 0):
                while ($c = $campaigns->fetch_assoc()):
                    $pct  = min(100, $c['target_amount'] > 0 ? round(($c['raised_amount'] / $c['target_amount']) * 100, 1) : 0);
                    $done   = $pct >= 100;
                    $urgent = $c['days_left'] > 0 && $c['days_left'] <= 7;
            ?>
            <div class="camp-card">
                <div class="camp-img" style="<?= !$c['image_url'] ? 'background:' . $grads[$gi % 3] : '' ?>">
                    <?php if ($c['image_url']): ?>
                        <img src="<?= htmlspecialchars($c['image_url']) ?>"
                             alt="<?= htmlspecialchars($c['title']) ?>"
                             onerror="this.style.display='none';this.closest('.camp-img').style.background='<?= $grads[$gi % 3] ?>'">
                    <?php else: ?>
                        <div class="camp-placeholder">
                            <i class="bi bi-heart-pulse" style="font-size:3rem;color:rgba(255,255,255,.2);"></i>
                        </div>
                    <?php endif; ?>
                    <div class="camp-img-overlay"></div>
                    <div class="camp-badge <?= $done ? 'done' : '' ?>"><?= $done ? 'Goal Reached' : 'Active' ?></div>
                    <div class="camp-days <?= $urgent ? 'urgent' : '' ?>">
                        <?php if ($c['days_left'] > 0): ?>
                            <i class="bi bi-clock" style="font-size:.6rem;margin-right:2px;"></i><?= $c['days_left'] ?>d left
                        <?php else: ?>
                            Ended
                        <?php endif; ?>
                    </div>
                </div>
                <div class="camp-body">
                    <div class="camp-meta">
                        <i class="bi bi-people" style="font-size:.76rem;"></i><?= $c['donor_count'] ?> donors
                    </div>
                    <div class="camp-title"><?= htmlspecialchars($c['title']) ?></div>
                    <div class="camp-desc"><?= htmlspecialchars($c['description']) ?></div>
                    <div class="prog-header">
                        <span class="prog-raised"><?= number_format($c['raised_amount'] / 1000000, 1) ?>M VND raised</span>
                        <span class="prog-pct <?= $done ? 'done' : '' ?>"><?= $pct ?>%</span>
                    </div>
                    <div class="prog-track">
                        <div class="prog-fill <?= $done ? 'done' : '' ?>" style="width:<?= $pct ?>%;"></div>
                    </div>
                    <div class="prog-goal">Goal: <?= number_format($c['target_amount'] / 1000000, 0) ?>M VND</div>
                    <a href="<?= SITE_URL ?>/auth/login.php" class="camp-btn">
                        <i class="bi bi-heart" style="font-size:.78rem;"></i>View &amp; Donate
                        <i class="bi bi-arrow-right" style="font-size:.76rem;margin-left:2px;"></i>
                    </a>
                </div>
            </div>
            <?php $gi++; endwhile; endif; ?>
        </div>

        <div style="text-align:center;margin-top:36px;">
            <a href="<?= SITE_URL ?>/auth/login.php"
               style="display:inline-flex;align-items:center;gap:8px;padding:11px 28px;border:1.5px solid #dbeafe;border-radius:11px;color:#1447c0;font-size:.83rem;font-weight:600;background:#eff6ff;transition:all .18s;"
               onmouseover="this.style.background='#dbeafe';this.style.borderColor='#93c5fd';"
               onmouseout="this.style.background='#eff6ff';this.style.borderColor='#dbeafe';">
                View All Campaigns&nbsp;<i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     HOW IT WORKS
═══════════════════════════════════════════════════════════ -->
<section class="how-section" id="how">
    <div class="w">
        <div class="sec-header">
            <div class="sec-tag">How It Works</div>
            <h2 class="sec-title">Simple, Transparent Process</h2>
            <p class="sec-sub">From donation to impact — every step is verified and tracked.</p>
        </div>

        <div class="how-grid">
            <?php
            $steps = [
                ['bi-person-plus-fill', '#eff6ff', '#1447c0', 'Register',           'Create your free donor account in under 2 minutes.'],
                ['bi-collection-fill',  '#f0fdf4', '#16a34a', 'Choose Campaign',    'Browse active campaigns and select a cause you care about.'],
                ['bi-cash-coin',        '#fffbeb', '#d97706', 'Make a Donation',    'Submit your donation with bank transaction details for verification.'],
                ['bi-shield-check-fill','#faf5ff', '#7c3aed', 'Verified &amp; Track','Finance Staff verifies your transaction. Track your real-world impact live.'],
            ];
            foreach ($steps as $i => [$ic, $bg, $co, $ti, $de]):
            ?>
            <div class="how-card">
                <div class="how-step-num"><?= $i + 1 ?></div>
                <div class="how-icon-wrap" style="background:<?= $bg ?>;color:<?= $co ?>;">
                    <i class="bi <?= $ic ?>"></i>
                </div>
                <div class="how-card-title"><?= $ti ?></div>
                <div class="how-card-desc"><?= $de ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     CTA
═══════════════════════════════════════════════════════════ -->
<section class="cta-section">
    <div class="w">
        <div class="cta-inner">
            <div class="cta-eyebrow">Ready to make an impact?</div>
            <h2 class="cta-title">Join Our Community of Donors</h2>
            <p class="cta-sub">Create a free account and start supporting campaigns that matter. Every donation is verified and tracked with complete transparency.</p>
            <div class="cta-btns">
                <a href="<?= SITE_URL ?>/auth/register.php" class="btn-hero primary">
                    <i class="bi bi-person-plus-fill"></i>Create Account
                </a>
                <a href="<?= SITE_URL ?>/auth/login.php" class="btn-hero outline">
                    <i class="bi bi-box-arrow-in-right"></i>Sign In
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════════════════ -->
<footer class="pub-footer">
    <div class="w">
        <div class="foot-top-grid">

            <!-- Brand column -->
            <div>
                <div class="foot-brand-row">
                    <div class="foot-logo-ico">
                        <i class="bi bi-heart-fill" style="color:white;font-size:.8rem;"></i>
                    </div>
                    <span class="foot-brand-name">VCF Donation System</span>
                </div>
                <p class="foot-about">Supporting education, healthcare, and community development programs across Vietnam through transparent, verified fundraising campaigns.</p>
                <div class="foot-socials">
                    <?php foreach ([['bi-facebook', '#'], ['bi-youtube', '#'], ['bi-linkedin', '#'], ['bi-envelope-fill', 'mailto:info@vcf.org']] as [$ic, $u]): ?>
                    <a href="<?= $u ?>" class="social-btn"><i class="bi <?= $ic ?>"></i></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quick links -->
            <div>
                <div class="foot-col-title">Quick Links</div>
                <?php foreach ([
                    ['Home',           SITE_URL . '/'],
                    ['Campaigns',      '#campaigns'],
                    ['How It Works',   '#how'],
                    ['Our Impact',     '#impact'],
                    ['Sign In',        SITE_URL . '/auth/login.php'],
                    ['Register',       SITE_URL . '/auth/register.php'],
                ] as [$l, $u]): ?>
                <a href="<?= $u ?>" class="foot-link"><i class="bi bi-chevron-right"></i><?= $l ?></a>
                <?php endforeach; ?>
            </div>

            <!-- Programs -->
            <div>
                <div class="foot-col-title">Our Programs</div>
                <?php foreach ([
                    'Education for Children',
                    'Healthcare Access',
                    'Clean Water Initiative',
                    'Scholarship Program',
                    'Elderly Care Support',
                    'Disaster Relief Fund',
                ] as $p): ?>
                <div style="font-size:.77rem;color:rgba(255,255,255,.38);margin-bottom:8px;display:flex;align-items:center;gap:7px;">
                    <div style="width:5px;height:5px;border-radius:50%;background:rgba(239,68,68,.55);flex-shrink:0;"></div>
                    <?= $p ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Contact -->
            <div>
                <div class="foot-col-title">Contact</div>
                <?php foreach ([
                    ['bi-geo-alt-fill',   '3rd Floor, Viet Dragon Tower, 141 Nguyen Du, D1, HCMC'],
                    ['bi-telephone-fill', '(+84) 28 3827 2255'],
                    ['bi-envelope-fill',  'info@vinacapitalfoundation.org'],
                    ['bi-globe',          'vinacapitalfoundation.org'],
                ] as [$ic, $txt]): ?>
                <div class="foot-contact-item">
                    <i class="bi <?= $ic ?>"></i>
                    <span><?= $txt ?></span>
                </div>
                <?php endforeach; ?>
                <div class="foot-nl-label">Stay updated</div>
                <div class="foot-nl-row">
                    <input type="email" placeholder="your@email.com">
                    <button type="button"><i class="bi bi-arrow-right"></i></button>
                </div>
            </div>

        </div><!-- /foot-top-grid -->

        <!-- Stats strip -->
        <div class="foot-stats-row">
            <?php foreach ([
                [number_format($t_raised / 1000000, 1) . 'M VND', 'Total Raised',   '#fbbf24'],
                [$t_donors,                                          'Donors',         '#4ade80'],
                [$t_camps,                                           'Campaigns',      '#60a5fa'],
                [$t_verif,                                           'Verified Txns',  '#f472b6'],
            ] as [$v, $l, $c]): ?>
            <div class="fstat-item">
                <span class="fstat-val" style="color:<?= $c ?>;"><?= $v ?></span>
                <span class="fstat-lbl"><?= $l ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Bottom bar -->
        <div class="foot-bottom-row">
            <span class="foot-copy">&copy; 2026 VinaCapital Foundation. All rights reserved. &middot; Capstone Project II &middot; INS3282</span>
            <div class="foot-legal-links">
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
