<?php
$pageTitle  = 'Link Donations';
$activePage = 'link_donations';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('campaign_staff');

$db = getApplicationDB();

// Handle link action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $donation_id = (int)($_POST['donation_id'] ?? 0);
    $campaign_id = (int)($_POST['campaign_id'] ?? 0);
    if ($donation_id && $campaign_id) {
        $db->query("UPDATE donations SET campaign_id=$campaign_id WHERE id=$donation_id AND status='verified'");
        // Update campaign raised amount
        $amt = $db->query("SELECT amount FROM donations WHERE id=$donation_id")->fetch_assoc()['amount'];
        $db->query("UPDATE campaigns SET raised_amount=raised_amount+$amt WHERE id=$campaign_id");
        setFlash('success', 'Donation linked to campaign successfully!');
    }
    redirect(SITE_URL . '/campaign_staff/link_donations.php');
}

// Pre-filter by campaign
$filter_campaign = (int)($_GET['campaign_id'] ?? 0);

// Unlinked verified donations
$unlinked = $db->query("
    SELECT d.*, u.name as donor_name
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    WHERE d.status = 'verified' AND d.campaign_id IS NULL
    ORDER BY d.created_at DESC
");

// Active campaigns for linking
$campaigns = $db->query("SELECT id, title, target_amount, raised_amount FROM campaigns WHERE status='active' ORDER BY title");
?>

<div class="page-header">
    <h1><i class="bi bi-link-45deg me-2 text-primary"></i>Link Donations to Campaigns</h1>
    <p class="text-muted mb-0">Associate verified donations with fundraising campaigns</p>
</div>

<?php
$unlinked_count = $unlinked ? $unlinked->num_rows : 0;
if ($unlinked_count === 0):
?>
    <div class="card text-center py-5">
        <div class="card-body">
            <i class="bi bi-check-all d-block text-success mb-3" style="font-size:3rem;"></i>
            <h5 class="fw-bold">All donations are linked!</h5>
            <p class="text-muted">No unlinked verified donations found.</p>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info d-flex gap-2 align-items-center mb-4">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <span><strong><?= $unlinked_count ?></strong> verified donation(s) are not yet linked to a campaign.</span>
    </div>

    <div class="table-card">
        <div class="table-header">
            <h6 class="fw-bold mb-0">Unlinked Verified Donations</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Donor</th>
                        <th>Amount</th>
                        <th>Channel</th>
                        <th>Date</th>
                        <th>Link to Campaign</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($d = $unlinked->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($d['is_anonymous'] ? 'Anonymous' : $d['donor_name']) ?></td>
                        <td class="fw-bold text-success"><?= formatCurrency($d['amount']) ?></td>
                        <td><span class="badge bg-light text-dark"><?= ucfirst(str_replace('_',' ',$d['channel'])) ?></span></td>
                        <td class="small text-muted"><?= formatDate($d['created_at']) ?></td>
                        <td>
                            <form method="POST" class="d-flex gap-2 align-items-center">
                                <input type="hidden" name="donation_id" value="<?= $d['id'] ?>">
                                <select name="campaign_id" class="form-select form-select-sm" style="min-width:200px;" required>
                                    <option value="">Select campaign...</option>
                                    <?php
                                    if ($campaigns) {
                                        $campaigns->data_seek(0);
                                        while ($c = $campaigns->fetch_assoc()):
                                    ?>
                                        <option value="<?= $c['id'] ?>" <?= $filter_campaign == $c['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['title']) ?>
                                            (<?= getCampaignProgress($c['raised_amount'],$c['target_amount']) ?>%)
                                        </option>
                                    <?php endwhile; } ?>
                                </select>
                        </td>
                        <td>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-link me-1"></i>Link
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
