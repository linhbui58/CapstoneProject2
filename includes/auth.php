<?php
// =====================================================
// Authentication & Authorization Helper
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once dirname(__DIR__) . '/config/db.php';

if (!function_exists('sanitize')) {
    function sanitize($value)
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}

function getApplicationDB(): mysqli
{
    $db = getDB();
    if (!$db->query("SET time_zone = '+07:00'")) {
        throw new RuntimeException('Unable to set the database timezone to Vietnam time: ' . $db->error);
    }

    return $db;
}

function campaignImageUrl($imageUrl): string
{
    $imageUrl = trim((string) $imageUrl);
    if ($imageUrl === '') {
        return '';
    }

    if (preg_match('~^(?:https?:)?//~i', $imageUrl)) {
        return $imageUrl;
    }

    return rtrim(SITE_URL, '/') . '/' . ltrim($imageUrl, '/');
}

// ----------------------
// Redirect helper
// ----------------------
// This function was missing, causing:
// Call to undefined function redirect()
if (!function_exists('redirect')) {
    function redirect($url)
    {
        $fallback = defined('SITE_URL')
            ? rtrim(SITE_URL, '/') . '/index.php'
            : '/';

        $url = trim((string) $url);

        // Reject empty destinations and header-injection characters.
        if ($url === '' || preg_match('/[\r\n]/', $url)) {
            $url = $fallback;
        }

        $target = parse_url($url);
        $siteHost = defined('SITE_URL') ? parse_url(SITE_URL, PHP_URL_HOST) : null;
        $valid = false;

        if ($target !== false) {
            if (!empty($target['host'])) {
                // An absolute URL must belong to this website.
                $scheme = strtolower($target['scheme'] ?? '');
                $valid = in_array($scheme, ['http', 'https'], true)
                    && !empty($siteHost)
                    && strcasecmp($target['host'], $siteHost) === 0;
            } else {
                // Allow local absolute paths, but not //external.example URLs.
                $path = $target['path'] ?? '';
                $valid = $path !== ''
                    && substr($path, 0, 1) === '/'
                    && substr($path, 0, 2) !== '//';
            }
        }

        if (!$valid) {
            $url = $fallback;
        }

        if (headers_sent($file, $line)) {
            error_log("Redirect failed: headers already sent in {$file} on line {$line}.");
            exit;
        }

        header('Location: ' . $url, true, 302);
        exit;
    }
}

// ----------------------
// Check if logged in
// ----------------------
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// ----------------------
// Log out current user
// ----------------------
function logoutUser() {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $cookie['path'],
            $cookie['domain'],
            $cookie['secure'],
            $cookie['httponly']
        );
    }

    session_destroy();
    redirect(SITE_URL . '/auth/login.php');
}

// ----------------------
// Log in a user
// ----------------------
if (!function_exists('loginUser')) {
    function loginUser($email, $password) {
        $db = getApplicationDB();
        $stmt = $db->prepare(
            'SELECT id, name, email, password, role, is_active FROM users WHERE email = ? LIMIT 1'
        );

        if (!$stmt) {
            error_log('Login query preparation failed: ' . $db->error);
            return ['success' => false, 'message' => 'Unable to sign in right now. Please try again.'];
        }

        $email = trim((string) $email);
        if (!$stmt->bind_param('s', $email) || !$stmt->execute()) {
            error_log('Login query execution failed: ' . $stmt->error);
            $stmt->close();
            return ['success' => false, 'message' => 'Unable to sign in right now. Please try again.'];
        }

        $stmt->bind_result($id, $name, $storedEmail, $passwordHash, $role, $isActive);
        $found = $stmt->fetch();
        $stmt->close();

        if (!$found || !password_verify((string) $password, (string) $passwordHash)) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        if (!(int) $isActive) {
            return ['success' => false, 'message' => 'This account is inactive. Please contact an administrator.'];
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $storedEmail;
        $_SESSION['user_role'] = $role;

        return ['success' => true, 'message' => 'Login successful.'];
    }
}

// ----------------------
// Get current user
// ----------------------
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role'  => $_SESSION['user_role'],
    ];
}

// ----------------------
// Get display label for a role
// ----------------------
function getRoleLabel($role) {
    $labels = [
        'donor'          => 'Donor',
        'finance_staff'  => 'Finance Staff',
        'campaign_staff' => 'Campaign Staff',
        'manager'        => 'Manager',
    ];

    return $labels[$role] ?? ucfirst(str_replace('_', ' ', (string) $role));
}

// ----------------------
// Shared display helpers
// ----------------------
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return number_format((float) $amount, 0, '.', ',') . ' VND';
    }
}

