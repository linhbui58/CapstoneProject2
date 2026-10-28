<?php
$pageTitle  = 'My Impact Summary';
$activePage = 'impact';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('donor');

$db  = getApplicationDB();
$uid = $user['id'];

// ── Overall donor stats ───────────────────────────
$overall = $db->query("
    SELECT
        COUNT(d.id) as total_donations,
        COALESCE(SUM(d.amount),0) as total_amount,
        COUNT(DISTINCT d.campaign_id) as campaigns_supported,
        MIN(d.created_at) as first_donation,
        MAX(d.created_at) as last_donation
    FROM donations d
    WHERE d.donor_id=$uid AND d.status='verified'
")->fetch_assoc();

// ── Donations per campaign ────────────────────────
$by_campaign = $db->query("
    SELECT c.id, c.title, c.description, c.target_amount, c.raised_amount,
           c.status as camp_status, c.image_url,
           SUM(d.amount) as my_amount,
           COUNT(d.id) as my_donations,
           ROUND((SUM(d.amount)/c.raised_amount)*100, 1) as my_contribution_pct,
           ROUND((c.raised_amount/c.target_amount)*100, 1) as camp_pct
    FROM donations d
    JOIN campaigns c ON d.campaign_id = c.id
    WHERE d.donor_id=$uid AND d.status='verified' AND c.id IS NOT NULL
    GROUP BY c.id
    ORDER BY my_amount DESC
");

// ── Fund allocations for campaigns I donated to ───
$campaign_ids = [];
if($by_campaign && $by_campaign->num_rows > 0) {
    $by_campaign->data_seek(0);
    while($r = $by_campaign->fetch_assoc()) $campaign_ids[] = $r['id'];
    $by_campaign->data_seek(0);
}
$alloc_map = [];
if(!empty($campaign_ids)) {
    $ids_str = implode(',', $campaign_ids);
    $allocs = $db->query("
        SELECT fa.campaign_id, fa.purpose, fa.description, fa.amount, fa.status, fa.allocation_date
        FROM fund_allocations fa
        WHERE fa.campaign_id IN ($ids_str)
        ORDER BY fa.allocation_date DESC
    ");
    if($allocs) while($a=$allocs->fetch_assoc()) {
        $alloc_map[$a['campaign_id']][] = $a;
    }
}

// ── Segment info ──────────────────────────────────
$segment_info = $db->query("SELECT segment, total_donated, total_campaigns FROM donor_profiles WHERE user_id=$uid")->fetch_assoc();
$seg_labels = [
    'new'        => ['New Donor',     'bi-person-plus-fill', '#dbeafe','#1447c0'],
    'repeat'     => ['Repeat Donor',  'bi-arrow-repeat',     '#dcfce7','#15803d'],
    'loyal'      => ['Loyal Donor',   'bi-heart-fill',       '#ede9fe','#7c3aed'],
    'high_value' => ['High-Value',    'bi-star-fill',        '#fef9c3','#d97706'],
    'lapsed'     => ['Lapsed Donor',  'bi-clock-history',    '#fee2e2','#991b1b'],
];
$seg = $segment_info['segment'] ?? 'new';
[$seg_label,$seg_icon,$seg_bg,$seg_color] = $seg_labels[$seg] ?? $seg_labels['new'];

$total_amount     = (float)$overall['total_amount'];
$total_donations  = (int)$overall['total_donations'];
$camps_supported  = (int)$overall['campaigns_supported'];
?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <span style="width:36px;height:36px;background:#fef9c3;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-award-fill" style="color:#d97706;font-size:.9rem;"></i>
            </span>
            My Impact Summary
            <span style="background:#fef9c3;color:#854d0e;padding:3px 10px;border-radius:20px;font-size:.68rem;font-weight:700;">Advanced Feature</span>
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0 0 0 45px;">See how your donations have made a real difference</p>
    </div>
    <a href="<?= SITE_URL ?>/donor/my_donations.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border:1.5px solid #e2e8f0;border-radius:10px;color:#64748b;font-size:.81rem;font-weight:600;background:white;text-decoration:none;transition:all .15s;white-space:nowrap;"
       onmouseover="this.style.background='#f8fafc';this.style.borderColor='#cbd5e1'" onmouseout="this.style.background='white';this.style.borderColor='#e2e8f0'">
        <i class="bi bi-receipt"></i> My Donations
    </a>
</div>

<?php if($total_donations === 0): ?>
<!-- No donations yet -->
<div style="background:white;border:1px solid #e2e8f0;border-radius:16px;padding:64px 20px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.04);">
    <div style="width:72px;height:72px;background:linear-gradient(135deg,#fef9c3,#fde68a);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
        <i class="bi bi-heart" style="font-size:1.8rem;color:#d97706;"></i>
    </div>
    <div style="font-size:.95rem;font-weight:700;color:#0f172a;margin-bottom:6px;">Your Impact Journey Starts Here</div>
    <div style="font-size:.83rem;color:#64748b;margin-bottom:20px;max-width:360px;margin-left:auto;margin-right:auto;">
        Make your first verified donation to see how your contribution is making a difference in Vietnam.
    </div>
    <a href="<?= SITE_URL ?>/donor/donate.php"
       style="display:inline-flex;align-items:center;gap:7px;padding:11px 24px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border-radius:10px;font-size:.85rem;font-weight:700;text-decoration:none;box-shadow:0 4px 14px rgba(239,68,68,.3);">
        <i class="bi bi-heart-fill"></i>Make Your First Donation
    </a>
</div>
<?php else: ?>

<!-- ── HERO: Personal impact statement ─────────── -->
<div style="background:linear-gradient(135deg,#0a1628 0%,#0d2157 55%,#1447c0 100%);border-radius:20px;padding:32px 36px;margin-bottom:22px;position:relative;overflow:hidden;">
    <!-- Decorative circles -->
    <div style="position:absolute;width:320px;height:320px;border-radius:50%;background:rgba(255,255,255,.03);top:-120px;right:-80px;pointer-events:none;"></div>
    <div style="position:absolute;width:200px;height:200px;border-radius:50%;background:rgba(239,68,68,.06);bottom:-60px;left:15%;pointer-events:none;"></div>
    <div style="position:absolute;width:120px;height:120px;border-radius:50%;background:rgba(251,191,36,.05);top:20px;left:40%;pointer-events:none;"></div>

    <div style="position:relative;z-index:1;display:grid;grid-template-columns:1fr auto;gap:28px;align-items:center;">
        <div>
            <div style="display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:20px;padding:4px 12px;margin-bottom:14px;">
                <i class="bi bi-stars" style="color:#fbbf24;font-size:.72rem;"></i>
                <span style="font-size:.68rem;color:rgba(255,255,255,.5);font-weight:600;text-transform:uppercase;letter-spacing:.08em;">Your Giving Impact</span>
            </div>
            <h2 style="font-size:1.65rem;font-weight:800;color:white;letter-spacing:-.03em;line-height:1.2;margin-bottom:12px;">
                Thank you, <?= htmlspecialchars(explode(' ',$user['name'])[0]) ?>!<br>
                <span style="background:linear-gradient(135deg,#fbbf24,#f97316);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">You've made a difference.</span>
            </h2>
            <p style="color:rgba(255,255,255,.6);font-size:.86rem;line-height:1.7;margin-bottom:20px;max-width:480px;">
                Your generosity has contributed
                <strong style="color:#fbbf24;"><?= number_format($total_amount/1000000,1) ?>M VND</strong>
                across <strong style="color:#4ade80;"><?= $camps_supported ?> campaign<?= $camps_supported>1?'s':'' ?></strong>,
                helping communities across Vietnam access education, healthcare, and clean water.
            </p>
            <!-- Segment badge -->
            <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.1);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.18);padding:8px 18px;border-radius:20px;">
                <i class="bi <?= $seg_icon ?>" style="color:#fbbf24;font-size:.9rem;"></i>
                <span style="font-size:.81rem;font-weight:700;color:white;">Status: <?= $seg_label ?></span>
            </div>
        </div>

        <!-- Stats card -->
        <div style="text-align:center;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:18px;padding:24px 30px;min-width:160px;">
            <div style="font-size:2.6rem;font-weight:900;color:#fbbf24;letter-spacing:-.05em;line-height:1;"><?= number_format($total_amount/1000000,1) ?>M</div>
            <div style="font-size:.7rem;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.08em;font-weight:700;margin-top:4px;margin-bottom:16px;">VND Donated</div>

            <div style="height:1px;background:rgba(255,255,255,.08);margin-bottom:14px;"></div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div>
                    <div style="font-size:1.4rem;font-weight:800;color:#4ade80;letter-spacing:-.02em;"><?= $total_donations ?></div>
                    <div style="font-size:.63rem;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.07em;font-weight:600;margin-top:2px;">Donation<?= $total_donations>1?'s':'' ?></div>
                </div>
                <div>
                    <div style="font-size:1.4rem;font-weight:800;color:#60a5fa;letter-spacing:-.02em;"><?= $camps_supported ?></div>
                    <div style="font-size:.63rem;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.07em;font-weight:600;margin-top:2px;">Campaign<?= $camps_supported>1?'s':'' ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Per-campaign impact cards ────────────────── -->
<div style="margin-bottom:20px;">
    <div style="font-size:.9rem;font-weight:700;color:#0f172a;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
        <span style="width:28px;height:28px;background:#dbeafe;border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-collection-fill" style="color:#1447c0;font-size:.76rem;"></i>
        </span>
        Your Campaign Impact
    </div>

    <div style="display:flex;flex-direction:column;gap:16px;">
    <?php
    if($by_campaign && $by_campaign->num_rows > 0):
        $by_campaign->data_seek(0);
        while($c = $by_campaign->fetch_assoc()):
            $camp_pct = min(100,(float)($c['camp_pct']??0));
            $my_pct   = (float)($c['my_contribution_pct']??0);
            $allocs   = $alloc_map[$c['id']] ?? [];
            $done     = $c['camp_status'] === 'completed' || $camp_pct >= 100;
    ?>
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);">

        <!-- Campaign header -->
        <div style="display:grid;grid-template-columns:160px 1fr;min-height:120px;">
            <!-- Image -->
            <div style="position:relative;overflow:hidden;background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
                <?php if($c['image_url']): ?>
                <img src="<?= htmlspecialchars(campaignImageUrl($c['image_url']), ENT_QUOTES, 'UTF-8') ?>"
                     style="width:100%;height:100%;object-fit:cover;display:block;"
                     onerror="this.style.display='none'">
                <?php else: ?>
                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-megaphone-fill" style="font-size:2rem;color:rgba(255,255,255,.2);"></i>
                </div>
                <?php endif; ?>
                <?php if($done): ?>
                <div style="position:absolute;inset:0;background:rgba(22,163,74,.6);display:flex;align-items:center;justify-content:center;">
                    <div style="text-align:center;">
                        <i class="bi bi-check-circle-fill" style="font-size:1.8rem;color:white;display:block;margin-bottom:4px;"></i>
                        <div style="font-size:.7rem;font-weight:700;color:white;">Goal Reached!</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div style="padding:16px 20px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:8px;gap:12px;">
                    <h3 style="font-size:.9rem;font-weight:700;color:#0f172a;margin:0;line-height:1.3;"><?= htmlspecialchars($c['title']) ?></h3>
                    <span style="background:<?= $done?'#dcfce7':'#dbeafe' ?>;color:<?= $done?'#15803d':'#1447c0' ?>;border:1px solid <?= $done?'#bbf7d0':'#bfdbfe' ?>;padding:3px 10px;border-radius:20px;font-size:.69rem;font-weight:700;white-space:nowrap;flex-shrink:0;">
                        <?= $done ? 'Completed' : ucfirst($c['camp_status']) ?>
                    </span>
                </div>

                <!-- Your contribution highlight -->
                <div style="background:linear-gradient(135deg,#fffbeb,#fef9c3);border:1.5px solid #fde68a;border-radius:10px;padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:28px;height:28px;background:#f59e0b;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-heart-fill" style="color:white;font-size:.72rem;"></i>
                        </div>
                        <span style="font-size:.78rem;font-weight:600;color:#854d0e;">Your contribution</span>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:.95rem;font-weight:800;color:#b45309;"><?= number_format($c['my_amount']/1000000,1) ?>M VND</div>
                        <div style="font-size:.67rem;color:#92400e;margin-top:1px;"><?= $my_pct ?>% of total raised</div>
                    </div>
                </div>

                <!-- Campaign progress -->
                <div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                        <span style="font-size:.7rem;color:#64748b;">Campaign progress</span>
                        <span style="font-size:.72rem;font-weight:800;color:<?= $done?'#15803d':'#1447c0' ?>;"><?= $camp_pct ?>%</span>
                    </div>
                    <div style="height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:<?= $camp_pct ?>%;background:<?= $done?'linear-gradient(90deg,#22c55e,#16a34a)':'linear-gradient(90deg,#3b82f6,#1447c0)' ?>;border-radius:3px;"></div>
                    </div>
                    <div style="font-size:.67rem;color:#94a3b8;margin-top:3px;"><?= number_format($c['raised_amount']/1000000,1) ?>M raised of <?= number_format($c['target_amount']/1000000,0) ?>M VND goal</div>
                </div>
            </div>
        </div>

        <!-- Fund allocations — how money was used -->
        <?php if(!empty($allocs)): ?>
        <div style="padding:14px 20px;border-top:1px solid #f1f5f9;background:#fafafa;">
            <div style="font-size:.75rem;font-weight:700;color:#374151;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                <i class="bi bi-pie-chart-fill" style="color:#d97706;font-size:.78rem;"></i>
                How Your Donation Was Used
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;">
                <?php foreach(array_slice($allocs,0,4) as $al):
                    $al_styles = [
                        'planned'   => ['#eff6ff','#1447c0','bi-calendar-check'],
                        'disbursed' => ['#fef9c3','#d97706','bi-cash-coin'],
                        'completed' => ['#dcfce7','#15803d','bi-check-circle-fill'],
                    ];
                    [$al_bg,$al_co,$al_ic] = $al_styles[$al['status']] ?? $al_styles['planned'];
                ?>
                <div style="background:<?= $al_bg ?>;border:1px solid;border-color:<?= $al_co ?>22;border-radius:9px;padding:10px 12px;display:flex;align-items:flex-start;gap:8px;">
                    <i class="bi <?= $al_ic ?>" style="color:<?= $al_co ?>;font-size:.82rem;flex-shrink:0;margin-top:1px;"></i>
                    <div style="min-width:0;">
                        <div style="font-size:.77rem;font-weight:700;color:#0f172a;margin-bottom:2px;"><?= htmlspecialchars($al['purpose']) ?></div>
                        <?php if($al['description']): ?>
                        <div style="font-size:.68rem;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($al['description']) ?></div>
                        <?php endif; ?>
                        <div style="font-size:.72rem;font-weight:800;color:<?= $al_co ?>;margin-top:3px;"><?= number_format($al['amount']/1000000,1) ?>M VND · <?= ucfirst($al['status']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(count($allocs) > 4): ?>
                <div style="background:#f1f5f9;border-radius:9px;padding:10px 12px;display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:.75rem;color:#64748b;font-weight:600;">+<?= count($allocs)-4 ?> more allocation<?= count($allocs)-4>1?'s':'' ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div style="padding:12px 20px;border-top:1px solid #f1f5f9;background:#fafafa;">
            <div style="font-size:.74rem;color:#94a3b8;display:flex;align-items:center;gap:6px;">
                <i class="bi bi-clock" style="font-size:.72rem;"></i>
                Fund allocation details will be published when the campaign completes.
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endwhile;
    endif; ?>
    </div>
</div>

<!-- ── Milestones / achievements ─────────────────── -->
<div style="background:white;border:1px solid #e2e8f0;border-radius:16px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.04);margin-bottom:20px;">
    <div style="font-size:.9rem;font-weight:700;color:#0f172a;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
        <span style="width:28px;height:28px;background:#fef9c3;border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-trophy-fill" style="color:#d97706;font-size:.76rem;"></i>
        </span>
        Your Achievements
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
        <?php
        $milestones = [
            [$total_donations >= 1,  'bi-heart-fill',         '#fef9c3','#d97706', 'First Donation',      'You made your first donation!'],
            [$total_donations >= 3,  'bi-arrow-repeat',       '#dcfce7','#15803d', '3 Donations',         'You\'re a repeat donor!'],
            [$total_amount >= 1000000,'bi-cash-coin',         '#ede9fe','#7c3aed', '1M VND Milestone',    'You\'ve given over 1M VND!'],
            [$camps_supported >= 2,  'bi-collection-fill',    '#dbeafe','#1447c0', 'Multi-Campaign',      'Supporting multiple campaigns!'],
            [$total_donations >= 5,  'bi-star-fill',          '#fef9c3','#d97706', 'Loyal Supporter',     'Over 5 donations — thank you!'],
            [$total_amount >= 5000000,'bi-award-fill',        '#ede9fe','#7c3aed', '5M VND Champion',     'Donated over 5M VND total!'],
            [$camps_supported >= 3,  'bi-people-fill',        '#dcfce7','#15803d', 'Community Builder',   'Supporting 3+ campaigns!'],
            [$total_donations >= 10, 'bi-gem',                '#dbeafe','#1447c0', 'Diamond Donor',       'Incredible — 10+ donations!'],
        ];
        foreach($milestones as [$earned,$icon,$bg,$color,$title,$desc]):
        ?>
        <div style="border:2px solid <?= $earned?$bg.'88':'#f1f5f9' ?>;border-radius:12px;padding:14px;text-align:center;background:<?= $earned?$bg:'#fafafa' ?>;opacity:<?= $earned?'1':'.45' ?>;transition:all .2s;">
            <div style="width:40px;height:40px;border-radius:50%;background:<?= $earned?$color:'#e2e8f0' ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">
                <i class="bi <?= $icon ?>" style="color:<?= $earned?'white':'#94a3b8' ?>;font-size:.9rem;"></i>
            </div>
            <div style="font-size:.77rem;font-weight:700;color:<?= $earned?'#0f172a':'#94a3b8' ?>;margin-bottom:3px;"><?= $title ?></div>
            <div style="font-size:.68rem;color:<?= $earned?'#64748b':'#cbd5e1' ?>;line-height:1.4;"><?= $desc ?></div>
            <?php if($earned): ?>
            <div style="margin-top:6px;"><i class="bi bi-check-circle-fill" style="color:<?= $color ?>;font-size:.8rem;"></i></div>
            <?php else: ?>
            <div style="margin-top:6px;font-size:.65rem;color:#94a3b8;">Locked</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── CTA ───────────────────────────────────────── -->
<div style="background:linear-gradient(135deg,#0a1628,#0d2157,#1447c0);border-radius:16px;padding:24px 28px;text-align:center;">
    <div style="font-size:.72rem;color:rgba(255,255,255,.35);font-weight:700;letter-spacing:.09em;text-transform:uppercase;margin-bottom:8px;">Keep making a difference</div>
    <div style="font-size:1.1rem;font-weight:800;color:white;letter-spacing:-.02em;margin-bottom:6px;">Every donation counts. Every life matters.</div>
    <div style="font-size:.82rem;color:rgba(255,255,255,.55);margin-bottom:18px;">Explore more campaigns and continue your impact journey.</div>
    <a href="<?= SITE_URL ?>/donor/campaigns.php"
       style="display:inline-flex;align-items:center;gap:7px;padding:11px 24px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border-radius:10px;font-size:.86rem;font-weight:700;text-decoration:none;box-shadow:0 4px 16px rgba(239,68,68,.4);transition:all .2s;"
       onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
        <i class="bi bi-heart-fill"></i>Donate Again
    </a>
</div>

<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
