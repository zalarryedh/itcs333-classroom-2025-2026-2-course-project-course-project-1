<?php

header("Content-Type: application/json");
require_once './config/Database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;


// ================== FUNCTIONS ==================

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL);
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}


// ================== RESOURCES ==================

function getAllResources($db) {

    $sql = "SELECT * FROM resources";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success'=>true,'data'=>$data]);
}

function getResourceById($db, $id) {

    if (!is_numeric($id)) {
        sendResponse(['success'=>false,'message'=>'Invalid ID'],400);
    }

    $stmt = $db->prepare("SELECT * FROM resources WHERE id=?");
    $stmt->execute([$id]);

    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        sendResponse(['success'=>false,'message'=>'Resource not found'],404);
    }

    sendResponse(['success'=>true,'data'=>$data]);
}

function createResource($db, $data) {

    if (empty($data['title']) || empty($data['link'])) {
        sendResponse(['success'=>false,'message'=>'Missing fields'],400);
    }

    if (!validateUrl($data['link'])) {
        sendResponse(['success'=>false,'message'=>'Invalid URL'],400);
    }

    $stmt = $db->prepare("INSERT INTO resources(title,description,link) VALUES(?,?,?)");
    $stmt->execute([
        sanitizeInput($data['title']),
        sanitizeInput($data['description'] ?? ''),
        sanitizeInput($data['link'])
    ]);

    sendResponse(['success'=>true,'id'=>$db->lastInsertId()],201);
}

function updateResource($db, $data) {

    if (!isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success'=>false,'message'=>'Invalid ID'],400);
    }

    $id = $data['id'];

    $fields = [];
    $values = [];

    if (isset($data['title'])) {
        $fields[] = "title=?";
        $values[] = sanitizeInput($data['title']);
    }

    if (isset($data['description'])) {
        $fields[] = "description=?";
        $values[] = sanitizeInput($data['description']);
    }

    if (isset($data['link'])) {
        if (!validateUrl($data['link'])) {
            sendResponse(['success'=>false,'message'=>'Invalid URL'],400);
        }
        $fields[] = "link=?";
        $values[] = sanitizeInput($data['link']);
    }

    if (empty($fields)) {
        sendResponse(['success'=>false,'message'=>'No fields'],400);
    }

    $values[] = $id;

    $sql = "UPDATE resources SET ".implode(',', $fields)." WHERE id=?";
    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    sendResponse(['success'=>true]);
}

function deleteResource($db, $id) {

    if (!is_numeric($id)) {
        sendResponse(['success'=>false],400);
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(['success'=>true]);
}


// ================== COMMENTS ==================

function getComments($db,$rid){
    $stmt = $db->prepare("SELECT * FROM comments_resource WHERE resource_id=?");
    $stmt->execute([$rid]);

    sendResponse([
        'success'=>true,
        'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function createComment($db,$data){

    if(empty($data['resource_id']) || empty($data['author']) || empty($data['text'])){
        sendResponse(['success'=>false],400);
    }

    $stmt=$db->prepare("INSERT INTO comments_resource(resource_id,author,text) VALUES(?,?,?)");

    $stmt->execute([
        $data['resource_id'],
        sanitizeInput($data['author']),
        sanitizeInput($data['text'])
    ]);

    sendResponse(['success'=>true],201);
}

function deleteComment($db,$id){

    $stmt=$db->prepare("DELETE FROM comments_resource WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(['success'=>true]);
}


// ================== ROUTER ==================

try {

    if ($method === 'GET') {

        if ($action === 'comments') {
            getComments($db,$resource_id);
        } elseif ($id) {
            getResourceById($db,$id);
        } else {
            getAllResources($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {
            createComment($db,$data);
        } else {
            createResource($db,$data);
        }

    } elseif ($method === 'PUT') {

        updateResource($db,$data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment') {
            deleteComment($db,$comment_id);
        } else {
            deleteResource($db,$id);
        }

    } else {
        sendResponse(['success'=>false,'message'=>'Method not allowed'],405);
    }

} catch (Exception $e) {

    error_log($e->getMessage());
    sendResponse(['success'=>false,'message'=>'Server error'],500);
}
