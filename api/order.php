<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

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
            u.username AS employee_name,
            COALESCE(o.percent_decrease, 16.7) as percent_decrease
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
    $percent_decrease = $input['percent_decrease'] ?? 16.7;
    $total_before_decrease = $input['total_before_decrease'] ?? 0;

    if (!$table_id || empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing fields']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Initialize totals to calculate them server-side for accuracy
        $calculated_total = 0;
        $calculated_total_before = 0;

        foreach ($items as $item) {
            $item_qty = floatval($item['quantity']);
            $unit_price = floatval($item['price']); // unit price (discounted)
            $item_total_discounted = $unit_price * $item_qty; // LINE TOTAL (discounted)
            $item_total_before = floatval($item['total_before_decrease'] ?? ($item_qty * ($unit_price / (1 - ($percent_decrease / 100)))));

            $calculated_total += $item_total_discounted;
            $calculated_total_before += $item_total_before;
        }

        $stmt = $pdo->prepare("
            INSERT INTO `order` (table_id, total, employee_id, status, order_time, percent_decrease, total_before_decrease)
            VALUES (:table_id, :total, :employee_id, 'pending', NOW(), :percent_decrease, :total_before_decrease)
        ");
        $stmt->execute([
            ':table_id' => $table_id,
            ':total' => $calculated_total,
            ':employee_id' => $employee_id,
            ':percent_decrease' => $percent_decrease,
            ':total_before_decrease' => $calculated_total_before
        ]);

        $order_id = $pdo->lastInsertId();

        foreach ($items as $item) {
            $item_qty = floatval($item['quantity']);
            $unit_price = floatval($item['price']);
            $item_total_discounted = $unit_price * $item_qty; // We store it in 'price' as requested

            $stmt = $pdo->prepare("
                INSERT INTO order_item (order_id, product_id, quantity, price, percent_decrease, total_before_decrease)
                VALUES (:order_id, :product_id, :quantity, :price, :percent_decrease, :total_before_decrease)
            ");
            $stmt->execute([
                ':order_id' => $order_id,
                ':product_id' => $item['product_id'],
                ':quantity' => $item_qty,
                ':price' => $item_total_discounted,
                ':percent_decrease' => $item['percent_decrease'] ?? $percent_decrease,
                ':total_before_decrease' => $item['total_before_decrease'] ?? ($item_total_before)
            ]);

            /* 🔻 STOCK DEDUCTION (Consume) */
            $stmt = $pdo->prepare("
                SELECT stock_id, quantity AS stock_qty_per_product
                FROM product_stock_ingredient
                WHERE product_id = :product_id
            ");
            $stmt->execute([':product_id' => $item['product_id']]);
            $ingredients = $stmt->fetchAll();

            foreach ($ingredients as $ing) {
                // Deduct: stock = stock - (ingredient_needed * quantity_ordered)
                $pdo->prepare("
                    UPDATE stock
                    SET quantity = quantity - (:used_qty * :order_qty)
                    WHERE id = :stock_id
                ")->execute([
                            ':used_qty' => $ing['stock_qty_per_product'],
                            ':order_qty' => $item['quantity'],
                            ':stock_id' => $ing['stock_id']
                        ]);
            }
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

        /* 🔹 Update status/payment (No Stock Change) */
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

        /* 🔹 Update items (Full replacement with Stock Adjustment) */
        if (isset($input['items']) && is_array($input['items'])) {
            // Calculate totals from items for accuracy
            $calculated_total = 0;
            $calculated_total_before = 0;
            foreach ($input['items'] as $item) {
                $item_qty = floatval($item['quantity']);
                $unit_price = floatval($item['price']); // This is the unit price (discounted)
                $item_total_discounted = $unit_price * $item_qty; // LINE TOTAL (discounted)
                $item_total_before = floatval($item['total_before_decrease'] ?? ($item_total_discounted / (1 - ($percent_decrease / 100))));

                $calculated_total += $item_total_discounted;
                $calculated_total_before += $item_total_before;
            }

            // Update Order Header
            $stmt = $pdo->prepare("
                UPDATE `order`
                SET total = :total,
                    employee_id = COALESCE(:employee_id, employee_id),
                    percent_decrease = :percent_decrease,
                    total_before_decrease = :total_before_decrease
                WHERE id = :id
            ");
            $stmt->execute([
                ':total' => $calculated_total,
                ':employee_id' => $input['employee_id'] ?? null,
                ':percent_decrease' => $input['percent_decrease'] ?? 16.7,
                ':total_before_decrease' => $calculated_total_before,
                ':id' => $id
            ]);

            /* 1. RESTORE STOCK for OLD items */
            $stmt = $pdo->prepare("
                SELECT oi.*, psi.stock_id, psi.quantity AS stock_qty_per_product
                FROM order_item oi
                JOIN product_stock_ingredient psi ON oi.product_id = psi.product_id
                WHERE oi.order_id = :id
            ");
            $stmt->execute([':id' => $id]);
            $old_items = $stmt->fetchAll();

            foreach ($old_items as $old) {
                // Restore: stock = stock + (ingredient_needed * old_quantity)
                $pdo->prepare("
                    UPDATE stock
                    SET quantity = quantity + (:used_qty * :order_qty)
                    WHERE id = :stock_id
                ")->execute([
                            ':used_qty' => $old['stock_qty_per_product'],
                            ':order_qty' => $old['quantity'],
                            ':stock_id' => $old['stock_id']
                        ]);
            }

            /* 2. DELETE OLD items */
            $pdo->prepare("DELETE FROM order_item WHERE order_id = :id")
                ->execute([':id' => $id]);

            /* 3. INSERT NEW items & DEDUCT STOCK */
            foreach ($input['items'] as $item) {
                $item_qty = floatval($item['quantity']);
                $unit_price = floatval($item['price']);
                $item_total_discounted = $unit_price * $item_qty;
                $p_decrease = $item['percent_decrease'] ?? ($input['percent_decrease'] ?? 16.7);
                $item_total_before = floatval($item['total_before_decrease'] ?? ($item_total_discounted / (1 - ($p_decrease / 100))));

                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO order_item (order_id, product_id, quantity, price, percent_decrease, total_before_decrease)
                    VALUES (:order_id, :product_id, :quantity, :price, :percent_decrease, :total_before_decrease)
                ");
                $stmt->execute([
                    ':order_id' => $id,
                    ':product_id' => $item['product_id'],
                    ':quantity' => $item_qty,
                    ':price' => $item_total_discounted,
                    ':percent_decrease' => $p_decrease,
                    ':total_before_decrease' => $item_total_before
                ]);

                // Deduct
                $stmt = $pdo->prepare("
                    SELECT stock_id, quantity AS stock_qty_per_product
                    FROM product_stock_ingredient
                    WHERE product_id = :product_id
                ");
                $stmt->execute([':product_id' => $item['product_id']]);
                $ingredients = $stmt->fetchAll();

                foreach ($ingredients as $ing) {
                    $pdo->prepare("
                        UPDATE stock
                        SET quantity = quantity - (:used_qty * :order_qty)
                        WHERE id = :stock_id
                    ")->execute([
                                ':used_qty' => $ing['stock_qty_per_product'],
                                ':order_qty' => $item['quantity'],
                                ':stock_id' => $ing['stock_id']
                            ]);
                }
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

        /* 🔻 Restore Stock before Deleting */
        $stmt = $pdo->prepare("
            SELECT oi.*, psi.stock_id, psi.quantity AS stock_qty_per_product
            FROM order_item oi
            JOIN product_stock_ingredient psi ON oi.product_id = psi.product_id
            WHERE oi.order_id = :id
        ");
        $stmt->execute([':id' => $id]);
        $items_to_restore = $stmt->fetchAll();

        foreach ($items_to_restore as $item) {
            $pdo->prepare("
                UPDATE stock
                SET quantity = quantity + (:used_qty * :order_qty)
                WHERE id = :stock_id
            ")->execute([
                        ':used_qty' => $item['stock_qty_per_product'],
                        ':order_qty' => $item['quantity'],
                        ':stock_id' => $item['stock_id']
                    ]);
        }

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
