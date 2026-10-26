<?php
// Shared notifications page
// Required vars from caller: $uid, $back_url, $color

$db = getApplicationDB();

if (isset($_GET['mark_read'])) {
    $db->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
    redirect($back_url);
}
if (isset($_GET['read_id'])) {
    $rid = (int)$_GET['read_id'];
    $db->query("UPDATE notifications SET is_read=1 WHERE id=$rid AND user_id=$uid");
    redirect($back_url);
}

$notifications = $db->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC");
$unread = (int)$db->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid AND is_read=0")->fetch_assoc()['c'];
$total  = $notifications ? $notifications->num_rows : 0;

// Type styles as simple function to avoid array destructure issues
function ns($type) {
    $map = array(
        'confirmation'    => array('bg'=>'#dcfce7','color'=>'#15803d','border'=>'#bbf7d0','icon'=>'bi-check-circle-fill','label'=>'Confirmation'),
        'rejection'       => array('bg'=>'#fee2e2','color'=>'#991b1b','border'=>'#fecaca','icon'=>'bi-x-circle-fill',    'label'=>'Rejection'),
        'campaign_update' => array('bg'=>'#dbeafe','color'=>'#1447c0','border'=>'#bfdbfe','icon'=>'bi-megaphone-fill',   'label'=>'Campaign'),
        'system'          => array('bg'=>'#fef9c3','color'=>'#854d0e','border'=>'#fde68a','icon'=>'bi-bell-fill',        'label'=>'System'),
    );
    return isset($map[$type]) ? $map[$type] : $map['system'];
}

// Count per type
function countType($db, $uid, $type) {
    $t = $db->real_escape_string($type);
    $r = $db->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid AND type='$t'");
    return $r ? (int)$r->fetch_assoc()['c'] : 0;
}
?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-bell-fill" style="color:<?= $color ?>;font-size:1rem;"></i>
            Notifications
            <?php if($unread > 0): ?>
            <span style="background:#ef4444;color:white;padding:2px 9px;border-radius:20px;font-size:.7rem;font-weight:800;box-shadow:0 2px 6px rgba(239,68,68,.3);"><?= $unread ?> new</span>
            <?php endif; ?>
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;"><?= $total ?> notification<?= $total != 1 ? 's' : '' ?> total<?= $unread > 0 ? ', '.$unread.' unread' : '' ?></p>
    </div>
    <?php if($unread > 0): ?>
    <a href="<?= $back_url ?>?mark_read=1"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:9px;color:#64748b;font-size:.8rem;font-weight:600;background:white;text-decoration:none;white-space:nowrap;transition:all .15s;"
       onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
        <i class="bi bi-check-all"></i>Mark All as Read
    </a>
    <?php endif; ?>
</div>

