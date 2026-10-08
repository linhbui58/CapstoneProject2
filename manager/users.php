<?php
$pageTitle  = 'Manage Users';
$activePage = 'users';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('manager');

$db  = getDB();
$uid = $user['id'];
$errors = [];

// Toggle active
if (isset($_GET['toggle']) && (int)$_GET['toggle'] !== $uid) {
    $tid = (int)$_GET['toggle'];
    $db->query("UPDATE users SET is_active = NOT is_active WHERE id=$tid");
    setFlash('success', 'User status updated.');
    redirect(SITE_URL . '/manager/users.php');
}

// Create user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitize($_POST['name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $role     = sanitize($_POST['role'] ?? '');
    $phone    = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $valid_roles = ['donor','finance_staff','campaign_staff','manager'];
    if (empty($name))  $errors[] = 'Name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    if (!in_array($role,$valid_roles)) $errors[] = 'Please select a valid role.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if (empty($errors)) {
        $ee = $db->real_escape_string($email);
        $ex = $db->query("SELECT id FROM users WHERE email='$ee' LIMIT 1");
        if ($ex && $ex->num_rows > 0) {
            $errors[] = 'Email already exists.';
        } else {
            $ne = $db->real_escape_string($name);
            $re = $db->real_escape_string($role);
            $pe = $db->real_escape_string($phone);
            $ha = $db->real_escape_string(password_hash($password, PASSWORD_DEFAULT));
            $db->query("INSERT INTO users (name,email,password,role,phone) VALUES ('$ne','$ee','$ha','$re','$pe')");
            $nid = $db->insert_id;
            if ($role === 'donor') $db->query("INSERT INTO donor_profiles (user_id,donor_type) VALUES ($nid,'individual')");
            setFlash('success', "User \"$name\" created successfully!");
            redirect(SITE_URL . '/manager/users.php');
        }
    }
}

