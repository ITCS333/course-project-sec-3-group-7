<?php
/**
 * Authentication Handler for Login Form
 */

// --- Session Management ---
session_start();

// --- Set Response Headers ---
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Check Request Method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// --- Get POST Data ---
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (empty($data['email']) || empty($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

$email    = trim($data['email']);
$password = $data['password'];

// --- Server-Side Validation ---
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}

// --- Database Connection ---
require_once '../../common/db.php';

$db = getDBConnection();

try {
    // --- Prepare SQL Query ---
    $sql  = 'SELECT id, name, email, password, is_admin FROM users WHERE email = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // --- Successful Authentication ---
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['is_admin']   = $user['is_admin'];
        $_SESSION['logged_in']  = true;

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user'    => [
                'id'       => $user['id'],
                'name'     => $user['name'],
                'email'    => $user['email'],
                'is_admin' => $user['is_admin'],
            ]
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    exit;
}
?>