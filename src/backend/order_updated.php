<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/* ==================== DB CONFIG ==================== */
require_once 'db.php';

/* ==================== GET ==================== */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $order_id = $_GET['id'] ?? null;
    $table_id = $_GET['table_id'] ?? null;

    /* 🔹 Get order by ID */
    if ($order_id) {
        $stmt = $pdo->prepare("
            SELECT o.*, u.username AS employee_name
            FROM `order` o
            LEFT JOIN `user` u ON o.employee_id = u.id
            WHERE o.id = :id
        ");
        $stmt->execute([':id' => $order_id]);
        $order = $stmt->fetch();

        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT oi.*, p.name AS product_name
            FROM order_item oi
            JOIN product p ON oi.product_id = p.id
            WHERE oi.order_id = :order_id
        ");
        $stmt->execute([':order_id' => $order_id]);
        $order['items'] = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $order]);
        exit;
    }

    /* 🔹 Get active order by table */
    if ($table_id) {
        $stmt = $pdo->prepare("
            SELECT o.*, u.username AS employee_name
            FROM `order` o
            LEFT JOIN `user` u ON o.employee_id = u.id
            WHERE o.table_id = :table_id
            AND o.status != 'paye'
            ORDER BY o.order_time DESC
            LIMIT 1
        ");
        $stmt->execute([':table_id' => $table_id]);
        $order = $stmt->fetch();

        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'No active order']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT oi.*, p.name AS product_name, p.price AS product_price
            FROM order_item oi
            JOIN product p ON oi.product_id = p.id
            WHERE oi.order_id = :order_id
        ");
        $stmt->execute([':order_id' => $order['id']]);
        $order['items'] = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $order]);
        exit;
    }

    /* 🔹 Get all orders */
    $stmt = $pdo->query("
        SELECT 
            o.*, 
            t.table_number,
            u.username AS employee_name
        FROM `order` o
        LEFT JOIN `table` t ON o.table_id = t.id
        LEFT JOIN `user` u ON o.employee_id = u.id
        ORDER BY o.order_time DESC
    ");

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

/* ==================== POST ==================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = json_decode(file_get_contents('php://input'), true);

    $table_id = $input['table_id'] ?? null;
    $items = $input['items'] ?? [];
    $total = $input['total'] ?? 0;
    $employee_id = $input['employee_id'] ?? null;

    if (!$table_id || empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing fields']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO `order` (table_id, total, employee_id, status, order_time)
            VALUES (:table_id, :total, :employee_id, 'pending', NOW())
        ");
        $stmt->execute([
            ':table_id' => $table_id,
            ':total' => $total,
            ':employee_id' => $employee_id
        ]);

        $order_id = $pdo->lastInsertId();

        foreach ($items as $item) {
            $stmt = $pdo->prepare("
                INSERT INTO order_item (order_id, product_id, quantity, price)
                VALUES (:order_id, :product_id, :quantity, :price)
            ");
            $stmt->execute([
                ':order_id' => $order_id,
                ':product_id' => $item['product_id'],
                ':quantity' => $item['quantity'],
                ':price' => $item['price']
            ]);
        }

        $pdo->prepare("UPDATE `table` SET status = 'Occupée' WHERE id = :id")
            ->execute([':id' => $table_id]);

        $pdo->commit();

        echo json_encode(['success' => true, 'order_id' => $order_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

/* ==================== PUT ==================== */
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {

    $id = $_GET['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $pdo->beginTransaction();

        /* 🔹 Update status/payment */
        if (isset($input['status'])) {
            $stmt = $pdo->prepare("
                UPDATE `order`
                SET status = :status,
                    employee_id = COALESCE(:employee_id, employee_id)
                WHERE id = :id
            ");
            $stmt->execute([
                ':status' => $input['status'],
                ':employee_id' => $input['employee_id'] ?? null,
                ':id' => $id
            ]);

            if ($input['status'] === 'paye') {
                $pdo->prepare("
                    UPDATE `table` t
                    JOIN `order` o ON o.table_id = t.id
                    SET t.status = 'disponible'
                    WHERE o.id = :id
                ")->execute([':id' => $id]);
            }
        }

        /* 🔹 Update items (Full replacement) */
        if (isset($input['items']) && is_array($input['items'])) {
            // Update Order Header (Total, etc.)
            $stmt = $pdo->prepare("
                UPDATE `order`
                SET total = :total,
                    employee_id = COALESCE(:employee_id, employee_id)
                WHERE id = :id
            ");
            $stmt->execute([
                ':total' => $input['total'] ?? 0,
                ':employee_id' => $input['employee_id'] ?? null,
                ':id' => $id
            ]);

            // Clear existing items
            $pdo->prepare("DELETE FROM order_item WHERE order_id = :id")
                ->execute([':id' => $id]);

            // Insert new items
            foreach ($input['items'] as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO order_item (order_id, product_id, quantity, price)
                    VALUES (:order_id, :product_id, :quantity, :price)
                ");
                $stmt->execute([
                    ':order_id' => $id,
                    ':product_id' => $item['product_id'],
                    ':quantity' => $item['quantity'],
                    ':price' => $item['price']
                ]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

/* ==================== DELETE ==================== */
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    $id = $_GET['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT table_id FROM `order` WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch();

        $pdo->prepare("DELETE FROM order_item WHERE order_id = :id")
            ->execute([':id' => $id]);

        $pdo->prepare("DELETE FROM `order` WHERE id = :id")
            ->execute([':id' => $id]);

        if ($order) {
            $pdo->prepare("UPDATE `table` SET status='disponible' WHERE id=:id")
                ->execute([':id' => $order['table_id']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