$users = $db->query("
    SELECT u.*, COUNT(d.id) as donation_count
    FROM users u
    LEFT JOIN donations d ON d.donor_id=u.id AND d.status='verified'
    GROUP BY u.id
    ORDER BY u.role, u.name
");

// Count by role
$role_counts = [];
foreach(['donor','finance_staff','campaign_staff','manager'] as $r) {
    $rc=$db->query("SELECT COUNT(*) as c FROM users WHERE role='$r'");
    $role_counts[$r]=(int)$rc->fetch_assoc()['c'];
}
$total_users = array_sum($role_counts);
$active_count= (int)$db->query("SELECT COUNT(*) as c FROM users WHERE is_active=1")->fetch_assoc()['c'];

$role_styles = [
    'donor'          => ['bg'=>'#eff6ff','color'=>'#1e40af','border'=>'#bfdbfe','label'=>'Donor'],
    'finance_staff'  => ['bg'=>'#fffbeb','color'=>'#854d0e','border'=>'#fde68a','label'=>'Finance Staff'],
    'campaign_staff' => ['bg'=>'#f0fdf4','color'=>'#15803d','border'=>'#bbf7d0','label'=>'Campaign Staff'],
    'manager'        => ['bg'=>'#fff1f2','color'=>'#991b1b','border'=>'#fecaca','label'=>'Manager'],
];
?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-people-fill" style="color:#7c3aed;font-size:1rem;"></i>Manage Users
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;">Create and manage system user accounts</p>
    </div>
</div>

<!-- Mini stats -->
<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:20px;">
    <?php
    $mini_stats = [
        ['Total',          $total_users,             '#f1f5f9','#475569','bi-people'],
        ['Active',         $active_count,             '#dcfce7','#15803d','bi-person-check-fill'],
        ['Donors',         $role_counts['donor'],     '#eff6ff','#1e40af','bi-heart-fill'],
        ['Finance Staff',  $role_counts['finance_staff'],'#fffbeb','#854d0e','bi-cash-coin'],
        ['Campaign Staff', $role_counts['campaign_staff'],'#f0fdf4','#15803d','bi-megaphone-fill'],
        ['Managers',       $role_counts['manager'],   '#fff1f2','#991b1b','bi-shield-fill'],
    ];
    foreach($mini_stats as [$lbl,$val,$bg,$color,$ic]): ?>
    <div style="background:white;border:1px solid #e2e8f0;border-radius:12px;padding:13px 14px;box-shadow:0 1px 4px rgba(0,0,0,.04);display:flex;align-items:center;gap:10px;">
        <div style="width:34px;height:34px;border-radius:9px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi <?= $ic ?>" style="color:<?= $color ?>;font-size:.88rem;"></i>
        </div>
        <div>
            <div style="font-size:1.1rem;font-weight:800;color:#0f172a;letter-spacing:-.02em;line-height:1.1;"><?= $val ?></div>
            <div style="font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-top:1px;"><?= $lbl ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:18px;align-items:start;">

    <!-- User table -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden;">
        <div style="padding:13px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;">
            <i class="bi bi-list-ul" style="color:#7c3aed;font-size:.88rem;"></i>
            <span style="font-size:.86rem;font-weight:700;color:#0f172a;">System Users</span>
            <span style="background:#ede9fe;color:#6d28d9;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:700;"><?= $total_users ?></span>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <?php foreach(['User','Email','Role','Phone','Donations','Status','Action'] as $h): ?>
                        <th style="padding:10px 16px;text-align:left;font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;border-bottom:1px solid #f1f5f9;white-space:nowrap;"><?= $h ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if($users && $users->num_rows > 0): while($u=$users->fetch_assoc()):
                    $rs = $role_styles[$u['role']] ?? $role_styles['donor'];
                    $is_me = $u['id'] === $uid;
                    $is_active = (bool)$u['is_active'];
                ?>
                <tr style="border-bottom:1px solid #f9fafb;transition:background .12s;<?= !$is_active?'opacity:.55;':'' ?>"
                    onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">

                    <!-- User -->
                    <td style="padding:12px 16px;min-width:150px;">
                        <div style="display:flex;align-items:center;gap:9px;">
                            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,<?= $rs['color'] ?>,<?= $rs['color'] ?>88);display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;color:white;flex-shrink:0;box-shadow:0 2px 6px rgba(0,0,0,.12);">
                                <?= strtoupper(substr($u['name'],0,1)) ?>
                            </div>
                            <div>
                                <div style="font-size:.8rem;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:5px;">
                                    <?= htmlspecialchars($u['name']) ?>
                                    <?php if($is_me): ?><span style="background:#eff6ff;color:#1447c0;padding:1px 6px;border-radius:4px;font-size:.62rem;font-weight:700;">You</span><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>

                    <!-- Email -->
                    <td style="padding:12px 16px;font-size:.75rem;color:#64748b;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= htmlspecialchars($u['email']) ?>
                    </td>

                    <!-- Role -->
                    <td style="padding:12px 16px;">
                        <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $rs['bg'] ?>;color:<?= $rs['color'] ?>;border:1px solid <?= $rs['border'] ?>;padding:3px 10px;border-radius:20px;font-size:.71rem;font-weight:700;white-space:nowrap;">
                            <?= $rs['label'] ?>
                        </span>
                    </td>

                    <!-- Phone -->
                    <td style="padding:12px 16px;font-size:.75rem;color:#64748b;white-space:nowrap;">
                        <?= htmlspecialchars($u['phone']??'—') ?>
                    </td>

                    <!-- Donations -->
                    <td style="padding:12px 16px;text-align:center;">
                        <?php if($u['donation_count']>0): ?>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;background:#dcfce7;border-radius:50%;font-size:.74rem;font-weight:800;color:#15803d;">
                            <?= $u['donation_count'] ?>
                        </span>
                        <?php else: ?>
                        <span style="color:#e2e8f0;font-size:.8rem;">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Status -->
                    <td style="padding:12px 16px;">
                        <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $is_active?'#dcfce7':'#f1f5f9' ?>;color:<?= $is_active?'#15803d':'#94a3b8' ?>;border:1px solid <?= $is_active?'#bbf7d0':'#e2e8f0' ?>;padding:3px 10px;border-radius:20px;font-size:.71rem;font-weight:700;">
                            <span style="width:6px;height:6px;border-radius:50%;background:<?= $is_active?'#22c55e':'#cbd5e1' ?>;flex-shrink:0;"></span>
                            <?= $is_active?'Active':'Inactive' ?>
                        </span>
                    </td>

                    <!-- Action -->
                    <td style="padding:12px 16px;">
                        <?php if(!$is_me): ?>
                        <a href="?toggle=<?= $u['id'] ?>"
                           onclick="return confirm('<?= $is_active?'Deactivate':'Activate' ?> this user?')"
                           style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:8px;font-size:.74rem;font-weight:600;transition:all .15s;text-decoration:none;white-space:nowrap;border:1.5px solid;
                           <?= $is_active?'background:#fff1f2;color:#991b1b;border-color:#fecaca;':'background:#f0fdf4;color:#15803d;border-color:#bbf7d0;' ?>"
                           onmouseover="this.style.filter='brightness(.95)'" onmouseout="this.style.filter='none'">
                            <i class="bi bi-<?= $is_active?'person-x-fill':'person-check-fill' ?>" style="font-size:.76rem;"></i>
                            <?= $is_active?'Deactivate':'Activate' ?>
                        </a>
                        <?php else: ?>
                        <span style="font-size:.73rem;color:#cbd5e1;">Current user</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="7" style="padding:40px;text-align:center;color:#94a3b8;font-size:.8rem;">No users found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create user form -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden;position:sticky;top:80px;">
        <div style="padding:13px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;background:linear-gradient(135deg,#faf5ff,#ede9fe);">
            <i class="bi bi-person-plus-fill" style="color:#7c3aed;font-size:.88rem;"></i>
            <span style="font-size:.86rem;font-weight:700;color:#0f172a;">Create New User</span>
        </div>

        <?php if(!empty($errors)): ?>
        <div style="margin:14px 16px 0;background:#fff1f2;border-left:3px solid #ef4444;padding:10px 12px;border-radius:8px;">
            <div style="font-size:.76rem;font-weight:700;color:#b91c1c;margin-bottom:5px;"><i class="bi bi-exclamation-circle-fill" style="margin-right:5px;"></i>Fix the following:</div>
            <ul style="margin:0;padding-left:16px;font-size:.74rem;color:#b91c1c;">
                <?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" style="padding:16px;">
            <?php
            $form_fields = [
                ['Full Name','name','text','Nguyen Van A', true],
                ['Email','email','email','user@email.com', true],
                ['Phone','phone','text','0901234567', false],
            ];
            foreach($form_fields as [$label,$name,$type,$placeholder,$required]): ?>
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:.73rem;font-weight:600;color:#374151;margin-bottom:4px;">
                    <?= $label ?><?= $required?' <span style="color:#ef4444;">*</span>':'' ?>
                </label>
                <input type="<?= $type ?>" name="<?= $name ?>" <?= $required?'required':'' ?>
                       style="width:100%;height:38px;padding:0 11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'"
                       placeholder="<?= $placeholder ?>"
                       value="<?= htmlspecialchars($_POST[$name]??'') ?>">
            </div>
            <?php endforeach; ?>

            <!-- Role -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:.73rem;font-weight:600;color:#374151;margin-bottom:4px;">
                    Role <span style="color:#ef4444;">*</span>
                </label>
                <select name="role" required
                        style="width:100%;height:38px;padding:0 11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;background:white;outline:none;transition:border-color .15s;cursor:pointer;"
                        onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'">
                    <option value="">Select role...</option>
                    <option value="donor"          <?= ($_POST['role']??'')==='donor'?'selected':'' ?>>Donor</option>
                    <option value="finance_staff"  <?= ($_POST['role']??'')==='finance_staff'?'selected':'' ?>>Finance Staff</option>
                    <option value="campaign_staff" <?= ($_POST['role']??'')==='campaign_staff'?'selected':'' ?>>Campaign Staff</option>
                    <option value="manager"        <?= ($_POST['role']??'')==='manager'?'selected':'' ?>>Manager</option>
                </select>
            </div>

            <!-- Password -->
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:.73rem;font-weight:600;color:#374151;margin-bottom:4px;">
                    Password <span style="color:#ef4444;">*</span>
                </label>
                <input type="password" name="password" required
                       style="width:100%;height:38px;padding:0 11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'"
                       placeholder="Min. 6 characters">
                <div style="font-size:.68rem;color:#94a3b8;margin-top:3px;">User can change password after login</div>
            </div>

            <button type="submit"
                    style="width:100%;padding:10px;background:linear-gradient(135deg,#7c3aed,#6d28d9);color:white;border:none;border-radius:10px;font-weight:700;font-size:.84rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;box-shadow:0 3px 10px rgba(124,58,237,.3);transition:all .18s;"
                    onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                <i class="bi bi-person-check-fill"></i>Create User
            </button>
        </form>
    </div>

</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
