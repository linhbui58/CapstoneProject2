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
    'active'    =>['bg'=>'#dcfce7','color'=>'#15803d','border'=>'#86efac','dot'=>'#22c55e'],
    'completed' =>['bg'=>'#dbeafe','color'=>'#1447c0','border'=>'#93c5fd','dot'=>'#3b82f6'],
    'draft'     =>['bg'=>'#fef9c3','color'=>'#854d0e','border'=>'#fde68a','dot'=>'#d97706'],
    'closed'    =>['bg'=>'#f1f5f9','color'=>'#64748b','border'=>'#e2e8f0','dot'=>'#94a3b8'],
];
$ss = $ss_map[$campaign['status']] ?? $ss_map['closed'];

$alloc_count = $allocations ? $allocations->num_rows : 0;
$upd_count   = $updates     ? $updates->num_rows     : 0;
$msg_count   = $messages    ? $messages->num_rows    : 0;
?>

<style>
/* ── tab system ──────────────────────────────── */
.cd-tabs { display:flex; gap:4px; background:#f1f5f9; padding:5px; border-radius:13px; margin-bottom:22px; }
.cd-tab  {
    flex:1; padding:9px 12px; border:none; background:transparent; border-radius:9px;
    font-size:.78rem; font-weight:600; color:#64748b; cursor:pointer; transition:all .18s;
    display:flex; align-items:center; justify-content:center; gap:6px;
}
.cd-tab.active { background:white; color:#0f172a; font-weight:700; box-shadow:0 1px 6px rgba(0,0,0,.08); }
.cd-tab:hover:not(.active) { background:rgba(255,255,255,.6); color:#334155; }
.cd-tab .badge { background:#e2e8f0; color:#64748b; padding:1px 7px; border-radius:9px; font-size:.65rem; font-weight:800; }
.cd-tab.active .badge { background:#1447c0; color:white; }

/* ── tab panels ─────────────────────────────── */
.cd-panel { display:none; }
.cd-panel.show { display:block; }

/* ── timeline ────────────────────────────────── */
.timeline { position:relative; padding-left:28px; }
.timeline::before { content:''; position:absolute; left:9px; top:6px; bottom:6px; width:2px; background:linear-gradient(180deg,#c4b5fd,#ddd6fe); border-radius:2px; }
.tl-item { position:relative; margin-bottom:18px; }
.tl-dot  { position:absolute; left:-22px; top:4px; width:14px; height:14px; border-radius:50%; border:2.5px solid white; box-shadow:0 0 0 2px #c4b5fd; flex-shrink:0; }

/* ── fund bar ────────────────────────────────── */
.fund-bar { height:8px; background:#f1f5f9; border-radius:4px; overflow:hidden; margin:5px 0; }
.fund-bar-fill { height:100%; border-radius:4px; background:linear-gradient(90deg,#f59e0b,#d97706); transition:width .7s ease; }
</style>

<!-- ── Breadcrumb ────────────────────────────── -->
<div style="display:flex;align-items:center;gap:6px;margin-bottom:14px;">
    <a href="<?= SITE_URL ?>/donor/campaigns.php"
       style="font-size:.78rem;color:#64748b;text-decoration:none;display:flex;align-items:center;gap:4px;transition:color .14s;"
       onmouseover="this.style.color='#1447c0'" onmouseout="this.style.color='#64748b'">
        <i class="bi bi-arrow-left"></i>Browse Campaigns
    </a>
    <span style="color:#e2e8f0;font-size:.8rem;">/</span>
    <span style="font-size:.78rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:300px;"><?= htmlspecialchars(mb_strimwidth($campaign['title'],0,45,'…')) ?></span>
</div>

<!-- ── Hero image ───────────────────────────── -->
<div style="position:relative;height:320px;border-radius:20px;overflow:hidden;margin-bottom:22px;box-shadow:0 8px 32px rgba(0,0,0,.15);">
    <?php if (!empty($campaign['image_url'])): ?>
    <img src="<?= htmlspecialchars(campaignImageUrl($campaign['image_url']), ENT_QUOTES, 'UTF-8') ?>" alt=""
         style="width:100%;height:100%;object-fit:cover;display:block;"
         onerror="this.style.display='none'">
    <?php endif; ?>
    <div style="position:absolute;inset:0;background:linear-gradient(0deg,rgba(0,0,0,.72) 0%,rgba(0,0,0,.1) 55%,transparent 100%);"></div>

    <!-- Status pill -->
    <div style="position:absolute;top:16px;left:16px;">
        <span style="display:inline-flex;align-items:center;gap:6px;background:white;padding:5px 13px;border-radius:20px;font-size:.76rem;font-weight:700;color:<?= $ss['color'] ?>;box-shadow:0 2px 8px rgba(0,0,0,.15);">
            <span style="width:7px;height:7px;border-radius:50%;background:<?= $ss['dot'] ?>;flex-shrink:0;<?= $campaign['status']==='active'?'animation:pulse-dot 1.5s infinite;':'' ?>"></span>
            <?= ucfirst($campaign['status']) ?>
        </span>
    </div>

    <?php if ($my_donations > 0): ?>
    <div style="position:absolute;top:16px;right:16px;">
        <span style="display:inline-flex;align-items:center;gap:5px;background:rgba(34,197,94,.9);backdrop-filter:blur(8px);color:white;padding:5px 12px;border-radius:20px;font-size:.74rem;font-weight:700;">
            <i class="bi bi-heart-fill" style="font-size:.7rem;"></i>You've donated
        </span>
    </div>
    <?php endif; ?>

    <!-- Bottom overlay -->
    <div style="position:absolute;bottom:0;left:0;right:0;padding:22px 24px;">
        <h1 style="font-size:1.5rem;font-weight:800;color:white;margin:0 0 10px;line-height:1.2;text-shadow:0 1px 6px rgba(0,0,0,.4);">
            <?= htmlspecialchars($campaign['title']) ?>
        </h1>
        <!-- Progress bar in hero -->
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="flex:1;height:5px;background:rgba(255,255,255,.25);border-radius:3px;overflow:hidden;">
                <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct>=100?'#22c55e':'#60a5fa' ?>;border-radius:3px;"></div>
            </div>
            <span style="font-size:.82rem;font-weight:800;color:white;white-space:nowrap;"><?= $pct ?>%</span>
        </div>
        <div style="display:flex;align-items:center;gap:16px;margin-top:7px;flex-wrap:wrap;">
            <span style="font-size:.79rem;color:rgba(255,255,255,.85);font-weight:600;"><?= number_format($campaign['raised_amount']/1000000,1) ?>M / <?= number_format($campaign['target_amount']/1000000,0) ?>M VND raised</span>
            <span style="font-size:.75rem;color:rgba(255,255,255,.6);">·</span>
            <span style="font-size:.75rem;color:rgba(255,255,255,.75);"><i class="bi bi-people-fill" style="font-size:.72rem;"></i> <?= $total_donors ?> donors</span>
            <?php if ($campaign['status']==='active' && $campaign['days_left']>0): ?>
            <span style="font-size:.75rem;color:rgba(255,255,255,.6);">·</span>
            <span style="font-size:.75rem;color:<?= $campaign['days_left']<=7?'#fbbf24':'rgba(255,255,255,.75)' ?>;">
                <i class="bi bi-clock" style="font-size:.72rem;"></i> <?= $campaign['days_left'] ?> days left
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Two-column layout ─────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 290px;gap:22px;align-items:start;">

<!-- ════ LEFT COLUMN ═════════════════════════ -->
<div>
    <!-- Tab navigation -->
    <div class="cd-tabs">
        <button class="cd-tab active" onclick="switchTab('about',this)">
            <i class="bi bi-file-text-fill"></i>About
        </button>
        <?php if ($upd_count > 0): ?>
        <button class="cd-tab" onclick="switchTab('updates',this)">
            <i class="bi bi-broadcast"></i>Updates
            <span class="badge"><?= $upd_count ?></span>
        </button>
        <?php endif; ?>
        <?php if ($alloc_count > 0): ?>
        <button class="cd-tab" onclick="switchTab('funds',this)">
            <i class="bi bi-pie-chart-fill"></i>Fund Use
            <span class="badge"><?= $alloc_count ?></span>
        </button>
        <?php endif; ?>
        <?php if ($msg_count > 0): ?>
        <button class="cd-tab" onclick="switchTab('messages',this)">
            <i class="bi bi-chat-heart-fill"></i>Community
            <span class="badge"><?= $msg_count ?></span>
        </button>
        <?php endif; ?>
    </div>

    <!-- ── TAB: About ──────────────────────── -->
    <div id="tab-about" class="cd-panel show">
        <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,.04);">
            <!-- Description -->
            <p style="font-size:.87rem;color:#374151;line-height:1.8;margin:0 0 22px;"><?= nl2br(htmlspecialchars($campaign['description'])) ?></p>

            <!-- Meta cards -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;padding-top:18px;border-top:1px solid #f1f5f9;margin-bottom:22px;">
                <?php foreach([
                    ['bi-person-badge-fill','#eff6ff','#1447c0','Organized by',htmlspecialchars($campaign['created_by_name'])],
                    ['bi-calendar2-event-fill','#f0fdf4','#15803d','Start Date',formatDate($campaign['start_date'])],
                    ['bi-calendar2-check-fill','#fff7ed','#c2410c','End Date',formatDate($campaign['end_date'])],
                ] as [$ic,$bg,$co,$lbl,$val]): ?>
                <div style="background:<?= $bg ?>;border-radius:12px;padding:14px;">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                        <i class="bi <?= $ic ?>" style="color:<?= $co ?>;font-size:.82rem;"></i>
                        <span style="font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:<?= $co ?>;"><?= $lbl ?></span>
                    </div>
                    <div style="font-size:.84rem;font-weight:700;color:#0f172a;"><?= $val ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Campaign stats strip -->
            <div style="border-top:1px solid #f1f5f9;padding-top:18px;">
                <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-bottom:14px;">Campaign Statistics</div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px;">
                    <?php
                    $total_all_donations = (int)$db->query("SELECT COUNT(*) c FROM donations WHERE campaign_id=$id")->fetch_assoc()['c'];
                    $pending_donations   = (int)$db->query("SELECT COUNT(*) c FROM donations WHERE campaign_id=$id AND status='pending'")->fetch_assoc()['c'];
                    $remaining = max(0, $campaign['target_amount'] - $campaign['raised_amount']);
                    foreach([
                        ['bi-receipt','#f1f5f9','#475569','Total Donations', $total_all_donations . ' submitted'],
                        ['bi-check-circle-fill','#dcfce7','#15803d','Verified', $total_donors . ' donors · ' . number_format($total_donated/1000000,1) . 'M VND'],
                        ['bi-bullseye','#ede9fe','#7c3aed','Still Needed', formatCurrency($remaining)],
                    ] as [$ic,$bg,$co,$lbl,$val]): ?>
                    <div style="background:<?= $bg ?>;border-radius:11px;padding:13px;">
                        <div style="display:flex;align-items:center;gap:5px;margin-bottom:5px;">
                            <i class="bi <?= $ic ?>" style="color:<?= $co ?>;font-size:.8rem;"></i>
                            <span style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:<?= $co ?>;"><?= $lbl ?></span>
                        </div>
                        <div style="font-size:.8rem;font-weight:700;color:#0f172a;line-height:1.4;"><?= $val ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Progress visualisation -->
                <div style="background:#f8fafc;border-radius:12px;padding:16px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                        <span style="font-size:.78rem;font-weight:700;color:#0f172a;">Fundraising Goal Progress</span>
                        <span style="font-size:.8rem;font-weight:800;color:<?= $pct>=100?'#15803d':'#1447c0' ?>;"><?= $pct ?>%</span>
                    </div>
                    <div style="height:10px;background:#e2e8f0;border-radius:5px;overflow:hidden;margin-bottom:8px;">
                        <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct>=100?'linear-gradient(90deg,#22c55e,#16a34a)':'linear-gradient(90deg,#60a5fa,#1447c0)' ?>;border-radius:5px;transition:width .8s;"></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:.74rem;color:#64748b;">
                        <span><strong style="color:#1447c0;"><?= number_format($campaign['raised_amount']/1000000,1) ?>M VND</strong> raised</span>
                        <span>Goal: <strong style="color:#475569;"><?= number_format($campaign['target_amount']/1000000,0) ?>M VND</strong></span>
                    </div>
                </div>
            </div>

            <?php if ($campaign['status']==='active'): ?>
            <!-- CTA inside About tab -->
            <div style="margin-top:18px;padding-top:18px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                <div style="font-size:.82rem;color:#475569;">
                    <?php if ($campaign['days_left'] > 0): ?>
                    <i class="bi bi-clock-fill" style="color:#d97706;"></i>
                    <strong style="color:#d97706;"><?= $campaign['days_left'] ?> days</strong> remaining to reach the goal
                    <?php else: ?>
                    <i class="bi bi-x-circle-fill" style="color:#ef4444;"></i> Campaign has ended
                    <?php endif; ?>
                </div>
                <a href="<?= SITE_URL ?>/donor/donate.php?campaign_id=<?= $id ?>"
                   style="display:inline-flex;align-items:center;gap:7px;padding:10px 22px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border-radius:10px;font-size:.84rem;font-weight:700;text-decoration:none;box-shadow:0 3px 12px rgba(239,68,68,.3);transition:all .18s;"
                   onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 5px 18px rgba(239,68,68,.4)'"
                   onmouseout="this.style.transform='none';this.style.boxShadow='0 3px 12px rgba(239,68,68,.3)'">
                    <i class="bi bi-heart-fill" style="font-size:.82rem;"></i>
                    <?= $my_donations > 0 ? 'Donate Again' : 'Donate Now' ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── TAB: Updates ────────────────────── -->
    <?php if ($upd_count > 0): ?>
    <div id="tab-updates" class="cd-panel">
        <div class="timeline">
        <?php while ($upd = $updates->fetch_assoc()): ?>
        <div class="tl-item">
            <div class="tl-dot" style="background:#7c3aed;"></div>
            <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:box-shadow .2s;"
                 onmouseover="this.style.boxShadow='0 6px 20px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow='0 2px 8px rgba(0,0,0,.04)'">
                <?php if ($upd['image_url']): ?>
                <img src="<?= htmlspecialchars(campaignImageUrl($upd['image_url']), ENT_QUOTES, 'UTF-8') ?>"
                     style="width:100%;height:200px;object-fit:cover;display:block;" alt="">
                <?php endif; ?>
                <div style="padding:16px 18px;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px;">
                        <h3 style="font-size:.92rem;font-weight:700;color:#0f172a;margin:0;line-height:1.3;"><?= htmlspecialchars($upd['title']) ?></h3>
                        <span style="font-size:.68rem;color:#94a3b8;white-space:nowrap;flex-shrink:0;margin-top:3px;"><?= formatDate($upd['created_at']) ?></span>
                    </div>
                    <p style="font-size:.82rem;color:#475569;line-height:1.7;margin:0 0 12px;"><?= nl2br(htmlspecialchars($upd['content'])) ?></p>
                    <div style="display:flex;align-items:center;gap:8px;padding-top:10px;border-top:1px solid #f8fafc;">
                        <div style="width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#6d28d9);display:flex;align-items:center;justify-content:center;font-size:.62rem;font-weight:800;color:white;flex-shrink:0;">
                            <?= strtoupper(mb_substr($upd['posted_by_name'],0,1)) ?>
                        </div>
                        <span style="font-size:.74rem;color:#64748b;">Posted by <strong style="color:#374151;"><?= htmlspecialchars($upd['posted_by_name']) ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── TAB: Fund Use ───────────────────── -->
    <?php if ($alloc_count > 0): ?>
    <div id="tab-funds" class="cd-panel">
        <!-- Summary bar -->
        <?php
        $allocations->data_seek(0);
        $total_alloc = 0;
        $rows = [];
        while ($a = $allocations->fetch_assoc()) { $total_alloc += $a['amount']; $rows[] = $a; }
        ?>
        <div style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1.5px solid #fde68a;border-radius:14px;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
                <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#92400e;margin-bottom:4px;">Total Disbursed / Completed</div>
                <div style="font-size:1.4rem;font-weight:900;color:#d97706;letter-spacing:-.03em;"><?= number_format($total_alloc/1000000,1) ?>M <span style="font-size:.8rem;font-weight:600;color:#a16207;">VND</span></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:.68rem;color:#92400e;font-weight:600;margin-bottom:3px;"><?= $alloc_count ?> allocation<?= $alloc_count>1?'s':'' ?></div>
                <div style="font-size:.74rem;color:#a16207;"><?= $campaign['raised_amount']>0 ? round($total_alloc/$campaign['raised_amount']*100,1) : 0 ?>% of funds raised</div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($rows as $i => $a):
            $as_c = $a['status']==='completed' ? '#15803d' : '#854d0e';
            $as_b = $a['status']==='completed' ? '#dcfce7' : '#fef9c3';
            $pct_a = $total_alloc > 0 ? min(100, round($a['amount']/$total_alloc*100,1)) : 0;
            $hasEv = !empty($a['evidence_url']);
            $isPdf = $hasEv && strtolower(pathinfo($a['evidence_url'],PATHINFO_EXTENSION))==='pdf';
        ?>
        <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:18px;box-shadow:0 1px 6px rgba(0,0,0,.04);">
            <div style="display:flex;align-items:flex-start;gap:14px;">
                <!-- Index circle -->
                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;color:white;flex-shrink:0;box-shadow:0 3px 8px rgba(217,119,6,.3);">
                    <?= $i+1 ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                        <span style="font-size:.88rem;font-weight:700;color:#0f172a;"><?= htmlspecialchars($a['purpose']) ?></span>
                        <span style="background:<?= $as_b ?>;color:<?= $as_c ?>;padding:2px 9px;border-radius:20px;font-size:.67rem;font-weight:700;white-space:nowrap;border:1px solid <?= $a['status']==='completed'?'#bbf7d0':'#fde68a' ?>;">
                            <?= ucfirst($a['status']) ?>
                        </span>
                    </div>
                    <?php if ($a['description']): ?>
                    <p style="font-size:.78rem;color:#64748b;margin:0 0 10px;line-height:1.55;"><?= htmlspecialchars($a['description']) ?></p>
                    <?php endif; ?>
                    <!-- Amount + bar -->
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
                        <span style="font-size:.82rem;font-weight:800;color:#d97706;"><?= formatCurrency($a['amount']) ?></span>
                        <span style="font-size:.7rem;color:#94a3b8;"><?= $pct_a ?>% of allocations · <?= formatDate($a['allocation_date']) ?></span>
                    </div>
                    <div class="fund-bar"><div class="fund-bar-fill" style="width:<?= $pct_a ?>%;"></div></div>

                    <!-- Evidence -->
                    <?php if ($hasEv): ?>
                    <div style="margin-top:12px;padding-top:12px;border-top:1px solid #f8fafc;">
                        <div style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:7px;display:flex;align-items:center;gap:4px;">
                            <i class="bi bi-shield-check-fill" style="color:#22c55e;"></i> Evidence Attached
                        </div>
                        <?php if ($isPdf): ?>
                        <a href="<?= SITE_URL.'/'.htmlspecialchars($a['evidence_url']) ?>" target="_blank"
                           style="display:inline-flex;align-items:center;gap:7px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:7px 14px;border-radius:9px;font-size:.76rem;font-weight:700;text-decoration:none;">
                            <i class="bi bi-file-pdf-fill" style="font-size:.95rem;"></i>View Invoice / Receipt (PDF)
                            <i class="bi bi-box-arrow-up-right" style="font-size:.68rem;"></i>
                        </a>
                        <?php else: ?>
                        <a href="<?= SITE_URL.'/'.htmlspecialchars($a['evidence_url']) ?>" target="_blank"
                           style="display:inline-flex;align-items:center;gap:10px;text-decoration:none;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:8px 12px;transition:border-color .15s;"
                           onmouseover="this.style.borderColor='#d97706'" onmouseout="this.style.borderColor='#e2e8f0'">
                            <img src="<?= SITE_URL.'/'.htmlspecialchars($a['evidence_url']) ?>"
                                 style="width:56px;height:42px;object-fit:cover;border-radius:7px;" alt="Receipt">
                            <div>
                                <div style="font-size:.77rem;font-weight:700;color:#0f172a;">View Receipt Photo</div>
                                <?php if ($a['evidence_note']): ?><div style="font-size:.68rem;color:#94a3b8;"><?= htmlspecialchars($a['evidence_note']) ?></div><?php endif; ?>
                            </div>
                            <i class="bi bi-box-arrow-up-right" style="color:#94a3b8;font-size:.78rem;margin-left:auto;"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div style="margin-top:10px;font-size:.72rem;color:#cbd5e1;display:flex;align-items:center;gap:4px;">
                        <i class="bi bi-clock" style="font-size:.7rem;"></i> Receipt not yet uploaded
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── TAB: Community ──────────────────── -->
    <?php if ($msg_count > 0): ?>
    <div id="tab-messages" class="cd-panel">
        <div style="display:flex;flex-direction:column;gap:10px;">
        <?php while ($m = $messages->fetch_assoc()): ?>
        <div style="background:white;border:1px solid #f3e8ff;border-radius:14px;padding:16px;box-shadow:0 1px 6px rgba(0,0,0,.04);transition:box-shadow .2s;"
             onmouseover="this.style.boxShadow='0 4px 14px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow='0 1px 6px rgba(0,0,0,.04)'">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <div style="width:36px;height:36px;background:linear-gradient(135deg,#db2777,#9333ea);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.76rem;font-weight:800;color:white;flex-shrink:0;box-shadow:0 3px 8px rgba(147,51,234,.25);">
                    <?= strtoupper(mb_substr($m['donor_name'],0,1)) ?>
                </div>
                <div style="flex:1;">
                    <div style="font-size:.82rem;font-weight:700;color:#0f172a;"><?= htmlspecialchars($m['donor_name']) ?></div>
                    <div style="font-size:.69rem;color:#94a3b8;"><?= formatDate($m['created_at']) ?></div>
                </div>
                <span style="background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:700;white-space:nowrap;"><?= formatCurrency($m['amount']) ?></span>
            </div>
            <p style="font-size:.82rem;color:#475569;margin:0;line-height:1.65;padding:10px 12px;background:#fdf4ff;border-radius:9px;border-left:3px solid #d8b4fe;font-style:italic;">"<?= htmlspecialchars($m['message']) ?>"</p>
        </div>
        <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ════ RIGHT SIDEBAR ═══════════════════════ -->
<div style="display:flex;flex-direction:column;gap:14px;position:sticky;top:80px;">

    <!-- Progress card -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.07);">
        <!-- Top accent bar -->
        <div style="height:4px;background:<?= $pct>=100?'linear-gradient(90deg,#22c55e,#16a34a)':'linear-gradient(90deg,#3b82f6,#1447c0)' ?>;"></div>
        <div style="padding:20px;">
            <div style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:8px;">Fundraising Progress</div>
            <div style="font-size:2rem;font-weight:900;color:<?= $pct>=100?'#15803d':'#1447c0' ?>;letter-spacing:-.05em;line-height:1;margin-bottom:2px;">
                <?= number_format($campaign['raised_amount']/1000000,1) ?><span style="font-size:.85rem;font-weight:700;">M</span>
            </div>
            <div style="font-size:.74rem;color:#94a3b8;margin-bottom:14px;">of <?= number_format($campaign['target_amount']/1000000,0) ?>M VND goal</div>
            <div style="height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden;margin-bottom:5px;">
                <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct>=100?'linear-gradient(90deg,#22c55e,#16a34a)':'linear-gradient(90deg,#60a5fa,#1447c0)' ?>;border-radius:4px;transition:width .8s ease;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:.76rem;margin-bottom:18px;">
                <span style="font-weight:800;color:<?= $pct>=100?'#15803d':'#1447c0' ?>;"><?= $pct ?>% funded</span>
                <?php if ($campaign['status']==='active'): ?>
                <?php if ($campaign['days_left']>0): ?>
                <span style="color:<?= $campaign['days_left']<=7?'#ef4444':'#64748b' ?>;font-weight:<?= $campaign['days_left']<=7?'700':'500' ?>;">
                    <i class="bi bi-clock" style="font-size:.68rem;"></i> <?= $campaign['days_left'] ?>d left
                </span>
                <?php else: ?>
                <span style="color:#ef4444;font-weight:700;"><i class="bi bi-x-circle-fill" style="font-size:.68rem;"></i> Ended</span>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Stats row -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px;">
                <div style="background:#eff6ff;border-radius:11px;padding:11px 13px;text-align:center;">
                    <div style="font-size:1.1rem;font-weight:800;color:#1447c0;letter-spacing:-.02em;"><?= $total_donors ?></div>
                    <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#3b82f6;margin-top:2px;">Donors</div>
                </div>
                <div style="background:#f0fdf4;border-radius:11px;padding:11px 13px;text-align:center;">
                    <div style="font-size:.88rem;font-weight:800;color:#15803d;letter-spacing:-.02em;"><?= number_format($total_donated/1000000,1) ?>M</div>
                    <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#16a34a;margin-top:2px;">Verified VND</div>
                </div>
            </div>

            <?php if ($campaign['status']==='active'): ?>
            <a href="<?= SITE_URL ?>/donor/donate.php?campaign_id=<?= $id ?>"
               style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:13px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border-radius:12px;font-size:.9rem;font-weight:700;text-decoration:none;box-shadow:0 4px 16px rgba(239,68,68,.35);transition:all .2s;letter-spacing:-.01em;"
               onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 7px 22px rgba(239,68,68,.48)'"
               onmouseout="this.style.transform='none';this.style.boxShadow='0 4px 16px rgba(239,68,68,.35)'">
                <i class="bi bi-heart-fill" style="font-size:.88rem;"></i>
                <?= $my_donations>0 ? 'Donate Again' : 'Donate Now' ?>
            </a>
            <?php else: ?>
            <div style="text-align:center;padding:11px;background:#f8fafc;border-radius:11px;font-size:.8rem;color:#94a3b8;font-weight:600;border:1.5px solid #f1f5f9;">
                <i class="bi bi-lock-fill me-1"></i>Campaign has ended
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($my_donations > 0): ?>
    <!-- Supporter badge -->
    <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #86efac;border-radius:14px;padding:15px 16px;display:flex;align-items:center;gap:12px;">
        <div style="width:40px;height:40px;background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 12px rgba(34,197,94,.3);">
            <i class="bi bi-heart-fill" style="color:white;font-size:.88rem;"></i>
        </div>
        <div>
            <div style="font-size:.82rem;font-weight:700;color:#15803d;">You're a supporter! 🎉</div>
            <div style="font-size:.73rem;color:#16a34a;margin-top:1px;"><?= $my_donations ?> donation<?= $my_donations>1?'s':'' ?> to this campaign</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick actions -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:16px;">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-bottom:12px;">Quick Actions</div>
        <a href="<?= SITE_URL ?>/donor/campaigns.php"
           style="display:flex;align-items:center;gap:9px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;color:#475569;font-size:.8rem;font-weight:600;text-decoration:none;transition:all .14s;margin-bottom:8px;background:white;"
           onmouseover="this.style.background='#f8fafc';this.style.borderColor='#1447c0';this.style.color='#1447c0'"
           onmouseout="this.style.background='white';this.style.borderColor='#e2e8f0';this.style.color='#475569'">
            <i class="bi bi-grid-fill" style="color:#1447c0;font-size:.9rem;"></i>All Campaigns
        </a>
        <a href="<?= SITE_URL ?>/donor/my_donations.php"
           style="display:flex;align-items:center;gap:9px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;color:#475569;font-size:.8rem;font-weight:600;text-decoration:none;transition:all .14s;background:white;"
           onmouseover="this.style.background='#faf5ff';this.style.borderColor='#7c3aed';this.style.color='#7c3aed'"
           onmouseout="this.style.background='white';this.style.borderColor='#e2e8f0';this.style.color='#475569'">
            <i class="bi bi-receipt-cutoff" style="color:#7c3aed;font-size:.9rem;"></i>My Donations
        </a>
    </div>

    <!-- Transparency note -->
    <div style="background:linear-gradient(135deg,#0d2157,#1447c0);border-radius:14px;padding:16px 18px;">
        <div style="font-size:.76rem;font-weight:700;color:white;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
            <i class="bi bi-shield-check-fill" style="color:#4ade80;"></i>100% Transparent
        </div>
        <div style="font-size:.73rem;color:rgba(255,255,255,.65);line-height:1.6;">Every donation is verified by Finance Staff. Fund usage is tracked with evidence and published here.</div>
    </div>
</div>

</div><!-- end grid -->

<style>
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.8)} }
</style>
<script>
function switchTab(name, btn) {
    // Hide all panels
    document.querySelectorAll('.cd-panel').forEach(p => p.classList.remove('show'));
    document.querySelectorAll('.cd-tab').forEach(b => b.classList.remove('active'));
    // Show selected
    const panel = document.getElementById('tab-' + name);
    if (panel) panel.classList.add('show');
    btn.classList.add('active');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
