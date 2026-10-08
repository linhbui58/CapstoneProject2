<?php
// =====================================================
// Authentication & Authorization Helper
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/db.php';

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

// ----------------------
// Login function
// ----------------------
function loginUser($email, $password) {
    $db = getDB();
    $email = $db->real_escape_string(trim($email));

    $sql = "SELECT id, name, email, password, role, is_active FROM users WHERE email = '$email' LIMIT 1";
    $result = $db->query($sql);

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Your account has been deactivated. Please contact the administrator.'];
        }

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];
            return ['success' => true, 'role' => $user['role']];
        }
    }

    return ['success' => false, 'message' => 'Invalid email or password.'];
}

// ----------------------
// Logout function
// ----------------------
function logoutUser() {
    session_unset();
    session_destroy();
    redirect(SITE_URL . '/auth/login.php');
}

// ----------------------
// Role label helper
// ----------------------
function getRoleLabel($role) {
    $labels = [
        'donor'          => 'Donor',
        'finance_staff'  => 'Finance Staff',
        'campaign_staff' => 'Campaign Staff',
        'manager'        => 'Manager',
    ];
    return $labels[$role] ?? ucfirst($role);
}

// ----------------------
// Role badge color
// ----------------------
function getRoleBadgeClass($role) {
    $classes = [
        'donor'          => 'bg-completed',
        'finance_staff'  => 'bg-pending',
        'campaign_staff' => 'bg-active',
        'manager'        => 'bg-rejected',
    ];
    return $classes[$role] ?? 'bg-draft';
}

// ----------------------
// Status badge color
// ----------------------
function getStatusBadgeClass($status) {
    $classes = [
        'pending'              => 'bg-warning text-dark',
        'pending_verification' => 'bg-warning text-dark',
        'verified'             => 'bg-success',
        'rejected'             => 'bg-rejected',
        'active'               => 'bg-active',
        'completed'            => 'bg-completed',
        'closed'               => 'bg-closed',
        'draft'                => 'bg-draft',
        'planned'              => 'bg-planned',
        'disbursed'            => 'bg-disbursed',
    ];
    return $classes[$status] ?? 'bg-draft';
}

// ----------------------
// Flash message
// ----------------------
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function showFlash() {
    $flash = getFlash();
    if ($flash) {
        $type    = $flash['type'];
        $message = htmlspecialchars($flash['message']);
        $icons   = ['success'=>'check-circle-fill','danger'=>'x-circle-fill','warning'=>'exclamation-triangle-fill','info'=>'info-circle-fill'];
        $icon    = $icons[$type] ?? 'info-circle-fill';
        echo "<div class='alert alert-{$type} alert-dismissible fade show d-flex align-items-center gap-2' role='alert'>
                <i class='bi bi-{$icon}'></i>
                <span>{$message}</span>
                <button type='button' class='btn-close ms-auto' data-bs-dismiss='alert'></button>
              </div>";
    }
}
