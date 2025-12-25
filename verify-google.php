<?php
// verify-google.php
// Verifies Google ID token (credential) with Google's tokeninfo endpoint
// and only allows the configured allowed email to sign in.
session_start();
header('Content-Type: application/json');

$allowed_email = 'brgysanisidrohealth@gmail.com';

// only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (empty($data['credential'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Missing credential']);
  exit;
}

$id_token = $data['credential'];

// Verify the token with Google's tokeninfo endpoint
$verify_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);
$resp = @file_get_contents($verify_url);
if ($resp === false) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Failed to verify token']);
  exit;
}

$info = json_decode($resp, true);
if (empty($info) || empty($info['email'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Invalid token']);
  exit;
}

$email = $info['email'];
$email_verified = isset($info['email_verified']) ? filter_var($info['email_verified'], FILTER_VALIDATE_BOOLEAN) : false;

// Only allow if email matches the allowed email and is verified
if (strtolower($email) === strtolower($allowed_email) && $email_verified) {
  // create session for the admin user
  $_SESSION['username'] = 'Administrator';
  $_SESSION['email'] = $email;
  $_SESSION['role'] = 'Administrator';
  echo json_encode(['success' => true, 'message' => 'OK']);
  exit;
} else {
  // Not allowed
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'This Google account is not authorized to sign in.']);
  exit;
}

?>