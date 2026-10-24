<?php
$pageTitle  = 'Donation Receipt';
$activePage = 'my_donations';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('donor');

$db = getDB();
$uid = (int)$user['id'];
$donationId = (int)($_GET['id'] ?? 0);

$statement = $db->prepare("
    SELECT d.id, d.amount, d.channel, d.created_at,
           t.reference_code, t.verified_at,
           u.name AS donor_name, u.email AS donor_email,
           c.title AS campaign_title
    FROM donations d
    JOIN transactions t ON t.donation_id = d.id
    JOIN users u ON u.id = d.donor_id
    LEFT JOIN campaigns c ON c.id = d.campaign_id
    WHERE d.id = ? AND d.donor_id = ?
      AND d.status = 'verified'
      AND t.verification_status = 'verified'
    LIMIT 1
");
$statement->bind_param('ii', $donationId, $uid);
$statement->execute();
$statement->bind_result(
    $receiptId,
    $amount,
    $channel,
    $createdAt,
    $referenceCode,
    $verifiedAt,
    $donorName,
    $donorEmail,
    $campaignTitle
);
$receiptFound = $statement->fetch();
$statement->close();

if (!$receiptFound) {
    setFlash('warning', 'A receipt is available only for your verified donations.');
    redirect(SITE_URL . '/donor/my_donations.php');
}

$receipt = [
    'id' => $receiptId,
    'amount' => $amount,
    'channel' => $channel,
    'created_at' => $createdAt,
    'reference_code' => $referenceCode,
    'verified_at' => $verifiedAt,
    'donor_name' => $donorName,
    'donor_email' => $donorEmail,
    'campaign_title' => $campaignTitle,
];
?>

<style>
    .receipt-card {
        max-width: 760px;
        margin: 24px auto;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 4px 18px rgba(15, 23, 42, .07);
    }
    .receipt-card .receipt-header {
        padding: 28px 32px;
        border-bottom: 1px solid #e2e8f0;
        text-align: center;
    }
    .receipt-card .receipt-body {
        padding: 28px 32px;
    }
    .receipt-row {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .receipt-row:last-child {
        border-bottom: 0;
    }
    @media print {
        @page {
            margin: 18mm;
        }
        body,
        .wrapper,
        .main-content,
        .page-content {
            display: block !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
        }
        .sidebar,
        .topbar,
        footer,
        .alert,
        .receipt-actions {
            display: none !important;
        }
        .receipt-card {
            max-width: none;
            margin: 0;
            border: 1px solid #94a3b8;
            border-radius: 0;
            box-shadow: none;
        }
        .receipt-card .receipt-header,
        .receipt-card .receipt-body {
            padding: 20px;
        }
    }
</style>

<div class="receipt-actions d-flex justify-content-between align-items-center mb-3">
    <a href="<?= SITE_URL ?>/donor/donation_detail.php?id=<?= (int)$receipt['id'] ?>"
       class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Donation
    </a>
    <button type="button" class="btn btn-success" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print / Save as PDF
    </button>
</div>

<article class="receipt-card">
    <header class="receipt-header">
        <div style="font-size:.75rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#64748b;">
            VinaCapital Foundation
        </div>
        <h1 style="font-size:1.55rem;font-weight:800;color:#0f172a;margin:10px 0 5px;">
            Donation Acknowledgement
        </h1>
        <div style="font-size:.85rem;color:#64748b;">
            Receipt #RCP-<?= str_pad((string)$receipt['id'], 6, '0', STR_PAD_LEFT) ?>
        </div>
    </header>
    <div class="receipt-body">
        <div style="text-align:center;padding:8px 0 24px;">
            <div style="font-size:.78rem;color:#64748b;margin-bottom:5px;">Verified donation amount</div>
            <div style="font-size:2rem;font-weight:800;color:#15803d;">
                <?= htmlspecialchars(formatCurrency($receipt['amount'])) ?>
            </div>
        </div>

        <div class="receipt-row">
            <span class="text-muted">Donor</span>
            <strong><?= htmlspecialchars($receipt['donor_name']) ?></strong>
        </div>
        <div class="receipt-row">
            <span class="text-muted">Email</span>
            <strong><?= htmlspecialchars($receipt['donor_email']) ?></strong>
        </div>
        <div class="receipt-row">
            <span class="text-muted">Campaign</span>
            <strong><?= htmlspecialchars($receipt['campaign_title'] ?? 'General Donation') ?></strong>
        </div>
        <div class="receipt-row">
            <span class="text-muted">Donation date</span>
            <strong><?= htmlspecialchars(formatDateTime($receipt['created_at'])) ?></strong>
        </div>
        <div class="receipt-row">
            <span class="text-muted">Verified at</span>
            <strong><?= htmlspecialchars(formatDateTime($receipt['verified_at'])) ?></strong>
        </div>
        <div class="receipt-row">
            <span class="text-muted">Payment channel</span>
            <strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $receipt['channel']))) ?></strong>
        </div>
        <div class="receipt-row">
            <span class="text-muted">Transaction reference</span>
            <strong><?= htmlspecialchars($receipt['reference_code'] ?: 'N/A') ?></strong>
        </div>

        <div style="margin-top:24px;padding:16px;border-radius:10px;background:#f0fdf4;color:#166534;text-align:center;">
            Thank you for your generosity. This donation has been verified and recorded.
        </div>
        <p style="font-size:.72rem;color:#94a3b8;text-align:center;margin:18px 0 0;">
            This document acknowledges the donation recorded by VinaCapital Foundation.
        </p>
    </div>
</article>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
