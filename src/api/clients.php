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
require_once __DIR__ . '/../../includes/audit.php';

$user   = requireApiRole(['admin', 'frontdesk']);
requireCsrf();
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
    
    // Name validation
    if (strlen($d['name']) > 100) jsonResponse(false, 'Name cannot exceed 100 characters.', [], 422);
    if (!preg_match('/^[a-zA-Z\s\-]+$/', $d['name'])) jsonResponse(false, 'Name can only contain letters, spaces, and hyphens.', [], 422);

    // Email validation
    if(empty($d['email'])) {
        jsonResponse(false, 'Email is required.', [], 422);
    }
    if (strlen($d['email']) > 100) jsonResponse(false, 'Email cannot exceed 100 characters.', [], 422);
    if(filter_var($d['email'], FILTER_VALIDATE_EMAIL) === false) {
        jsonResponse(false, 'Invalid email address.', [], 422);
    }

    // Phone validation
    if(!preg_match('/^09\d{9}$/', $d['phone'])) {
        jsonResponse(false, 'Phone number must be exactly 11 digits starting with 09.', [], 422);
    }
    
    // Address & Link validation
    if (!empty($d['address']) && strlen($d['address']) > 255) jsonResponse(false, 'Address cannot exceed 255 characters.', [], 422);
    if (!empty($d['messenger_link']) && strlen($d['messenger_link']) > 255) jsonResponse(false, 'Messenger link cannot exceed 255 characters.', [], 422);

    // Enforce unique email
    $dupChk = $pdo->prepare("SELECT id FROM clients WHERE email = :email LIMIT 1");
    $dupChk->execute([':email' => trim($d['email'])]);
    if ($dupChk->fetch()) {
        jsonResponse(false, 'A client with this email address already exists.', ['field' => 'email'], 409);
    }

    $stmt = $pdo->prepare("
        INSERT INTO clients (name, email, phone, address, messenger_link)
        VALUES (:name, :email, :phone, :address, :messenger_link)
    ");
    $stmt->execute([
        ':name'    => trim($d['name']),
        ':email'   => trim($d['email'] ?? ''),
        ':phone'   => trim($d['phone']),
        ':address' => trim($d['address'] ?? ''),
        ':messenger_link' => trim($d['messenger_link'] ?? ''),
    ]);
    $newId = $pdo->lastInsertId();

    // Audit: client created
    auditLog($pdo, 'client_created', 'client', (int)$newId,
        null,
        ['name' => trim($d['name']), 'email' => trim($d['email'] ?? '')]
    );

    jsonResponse(true, 'Client added.', ['id' => $newId], 201);
}

if ($method === 'PUT') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'Client ID required.', [], 422);

    // Name validation
    if (isset($d['name'])) {
        if (strlen($d['name']) > 100) jsonResponse(false, 'Name cannot exceed 100 characters.', [], 422);
        if (!preg_match('/^[a-zA-Z\s\-]+$/', $d['name'])) jsonResponse(false, 'Name can only contain letters, spaces, and hyphens.', [], 422);
    }

    // Phone validation
    if (isset($d['phone']) && !preg_match('/^09\d{9}$/', $d['phone'])) {
        jsonResponse(false, 'Phone number must be exactly 11 digits starting with 09.', [], 422);
    }

    // Address & Link validation
    if (isset($d['address']) && strlen($d['address']) > 255) jsonResponse(false, 'Address cannot exceed 255 characters.', [], 422);
    if (isset($d['messenger_link']) && strlen($d['messenger_link']) > 255) jsonResponse(false, 'Messenger link cannot exceed 255 characters.', [], 422);

    // Enforce unique email on update (exclude self)
    if (!empty($d['email'])) {
        if (strlen($d['email']) > 100) jsonResponse(false, 'Email cannot exceed 100 characters.', [], 422);
        if(filter_var($d['email'], FILTER_VALIDATE_EMAIL) === false) jsonResponse(false, 'Invalid email address.', [], 422);

        $emailChk = $pdo->prepare("SELECT id FROM clients WHERE email = :email AND id != :id LIMIT 1");
        $emailChk->execute([':email' => trim($d['email']), ':id' => (int)$d['id']]);
        if ($emailChk->fetch()) {
            jsonResponse(false, 'This email address is already registered to another client.', ['field' => 'email'], 409);
        }
    }

    // FIX: Use COALESCE for ALL fields to prevent accidental null overwrite
    $stmt = $pdo->prepare("
        UPDATE clients SET
            name    = COALESCE(:name, name),
            email   = COALESCE(:email, email),
            phone   = COALESCE(:phone, phone),
            address = COALESCE(:address, address),
            messenger_link = COALESCE(:messenger_link, messenger_link)
        WHERE id = :id
    ");
    $stmt->execute([
        ':id'      => (int)$d['id'],
        ':name'    => $d['name']    ?? null,
        ':email'   => $d['email']   ?? null,
        ':phone'   => $d['phone']   ?? null,
        ':address' => $d['address'] ?? null,
        ':messenger_link' => $d['messenger_link'] ?? null,
    ]);

    // Audit: client updated
    auditLog($pdo, 'client_updated', 'client', (int)$d['id'],
        null,
        array_filter([
            'name'    => $d['name']    ?? null,
            'email'   => $d['email']   ?? null,
            'phone'   => $d['phone']   ?? null,
            'address' => $d['address'] ?? null,
        ], fn($v) => $v !== null)
    );

    jsonResponse(true, 'Client updated.');
}

if ($method === 'DELETE') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'Client ID required.', [], 422);

    // Get client info for audit before deletion
    $clientStmt = $pdo->prepare("SELECT name, email FROM clients WHERE id = :id");
    $clientStmt->execute([':id' => (int)$d['id']]);
    $clientData = $clientStmt->fetch();

    // Check for existing bookings
    $count = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE client_id = :id");
    $count->execute([':id' => (int)$d['id']]);
    if ($count->fetchColumn() > 0) {
        jsonResponse(false, 'Cannot delete a client who has existing bookings.', [], 409);
    }
    $pdo->prepare("DELETE FROM clients WHERE id = :id")->execute([':id' => (int)$d['id']]);

    // Audit: client deleted
    auditLog($pdo, 'client_deleted', 'client', (int)$d['id'],
        $clientData ? ['name' => $clientData['name'], 'email' => $clientData['email']] : null,
        null
    );

    jsonResponse(true, 'Client removed.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
