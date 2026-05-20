<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Dynamic include path fallback to handle local vs GitHub Action runner paths
if (file_exists(__DIR__ . '/../../common/db.php')) {
    require_once __DIR__ . '/../../common/db.php';
} elseif (file_exists(__DIR__ . '/../common/db.php')) {
    require_once __DIR__ . '/../common/db.php';
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Critical Error: db.php file could not be located.']);
    exit;
}

// Safely establish connection
try {
    if (!function_exists('getDBConnection')) {
        throw new Exception("Function getDBConnection() is undefined. Check your db.php file layout.");
    }
    $db = getDBConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$method    = $_SERVER['REQUEST_METHOD'];
$rawData   = file_get_contents('php://input');
$data      = json_decode($rawData, true) ?? [];
$action    = $_GET['action']     ?? null;
$id        = $_GET['id']         ?? null;
$weekId    = $_GET['week_id']    ?? null;
$commentId = $_GET['comment_id'] ?? null;

// ============================================================================
// WEEKS FUNCTIONS
// ============================================================================

function getAllWeeks(PDO $db): void
{
    $query  = "SELECT id, title, start_date, description, links, created_at FROM weeks";
    $params = [];

    if (!empty($_GET['search'])) {
        $query .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $allowedSort = ['title', 'start_date'];
    $sort  = in_array($_GET['sort'] ?? '', $allowedSort) ? $_GET['sort'] : 'start_date';
    $order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

    $query .= " ORDER BY $sort $order";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($weeks as &$week) {
        $week['links'] = json_decode($week['links'], true) ?? [];
    }

    sendResponse(['success' => true, 'data' => $weeks]);
}

function getWeekById(PDO $db, $id): void
{
    $stmt = $db->prepare("SELECT id, title, start_date, description, links, created_at FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($week) {
        $week['links'] = json_decode($week['links'], true) ?? [];
        sendResponse(['success' => true, 'data' => $week]);
    } else {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }
}

function createWeek(PDO $db, array $data): void
{
    $title       = isset($data['title']) ? trim($data['title']) : '';
    $start_date  = isset($data['start_date']) ? trim($data['start_date']) : '';
    $description = isset($data['description']) ? trim($data['description']) : '';
    $links       = $data['links'] ?? [];

    if (empty($title)) {
        sendResponse(['success' => false, 'message' => 'Title is required'], 400);
    }
    if (empty($start_date)) {
        sendResponse(['success' => false, 'message' => 'Start date is required'], 400);
    }
    if (!validateDate($start_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD'], 400);
    }

    $stmt = $db->prepare("INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$title, $start_date, $description, json_encode($links)])) {
        sendResponse(['success' => true, 'message' => 'Week created', 'id' => (int)$db->lastInsertId()], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function updateWeek(PDO $db, array $data, $urlId = null): void
{
    $id = $data['id'] ?? $urlId ?? null;

    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    // Fetch the existing record first to enable partial patching/updates
    $check = $db->prepare("SELECT * FROM weeks WHERE id = ?");
    $check->execute([(int)$id]);
    $existingWeek = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existingWeek) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }

    // Fall back to existing database values if the fields aren't provided in the request payload
    $title       = isset($data['title']) ? trim($data['title']) : $existingWeek['title'];
    $start_date  = isset($data['start_date']) ? trim($data['start_date']) : $existingWeek['start_date'];
    $description = isset($data['description']) ? trim($data['description']) : $existingWeek['description'];
    
    if (isset($data['links'])) {
        $links = is_array($data['links']) ? $data['links'] : [];
    } else {
        $links = json_decode($existingWeek['links'], true) ?? [];
    }

    // Validation checks for updated properties
    if (empty($title)) {
        sendResponse(['success' => false, 'message' => 'Title is required'], 400);
    }
    if (empty($start_date)) {
        sendResponse(['success' => false, 'message' => 'Start date is required'], 400);
    }
    if (!validateDate($start_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD'], 400);
    }

    $stmt = $db->prepare("UPDATE weeks SET title = ?, start_date = ?, description = ?, links = ? WHERE id = ?");
    $stmt->execute([$title, $start_date, $description, json_encode($links), (int)$id]);

    sendResponse(['success' => true, 'message' => 'Week updated']);
}

function deleteWeek(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $check = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
    $stmt->execute([$id]);

    sendResponse(['success' => true, 'message' => 'Week deleted']);
}

// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================

function getCommentsByWeek(PDO $db, $weekId): void
{
    if (!$weekId || !is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'Invalid Week ID'], 400);
    }

    $stmt = $db->prepare("SELECT * FROM comments_week WHERE week_id = ? ORDER BY created_at ASC");
    $stmt->execute([$weekId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $comments]);
}

function createComment(PDO $db, array $data): void
{
    $weekId = $data['week_id'] ?? null;
    $author = trim($data['author'] ?? '');
    $text   = trim($data['text']   ?? '');

    if (empty($text) || empty($author)) {
        sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
    }

    if (!$weekId || !is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'Invalid week_id'], 400);
    }

    $check = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $check->execute([$weekId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }

    $stmt = $db->prepare("INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)");
    if ($stmt->execute([$weekId, $author, $text])) {
        $newId = (int)$db->lastInsertId();
        $fetch = $db->prepare("SELECT * FROM comments_week WHERE id = ?");
        $fetch->execute([$newId]);
        $comment = $fetch->fetch(PDO::FETCH_ASSOC);
        sendResponse(['success' => true, 'message' => 'Comment added', 'id' => $newId, 'data' => $comment], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function deleteComment(PDO $db, $commentId): void
{
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment ID'], 400);
    }

    $check = $db->prepare("SELECT id FROM comments_week WHERE id = ?");
    $check->execute([$commentId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_week WHERE id = ?");
    $stmt->execute([$commentId]);

    sendResponse(['success' => true, 'message' => 'Comment deleted']);
}

// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);
        } elseif ($id) {
            getWeekById($db, $id);
        } else {
            getAllWeeks($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createWeek($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateWeek($db, $data, $id);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method Not Allowed'], 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Database error'], 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Server error'], 500);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
