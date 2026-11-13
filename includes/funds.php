<?php

function getCampaignFunds(mysqli $db, int $campaignId): array
{
    $sql = "SELECT
                COALESCE((SELECT SUM(amount) FROM donations
                          WHERE campaign_id = ? AND status = 'verified'), 0) AS raised,
                COALESCE((SELECT SUM(amount) FROM fund_allocations
                          WHERE campaign_id = ?), 0) AS allocated,
                COALESCE((SELECT SUM(amount) FROM disbursement_requests
                          WHERE campaign_id = ? AND status IN ('pending', 'approved')), 0) AS pending_requests";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare funds query: ' . $db->error);
    }
    if (!$stmt->bind_param('iii', $campaignId, $campaignId, $campaignId)
        || !$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to query campaign funds: ' . $error);
    }
    if (!$stmt->bind_result($raised, $allocated, $pendingRequests) || !$stmt->fetch()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to read campaign funds: ' . $error);
    }
    $stmt->close();

    $raised = (float)$raised;
    $allocated = (float)$allocated;
    $pendingRequests = (float)$pendingRequests;

    return [
        'raised' => $raised,
        'allocated' => $allocated,
        'pending_requests' => $pendingRequests,
        'available' => $raised - $allocated - $pendingRequests,
    ];
}
