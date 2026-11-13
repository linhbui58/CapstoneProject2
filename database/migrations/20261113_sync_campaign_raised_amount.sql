UPDATE campaigns c
SET c.raised_amount = COALESCE(
    (SELECT SUM(d.amount) FROM donations d WHERE d.campaign_id = c.id AND d.status = 'verified'),
    0
);
