<?php
/**
 * Clients API
 * GET    — list / single
 * POST   — create
 * PUT    — update
 * DELETE — admin only
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$user   = requireApiRole(['admin', 'frontdesk']);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = :id");
        $stmt->execute([':id' => (int)$_GET['id']]);
        $client = $stmt->fetch();
        if (!$client) jsonResponse(false, 'Client not found.', [], 404);
        jsonResponse(true, '', ['client' => $client]);
    }

    $search = $_GET['search'] ?? '';
    $like   = "%$search%";
    $stmt = $pdo->prepare("
        SELECT c.*, COUNT(b.id) AS total_bookings
        FROM clients c
        LEFT JOIN bookings b ON b.client_id = c.id
        WHERE c.name LIKE :s1 OR c.phone LIKE :s2 OR c.email LIKE :s3
        GROUP BY c.id
        ORDER BY c.name ASC
    ");
    $stmt->execute([':s1' => $like, ':s2' => $like, ':s3' => $like]);
    jsonResponse(true, '', ['clients' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['name']) || empty($d['phone'])) {
        jsonResponse(false, 'Name and phone are required.', [], 422);
    }
    $stmt = $pdo->prepare("
        INSERT INTO clients (name, email, phone, address)
        VALUES (:name, :email, :phone, :address)
    ");
    $stmt->execute([
        ':name'    => trim($d['name']),
        ':email'   => trim($d['email'] ?? ''),
        ':phone'   => trim($d['phone']),
        ':address' => trim($d['address'] ?? ''),
    ]);
    jsonResponse(true, 'Client added.', ['id' => $pdo->lastInsertId()], 201);
}

if ($method === 'PUT') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'Client ID required.', [], 422);
    $stmt = $pdo->prepare("
        UPDATE clients SET
            name    = COALESCE(:name, name),
            email   = :email,
            phone   = COALESCE(:phone, phone),
            address = :address
        WHERE id = :id
    ");
    $stmt->execute([
        ':id'      => (int)$d['id'],
        ':name'    => $d['name']    ?? null,
        ':email'   => $d['email']   ?? null,
        ':phone'   => $d['phone']   ?? null,
        ':address' => $d['address'] ?? null,
    ]);
    jsonResponse(true, 'Client updated.');
}

if ($method === 'DELETE') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'Client ID required.', [], 422);
    // Check for existing bookings
    $count = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = :id");
    $count->execute([':id' => (int)$d['id']]);
    if ($count->fetchColumn() > 0) {
        jsonResponse(false, 'Cannot delete a client who has existing bookings.', [], 409);
    }
    $pdo->prepare("DELETE FROM clients WHERE id = :id")->execute([':id' => (int)$d['id']]);
    jsonResponse(true, 'Client removed.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
