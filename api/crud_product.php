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

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = null;
    if ($method === 'POST' || $method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
            exit();
        }
    }

    $id = isset($_GET['id']) ? $_GET['id'] : (isset($input['id']) ? $input['id'] : null);
    $type = isset($_GET['type']) ? $_GET['type'] : (isset($input['type']) ? $input['type'] : 'product');

    switch ($method) {
        case 'GET':
            if ($type === 'ingredient') {
                // Get ingredients
                if ($id) {
                    $stmt = $pdo->prepare("SELECT id, name, weight FROM product_ingredient WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($ingredient) {
                        http_response_code(200);
                        echo json_encode(['success' => true, 'data' => $ingredient]);
                    } else {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Ingredient not found']);
                    }
                } else {
                    $stmt = $pdo->prepare("SELECT id, name, weight FROM product_ingredient ORDER BY name ASC");
                    $stmt->execute();
                    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    http_response_code(200);
                    echo json_encode(['success' => true, 'data' => $ingredients]);
                }
            } elseif ($type === 'categories') {
                // Get categories for dropdown
                $stmt = $pdo->prepare("SELECT id, name FROM category ORDER BY name ASC");
                $stmt->execute();
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                http_response_code(200);
                echo json_encode(['success' => true, 'data' => $categories]);
            } elseif ($type === 'product_ingredients') {
                // Get ingredients for a specific product
                if ($id) {
                    $stmt = $pdo->prepare("
                        SELECT pi.id, pi.name, pi.weight, pim.id as map_id
                        FROM product_ingredient pi
                        INNER JOIN product_ingredient_map pim ON pi.id = pim.ingredient_id
                        WHERE pim.product_id = :product_id
                    ");
                    $stmt->execute(['product_id' => $id]);
                    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    http_response_code(200);
                    echo json_encode(['success' => true, 'data' => $ingredients]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Product ID required']);
                }
            } else {
                // Get products
                if ($id) {
                    $stmt = $pdo->prepare("
                        SELECT p.id, p.name, p.price, p.price_strangers, p.category_id, 
                               c.name as category_name
                        FROM product p
                        LEFT JOIN category c ON p.category_id = c.id
                        WHERE p.id = :id
                    ");
                    $stmt->execute(['id' => $id]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($product) {
                        // Get stock ingredients for this product
                        $stmt2 = $pdo->prepare("
                            SELECT psi.stock_id as stock_ingredient_id, psi.quantity,
                                   s.name as stock_name, s.unit, s.quantity AS stock_quantity
                            FROM product_stock_ingredient psi
                            INNER JOIN stock s ON psi.stock_id = s.id
                            WHERE psi.product_id = :product_id
                        ");
                        $stmt2->execute(['product_id' => $id]);
                        $product['stock_ingredients'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

                        http_response_code(200);
                        echo json_encode(['success' => true, 'data' => $product]);
                    } else {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Product not found']);
                    }
                } else {
                    // Get all products with their stock ingredients
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT p.id, p.name, p.price, p.price_strangers, p.category_id, 
                               c.name as category_name
                        FROM product p
                        LEFT JOIN category c ON p.category_id = c.id
                        ORDER BY p.name ASC
                    ");
                    $stmt->execute();
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Get stock ingredients for each product
                    foreach ($products as &$product) {
                        $stmt2 = $pdo->prepare("
                            SELECT psi.stock_id as stock_ingredient_id, psi.quantity,
                                   s.name as stock_name, s.unit, s.quantity AS stock_quantity
                            FROM product_stock_ingredient psi
                            INNER JOIN stock s ON psi.stock_id = s.id
                            WHERE psi.product_id = :product_id
                        ");
                        $stmt2->execute(['product_id' => $product['id']]);
                        $product['stock_ingredients'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                    }
                    unset($product);

                    http_response_code(200);
                    echo json_encode(['success' => true, 'data' => $products]);
                }
            }
            break;

        case 'POST':
            if ($type === 'ingredient') {
                // Create ingredient
                if (!isset($input['name']) || empty(trim($input['name']))) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Ingredient name is required']);
                    exit();
                }

                $name = trim($input['name']);
                $weight = isset($input['weight']) ? floatval($input['weight']) : 0;

                $stmt = $pdo->prepare("INSERT INTO product_ingredient (name, weight) VALUES (:name, :weight)");
                $stmt->execute(['name' => $name, 'weight' => $weight]);

                $ingredientId = $pdo->lastInsertId();
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Ingredient created successfully',
                    'data' => ['id' => $ingredientId, 'name' => $name, 'weight' => $weight]
                ]);
            } else {
                // Create product
                if (!isset($input['name']) || empty(trim($input['name']))) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Product name is required']);
                    exit();
                }

                $name = trim($input['name']);
                $price = isset($input['price']) ? floatval($input['price']) : 0;
                $price_strangers = isset($input['price_strangers']) ? floatval($input['price_strangers']) : 0;
                $categoryId = isset($input['category_id']) && !empty($input['category_id']) ? intval($input['category_id']) : null;
                $stockIngredients = isset($input['stock_ingredients']) && is_array($input['stock_ingredients']) ? $input['stock_ingredients'] : [];

                // Build SQL query for product insertion
                $fields = ['name', 'price', 'price_strangers'];
                $values = [':name', ':price', ':price_strangers'];
                $bindValues = [
                    'name' => $name,
                    'price' => $price,
                    'price_strangers' => $price_strangers
                ];

                if ($categoryId !== null) {
                    $fields[] = 'category_id';
                    $values[] = ':category_id';
                    $bindValues['category_id'] = $categoryId;
                }

                $sql = "INSERT INTO product (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($bindValues);

                $productId = $pdo->lastInsertId();

                // Insert stock ingredients if provided
                if (!empty($stockIngredients)) {
                    foreach ($stockIngredients as $stockIng) {
                        if (isset($stockIng['stock_ingredient_id']) && isset($stockIng['quantity'])) {
                            $stmt = $pdo->prepare("
                                INSERT INTO product_stock_ingredient (product_id, stock_id, quantity) 
                                VALUES (:product_id, :stock_id, :quantity)
                            ");
                            $stmt->execute([
                                'product_id' => $productId,
                                'stock_id' => intval($stockIng['stock_ingredient_id']),
                                'quantity' => floatval($stockIng['quantity'])
                            ]);
                        }
                    }
                }

                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Product created successfully',
                    'data' => [
                        'id' => $productId,
                        'name' => $name,
                        'price' => $price,
                        'price_strangers' => $price_strangers,
                        'category_id' => $categoryId,
                        'stock_ingredients' => $stockIngredients
                    ]
                ]);
            }
            break;

        case 'PUT':
            if ($type === 'ingredient') {
                // Update ingredient
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Ingredient ID is required']);
                    exit();
                }

                $updateFields = [];
                $updateValues = ['id' => $id];

                if (isset($input['name'])) {
                    $updateFields[] = "name = :name";
                    $updateValues['name'] = trim($input['name']);
                }

                if (isset($input['weight'])) {
                    $updateFields[] = "weight = :weight";
                    $updateValues['weight'] = floatval($input['weight']);
                }

                if (empty($updateFields)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'No fields to update']);
                    exit();
                }

                $sql = "UPDATE product_ingredient SET " . implode(", ", $updateFields) . " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($updateValues);

                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Ingredient updated successfully']);
            } else {
                // Update product
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
                    exit();
                }

                $updateFields = [];
                $updateValues = ['id' => $id];

                if (isset($input['name'])) {
                    $updateFields[] = "name = :name";
                    $updateValues['name'] = trim($input['name']);
                }

                if (isset($input['price'])) {
                    $updateFields[] = "price = :price";
                    $updateValues['price'] = floatval($input['price']);
                }

                if (isset($input['price_strangers'])) {
                    $updateFields[] = "price_strangers = :price_strangers";
                    $updateValues['price_strangers'] = floatval($input['price_strangers']);
                }

                if (isset($input['category_id'])) {
                    $updateFields[] = "category_id = :category_id";
                    $updateValues['category_id'] = !empty($input['category_id']) ? intval($input['category_id']) : null;
                }

                if (!empty($updateFields)) {
                    $sql = "UPDATE product SET " . implode(", ", $updateFields) . " WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($updateValues);
                }

                // Update stock ingredients if provided
                if (isset($input['stock_ingredients']) && is_array($input['stock_ingredients'])) {
                    $stmt = $pdo->prepare("DELETE FROM product_stock_ingredient WHERE product_id = :product_id");
                    $stmt->execute(['product_id' => $id]);

                    foreach ($input['stock_ingredients'] as $stockIng) {
                        if (isset($stockIng['stock_ingredient_id']) && isset($stockIng['quantity'])) {
                            $stmt = $pdo->prepare("
                                INSERT INTO product_stock_ingredient (product_id, stock_id, quantity) 
                                VALUES (:product_id, :stock_id, :quantity)
                            ");
                            $stmt->execute([
                                'product_id' => $id,
                                'stock_id' => intval($stockIng['stock_ingredient_id']),
                                'quantity' => floatval($stockIng['quantity'])
                            ]);
                        }
                    }
                }

                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
            }
            break;

        case 'DELETE':
            if ($type === 'ingredient') {
                // Delete ingredient
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Ingredient ID is required']);
                    exit();
                }

                $stmt = $pdo->prepare("DELETE FROM product_ingredient WHERE id = :id");
                $stmt->execute(['id' => $id]);

                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Ingredient deleted successfully']);
            } else {
                // Delete product
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
                    exit();
                }

                $stmt = $pdo->prepare("DELETE FROM product_stock_ingredient WHERE product_id = :id");
                $stmt->execute(['id' => $id]);

                $stmt = $pdo->prepare("DELETE FROM product WHERE id = :id");
                $stmt->execute(['id' => $id]);

                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("PDO Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("General Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>