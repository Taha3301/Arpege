<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Import database configuration
require_once 'db.php';

function normalizeStatus($status)
{
    if (!$status)
        return 'disponible';

    $status = trim($status);
    $statusMap = [
        'available' => 'disponible',
        'occupied' => 'Occupée',
        'reserved' => 'Réservée',
        'unavailable' => 'indisponible',
        'disponible' => 'disponible',
        'Disponible' => 'disponible',
        'occupée' => 'Occupée',
        'Occupée' => 'Occupée',
        'réservée' => 'Réservée',
        'Réservée' => 'Réservée',
        'indisponible' => 'indisponible',
        'Indisponible' => 'indisponible'
    ];

    $statusLower = strtolower($status);
    if (isset($statusMap[$status]))
        return $statusMap[$status];
    if (isset($statusMap[$statusLower]))
        return $statusMap[$statusLower];

    // Fallback search
    if (stripos($status, 'dispon') !== false)
        return 'disponible';
    if (stripos($status, 'occup') !== false)
        return 'Occupée';
    if (stripos($status, 'reserv') !== false)
        return 'Réservée';
    if (stripos($status, 'indispon') !== false)
        return 'indisponible';

    return 'disponible';
}

try {
    // Ensure status column exists and is properly configured
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `table` WHERE Field = 'status'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$column) {
            $pdo->exec("ALTER TABLE `table` ADD COLUMN status VARCHAR(20) DEFAULT 'disponible'");
        }
    } catch (PDOException $e) {
    }

    // Ensure image column exists and is LONGTEXT to prevent truncation
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `table` WHERE Field = 'image'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$column) {
            $pdo->exec("ALTER TABLE `table` ADD COLUMN image LONGTEXT NULL");
        } else if (strtolower($column['Type']) !== 'longtext') {
            // Upgrade to LONGTEXT if it's something smaller (like TEXT or VARCHAR)
            $pdo->exec("ALTER TABLE `table` MODIFY COLUMN image LONGTEXT NULL");
        }
    } catch (PDOException $e) {
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // Only parse JSON input for POST and PUT requests
    $input = null;
    if ($method === 'POST' || $method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
    }

    // Get ID from query string (for GET, DELETE) or from input (for POST, PUT)
    $id = isset($_GET['id']) ? $_GET['id'] : (isset($input['id']) ? $input['id'] : null);

    switch ($method) {
        case 'GET':
            if ($id) {
                $stmt = $pdo->prepare("SELECT id, table_number, status, image FROM `table` WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $table = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($table) {
                    http_response_code(200);
                    echo json_encode(['success' => true, 'data' => $table]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Table not found']);
                }
            } else {
                $stmt = $pdo->prepare("SELECT id, table_number, status, image FROM `table` ORDER BY table_number ASC");
                $stmt->execute();
                $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
                http_response_code(200);
                echo json_encode(['success' => true, 'data' => $tables]);
            }
            break;

        case 'POST':
            if (!isset($input['table_number'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Table number is required']);
                exit();
            }

            $tableNumber = trim($input['table_number']);
            $status = isset($input['status']) ? normalizeStatus($input['status']) : 'disponible';
            $image = isset($input['image']) ? $input['image'] : null;

            // Check if table number already exists
            $stmt = $pdo->prepare("SELECT id FROM `table` WHERE table_number = :table_number");
            $stmt->execute(['table_number' => $tableNumber]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Table number already exists']);
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO `table` (table_number, status, image) VALUES (:table_number, :status, :image)");
            $stmt->execute([
                'table_number' => $tableNumber,
                'status' => $status,
                'image' => $image
            ]);

            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Table created', 'data' => ['id' => $pdo->lastInsertId()]]);
            break;

        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Table ID is required']);
                exit();
            }

            $updateFields = [];
            $updateValues = ['id' => $id];

            if (isset($input['table_number'])) {
                $updateFields[] = "table_number = :table_number";
                $updateValues['table_number'] = trim($input['table_number']);
            }
            if (isset($input['status'])) {
                $updateFields[] = "status = :status";
                $updateValues['status'] = normalizeStatus($input['status']);
            }
            if (isset($input['image'])) {
                $updateFields[] = "image = :image";
                $updateValues['image'] = $input['image'];
            }

            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                exit();
            }

            $sql = "UPDATE `table` SET " . implode(", ", $updateFields) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateValues);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Table updated']);
            break;

        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Table ID is required']);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM `table` WHERE id = :id");
            $stmt->execute(['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Table deleted']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>