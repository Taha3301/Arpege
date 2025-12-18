<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Import database configuration
require_once 'db.php';

try {

    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);

    // Get role from query parameter or request body
    $role = isset($_GET['role']) ? $_GET['role'] : (isset($input['role']) ? $input['role'] : null);
    $id = isset($_GET['id']) ? $_GET['id'] : (isset($input['id']) ? $input['id'] : null);

    switch ($method) {
        case 'GET':
            // List all users (optionally filtered by role)
            if ($id) {
                // Get single user by ID
                $stmt = $pdo->prepare("SELECT id, username, password, role, status, created_at FROM user WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    http_response_code(200);
                    echo json_encode(['success' => true, 'data' => $user]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                }
            } else {
                // Get all users (optionally filtered by role)
                if ($role) {
                    $stmt = $pdo->prepare("SELECT id, username, password, role, status, created_at FROM user WHERE role = :role ORDER BY created_at DESC");
                    $stmt->execute(['role' => $role]);
                } else {
                    $stmt = $pdo->prepare("SELECT id, username, password, role, status, created_at FROM user ORDER BY created_at DESC");
                    $stmt->execute();
                }

                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                http_response_code(200);
                echo json_encode(['success' => true, 'data' => $users]);
            }
            break;

        case 'POST':
            // Create new user
            if (!isset($input['username']) || !isset($input['password']) || !isset($input['role'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Username, password, and role are required']);
                exit();
            }

            $username = trim($input['username']);
            $password = trim($input['password']);
            $role = trim($input['role']);
            $status = isset($input['status']) ? trim($input['status']) : 'active'; // Default to 'active'

            // Validate role
            if (!in_array($role, ['admin', 'employee'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Role must be either "admin" or "employee"']);
                exit();
            }

            // Validate status
            if (!in_array($status, ['active', 'inactive'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Status must be either "active" or "inactive"']);
                exit();
            }

            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM user WHERE username = :username");
            $stmt->execute(['username' => $username]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Username already exists']);
                exit();
            }

            // Insert new user
            $stmt = $pdo->prepare("INSERT INTO user (username, password, role, status, created_at) VALUES (:username, :password, :role, :status, NOW())");
            $stmt->execute([
                'username' => $username,
                'password' => $password,
                'role' => $role,
                'status' => $status
            ]);

            $userId = $pdo->lastInsertId();
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'User created successfully',
                'data' => [
                    'id' => $userId,
                    'username' => $username,
                    'role' => $role,
                    'status' => $status
                ]
            ]);
            break;

        case 'PUT':
            // Update user
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                exit();
            }

            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM user WHERE id = :id");
            $stmt->execute(['id' => $id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit();
            }

            $updateFields = [];
            $updateValues = ['id' => $id];

            if (isset($input['username'])) {
                // Check if new username already exists (excluding current user)
                $stmt = $pdo->prepare("SELECT id FROM user WHERE username = :username AND id != :id");
                $stmt->execute(['username' => trim($input['username']), 'id' => $id]);
                if ($stmt->fetch()) {
                    http_response_code(409);
                    echo json_encode(['success' => false, 'message' => 'Username already exists']);
                    exit();
                }
                $updateFields[] = "username = :username";
                $updateValues['username'] = trim($input['username']);
            }

            if (isset($input['password'])) {
                $updateFields[] = "password = :password";
                $updateValues['password'] = trim($input['password']);
            }

            if (isset($input['role'])) {
                $role = trim($input['role']);
                if (!in_array($role, ['admin', 'employee'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Role must be either "admin" or "employee"']);
                    exit();
                }
                $updateFields[] = "role = :role";
                $updateValues['role'] = $role;
            }

            if (isset($input['status'])) {
                $status = trim($input['status']);
                if (!in_array($status, ['active', 'inactive'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Status must be either "active" or "inactive"']);
                    exit();
                }
                $updateFields[] = "status = :status";
                $updateValues['status'] = $status;
            }

            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                exit();
            }

            $sql = "UPDATE user SET " . implode(", ", $updateFields) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateValues);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            break;

        case 'DELETE':
            // Delete user
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                exit();
            }

            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM user WHERE id = :id");
            $stmt->execute(['id' => $id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM user WHERE id = :id");
            $stmt->execute(['id' => $id]);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
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