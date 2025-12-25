<?php
// verify_email_api.php - Verify if email is a valid and existing Gmail account
header('Content-Type: application/json');

$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if (empty($email)) {
    echo json_encode(['valid' => false, 'message' => 'Email is required']);
    exit;
}

// Check if it's a Gmail address
if (!preg_match('/@gmail\.com$/i', $email)) {
    echo json_encode(['valid' => false, 'message' => 'Only @gmail.com addresses are allowed']);
    exit;
}

// Basic format validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['valid' => false, 'message' => 'Invalid email format']);
    exit;
}

// Method 1: Try using Hunter.io API (free tier: 50 requests/month)
// Get API key from: https://hunter.io/api-keys
function verifyWithHunter($email) {
    $apiKey = ''; // Add your Hunter.io API key here
    
    if (empty($apiKey)) {
        return null; // Skip if no API key
    }
    
    $url = "https://api.hunter.io/v2/email-verifier?email=" . urlencode($email) . "&api_key=" . $apiKey;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['data']['status'])) {
            $status = $data['data']['status'];
            if ($status === 'valid') {
                return ['valid' => true, 'message' => 'Gmail account verified'];
            } else {
                return ['valid' => false, 'message' => 'Couldn\'t find email'];
            }
        }
    }
    
    return null;
}

// Method 2: Try using Abstract API (free tier: 100 requests/month)
// Get API key from: https://app.abstractapi.com/api/email-validation/
function verifyWithAbstract($email) {
    $apiKey = ''; // Add your Abstract API key here
    
    if (empty($apiKey)) {
        return null; // Skip if no API key
    }
    
    $url = "https://emailvalidation.abstractapi.com/v1/?api_key=" . $apiKey . "&email=" . urlencode($email);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['deliverability'])) {
            if ($data['deliverability'] === 'DELIVERABLE') {
                return ['valid' => true, 'message' => 'Gmail account verified'];
            } else {
                return ['valid' => false, 'message' => 'Couldn\'t find email'];
            }
        }
    }
    
    return null;
}

// Method 3: Basic regex and DNS validation (fallback)
function basicValidation($email) {
    // Check email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Invalid email format'];
    }
    
    // Extract domain
    $domain = substr(strrchr($email, "@"), 1);
    
    // Check if domain has MX records
    if (!checkdnsrr($domain, 'MX')) {
        return ['valid' => false, 'message' => 'Invalid email domain'];
    }
    
    // Check common Gmail patterns that are likely invalid
    $username = substr($email, 0, strpos($email, '@'));
    
    // Gmail usernames must be 6-30 characters
    if (strlen($username) < 6 || strlen($username) > 30) {
        return ['valid' => false, 'message' => 'Couldn\'t find email'];
    }
    
    // Gmail usernames can only contain letters, numbers, and periods
    if (!preg_match('/^[a-zA-Z0-9.]+$/', $username)) {
        return ['valid' => false, 'message' => 'Couldn\'t find email'];
    }
    
    // Cannot start or end with a period
    if ($username[0] === '.' || $username[strlen($username) - 1] === '.') {
        return ['valid' => false, 'message' => 'Couldn\'t find email'];
    }
    
    // Cannot have consecutive periods
    if (strpos($username, '..') !== false) {
        return ['valid' => false, 'message' => 'Couldn\'t find email'];
    }
    
    // If all checks pass, consider it potentially valid
    return ['valid' => true, 'message' => 'Email format is valid'];
}

// Try verification methods in order
$result = verifyWithHunter($email);
if ($result === null) {
    $result = verifyWithAbstract($email);
}
if ($result === null) {
    $result = basicValidation($email);
}

echo json_encode([
    'valid' => $result['valid'],
    'message' => $result['message'],
    'email' => $email
]);