<!-- Type stats -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
    <?php
    $stat_types = array('confirmation','rejection','campaign_update','system');
    foreach($stat_types as $st):
        $style = ns($st);
        $cnt   = countType($db, $uid, $st);
    ?>
    <div style="background:white;border:1px solid #e2e8f0;border-radius:12px;padding:13px 14px;box-shadow:0 1px 4px rgba(0,0,0,.04);display:flex;align-items:center;gap:10px;">
        <div style="width:34px;height:34px;border-radius:9px;background:<?= $style['bg'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi <?= $style['icon'] ?>" style="color:<?= $style['color'] ?>;font-size:.88rem;"></i>
        </div>
        <div>
            <div style="font-size:1.1rem;font-weight:800;color:#0f172a;letter-spacing:-.02em;line-height:1.1;"><?= $cnt ?></div>
            <div style="font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-top:1px;"><?= $style['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Notification list -->
<div style="background:white;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden;">

    <!-- Card header -->
    <div style="padding:13px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i class="bi bi-inbox" style="color:<?= $color ?>;font-size:.88rem;"></i>
            <span style="font-size:.86rem;font-weight:700;color:#0f172a;">All Notifications</span>
        </div>
        <?php if($unread > 0): ?>
        <span style="background:#fee2e2;color:#991b1b;padding:2px 9px;border-radius:20px;font-size:.69rem;font-weight:700;"><?= $unread ?> unread</span>
        <?php else: ?>
        <span style="background:#dcfce7;color:#15803d;padding:2px 9px;border-radius:20px;font-size:.69rem;font-weight:700;display:flex;align-items:center;gap:4px;">
            <i class="bi bi-check2-all" style="font-size:.68rem;"></i>All read
        </span>
        <?php endif; ?>
    </div>

    <!-- Items -->
    <?php if($notifications && $notifications->num_rows > 0): ?>
    <div>
        <?php
        $notifications->data_seek(0);
        while($n = $notifications->fetch_assoc()):
            $ns       = ns($n['type']);
            $is_unread = !(bool)$n['is_read'];
            $row_bg   = $is_unread ? '#fafbff' : 'white';
        ?>
        <div style="display:flex;gap:14px;padding:14px 18px;border-bottom:1px solid #f9fafb;background:<?= $row_bg ?>;transition:background .12s;"
             onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='<?= $row_bg ?>'">

            <!-- Icon circle -->
            <div style="width:42px;height:42px;border-radius:50%;background:<?= $ns['bg'] ?>;border:2px solid <?= $ns['border'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;">
                <i class="bi <?= $ns['icon'] ?>" style="color:<?= $ns['color'] ?>;font-size:.95rem;"></i>
            </div>

            <!-- Content -->
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:3px;">
                    <div style="font-size:.83rem;font-weight:<?= $is_unread ? '700' : '600' ?>;color:#0f172a;">
                        <?= htmlspecialchars($n['title']) ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:7px;flex-shrink:0;">
                        <?php if($is_unread): ?>
                        <span style="width:8px;height:8px;border-radius:50%;background:#3b82f6;flex-shrink:0;"></span>
                        <?php endif; ?>
                        <span style="font-size:.69rem;color:#94a3b8;white-space:nowrap;"><?= formatDateTime($n['created_at']) ?></span>
                    </div>
                </div>

                <div style="font-size:.78rem;color:#64748b;line-height:1.55;margin-bottom:7px;">
                    <?= htmlspecialchars($n['message']) ?>
                </div>

                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $ns['bg'] ?>;color:<?= $ns['color'] ?>;border:1px solid <?= $ns['border'] ?>;padding:2px 9px;border-radius:20px;font-size:.67rem;font-weight:700;">
                        <?= $ns['label'] ?>
                    </span>
                    <?php if($is_unread): ?>
                    <a href="<?= $back_url ?>?read_id=<?= $n['id'] ?>"
                       style="font-size:.7rem;color:#64748b;text-decoration:none;display:flex;align-items:center;gap:3px;transition:color .14s;"
                       onmouseover="this.style.color='#1447c0'" onmouseout="this.style.color='#64748b'">
                        <i class="bi bi-check2" style="font-size:.72rem;"></i>Mark read
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <?php else: ?>
    <!-- Empty state -->
    <div style="padding:64px 20px;text-align:center;">
        <div style="width:72px;height:72px;background:#f1f5f9;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
            <i class="bi bi-bell-slash" style="font-size:1.8rem;color:#94a3b8;"></i>
        </div>
        <div style="font-size:.88rem;font-weight:700;color:#94a3b8;margin-bottom:4px;">No notifications yet</div>
        <div style="font-size:.78rem;color:#cbd5e1;">System notifications will appear here</div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <?php if($total > 0): ?>
    <div style="padding:11px 18px;border-top:1px solid #f8fafc;background:#fafafa;display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:.72rem;color:#94a3b8;">
            <?= $total ?> total &nbsp;·&nbsp; <?= $unread ?> unread &nbsp;·&nbsp; <?= $total - $unread ?> read
        </span>
        <?php if($unread > 0): ?>
        <a href="<?= $back_url ?>?mark_read=1"
           style="font-size:.72rem;color:#1447c0;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:4px;"
           onmouseover="this.style.color='#0d2157'" onmouseout="this.style.color='#1447c0'">
            <i class="bi bi-check-all" style="font-size:.76rem;"></i>Mark all as read
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
