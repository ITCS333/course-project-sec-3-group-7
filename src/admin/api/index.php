<?php
/**
 * User Management API
 * Handles CRUD operations for users and password changes.
 */

// --- Headers ---
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- DB Connection ---
require_once '../../common/db.php';
$db = getDBConnection();

// --- Request Info ---
$method = $_SERVER['REQUEST_METHOD'];
$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true) ?? [];

$id     = isset($_GET['id'])     ? (int)$_GET['id']     : null;
$action = isset($_GET['action']) ? $_GET['action']       : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$sort   = isset($_GET['sort'])   ? $_GET['sort']         : null;
$order  = isset($_GET['order'])  ? strtolower($_GET['order']) : 'asc';

// ============================================================================
// FUNCTIONS
// ============================================================================

function getUsers($db) {
    $allowedSort  = ['name', 'email', 'is_admin'];
    $allowedOrder = ['asc', 'desc'];

    global $search, $sort, $order;

    $sql    = 'SELECT id, name, email, is_admin, created_at FROM users';
    $params = [];

    if ($search) {
        $sql      .= ' WHERE name LIKE :search OR email LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    if ($sort && in_array($sort, $allowedSort)) {
        $dir  = in_array($order, $allowedOrder) ? $order : 'asc';
        $sql .= ' ORDER BY ' . $sort . ' ' . strtoupper($dir);
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse($rows, 200);
}

function getUserById($db, $id) {
    $stmt = $db->prepare('SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse('User not found', 404);
    }
    sendResponse($user, 200);
}

function createUser($db, $data) {
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        sendResponse('Name, email, and password are required', 400);
    }

    $name     = sanitizeInput($data['name']);
    $email    = sanitizeInput($data['email']);
    $password = trim($data['password']);

    if (!validateEmail($email)) {
        sendResponse('Invalid email format', 400);
    }
    if (strlen($password) < 8) {
        sendResponse('Password must be at least 8 characters', 400);
    }

    // Check duplicate email
    $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        sendResponse('Email already in use', 409);
    }

    $hashed  = password_hash($password, PASSWORD_DEFAULT);
    $isAdmin = isset($data['is_admin']) && in_array((int)$data['is_admin'], [0, 1])
               ? (int)$data['is_admin'] : 0;

    $stmt = $db->prepare('INSERT INTO users (name, email, password, is_admin) VALUES (:name, :email, :password, :is_admin)');
    $ok   = $stmt->execute([':name' => $name, ':email' => $email, ':password' => $hashed, ':is_admin' => $isAdmin]);

    if ($ok) {
        sendResponse(['id' => (int)$db->lastInsertId()], 201);
    } else {
        sendResponse('Failed to create user', 500);
    }
}

function updateUser($db, $data) {
    if (empty($data['id'])) {
        sendResponse('User id is required', 400);
    }

    $id   = (int)$data['id'];
    $stmt = $db->prepare('SELECT id FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) {
        sendResponse('User not found', 404);
    }

    $fields = [];
    $params = [':id' => $id];

    if (isset($data['name'])) {
        $fields[]       = 'name = :name';
        $params[':name'] = sanitizeInput($data['name']);
    }
    if (isset($data['email'])) {
        $email = sanitizeInput($data['email']);
        if (!validateEmail($email)) {
            sendResponse('Invalid email format', 400);
        }
        // Check duplicate
        $chk = $db->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
        $chk->execute([':email' => $email, ':id' => $id]);
        if ($chk->fetch()) {
            sendResponse('Email already in use', 409);
        }
        $fields[]        = 'email = :email';
        $params[':email'] = $email;
    }
    if (isset($data['is_admin'])) {
        $fields[]          = 'is_admin = :is_admin';
        $params[':is_admin'] = in_array((int)$data['is_admin'], [0, 1]) ? (int)$data['is_admin'] : 0;
    }

    if (empty($fields)) {
        sendResponse('No fields to update', 400);
    }

    $sql  = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    sendResponse('User updated successfully', 200);
}

function deleteUser($db, $id) {
    if (!$id) {
        sendResponse('User id is required', 400);
    }

    $stmt = $db->prepare('SELECT id FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) {
        sendResponse('User not found', 404);
    }

    $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
    $ok   = $stmt->execute([':id' => $id]);

    if ($ok) {
        sendResponse('User deleted successfully', 200);
    } else {
        sendResponse('Failed to delete user', 500);
    }
}

function changePassword($db, $data) {
    if (empty($data['id']) || empty($data['current_password']) || empty($data['new_password'])) {
        sendResponse('id, current_password, and new_password are required', 400);
    }

    if (strlen($data['new_password']) < 8) {
        sendResponse('New password must be at least 8 characters', 400);
    }

    $stmt = $db->prepare('SELECT password FROM users WHERE id = :id');
    $stmt->execute([':id' => (int)$data['id']]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        sendResponse('User not found', 404);
    }

    if (!password_verify($data['current_password'], $row['password'])) {
        sendResponse('Current password is incorrect', 401);
    }

    $hashed = password_hash($data['new_password'], PASSWORD_DEFAULT);
    $stmt   = $db->prepare('UPDATE users SET password = :password WHERE id = :id');
    $ok     = $stmt->execute([':password' => $hashed, ':id' => (int)$data['id']]);

    if ($ok) {
        sendResponse('Password updated successfully', 200);
    } else {
        sendResponse('Failed to update password', 500);
    }
}

// ============================================================================
// MAIN ROUTER
// ============================================================================

try {

    if ($method === 'GET') {
        if ($id) {
            getUserById($db, $id);
        } else {
            getUsers($db);
        }

    } elseif ($method === 'POST') {
        if ($action === 'change_password') {
            changePassword($db, $data);
        } else {
            createUser($db, $data);
        }

    } elseif ($method === 'PUT') {
        updateUser($db, $data);

    } elseif ($method === 'DELETE') {
        deleteUser($db, $id);

    } else {
        sendResponse('Method not allowed', 405);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse('Database error. Please try again later.', 500);

} catch (Exception $e) {
    sendResponse($e->getMessage(), 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    if ($statusCode < 400) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => $data]);
    }
    exit;
}

function validateEmail($email) {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

?>