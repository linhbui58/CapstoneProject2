<?php
// Điều chỉnh đường dẫn require cho đúng với vị trí bạn lưu thư viện PHPMailer
require_once __DIR__ . '/../vendor/autoload.php'; // Dùng nếu cài qua Composer
/* 
Nếu tải thủ công, dùng 3 dòng sau thay thế:
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
*/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendThankYouEmail($toEmail, $toName, $amount, $campaignTitle, $transactionCode) {
    $mail = new PHPMailer(true);
    
    try {
        // Cấu hình Server SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'email_cua_ban@gmail.com'; // Thay bằng email hệ thống
        $mail->Password   = 'app_password_cua_ban';    // Dùng Mật khẩu ứng dụng (App Password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Cấu hình người gửi & nhận
        $mail->setFrom('email_cua_ban@gmail.com', 'VinaCapital Foundation');
        $mail->addAddress($toEmail, $toName);

        // Nội dung Email
        $mail->isHTML(true);
        $mail->Subject = 'Xác nhận quyên góp thành công - Cảm ơn bạn!';
        
        $formattedAmount = number_format($amount, 0, ',', '.') . ' VNĐ';
        $campaignText = $campaignTitle ? "vào chiến dịch <strong>$campaignTitle</strong>" : "cho quỹ của chúng tôi";
        
        // Template HTML Email/Biên lai
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <h2 style='color: #d9534f;'>Cảm ơn tấm lòng vàng của bạn!</h2>
                <p>Kính gửi <strong>$toName</strong>,</p>
                <p>VinaCapital Foundation xin chân thành cảm ơn bạn đã quyên góp số tiền <strong>$formattedAmount</strong> $campaignText.</p>
                <div style='background-color: #f9f9f9; padding: 15px; border-left: 4px solid #5cb85c; margin: 20px 0;'>
                    <h3 style='margin-top: 0;'>Chi tiết biên lai điện tử:</h3>
                    <ul style='list-style: none; padding: 0;'>
                        <li><strong>Mã giao dịch:</strong> $transactionCode</li>
                        <li><strong>Số tiền:</strong> $formattedAmount</li>
                        <li><strong>Ngày xác nhận:</strong> " . date('d/m/Y H:i:s') . "</li>
                    </ul>
                </div>
                <p>Sự đóng góp của bạn mang ý nghĩa rất lớn và sẽ giúp tạo ra những thay đổi tích cực cho cộng đồng.</p>
                <br>
                <p>Trân trọng,<br><strong>Đội ngũ VinaCapital Foundation</strong></p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Ghi log nếu gửi mail thất bại để không làm gián đoạn UX của nhân viên Finance
        error_log("Lỗi gửi email tri ân tới {$toEmail}: {$mail->ErrorInfo}");
        return false;
    }
}   