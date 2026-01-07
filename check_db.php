<?php
require_once 'api/db.php';

try {
    echo "Checking 'order' table columns:\n";
    $stmt = $pdo->query("DESCRIBE `order` ");
    while ($row = $stmt->fetch()) {
        echo "{$row['Field']}: {$row['Type']}\n";
    }

    echo "\nChecking 'order_item' table columns:\n";
    $stmt = $pdo->query("DESCRIBE `order_item` ");
    while ($row = $stmt->fetch()) {
        echo "{$row['Field']}: {$row['Type']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>