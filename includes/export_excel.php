<?php
require_once __DIR__ . '/auth.php';

$type = isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : '';
$exportTypes = [
    'manager_report' => 'manager',
    'finance_transactions' => 'finance_staff',
    'finance_history' => 'finance_staff',
];

if (!isset($exportTypes[$type])) {
    http_response_code(400);
    exit('Invalid export type.');
}

requireRole($exportTypes[$type]);

function exportFailure($message, $details)
{
    error_log($message . ': ' . $details);
    http_response_code(500);
    exit('Unable to generate the export. Please try again later.');
}

function exportInvalidRequest($message)
{
    http_response_code(400);
    exit($message);
}

function exportSpreadsheet($filename, $headers, $fetchRow)
{
    $output = fopen('php://output', 'w');
    if ($output === false) {
        exportFailure('Unable to open spreadsheet output stream', 'fopen failed');
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers);

    while ($row = call_user_func($fetchRow)) {
        if ($row === false) {
            break;
        }
        foreach ($row as &$value) {
            if ($value === null) {
                $value = '';
            } elseif (is_string($value) && preg_match('/^[\t\r\n ]*[=+\-@]/', $value)) {
                $value = "'" . $value;
            }
        }
        unset($value);
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

$db = getApplicationDB();

if ($type === 'manager_report') {
    $result = $db->query("
        SELECT c.title,
               c.target_amount,
               COALESCE(d.verified_raised, 0) AS verified_raised,
               CASE WHEN c.target_amount > 0
                    THEN ROUND(COALESCE(d.verified_raised, 0) / c.target_amount * 100, 1)
                    ELSE 0 END AS progress_percent,
               COALESCE(d.donation_count, 0) AS verified_donations,
               COALESCE(fa.allocated, 0) AS recorded_allocations,
               c.status
        FROM campaigns c
        LEFT JOIN (
            SELECT campaign_id, COUNT(*) AS donation_count, SUM(amount) AS verified_raised
            FROM donations
            WHERE status = 'verified' AND campaign_id IS NOT NULL
            GROUP BY campaign_id
        ) d ON d.campaign_id = c.id
        LEFT JOIN (
            SELECT campaign_id, SUM(amount) AS allocated
            FROM fund_allocations
            GROUP BY campaign_id
        ) fa ON fa.campaign_id = c.id
        ORDER BY verified_raised DESC, c.title ASC
    ");

    if (!$result) {
        exportFailure('Manager report export query failed', $db->error);
    }

    $fetchRow = function () use ($result, $db) {
        $row = $result->fetch_row();
        if ($row === false) {
            exportFailure('Manager report export fetch failed', $db->error);
        }
        return $row === null ? false : $row;
    };

    exportSpreadsheet(
        'campaign-performance-' . date('Y-m-d') . '.csv',
        ['Campaign', 'Target (VND)', 'Verified raised (VND)', 'Progress (%)', 'Verified donations', 'Recorded allocations (VND)', 'Status'],
        $fetchRow
    );
}

if ($type === 'finance_transactions') {
    $allowedStatuses = ['pending_verification', 'verified', 'rejected'];
    if (isset($_GET['status']) && !is_string($_GET['status'])) {
        exportInvalidRequest('Invalid transaction status filter.');
    }
    $status = isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : '';
    if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
        exportInvalidRequest('Invalid transaction status filter.');
    }

    if (isset($_GET['search']) && !is_string($_GET['search'])) {
        exportInvalidRequest('Invalid transaction search filter.');
    }
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $searchPattern = '%' . $search . '%';
    $stmt = $db->prepare("
        SELECT t.id,
               t.reference_code,
               donor.name AS donor_name,
               donor.email AS donor_email,
               COALESCE(c.title, 'General Donation') AS campaign_title,
               d.amount,
               d.channel,
               COALESCE(t.transaction_date, d.created_at) AS transaction_date,
               t.verification_status,
               verifier.name AS verified_by,
               t.verified_at,
               t.rejection_reason
        FROM transactions t
        JOIN donations d ON t.donation_id = d.id
        JOIN users donor ON d.donor_id = donor.id
        LEFT JOIN campaigns c ON d.campaign_id = c.id
        LEFT JOIN users verifier ON t.verified_by = verifier.id
        WHERE (? = '' OR t.verification_status = ?)
          AND (? = '' OR t.reference_code LIKE ? OR donor.name LIKE ? OR c.title LIKE ?)
        ORDER BY t.created_at DESC
    ");

    if (!$stmt) {
        exportFailure('Finance transaction export preparation failed', $db->error);
    }

    if (!$stmt->bind_param('ssssss', $status, $status, $search, $searchPattern, $searchPattern, $searchPattern)
        || !$stmt->execute()) {
        exportFailure('Finance transaction export query failed', $stmt->error);
    }

    if (!$stmt->bind_result(
        $transactionId,
        $reference,
        $donorName,
        $donorEmail,
        $campaignTitle,
        $amount,
        $channel,
        $transactionDate,
        $verificationStatus,
        $verifiedBy,
        $verifiedAt,
        $rejectionReason
    )) {
        exportFailure('Finance transaction export result failed', $stmt->error);
    }

    $fetchRow = function () use (
        $stmt,
        &$transactionId,
        &$reference,
        &$donorName,
        &$donorEmail,
        &$campaignTitle,
        &$amount,
        &$channel,
        &$transactionDate,
        &$verificationStatus,
        &$verifiedBy,
        &$verifiedAt,
        &$rejectionReason
    ) {
        $fetchStatus = $stmt->fetch();
        if ($fetchStatus === null) {
            return false;
        }
        if ($fetchStatus === false) {
            exportFailure('Finance transaction export fetch failed', $stmt->error);
        }

        return [
            $transactionId,
            $reference,
            $donorName,
            $donorEmail,
            $campaignTitle,
            $amount,
            $channel,
            $transactionDate,
            $verificationStatus,
            $verifiedBy,
            $verifiedAt,
            $rejectionReason,
        ];
    };

    exportSpreadsheet(
        'finance-transactions-' . date('Y-m-d') . '.csv',
        ['Transaction ID', 'Reference', 'Donor', 'Donor email', 'Campaign', 'Amount (VND)', 'Channel', 'Transaction date', 'Status', 'Verified by', 'Verified at', 'Rejection reason'],
        $fetchRow
    );
}

$allowedActions = ['submitted', 'verified', 'rejected', 'resubmitted'];
if (isset($_GET['action']) && !is_string($_GET['action'])) {
    exportInvalidRequest('Invalid verification history filter.');
}
$action = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';
if ($action !== '' && !in_array($action, $allowedActions, true)) {
    exportInvalidRequest('Invalid verification history filter.');
}
$stmt = $db->prepare("
    SELECT vh.created_at,
           vh.action,
           t.reference_code,
           donor.name AS donor_name,
           d.amount,
           d.channel,
           COALESCE(c.title, 'General Donation') AS campaign_title,
           staff.name AS performed_by,
           vh.notes
    FROM verification_history vh
    JOIN transactions t ON vh.transaction_id = t.id
    JOIN donations d ON t.donation_id = d.id
    JOIN users donor ON d.donor_id = donor.id
    JOIN users staff ON vh.performed_by = staff.id
    LEFT JOIN campaigns c ON d.campaign_id = c.id
    WHERE (? = '' OR vh.action = ?)
    ORDER BY vh.created_at DESC
");

if (!$stmt) {
    exportFailure('Finance history export preparation failed', $db->error);
}

if (!$stmt->bind_param('ss', $action, $action) || !$stmt->execute()) {
    exportFailure('Finance history export query failed', $stmt->error);
}

if (!$stmt->bind_result(
    $createdAt,
    $historyAction,
    $historyReference,
    $historyDonor,
    $historyAmount,
    $historyChannel,
    $historyCampaign,
    $performedBy,
    $historyNotes
)) {
    exportFailure('Finance history export result failed', $stmt->error);
}

$fetchRow = function () use (
    $stmt,
    &$createdAt,
    &$historyAction,
    &$historyReference,
    &$historyDonor,
    &$historyAmount,
    &$historyChannel,
    &$historyCampaign,
    &$performedBy,
    &$historyNotes
) {
    $fetchStatus = $stmt->fetch();
    if ($fetchStatus === null) {
        return false;
    }
    if ($fetchStatus === false) {
        exportFailure('Finance history export fetch failed', $stmt->error);
    }

    return [
        $createdAt,
        $historyAction,
        $historyReference,
        $historyDonor,
        $historyAmount,
        $historyChannel,
        $historyCampaign,
        $performedBy,
        $historyNotes,
    ];
};

exportSpreadsheet(
    'verification-history-' . date('Y-m-d') . '.csv',
    ['Date/time', 'Action', 'Reference', 'Donor', 'Amount (VND)', 'Channel', 'Campaign', 'Performed by', 'Notes'],
    $fetchRow
);
