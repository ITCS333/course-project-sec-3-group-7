<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../common/db.php';

$db = getDBConnection();

$method  = $_SERVER['REQUEST_METHOD'];
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

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
    $title       = trim($data['title']       ?? '');
    $start_date  = trim($data['start_date']  ?? '');
    $description = trim($data['description'] ?? '');
    $links       = $data['links'] ?? [];

    if (empty($title) || empty($start_date)) {
        sendResponse(['success' => false, 'message' => 'Missing required fields'], 400);
    }

    $stmt = $db->prepare("INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$title, $start_date, $description, json_encode($links)])) {
        sendResponse(['success' => true, 'message' => 'Week created', 'id' => (int)$db->lastInsertId()], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function updateWeek(PDO $db, array $data): void
{
    $id          = $data['id']          ?? null;
    $title       = trim($data['title']       ?? '');
    $start_date  = trim($data['start_date']  ?? '');
    $description = trim($data['description'] ?? '');
    $links       = $data['links'] ?? [];

    if (!$id || !is_numeric($id) || empty($title) || empty($start_date)) {
        sendResponse(['success' => false, 'message' => 'Missing required fields'], 400);
    }

    $stmt = $db->prepare("UPDATE weeks SET title = ?, start_date = ?, description = ?, links = ? WHERE id = ?");
    if ($stmt->execute([$title, $start_date, $description, json_encode($links), $id])) {
        if ($stmt->rowCount() > 0) {
            sendResponse(['success' => true, 'message' => 'Week updated']);
        } else {
            sendResponse(['success' => false, 'message' => 'Week not found'], 404);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function deleteWeek(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week deleted']);
    } else {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }
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

    if (!$weekId || empty($author) || empty($text)) {
        sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
    }

    $stmt = $db->prepare("INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)");
    if ($stmt->execute([$weekId, $author, $text])) {
        $newId = (int)$db->lastInsertId();

        // Fetch the inserted comment so details.js gets the full object in result.data
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

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted']);
    } else {
        sendResponse(['success' => false, 'message' => 'Database error'], 500);
    }
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
        updateWeek($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} catch (PDOException $e) {
    sendResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    sendResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
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
    return (bool)DateTime::createFromFormat('Y-m-d', $date);
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}    // TODO: return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
