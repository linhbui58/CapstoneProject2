<?php
// =====================================================
// Authentication & Authorization Helper
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once dirname(__DIR__) . '/config/db.php';

function getApplicationDB(): mysqli
{
    $db = getDB();
    if (!$db->query("SET time_zone = '+07:00'")) {
        throw new RuntimeException('Unable to set the database timezone to Vietnam time: ' . $db->error);
    }

    return $db;
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