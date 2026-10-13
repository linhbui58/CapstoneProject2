<?php
// =====================================================
// Authentication & Authorization Helper
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/db.php';

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