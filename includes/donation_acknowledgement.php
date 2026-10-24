<?php

use PHPMailer\PHPMailer\PHPMailer;

function sendDonationAcknowledgement(array $donation): void
{
    $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoloadPath)) {
        throw new RuntimeException('PHPMailer is not installed. Run composer install.');
    }
    require_once $autoloadPath;

    $requiredSettings = [
        'SMTP_HOST' => getenv('SMTP_HOST'),
        'SMTP_USERNAME' => getenv('SMTP_USERNAME'),
        'SMTP_PASSWORD' => getenv('SMTP_PASSWORD'),
        'SMTP_FROM_EMAIL' => getenv('SMTP_FROM_EMAIL'),
    ];
    foreach ($requiredSettings as $setting => $value) {
        if ($value === false || $value === '') {
            throw new RuntimeException("Required mail setting {$setting} is missing.");
        }
    }

    $host = $requiredSettings['SMTP_HOST'];
    $username = $requiredSettings['SMTP_USERNAME'];
    $password = $requiredSettings['SMTP_PASSWORD'];
    $fromEmail = $requiredSettings['SMTP_FROM_EMAIL'];
    $fromName = getenv('SMTP_FROM_NAME') ?: 'VinaCapital Foundation';
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    $encryption = strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls');

    if (!in_array($encryption, ['tls', 'ssl'], true)) {
        throw new RuntimeException('SMTP_ENCRYPTION must be either tls or ssl.');
    }
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('SMTP_PORT must be a valid TCP port.');
    }

    $escape = static fn($value): string => htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
    $donorName = (string)$donation['donor_name'];
    $campaignName = $donation['campaign_title'] ?: 'General Donation';
    $amount = number_format((float)$donation['amount'], 0, '.', ',') . ' VND';
    $receiptUrl = rtrim(SITE_URL, '/') . '/donor/receipt.php?id=' . (int)$donation['donation_id'];
    $reference = $donation['reference_code'] ?: 'N/A';
    $donationDate = $donation['donation_date'] ?: 'N/A';

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->Port = $port;
    $mail->SMTPSecure = $encryption === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress((string)$donation['donor_email'], $donorName);
    $mail->isHTML(true);
    $mail->Subject = 'Thank you for your donation';
    $mail->Body = '<!doctype html><html lang="en"><body style="font-family:Arial,sans-serif;color:#0f172a;line-height:1.6">'
        . '<h2>Thank you, ' . $escape($donorName) . '!</h2>'
        . '<p>Your donation has been verified and recorded. Your generosity helps support our community.</p>'
        . '<table style="border-collapse:collapse">'
        . '<tr><td style="padding:6px 16px 6px 0;color:#64748b">Amount</td><td><strong>' . $escape($amount) . '</strong></td></tr>'
        . '<tr><td style="padding:6px 16px 6px 0;color:#64748b">Campaign</td><td>' . $escape($campaignName) . '</td></tr>'
        . '<tr><td style="padding:6px 16px 6px 0;color:#64748b">Donation date</td><td>' . $escape($donationDate) . '</td></tr>'
        . '<tr><td style="padding:6px 16px 6px 0;color:#64748b">Reference</td><td>' . $escape($reference) . '</td></tr>'
        . '</table>'
        . '<p><a href="' . $escape($receiptUrl) . '" style="display:inline-block;padding:10px 16px;background:#15803d;color:#fff;text-decoration:none;border-radius:6px">View / print your receipt</a></p>'
        . '<p style="color:#64748b;font-size:13px">This receipt link requires you to sign in to your donor account.</p>'
        . '</body></html>';
    $mail->AltBody = "Thank you, {$donorName}!\n\n"
        . "Your donation of {$amount} to {$campaignName} has been verified and recorded.\n"
        . "Donation date: {$donationDate}\n"
        . "Reference: {$reference}\n"
        . "View or print your receipt: {$receiptUrl}\n\n"
        . 'This receipt link requires you to sign in to your donor account.';
    $mail->send();
}
