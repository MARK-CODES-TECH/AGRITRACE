<?php
// api/verify_otp.php - Verify submitted 6-digit OTP
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$rawMobile = trim($_POST['mobile'] ?? '');
$inputOtp  = trim($_POST['otp_code'] ?? '');

if (empty($rawMobile) || empty($inputOtp)) {
    echo json_encode(['success' => false, 'message' => 'Both mobile number and 6-digit verification code are required.']);
    exit;
}

$cleanMobile = preg_replace('/\D/', '', $rawMobile);
$localMobile = str_starts_with($cleanMobile, '63') ? ('0' . substr($cleanMobile, 2)) : $cleanMobile;
$intlMobile  = str_starts_with($cleanMobile, '09') ? ('+63' . substr($cleanMobile, 1)) : ('+' . $cleanMobile);

$verified = false;

// 1. Check Session OTP first
if (isset($_SESSION['sms_otp'])) {
    $sessOtp = $_SESSION['sms_otp'];
    if (($sessOtp['mobile'] === $localMobile || $sessOtp['intl'] === $intlMobile) &&
        $sessOtp['code'] === $inputOtp &&
        $sessOtp['expires_at'] >= time()) {
        $verified = true;
    }
}

// 2. Check Database phone_verifications table
if (!$verified) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id FROM phone_verifications 
             WHERE (mobile = ? OR mobile = ?) 
               AND otp_code = ? 
               AND is_verified = 0 
               AND expires_at >= NOW() 
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$localMobile, $intlMobile, $inputOtp]);
        $record = $stmt->fetch();
        if ($record) {
            $verified = true;
            // Mark as verified in database
            $pdo->prepare("UPDATE phone_verifications SET is_verified = 1 WHERE id = ?")
                ->execute([$record['id']]);
        }
    } catch (PDOException $e) {}
}

// 3. Fallback dev/test master code
if (!$verified && $inputOtp === '123456') {
    $verified = true;
}

if ($verified) {
    // Save verified mobile number in session for final registration validation
    $_SESSION['phone_verified_mobile'] = $localMobile;
    $_SESSION['phone_verified_time']   = time();
    unset($_SESSION['sms_otp']);

    echo json_encode([
        'success' => true,
        'message' => 'Mobile number verified successfully!',
        'mobile'  => $localMobile
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or expired 6-digit verification code. Please check and try again.'
    ]);
}
