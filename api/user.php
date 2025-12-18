<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Import database configuration
require_once 'db.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['code']) || empty($input['code'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Code is required']);
        exit();
    }

    $code = trim($input['code']);

    // Query the database to find user by password (code) and include status
    $stmt = $pdo->prepare("SELECT id, username, role, status FROM user WHERE password = :password LIMIT 1");
    $stmt->execute(['password' => $code]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // User found, return success with role and status
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'status' => $user['status'] ?? 'active' // Default to 'active' if null
            ]
        ]);
    } else {
        // User not found
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid code'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>