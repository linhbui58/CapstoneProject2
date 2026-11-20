<?php
$pageTitle  = 'Campaign Detail';
$activePage = 'campaigns';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('donor');

$db  = getApplicationDB();
$uid = $user['id'];
$id  = (int)($_GET['id'] ?? 0);

if (!$id) { setFlash('danger','Campaign not found.'); redirect(SITE_URL.'/donor/campaigns.php'); }

$campaign = $db->query("
    SELECT c.*, u.name AS created_by_name, DATEDIFF(c.end_date,CURDATE()) AS days_left
    FROM campaigns c JOIN users u ON c.created_by=u.id
    WHERE c.id=$id LIMIT 1
")->fetch_assoc();

if (!$campaign) { setFlash('danger','Campaign not found.'); redirect(SITE_URL.'/donor/campaigns.php'); }

$pct           = $campaign['target_amount']>0 ? min(100,round($campaign['raised_amount']/$campaign['target_amount']*100,1)) : 0;
$total_donors  = (int)$db->query("SELECT COUNT(DISTINCT donor_id) c FROM donations WHERE campaign_id=$id AND status='verified'")->fetch_assoc()['c'];
$total_donated = (float)$db->query("SELECT COALESCE(SUM(amount),0) t FROM donations WHERE campaign_id=$id AND status='verified'")->fetch_assoc()['t'];
$my_donations  = (int)$db->query("SELECT COUNT(*) c FROM donations WHERE campaign_id=$id AND donor_id=$uid")->fetch_assoc()['c'];
$total_all_donations = (int)$db->query("SELECT COUNT(*) c FROM donations WHERE campaign_id=$id")->fetch_assoc()['c'];
$remaining     = max(0, $campaign['target_amount'] - $campaign['raised_amount']);

$messages = $db->query("
    SELECT d.message,d.amount,d.created_at,u.name donor_name
    FROM donations d JOIN users u ON d.donor_id=u.id
    WHERE d.campaign_id=$id AND d.status='verified' AND d.is_anonymous=0
      AND d.message IS NOT NULL AND d.message<>''
    ORDER BY d.created_at DESC LIMIT 8
");

$allocations = $db->query("
    SELECT purpose,description,amount,allocation_date,status,evidence_url,evidence_note
    FROM fund_allocations
    WHERE campaign_id=$id AND status IN('disbursed','completed')
    ORDER BY allocation_date DESC
");

$updates = $db->query("
    SELECT cu.title,cu.content,cu.image_url,cu.created_at,u.name posted_by_name
    FROM campaign_updates cu JOIN users u ON cu.posted_by=u.id
    WHERE cu.campaign_id=$id AND cu.is_published=1
    ORDER BY cu.created_at DESC LIMIT 10
");

$ss_map = [
    'active'    => ['label'=>'Active',    'dot'=>'#22c55e', 'color'=>'#15803d', 'bg'=>'#f0fdf4'],
    'completed' => ['label'=>'Completed', 'dot'=>'#3b82f6', 'color'=>'#1447c0', 'bg'=>'#eff6ff'],
    'draft'     => ['label'=>'Draft',     'dot'=>'#f59e0b', 'color'=>'#92400e', 'bg'=>'#fffbeb'],
    'closed'    => ['label'=>'Closed',    'dot'=>'#94a3b8', 'color'=>'#475569', 'bg'=>'#f8fafc'],
];
$ss = $ss_map[$campaign['status']] ?? $ss_map['closed'];

$alloc_count = $allocations ? $allocations->num_rows : 0;
$upd_count   = $updates     ? $updates->num_rows     : 0;
$msg_count   = $messages    ? $messages->num_rows    : 0;

// Transparency score
$ts_alloc_count    = (int)$db->query("SELECT COUNT(*) c FROM fund_allocations WHERE campaign_id=$id")->fetch_assoc()['c'];
$ts_evidence_count = (int)$db->query("SELECT COUNT(*) c FROM fund_allocations WHERE campaign_id=$id AND evidence_url IS NOT NULL AND evidence_url<>''")->fetch_assoc()['c'];
$ts_disbursed      = (int)$db->query("SELECT COUNT(*) c FROM fund_allocations WHERE campaign_id=$id AND status IN('disbursed','completed')")->fetch_assoc()['c'];
$ts_updates_count  = $upd_count;
$ts_total_alloc    = (float)$db->query("SELECT COALESCE(SUM(amount),0) t FROM fund_allocations WHERE campaign_id=$id")->fetch_assoc()['t'];
$ts_score = 0;
if ($ts_alloc_count > 0)  $ts_score += 25;
if ($ts_disbursed > 0)    $ts_score += min(35, (int)round(($ts_evidence_count / $ts_disbursed) * 35));
if ($ts_updates_count > 0) $ts_score += min(25, $ts_updates_count * 8);
if ($campaign['raised_amount'] > 0 && $ts_total_alloc > 0) $ts_score += min(15, (int)round(min(1, $ts_total_alloc / $campaign['raised_amount']) * 15));
$ts_score = min(100, $ts_score);
$ts_color = $ts_score >= 80 ? '#15803d' : ($ts_score >= 50 ? '#d97706' : '#dc2626');
$ts_bg    = $ts_score >= 80 ? '#f0fdf4'  : ($ts_score >= 50 ? '#fffbeb'  : '#fef2f2');
?>
<style>
/* ── Layout ───────────────────────────────── */
.cd-wrap   { display:grid; grid-template-columns:1fr 300px; gap:28px; align-items:start; }
.cd-main   {}
.cd-side   { position:sticky; top:76px; display:flex; flex-direction:column; gap:12px; }

/* ── Hero ─────────────────────────────────── */
.cd-hero {
    position:relative; height:340px; border-radius:18px; overflow:hidden;
    margin-bottom:24px; background:#e2e8f0;
    box-shadow:0 2px 16px rgba(0,0,0,.10);
}
.cd-hero img { width:100%; height:100%; object-fit:cover; display:block; }
.cd-hero-overlay {
    position:absolute; inset:0;
    background:linear-gradient(to top, rgba(0,0,0,.70) 0%, rgba(0,0,0,.15) 50%, transparent 100%);
}
.cd-hero-top  { position:absolute; top:16px; left:16px; right:16px; display:flex; justify-content:space-between; align-items:flex-start; }
.cd-hero-body { position:absolute; bottom:0; left:0; right:0; padding:20px 22px 22px; }

/* ── Status pill ──────────────────────────── */
.cd-status {
    display:inline-flex; align-items:center; gap:7px;
    background:rgba(255,255,255,.92); backdrop-filter:blur(6px);
    padding:5px 12px; border-radius:20px;
    font-size:.74rem; font-weight:700;
}
.cd-status-dot {
    width:7px; height:7px; border-radius:50%; flex-shrink:0;
}

/* ── Tabs ─────────────────────────────────── */
.cd-tabs {
    display:flex; gap:3px; background:#f1f5f9;
    padding:4px; border-radius:12px; margin-bottom:20px;
}
.cd-tab {
    flex:1; display:flex; align-items:center; justify-content:center; gap:6px;
    padding:9px 10px; border:none; background:transparent; border-radius:9px;
    font-size:.77rem; font-weight:600; color:#64748b; cursor:pointer;
    transition:all .15s;
}
.cd-tab.active { background:white; color:#0f172a; font-weight:700; box-shadow:0 1px 5px rgba(0,0,0,.09); }
.cd-tab:hover:not(.active) { background:rgba(255,255,255,.55); color:#374151; }
.cd-tab .cnt { background:#e2e8f0; color:#64748b; padding:1px 6px; border-radius:8px; font-size:.64rem; font-weight:800; }
.cd-tab.active .cnt { background:#1447c0; color:white; }

/* ── Panels ───────────────────────────────── */
.cd-panel { display:none; }
.cd-panel.show { display:block; }

/* ── Card base ────────────────────────────── */
.cd-card {
    background:white; border:1px solid #e8ecf0;
    border-radius:14px; overflow:hidden;
}

/* ── Progress bar ─────────────────────────── */
.cd-prog-track { height:8px; background:#f1f5f9; border-radius:6px; overflow:hidden; }
.cd-prog-fill  { height:100%; border-radius:6px; transition:width .9s cubic-bezier(.4,0,.2,1); }

/* ── Fund bar (allocations) ───────────────── */
.fund-bar      { height:5px; background:#f1f5f9; border-radius:3px; overflow:hidden; margin:6px 0 0; }
.fund-bar-fill { height:100%; border-radius:3px; background:#f59e0b; transition:width .7s ease; }

/* ── Timeline (updates) ───────────────────── */
.tl { position:relative; padding-left:26px; }
.tl::before { content:''; position:absolute; left:8px; top:4px; bottom:4px; width:2px; background:#e2e8f0; border-radius:2px; }
.tl-item { position:relative; margin-bottom:16px; }
.tl-dot  { position:absolute; left:-20px; top:5px; width:12px; height:12px; border-radius:50%; background:#1447c0; border:2px solid white; box-shadow:0 0 0 2px #bfdbfe; }

/* ── Sidebar card ─────────────────────────── */
.side-card { background:white; border:1px solid #e8ecf0; border-radius:14px; padding:18px; }

/* ── CTA button ───────────────────────────── */
.cta-btn {
    display:flex; align-items:center; justify-content:center; gap:8px;
    width:100%; padding:13px; border-radius:11px;
    background:#dc2626; color:white;
    font-size:.88rem; font-weight:700; text-decoration:none;
    transition:background .15s, transform .15s, box-shadow .15s;
    box-shadow:0 2px 8px rgba(220,38,38,.3);
}
.cta-btn:hover { background:#b91c1c; transform:translateY(-1px); box-shadow:0 4px 14px rgba(220,38,38,.38); }

@media(max-width:900px) {
    .cd-wrap { grid-template-columns:1fr; }
    .cd-side { position:static; }
    .cd-hero { height:260px; }
}
@keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.4} }
</style>

<!-- Breadcrumb -->
<nav style="display:flex;align-items:center;gap:6px;margin-bottom:14px;font-size:.78rem;">
    <a href="<?= SITE_URL ?>/donor/campaigns.php" style="color:#64748b;text-decoration:none;display:flex;align-items:center;gap:4px;" onmouseover="this.style.color='#1447c0'" onmouseout="this.style.color='#64748b'">
        <i class="bi bi-arrow-left" style="font-size:.72rem;"></i> Browse Campaigns
    </a>
    <span style="color:#cbd5e1;">/</span>
    <span style="color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:280px;"><?= htmlspecialchars(mb_strimwidth($campaign['title'],0,50,'…')) ?></span>
</nav>

<!-- Hero -->
<div class="cd-hero">
    <?php if (!empty($campaign['image_url'])): ?>
    <img src="<?= htmlspecialchars(campaignImageUrl($campaign['image_url']), ENT_QUOTES, 'UTF-8') ?>" alt="" onerror="this.style.display='none'">
    <?php endif; ?>
    <div class="cd-hero-overlay"></div>

    <!-- Top row -->
    <div class="cd-hero-top">
        <span class="cd-status" style="color:<?= $ss['color'] ?>;">
            <span class="cd-status-dot" style="background:<?= $ss['dot'] ?>;<?= $campaign['status']==='active'?'animation:pulse-dot 1.6s infinite;':'' ?>"></span>
            <?= $ss['label'] ?>
        </span>
        <?php if ($my_donations > 0): ?>
        <span style="display:inline-flex;align-items:center;gap:5px;background:rgba(21,128,61,.88);backdrop-filter:blur(6px);color:white;padding:5px 11px;border-radius:20px;font-size:.73rem;font-weight:700;">
            <i class="bi bi-heart-fill" style="font-size:.68rem;"></i> You've donated
        </span>
        <?php endif; ?>
    </div>

    <!-- Bottom overlay -->
    <div class="cd-hero-body">
        <h1 style="font-size:1.45rem;font-weight:800;color:white;margin:0 0 12px;line-height:1.25;text-shadow:0 1px 4px rgba(0,0,0,.35);">
            <?= htmlspecialchars($campaign['title']) ?>
        </h1>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
            <div style="flex:1;height:4px;background:rgba(255,255,255,.22);border-radius:3px;overflow:hidden;">
                <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct>=100?'#4ade80':'#60a5fa' ?>;border-radius:3px;"></div>
            </div>
            <span style="font-size:.8rem;font-weight:800;color:white;white-space:nowrap;"><?= $pct ?>%</span>
        </div>
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <span style="font-size:.78rem;color:rgba(255,255,255,.85);font-weight:600;">
                <?= number_format($campaign['raised_amount']/1000000,1) ?>M / <?= number_format($campaign['target_amount']/1000000,0) ?>M VND
            </span>
            <span style="color:rgba(255,255,255,.35);">·</span>
            <span style="font-size:.74rem;color:rgba(255,255,255,.75);display:flex;align-items:center;gap:4px;">
                <i class="bi bi-people-fill" style="font-size:.7rem;"></i> <?= $total_donors ?> donors
            </span>
            <?php if ($campaign['status']==='active' && $campaign['days_left'] > 0): ?>
            <span style="color:rgba(255,255,255,.35);">·</span>
            <span style="font-size:.74rem;color:<?= $campaign['days_left']<=7?'#fbbf24':'rgba(255,255,255,.75)' ?>;display:flex;align-items:center;gap:4px;">
                <i class="bi bi-clock" style="font-size:.68rem;"></i> <?= $campaign['days_left'] ?> days left
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Two-column layout -->
<div class="cd-wrap">

<!-- ════ MAIN COLUMN ════ -->
<div class="cd-main">

    <!-- Tabs -->
    <div class="cd-tabs">
        <button class="cd-tab active" onclick="switchTab('about',this)">
            <i class="bi bi-file-text"></i> About
        </button>
        <?php if ($upd_count > 0): ?>
        <button class="cd-tab" onclick="switchTab('updates',this)">
            <i class="bi bi-broadcast"></i> Updates <span class="cnt"><?= $upd_count ?></span>
        </button>
        <?php endif; ?>
        <?php if ($alloc_count > 0): ?>
        <button class="cd-tab" onclick="switchTab('funds',this)">
            <i class="bi bi-pie-chart"></i> Fund Use <span class="cnt"><?= $alloc_count ?></span>
        </button>
        <?php endif; ?>
        <?php if ($msg_count > 0): ?>
        <button class="cd-tab" onclick="switchTab('messages',this)">
            <i class="bi bi-chat-heart"></i> Community <span class="cnt"><?= $msg_count ?></span>
        </button>
        <?php endif; ?>
    </div>

    <!-- ── TAB: About ── -->
    <div id="tab-about" class="cd-panel show">
        <div class="cd-card">
            <div style="padding:22px 24px;">

                <!-- Description -->
                <p style="font-size:.88rem;color:#374151;line-height:1.85;margin:0 0 24px;"><?= nl2br(htmlspecialchars($campaign['description'])) ?></p>

                <!-- Meta row -->
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding-top:20px;border-top:1px solid #f1f5f9;margin-bottom:24px;">
                    <?php foreach([
                        ['bi-person','#eff6ff','#1d4ed8','Organized by', htmlspecialchars($campaign['created_by_name'])],
                        ['bi-calendar-event','#f0fdf4','#166534','Start date', formatDate($campaign['start_date'])],
                        ['bi-calendar-check','#fef9c3','#92400e','End date',   formatDate($campaign['end_date'])],
                    ] as [$ic,$bg,$co,$lbl,$val]): ?>
                    <div style="background:<?= $bg ?>;border-radius:10px;padding:13px 14px;">
                        <div style="display:flex;align-items:center;gap:5px;margin-bottom:5px;">
                            <i class="bi <?= $ic ?>" style="color:<?= $co ?>;font-size:.8rem;"></i>
                            <span style="font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:<?= $co ?>;"><?= $lbl ?></span>
                        </div>
                        <div style="font-size:.82rem;font-weight:700;color:#111827;"><?= $val ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Stats -->
                <div style="border-top:1px solid #f1f5f9;padding-top:20px;">
                    <p style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin:0 0 12px;">Campaign Statistics</p>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">
                        <?php foreach([
                            ['bi-receipt',          '#f8fafc','#475569', 'Total submitted',   $total_all_donations . ' donations'],
                            ['bi-check-circle',     '#f0fdf4','#166534', 'Verified',          $total_donors . ' donors · ' . number_format($total_donated/1e6,1) . 'M VND'],
                            ['bi-bullseye',         '#f5f3ff','#6d28d9', 'Still needed',      formatCurrency($remaining)],
                        ] as [$ic,$bg,$co,$lbl,$val]): ?>
                        <div style="background:<?= $bg ?>;border-radius:10px;padding:12px 13px;">
                            <div style="display:flex;align-items:center;gap:5px;margin-bottom:4px;">
                                <i class="bi <?= $ic ?>" style="color:<?= $co ?>;font-size:.78rem;"></i>
                                <span style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:<?= $co ?>;"><?= $lbl ?></span>
                            </div>
                            <div style="font-size:.79rem;font-weight:700;color:#111827;line-height:1.4;"><?= $val ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Progress -->
                    <div style="background:#f8fafc;border-radius:11px;padding:16px 18px;">
                        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px;">
                            <span style="font-size:.8rem;font-weight:700;color:#111827;">Fundraising Goal</span>
                            <span style="font-size:.85rem;font-weight:800;color:<?= $pct>=100?'#166534':'#1d4ed8' ?>;"><?= $pct ?>%</span>
                        </div>
                        <div class="cd-prog-track" style="margin-bottom:8px;">
                            <div class="cd-prog-fill" style="width:<?= $pct ?>%;background:<?= $pct>=100?'#22c55e':'#3b82f6' ?>;"></div>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:.74rem;color:#6b7280;">
                            <span><strong style="color:#1d4ed8;"><?= number_format($campaign['raised_amount']/1e6,1) ?>M VND</strong> raised</span>
                            <span>Goal: <strong style="color:#374151;"><?= number_format($campaign['target_amount']/1e6,0) ?>M VND</strong></span>
                        </div>
                    </div>
                </div>

                <?php if ($campaign['status']==='active'): ?>
                <!-- CTA row -->
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:20px;padding-top:18px;border-top:1px solid #f1f5f9;flex-wrap:wrap;">
                    <span style="font-size:.8rem;color:#6b7280;">
                        <?php if ($campaign['days_left'] > 0): ?>
                        <i class="bi bi-clock" style="color:#d97706;"></i>
                        <strong style="color:#d97706;"><?= $campaign['days_left'] ?> days</strong> remaining
                        <?php else: ?>
                        <i class="bi bi-x-circle" style="color:#ef4444;"></i> Campaign has ended
                        <?php endif; ?>
                    </span>
                    <a href="<?= SITE_URL ?>/donor/donate.php?campaign_id=<?= $id ?>" class="cta-btn" style="width:auto;padding:10px 24px;">
                        <i class="bi bi-heart-fill" style="font-size:.8rem;"></i>
                        <?= $my_donations > 0 ? 'Donate Again' : 'Donate Now' ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── TAB: Updates ── -->
    <?php if ($upd_count > 0): ?>
    <div id="tab-updates" class="cd-panel">
        <div class="tl">
        <?php while ($upd = $updates->fetch_assoc()): ?>
        <div class="tl-item">
            <div class="tl-dot"></div>
            <div class="cd-card" style="transition:box-shadow .2s;" onmouseover="this.style.boxShadow='0 4px 18px rgba(0,0,0,.09)'" onmouseout="this.style.boxShadow='none'">
                <?php if ($upd['image_url']): ?>
                <img src="<?= htmlspecialchars(campaignImageUrl($upd['image_url']), ENT_QUOTES, 'UTF-8') ?>" style="width:100%;height:190px;object-fit:cover;display:block;" alt="">
                <?php endif; ?>
                <div style="padding:16px 18px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:8px;">
                        <h3 style="font-size:.9rem;font-weight:700;color:#111827;margin:0;line-height:1.3;"><?= htmlspecialchars($upd['title']) ?></h3>
                        <span style="font-size:.68rem;color:#9ca3af;white-space:nowrap;flex-shrink:0;padding-top:1px;"><?= formatDate($upd['created_at']) ?></span>
                    </div>
                    <p style="font-size:.82rem;color:#4b5563;line-height:1.75;margin:0 0 12px;"><?= nl2br(htmlspecialchars($upd['content'])) ?></p>
                    <div style="display:flex;align-items:center;gap:8px;padding-top:10px;border-top:1px solid #f9fafb;">
                        <div style="width:22px;height:22px;border-radius:50%;background:#1d4ed8;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:800;color:white;flex-shrink:0;">
                            <?= strtoupper(mb_substr($upd['posted_by_name'],0,1)) ?>
                        </div>
                        <span style="font-size:.73rem;color:#6b7280;">Posted by <strong style="color:#374151;"><?= htmlspecialchars($upd['posted_by_name']) ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── TAB: Fund Use ── -->
    <?php if ($alloc_count > 0): ?>
    <div id="tab-funds" class="cd-panel">
        <?php
        $allocations->data_seek(0);
        $total_alloc = 0; $rows = [];
        while ($a = $allocations->fetch_assoc()) { $total_alloc += $a['amount']; $rows[] = $a; }
        $raised_f   = (float)$campaign['raised_amount'];
        $target_f   = (float)$campaign['target_amount'];
        $disbursed_f = $total_alloc;
        $remaining_f = max(0, $raised_f - $disbursed_f);
        $unraised_f  = max(0, $target_f - $raised_f);
        $util_pct    = $raised_f > 0 ? round($disbursed_f / $raised_f * 100, 1) : 0;
        $chart_labels = []; $chart_data = []; $chart_colors = [];
        if ($disbursed_f > 0) { $chart_labels[] = 'Disbursed';              $chart_data[] = round($disbursed_f/1000); $chart_colors[] = '#f59e0b'; }
        if ($remaining_f > 0) { $chart_labels[] = 'Raised – not allocated'; $chart_data[] = round($remaining_f/1000); $chart_colors[] = '#3b82f6'; }
        if ($unraised_f  > 0) { $chart_labels[] = 'Still needed';           $chart_data[] = round($unraised_f/1000);  $chart_colors[] = '#e5e7eb'; }
        ?>

        <!-- Visual breakdown -->
        <div class="cd-card" style="margin-bottom:14px;">
            <div style="padding:20px 22px;">
                <p style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin:0 0 16px;display:flex;align-items:center;gap:5px;">
                    <i class="bi bi-pie-chart-fill" style="color:#f59e0b;"></i> How funds are used
                </p>
                <div style="display:grid;grid-template-columns:150px 1fr;gap:24px;align-items:center;">
                    <!-- Doughnut -->
                    <div style="position:relative;width:150px;height:150px;">
                        <canvas id="fundChart-<?= $id ?>" width="150" height="150"></canvas>
                        <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                            <div style="font-size:1.15rem;font-weight:800;color:#d97706;"><?= $util_pct ?>%</div>
                            <div style="font-size:.59rem;color:#9ca3af;font-weight:600;">utilized</div>
                        </div>
                    </div>
                    <!-- Legend -->
                    <div>
                        <?php
                        $items = [];
                        if ($disbursed_f > 0) $items[] = ['#f59e0b','Disbursed',     formatCurrency($disbursed_f), $raised_f>0?round($disbursed_f/$raised_f*100,1):0,'%'];
                        if ($remaining_f > 0) $items[] = ['#3b82f6','Pending alloc.', formatCurrency($remaining_f), $raised_f>0?round($remaining_f/$raised_f*100,1):0,'%'];
                        if ($unraised_f  > 0) $items[] = ['#e5e7eb','Still needed',   formatCurrency($unraised_f),  $target_f>0?round($unraised_f/$target_f*100,1):0,'% of goal'];
                        foreach ($items as $it): ?>
                        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f9fafb;">
                            <div style="width:10px;height:10px;border-radius:2px;background:<?= $it[0] ?>;flex-shrink:0;"></div>
                            <div style="flex:1;">
                                <div style="font-size:.76rem;font-weight:600;color:#374151;"><?= $it[1] ?></div>
                                <div style="font-size:.7rem;color:#9ca3af;"><?= $it[2] ?> · <?= $it[3] ?><?= $it[4] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div style="margin-top:12px;display:inline-flex;align-items:center;gap:6px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:5px 11px;">
                            <i class="bi bi-shield-check" style="color:#d97706;font-size:.78rem;"></i>
                            <span style="font-size:.71rem;font-weight:700;color:#92400e;">Fund Utilization: <?= $util_pct ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="<?= SITE_URL ?>/assets/js/chart.umd.min.js"></script>
        <script>
        (function(){
            var ctx = document.getElementById('fundChart-<?= $id ?>');
            if (!ctx) return;
            new Chart(ctx,{
                type:'doughnut',
                data:{
                    labels:<?= json_encode($chart_labels) ?>,
                    datasets:[{
                        data:<?= json_encode($chart_data) ?>,
                        backgroundColor:<?= json_encode($chart_colors) ?>,
                        borderWidth:2, borderColor:'#fff', hoverOffset:5
                    }]
                },
                options:{
                    responsive:false, cutout:'68%',
                    plugins:{
                        legend:{display:false},
                        tooltip:{callbacks:{label:function(c){return ' '+new Intl.NumberFormat('vi-VN').format(c.raw*1000)+' VND';}}}
                    }
                }
            });
        })();
        </script>

        <!-- Allocation list -->
        <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($rows as $i => $a):
            $is_done = $a['status']==='completed';
            $pct_a   = $total_alloc > 0 ? min(100, round($a['amount']/$total_alloc*100,1)) : 0;
            $hasEv   = !empty($a['evidence_url']);
            $isPdf   = $hasEv && strtolower(pathinfo($a['evidence_url'],PATHINFO_EXTENSION))==='pdf';
        ?>
        <div class="cd-card">
            <div style="padding:16px 18px;">
                <div style="display:flex;align-items:flex-start;gap:13px;">
                    <!-- Number -->
                    <div style="width:32px;height:32px;border-radius:8px;background:#fef9c3;display:flex;align-items:center;justify-content:center;font-size:.76rem;font-weight:800;color:#92400e;flex-shrink:0;">
                        <?= $i+1 ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <!-- Title row -->
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                            <span style="font-size:.86rem;font-weight:700;color:#111827;"><?= htmlspecialchars($a['purpose']) ?></span>
                            <span style="font-size:.66rem;font-weight:700;padding:2px 9px;border-radius:20px;white-space:nowrap;
                                background:<?= $is_done?'#f0fdf4':'#fef9c3' ?>;
                                color:<?= $is_done?'#166534':'#92400e' ?>;
                                border:1px solid <?= $is_done?'#bbf7d0':'#fde68a' ?>;">
                                <?= ucfirst($a['status']) ?>
                            </span>
                        </div>
                        <?php if ($a['description']): ?>
                        <p style="font-size:.77rem;color:#6b7280;margin:0 0 10px;line-height:1.6;"><?= htmlspecialchars($a['description']) ?></p>
                        <?php endif; ?>
                        <!-- Amount -->
                        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:1px;">
                            <span style="font-size:.84rem;font-weight:800;color:#d97706;"><?= formatCurrency($a['amount']) ?></span>
                            <span style="font-size:.69rem;color:#9ca3af;"><?= $pct_a ?>% · <?= formatDate($a['allocation_date']) ?></span>
                        </div>
                        <div class="fund-bar"><div class="fund-bar-fill" style="width:<?= $pct_a ?>%;"></div></div>

                        <!-- Evidence -->
                        <?php if ($hasEv): ?>
                        <div style="margin-top:12px;padding-top:11px;border-top:1px solid #f9fafb;">
                            <div style="font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;margin-bottom:7px;display:flex;align-items:center;gap:4px;">
                                <i class="bi bi-shield-check-fill" style="color:#22c55e;"></i> Evidence
                            </div>
                            <?php if ($isPdf): ?>
                            <a href="<?= SITE_URL.'/'.htmlspecialchars($a['evidence_url']) ?>" target="_blank"
                               style="display:inline-flex;align-items:center;gap:7px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:7px 13px;border-radius:8px;font-size:.75rem;font-weight:700;text-decoration:none;">
                                <i class="bi bi-file-pdf-fill"></i> View PDF Receipt
                                <i class="bi bi-box-arrow-up-right" style="font-size:.65rem;"></i>
                            </a>
                            <?php else: ?>
                            <a href="<?= SITE_URL.'/'.htmlspecialchars($a['evidence_url']) ?>" target="_blank"
                               style="display:inline-flex;align-items:center;gap:10px;text-decoration:none;border:1px solid #e5e7eb;border-radius:9px;padding:7px 11px;background:#f9fafb;transition:border-color .15s;"
                               onmouseover="this.style.borderColor='#d97706'" onmouseout="this.style.borderColor='#e5e7eb'">
                                <img src="<?= SITE_URL.'/'.htmlspecialchars($a['evidence_url']) ?>" style="width:52px;height:38px;object-fit:cover;border-radius:6px;" alt="Receipt">
                                <div>
                                    <div style="font-size:.76rem;font-weight:700;color:#111827;">View Receipt</div>
                                    <?php if ($a['evidence_note']): ?><div style="font-size:.67rem;color:#9ca3af;"><?= htmlspecialchars($a['evidence_note']) ?></div><?php endif; ?>
                                </div>
                                <i class="bi bi-box-arrow-up-right" style="color:#9ca3af;font-size:.75rem;margin-left:auto;"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div style="margin-top:9px;font-size:.71rem;color:#d1d5db;display:flex;align-items:center;gap:4px;">
                            <i class="bi bi-clock" style="font-size:.68rem;"></i> Receipt not yet uploaded
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── TAB: Community ── -->
    <?php if ($msg_count > 0): ?>
    <div id="tab-messages" class="cd-panel">
        <div style="display:flex;flex-direction:column;gap:9px;">
        <?php while ($m = $messages->fetch_assoc()): ?>
        <div class="cd-card" style="transition:box-shadow .15s;" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow='none'">
            <div style="padding:14px 16px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                    <div style="width:34px;height:34px;border-radius:50%;background:#7c3aed;display:flex;align-items:center;justify-content:center;font-size:.74rem;font-weight:800;color:white;flex-shrink:0;">
                        <?= strtoupper(mb_substr($m['donor_name'],0,1)) ?>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:.82rem;font-weight:700;color:#111827;"><?= htmlspecialchars($m['donor_name']) ?></div>
                        <div style="font-size:.68rem;color:#9ca3af;"><?= formatDate($m['created_at']) ?></div>
                    </div>
                    <span style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;padding:3px 10px;border-radius:20px;font-size:.73rem;font-weight:700;white-space:nowrap;"><?= formatCurrency($m['amount']) ?></span>
                </div>
                <p style="font-size:.82rem;color:#4b5563;margin:0;line-height:1.7;padding:10px 13px;background:#faf5ff;border-radius:8px;border-left:3px solid #c4b5fd;font-style:italic;">"<?= htmlspecialchars($m['message']) ?>"</p>
            </div>
        </div>
        <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ════ SIDEBAR ════ -->
<div class="cd-side">

    <!-- Progress card -->
    <div style="background:white;border:1px solid #e8ecf0;border-radius:14px;overflow:hidden;">
        <div style="height:3px;background:<?= $pct>=100?'#22c55e':'#3b82f6' ?>;"></div>
        <div style="padding:18px 20px;">
            <p style="font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin:0 0 6px;">Fundraising Progress</p>
            <div style="font-size:1.9rem;font-weight:900;color:<?= $pct>=100?'#166534':'#1d4ed8' ?>;letter-spacing:-.05em;line-height:1;margin-bottom:2px;">
                <?= number_format($campaign['raised_amount']/1e6,1) ?><span style="font-size:.8rem;font-weight:700;"> M</span>
            </div>
            <p style="font-size:.73rem;color:#9ca3af;margin:0 0 14px;">of <?= number_format($campaign['target_amount']/1e6,0) ?>M VND goal</p>
            <div class="cd-prog-track" style="margin-bottom:6px;">
                <div class="cd-prog-fill" style="width:<?= $pct ?>%;background:<?= $pct>=100?'#22c55e':'#3b82f6' ?>;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:.75rem;margin-bottom:16px;">
                <span style="font-weight:800;color:<?= $pct>=100?'#166534':'#1d4ed8' ?>;"><?= $pct ?>% funded</span>
                <?php if ($campaign['status']==='active'): ?>
                <?php if ($campaign['days_left'] > 0): ?>
                <span style="color:<?= $campaign['days_left']<=7?'#ef4444':'#6b7280' ?>;font-weight:<?= $campaign['days_left']<=7?700:400 ?>;">
                    <i class="bi bi-clock" style="font-size:.67rem;"></i> <?= $campaign['days_left'] ?>d left
                </span>
                <?php else: ?>
                <span style="color:#ef4444;font-weight:700;"><i class="bi bi-x-circle-fill" style="font-size:.67rem;"></i> Ended</span>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Donor / raised row -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;">
                <div style="background:#eff6ff;border-radius:10px;padding:11px;text-align:center;">
                    <div style="font-size:1.1rem;font-weight:800;color:#1d4ed8;"><?= $total_donors ?></div>
                    <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#3b82f6;margin-top:1px;">Donors</div>
                </div>
                <div style="background:#f0fdf4;border-radius:10px;padding:11px;text-align:center;">
                    <div style="font-size:.88rem;font-weight:800;color:#166534;"><?= number_format($total_donated/1e6,1) ?>M</div>
                    <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#16a34a;margin-top:1px;">VND raised</div>
                </div>
            </div>

            <?php if ($campaign['status']==='active'): ?>
            <a href="<?= SITE_URL ?>/donor/donate.php?campaign_id=<?= $id ?>" class="cta-btn">
                <i class="bi bi-heart-fill" style="font-size:.82rem;"></i>
                <?= $my_donations > 0 ? 'Donate Again' : 'Donate Now' ?>
            </a>
            <?php else: ?>
            <div style="text-align:center;padding:11px;background:#f9fafb;border-radius:10px;font-size:.8rem;color:#9ca3af;font-weight:600;border:1px solid #f1f5f9;">
                <i class="bi bi-lock"></i> Campaign has ended
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($my_donations > 0): ?>
    <!-- Supporter badge -->
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:13px 15px;display:flex;align-items:center;gap:11px;">
        <div style="width:36px;height:36px;background:#22c55e;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-heart-fill" style="color:white;font-size:.82rem;"></i>
        </div>
        <div>
            <div style="font-size:.82rem;font-weight:700;color:#166534;">You're a supporter 🎉</div>
            <div style="font-size:.71rem;color:#16a34a;margin-top:1px;"><?= $my_donations ?> donation<?= $my_donations>1?'s':'' ?> to this campaign</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick actions -->
    <div class="side-card">
        <p style="font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;margin:0 0 10px;">Quick Actions</p>
        <?php foreach([
            [SITE_URL.'/donor/campaigns.php',    'bi-grid',           '#1d4ed8','All Campaigns'],
            [SITE_URL.'/donor/my_donations.php', 'bi-receipt-cutoff', '#7c3aed','My Donations'],
        ] as [$url,$ic,$co,$lbl]): ?>
        <a href="<?= $url ?>"
           style="display:flex;align-items:center;gap:8px;padding:9px 11px;border:1px solid #f1f5f9;border-radius:9px;color:#4b5563;font-size:.79rem;font-weight:600;text-decoration:none;margin-bottom:6px;background:white;transition:all .13s;"
           onmouseover="this.style.borderColor='<?= $co ?>';this.style.color='<?= $co ?>'" onmouseout="this.style.borderColor='#f1f5f9';this.style.color='#4b5563'">
            <i class="bi <?= $ic ?>" style="color:<?= $co ?>;font-size:.87rem;"></i> <?= $lbl ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Transparency note -->
    <div style="background:#1e3a8a;border-radius:12px;padding:15px 17px;">
        <div style="font-size:.75rem;font-weight:700;color:white;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
            <i class="bi bi-shield-check-fill" style="color:#4ade80;font-size:.82rem;"></i> 100% Transparent
        </div>
        <div style="font-size:.72rem;color:rgba(255,255,255,.6);line-height:1.65;">Every donation is verified by Finance Staff. Fund usage is tracked with evidence and published here.</div>
    </div>

    <!-- Transparency Score -->
    <div class="side-card" style="border-color:<?= $ts_score>=80?'#bbf7d0':($ts_score>=50?'#fde68a':'#fecaca') ?>;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <span style="font-size:.76rem;font-weight:700;color:<?= $ts_color ?>;">Fund Transparency</span>
            <span style="font-size:1rem;font-weight:900;color:<?= $ts_color ?>;"><?= $ts_score ?><span style="font-size:.68rem;font-weight:600;">/100</span></span>
        </div>
        <div class="cd-prog-track" style="margin-bottom:12px;">
            <div class="cd-prog-fill" style="width:<?= $ts_score ?>%;background:<?= $ts_color ?>;"></div>
        </div>
        <?php
        $criteria = [
            [$ts_alloc_count > 0,   'Allocation records',     '+25'],
            [$ts_disbursed>0 && $ts_evidence_count>=$ts_disbursed, 'All disbursements evidenced', '+35'],
            [$ts_updates_count > 0, 'Progress updates posted', '+25'],
            [$campaign['raised_amount']>0 && $ts_total_alloc>0, 'Funds actively utilized', '+15'],
        ];
        foreach($criteria as [$met,$lbl,$pts]): ?>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:7px;">
            <i class="bi bi-<?= $met?'check-circle-fill':'circle' ?>" style="color:<?= $met?$ts_color:'#d1d5db' ?>;font-size:.76rem;flex-shrink:0;"></i>
            <span style="font-size:.72rem;color:<?= $met?'#374151':'#9ca3af' ?>;flex:1;"><?= $lbl ?></span>
            <span style="font-size:.67rem;font-weight:700;color:<?= $met?$ts_color:'#d1d5db' ?>;"><?= $pts ?></span>
        </div>
        <?php endforeach; ?>
    </div>

</div><!-- end sidebar -->
</div><!-- end grid -->

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.cd-panel').forEach(p => p.classList.remove('show'));
    document.querySelectorAll('.cd-tab').forEach(b => b.classList.remove('active'));
    var panel = document.getElementById('tab-' + name);
    if (panel) panel.classList.add('show');
    btn.classList.add('active');
}
</script>
<style>
@keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.35} }
</style>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
