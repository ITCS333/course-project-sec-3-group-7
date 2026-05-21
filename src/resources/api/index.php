<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../common/db.php';$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

$action     = $_GET['action']      ?? null;
$id         = $_GET['id']          ?? null;
$resourceId = $_GET['resource_id'] ?? null;
$commentId  = $_GET['comment_id']  ?? null;


function getAllResources($db) {
    $sql = 'SELECT id, title, description, link, created_at FROM resources';
    $search = $_GET['search'] ?? null;
    if ($search) {
        $sql .= ' WHERE title LIKE :search OR description LIKE :search';
    }
    $allowedSort = ['title', 'created_at'];
    $sort = $_GET['sort'] ?? 'created_at';
    if (!in_array($sort, $allowedSort)) {
        $sort = 'created_at';
    }
    $allowedOrder = ['asc', 'desc'];
    $order = strtolower($_GET['order'] ?? 'desc');
    if (!in_array($order, $allowedOrder)) {
        $order = 'desc';
    }
    $sql .= " ORDER BY $sort $order";
    $stmt = $db->prepare($sql);
    if ($search) {
        $stmt->bindValue(':search', '%' . $search . '%');
    }
    $stmt->execute();
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(['success' => true, 'data' => $resources]);
}


function getResourceById($db, $resourceId) {
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
        return;
    }
    $stmt = $db->prepare('SELECT id, title, description, link, created_at FROM resources WHERE id = ?');
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($resource) {
        sendResponse(['success' => true, 'data' => $resource]);
    } else {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }
}


function createResource($db, $data) {
    $validation = validateRequiredFields($data, ['title', 'link']);
    if (!$validation['valid']) {
        sendResponse(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $validation['missing'])], 400);
        return;
    }
    $title       = sanitizeInput($data['title']);
    $description = sanitizeInput($data['description'] ?? '');
    $link        = sanitizeInput($data['link']);
    if (!validateUrl($link)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL format.'], 400);
        return;
    }
    if (empty($description)) {
        $description = '';
    }
    $stmt = $db->prepare('INSERT INTO resources (title, description, link) VALUES (?, ?, ?)');
    $stmt->execute([$title, $description, $link]);
    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Resource created successfully.', 'id' => $db->lastInsertId()], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create resource.'], 500);
    }
}


function updateResource($db, $data) {
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing resource ID.'], 400);
        return;
    }
    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$data['id']]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
        return;
    }
    $fields = [];
    $values = [];
    if (isset($data['title']) && $data['title'] !== '') {
        $fields[] = 'title = ?';
        $values[] = sanitizeInput($data['title']);
    }
    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $values[] = sanitizeInput($data['description']);
    }
    if (isset($data['link']) && $data['link'] !== '') {
        if (!validateUrl($data['link'])) {
            sendResponse(['success' => false, 'message' => 'Invalid URL format.'], 400);
            return;
        }
        $fields[] = 'link = ?';
        $values[] = sanitizeInput($data['link']);
    }
    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No fields provided to update.'], 400);
        return;
    }
    $sql = 'UPDATE resources SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $values[] = $data['id'];
    $stmt = $db->prepare($sql);
    $stmt->execute($values);
    sendResponse(['success' => true, 'message' => 'Resource updated successfully.']);
}


function deleteResource($db, $resourceId) {
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
        return;
    }
    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$resourceId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
        return;
    }
    $stmt = $db->prepare('DELETE FROM resources WHERE id = ?');
    $stmt->execute([$resourceId]);
    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Resource deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete resource.'], 500);
    }
}


function getCommentsByResourceId($db, $resourceId) {
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
        return;
    }
    $stmt = $db->prepare('SELECT id, resource_id, author, text, created_at FROM comments_resource WHERE resource_id = ? ORDER BY created_at ASC');
    $stmt->execute([$resourceId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(['success' => true, 'data' => $comments]);
}


function createComment($db, $data) {
    $validation = validateRequiredFields($data, ['resource_id', 'author', 'text']);
    if (!$validation['valid']) {
        sendResponse(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $validation['missing'])], 400);
        return;
    }
    if (!is_numeric($data['resource_id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
        return;
    }
    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$data['resource_id']]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
        return;
    }
    $author = sanitizeInput($data['author']);
    $text   = sanitizeInput($data['text']);
    $stmt = $db->prepare('INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)');
    $stmt->execute([$data['resource_id'], $author, $text]);
    if ($stmt->rowCount() > 0) {
        $newId = $db->lastInsertId();
        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully.',
            'id'      => $newId,
            'data'    => [
                'id'          => $newId,
                'resource_id' => $data['resource_id'],
                'author'      => $author,
                'text'        => $text
            ]
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create comment.'], 500);
    }
}


function deleteComment($db, $commentId) {
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment ID.'], 400);
        return;
    }
    $check = $db->prepare('SELECT id FROM comments_resource WHERE id = ?');
    $check->execute([$commentId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
        return;
    }
    $stmt = $db->prepare('DELETE FROM comments_resource WHERE id = ?');
    $stmt->execute([$commentId]);
    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment.'], 500);
    }
}


try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByResourceId($db, $resourceId);
        } elseif ($id) {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateResource($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteResource($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'A database error occurred.'], 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
}


function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    if (!is_array($data)) {
        $data = ['data' => $data];
    }
    echo json_encode($data);
    exit;
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateRequiredFields($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            $missing[] = $field;
        }
    }
    return ['valid' => count($missing) === 0, 'missing' => $missing];
}
?>
