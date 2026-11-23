<?php
// api/send_otp.php - Native SMS OTP Dispatcher
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$rawMobile = trim($_POST['mobile'] ?? '');
if (empty($rawMobile)) {
    echo json_encode(['success' => false, 'message' => 'Mobile number is required.']);
    exit;
}

// Clean and normalize mobile number
$cleanMobile = preg_replace('/\D/', '', $rawMobile);

// Convert to local 09XXXXXXXXX and international +639XXXXXXXXX
if (str_starts_with($cleanMobile, '63') && strlen($cleanMobile) === 12) {
    $localMobile = '0' . substr($cleanMobile, 2);
    $intlMobile  = '+' . $cleanMobile;
} elseif (str_starts_with($cleanMobile, '09') && strlen($cleanMobile) === 11) {
    $localMobile = $cleanMobile;
    $intlMobile  = '+63' . substr($cleanMobile, 1);
} elseif (str_starts_with($cleanMobile, '9') && strlen($cleanMobile) === 10) {
    $localMobile = '0' . $cleanMobile;
    $intlMobile  = '+63' . $cleanMobile;
} else {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid 11-digit Philippine mobile number (e.g. 09630972289).']);
    exit;
}

// Check if mobile already exists in users table
try {
    $checkUser = $pdo->prepare("SELECT id FROM users WHERE mobile = ? OR mobile = ? OR mobile = ? LIMIT 1");
    $checkUser->execute([$rawMobile, $localMobile, $intlMobile]);
    if ($checkUser->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This mobile number is already registered. Please log in instead.']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error during account check.']);
    exit;
}

// Rate limiting: check if OTP was sent in the last 20 seconds
if (isset($_SESSION['last_otp_time']) && (time() - $_SESSION['last_otp_time'] < 20)) {
    $wait = 20 - (time() - $_SESSION['last_otp_time']);
    echo json_encode(['success' => false, 'message' => "Please wait {$wait} seconds before requesting another code."]);
    exit;
}

// Generate secure 6-digit OTP
$otpCode = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$expiresAt = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutes validity

try {
    // Invalidate previous unverified OTPs for this number
    $pdo->prepare("UPDATE phone_verifications SET is_verified = 2 WHERE (mobile = ? OR mobile = ?) AND is_verified = 0")
        ->execute([$localMobile, $intlMobile]);

    // Save new OTP
    $stmt = $pdo->prepare("INSERT INTO phone_verifications (mobile, otp_code, expires_at, is_verified) VALUES (?, ?, ?, 0)");
    $stmt->execute([$localMobile, $otpCode, $expiresAt]);

    // Save to session
    $_SESSION['sms_otp'] = [
        'mobile'     => $localMobile,
        'intl'       => $intlMobile,
        'code'       => $otpCode,
        'expires_at' => time() + 600
    ];
    $_SESSION['last_otp_time'] = time();

    // SMS Message Template
    $smsMessage = "AgriTrace+ Security Code: {$otpCode}. Use this 6-digit OTP to verify your farm account. Valid for 10 minutes.";
    $liveSmsSent = false;
    $smsGatewayNote = '';

    // 1. Try Semaphore (Philippine SMS API) if API key configured
    $semaphoreKey = getenv('SEMAPHORE_API_KEY') ?: '';
    if (!empty($semaphoreKey)) {
        $ch = curl_init('https://api.semaphore.co/api/v4/messages');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'apikey'  => $semaphoreKey,
            'number'  => $localMobile,
            'message' => $smsMessage,
            'sendername' => 'SEMAPHORE'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res && str_contains($res, 'message_id')) {
            $liveSmsSent = true;
            $smsGatewayNote = 'Live SMS sent via Semaphore.';
        }
    }

    // 2. Try n8n SMS Webhook if configured
    $n8nWebhook = getenv('N8N_SMS_WEBHOOK') ?: '';
    if (!$liveSmsSent && !empty($n8nWebhook)) {
        $ch = curl_init($n8nWebhook);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'mobile'  => $intlMobile,
            'local_mobile' => $localMobile,
            'otp'     => $otpCode,
            'message' => $smsMessage
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res) {
            $liveSmsSent = true;
            $smsGatewayNote = 'Live SMS queued via n8n.';
        }
    }

    // 3. Try Twilio if configured
    $twilioSid   = getenv('TWILIO_SID')   ?: (defined('TWILIO_SID') ? TWILIO_SID : '');
    $twilioToken = getenv('TWILIO_TOKEN') ?: (defined('TWILIO_TOKEN') ? TWILIO_TOKEN : '');
    $twilioFrom  = getenv('TWILIO_FROM')  ?: (defined('TWILIO_FROM') ? TWILIO_FROM : '');
    if (!$liveSmsSent && !empty($twilioSid) && !empty($twilioToken) && !empty($twilioFrom)) {
        try {
            $vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
            if (file_exists($vendorAutoload)) {
                require_once $vendorAutoload;
            }
            if (class_exists('Twilio\Rest\Client')) {
                $client = new \Twilio\Rest\Client($twilioSid, $twilioToken);
                $client->messages->create($intlMobile, [
                    'from' => $twilioFrom,
                    'body' => $smsMessage
                ]);
                $liveSmsSent = true;
                $smsGatewayNote = 'Live SMS sent via Twilio.';
            }
        } catch (Throwable $te) {
            error_log('Twilio SMS error: ' . $te->getMessage());
        }
    }

    $hasGateway = (!empty($semaphoreKey) || !empty($n8nWebhook) || (!empty($twilioSid) && !empty($twilioToken)));

    // Response: Do NOT send the OTP code to the device; user must read SMS on their phone/SIM!
    echo json_encode([
        'success'            => true,
        'message'            => "Verification code sent via SMS to {$localMobile}.",
        'mobile'             => $localMobile,
        'live_sms_sent'      => $liveSmsSent,
        'gateway_configured' => $hasGateway,
        'gateway_note'       => $smsGatewayNote ?: ($hasGateway ? 'SMS dispatched to mobile carrier.' : 'SMS gateway credentials needed in .env')
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Could not generate verification code: ' . $e->getMessage()]);
}