if (!function_exists('formatDate')) {
    function formatDate($value) {
        $timestamp = $value === null || $value === '' ? false : strtotime((string) $value);
        return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime($value) {
        $timestamp = $value === null || $value === '' ? false : strtotime((string) $value);
        return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
    }
}

if (!function_exists('getCampaignProgress')) {
    function getCampaignProgress($raised, $target) {
        $target = (float) $target;
        if ($target <= 0) {
            return 0;
        }

        return round(min(100, max(0, ((float) $raised / $target) * 100)), 1);
    }
}

if (!function_exists('setFlash')) {
    function setFlash($type, $message) {
        $_SESSION['flash_message'] = [
            'type' => (string) $type,
            'message' => (string) $message,
        ];
    }
}

if (!function_exists('showFlash')) {
    function showFlash() {
        if (!isset($_SESSION['flash_message'])) {
            return;
        }

        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);

        $types = ['success', 'danger', 'warning', 'info'];
        $type = is_array($flash) && isset($flash['type']) && in_array($flash['type'], $types, true)
            ? $flash['type']
            : 'info';
        $message = is_array($flash) && isset($flash['message']) ? (string) $flash['message'] : '';

        if ($message === '') {
            return;
        }

        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
            . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
            . '</div>';
    }
}

// ----------------------
// Require login
// ----------------------
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(SITE_URL . '/auth/login.php');
    }
}

// ----------------------
// Require specific role(s)
// ----------------------
function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) $roles = [$roles];
    if (!in_array($_SESSION['user_role'], $roles)) {
        // Redirect to their own dashboard
        redirectToDashboard();
    }
}

// ----------------------
// Generate donor impact summary after verification
// ----------------------
if (!function_exists('generateImpactSummary')) {
    function generateImpactSummary($db, int $donor_id, int $campaign_id, float $donation_amount): void {
        // Get campaign data
        $cid = (int)$campaign_id;
        $did = (int)$donor_id;
        $camp = $db->query("SELECT title, target_amount, raised_amount FROM campaigns WHERE id=$cid")->fetch_assoc();
        if (!$camp) return;

        // Get donor's total to this campaign
        $donor_total = (float)$db->query("SELECT COALESCE(SUM(amount),0) as t FROM donations WHERE donor_id=$did AND campaign_id=$cid AND status='verified'")->fetch_assoc()['t'];

        // Get total donors for this campaign
        $total_donors = (int)$db->query("SELECT COUNT(DISTINCT donor_id) as c FROM donations WHERE campaign_id=$cid AND status='verified'")->fetch_assoc()['c'];

        // Calculate percentages
        $camp_pct    = $camp['target_amount'] > 0 ? round(($camp['raised_amount'] / $camp['target_amount']) * 100, 1) : 0;
        $contrib_pct = $camp['raised_amount'] > 0  ? round(($donor_total / $camp['raised_amount']) * 100, 1) : 0;

        $raised_m = number_format($camp['raised_amount'] / 1000000, 1);
        $target_m = number_format($camp['target_amount'] / 1000000, 0);
        $this_m   = number_format($donation_amount / 1000000, 1);
        $title    = $camp['title'];

        // Build natural-language summary
        $lines = [];
        $lines[] = "Your donation of " . number_format($donation_amount, 0, '.', ',') . " VND to \"{$title}\" has been verified and recorded.";

        if ($contrib_pct > 0) {
            $lines[] = "Your total contributions to this campaign account for {$contrib_pct}% of all funds raised so far.";
        }

        $lines[] = "Together with {$total_donors} donor" . ($total_donors != 1 ? 's' : '') . ", this campaign has now raised {$raised_m}M VND — {$camp_pct}% of its {$target_m}M VND target.";

        if ($camp_pct >= 100) {
            $lines[] = "Thanks to supporters like you, this campaign has reached its fundraising goal!";
        } elseif ($camp_pct >= 75) {
            $lines[] = "The campaign is in its final stretch. Your support is making a real difference.";
        } elseif ($camp_pct >= 50) {
            $lines[] = "The campaign is more than halfway to its goal. Every contribution counts.";
        } else {
            $lines[] = "Your generosity helps build momentum for this important cause.";
        }

        $summary_text = implode(' ', $lines);
        $st = $db->real_escape_string($summary_text);

        // Upsert — replace existing summary for same donor+campaign if any
        $db->query("
            INSERT INTO impact_summaries (donor_id, campaign_id, summary_text, generated_at, is_sent)
            VALUES ($did, $cid, '$st', NOW(), 0)
            ON DUPLICATE KEY UPDATE summary_text='$st', generated_at=NOW(), is_sent=0
        ");
    }
}

// ----------------------
// Redirect to role dashboard
// ----------------------
function redirectToDashboard() {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/auth/login.php');
    }
    switch ($_SESSION['user_role']) {
        case 'donor':
            redirect(SITE_URL . '/donor/dashboard.php');
            break;
        case 'finance_staff':
            redirect(SITE_URL . '/finance_staff/dashboard.php');
            break;
        case 'campaign_staff':
            redirect(SITE_URL . '/campaign_staff/dashboard.php');
            break;
        case 'manager':
            redirect(SITE_URL . '/manager/dashboard.php');
            break;
        default:
            redirect(SITE_URL . '/index.php');
    }
}