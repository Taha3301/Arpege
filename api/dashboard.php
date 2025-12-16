<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
$host = 'localhost';
$dbname = 'app';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Get the endpoint from query parameter
$endpoint = $_GET['endpoint'] ?? '';

// Router
switch ($endpoint) {
    // ==================== REVENUE ANALYTICS ====================
    case 'revenue-today':
        getRevenueToday($pdo);
        break;

    case 'revenue-yesterday':
        getRevenueYesterday($pdo);
        break;

    case 'revenue-this-week':
        getRevenueThisWeek($pdo);
        break;

    case 'revenue-last-week':
        getRevenueLastWeek($pdo);
        break;

    case 'revenue-this-month':
        getRevenueThisMonth($pdo);
        break;

    case 'revenue-last-month':
        getRevenueLastMonth($pdo);
        break;

    case 'revenue-this-year':
        getRevenueThisYear($pdo);
        break;

    case 'revenue-last-year':
        getRevenueLastYear($pdo);
        break;

    case 'revenue-by-date-range':
        getRevenueByDateRange($pdo);
        break;

    case 'revenue-by-hour-today':
        getRevenueByHourToday($pdo);
        break;

    case 'revenue-by-day-this-month':
        getRevenueByDayThisMonth($pdo);
        break;

    case 'revenue-by-month-this-year':
        getRevenueByMonthThisYear($pdo);
        break;

    case 'revenue-comparison':
        getRevenueComparison($pdo);
        break;

    // ==================== ORDER ANALYTICS ====================
    case 'orders-today':
        getOrdersToday($pdo);
        break;

    case 'orders-this-week':
        getOrdersThisWeek($pdo);
        break;

    case 'orders-this-month':
        getOrdersThisMonth($pdo);
        break;

    case 'orders-this-year':
        getOrdersThisYear($pdo);
        break;

    case 'orders-by-status':
        getOrdersByStatus($pdo);
        break;

    case 'orders-by-status-today':
        getOrdersByStatusToday($pdo);
        break;

    case 'average-order-value':
        getAverageOrderValue($pdo);
        break;

    case 'peak-hours':
        getPeakHours($pdo);
        break;

    case 'orders-by-date-range':
        getOrdersByDateRange($pdo);
        break;

    // ==================== PRODUCT ANALYTICS ====================
    case 'top-selling-products':
        getTopSellingProducts($pdo);
        break;

    case 'top-selling-products-today':
        getTopSellingProductsToday($pdo);
        break;

    case 'top-selling-products-this-month':
        getTopSellingProductsThisMonth($pdo);
        break;

    case 'revenue-by-category':
        getRevenueByCategory($pdo);
        break;

    case 'revenue-by-category-today':
        getRevenueByCategoryToday($pdo);
        break;

    case 'product-performance':
        getProductPerformance($pdo);
        break;

    case 'low-stock-products':
        getLowStockProducts($pdo);
        break;

    // ==================== EMPLOYEE ANALYTICS ====================
    case 'orders-by-employee':
        getOrdersByEmployee($pdo);
        break;

    case 'orders-by-employee-today':
        getOrdersByEmployeeToday($pdo);
        break;

    case 'revenue-by-employee':
        getRevenueByEmployee($pdo);
        break;

    case 'employee-performance':
        getEmployeePerformance($pdo);
        break;

    // ==================== TABLE ANALYTICS ====================
    case 'orders-by-table':
        getOrdersByTable($pdo);
        break;

    case 'revenue-by-table':
        getRevenueByTable($pdo);
        break;

    case 'table-utilization':
        getTableUtilization($pdo);
        break;

    // ==================== COMPREHENSIVE DASHBOARD ====================
    case 'dashboard-overview':
        getDashboardOverview($pdo);
        break;

    case 'dashboard-stats':
        getDashboardStats($pdo);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found', 'available_endpoints' => getAvailableEndpoints()]);
        break;
}

// ==================== REVENUE FUNCTIONS ====================

function getRevenueToday($pdo)
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as order_count
        FROM `order`
        WHERE DATE(order_time) = CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'period' => 'today',
        'revenue' => floatval($result['revenue']),
        'order_count' => intval($result['order_count']),
        'date' => date('Y-m-d')
    ]);
}

function getRevenueYesterday($pdo)
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as order_count
        FROM `order`
        WHERE DATE(order_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'period' => 'yesterday',
        'revenue' => floatval($result['revenue']),
        'order_count' => intval($result['order_count']),
        'date' => date('Y-m-d', strtotime('-1 day'))
    ]);
}

function getRevenueThisWeek($pdo)
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as order_count
        FROM `order`
        WHERE YEARWEEK(order_time, 1) = YEARWEEK(CURDATE(), 1)
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'period' => 'this_week',
        'revenue' => floatval($result['revenue']),
        'order_count' => intval($result['order_count']),
        'week_number' => date('W'),
        'year' => date('Y')
    ]);
}

function getRevenueLastWeek($pdo)
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as order_count
        FROM `order`
        WHERE YEARWEEK(order_time, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'period' => 'last_week',
        'revenue' => floatval($result['revenue']),
        'order_count' => intval($result['order_count'])
    ]);
}

function getRevenueThisMonth($pdo)
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as order_count
        FROM `order`
        WHERE YEAR(order_time) = YEAR(CURDATE()) AND MONTH(order_time) = MONTH(CURDATE())
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'period' => 'this_month',
        'revenue' => floatval($result['revenue']),
        'order_count' => intval($result['order_count']),
        'month' => date('F'),
        'year' => date('Y')
    ]);
}

function getRevenueLastMonth($pdo)
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as order_count
        FROM `order`
        WHERE YEAR(order_time) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
        AND MONTH(order_time) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'period' => 'last_month',
        'revenue' => floatval($result['revenue']),
        'order_count' => intval($result['order_count']),
        'month' => date('F', strtotime('-1 month')),
        'year' => date('Y', strtotime('-1 month'))
    ]);
}

function getRevenueThisYear($pdo)
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as order_count
        FROM `order`
        WHERE YEAR(order_time) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'period' => 'this_year',
        'revenue' => floatval($result['revenue']),
        'order_count' => intval($result['order_count']),
        'year' => date('Y')
    ]);
}

function getRevenueLastYear($pdo)
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as order_count
        FROM `order`
        WHERE YEAR(order_time) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'period' => 'last_year',
        'revenue' => floatval($result['revenue']),
        'order_count' => intval($result['order_count']),
        'year' => date('Y', strtotime('-1 year'))
    ]);
}

function getRevenueByDateRange($pdo)
{
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT 
            DATE(order_time) as date,
            COALESCE(SUM(total), 0) as revenue,
            COUNT(*) as order_count
        FROM `order`
        WHERE DATE(order_time) BETWEEN :start_date AND :end_date
        GROUP BY DATE(order_time)
        ORDER BY date
    ");
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'custom_range',
        'start_date' => $start_date,
        'end_date' => $end_date,
        'data' => array_map(function ($row) {
            return [
                'date' => $row['date'],
                'revenue' => floatval($row['revenue']),
                'order_count' => intval($row['order_count'])
            ];
        }, $results)
    ]);
}

function getRevenueByHourToday($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(order_time) as hour,
            COALESCE(SUM(total), 0) as revenue,
            COUNT(*) as order_count
        FROM `order`
        WHERE DATE(order_time) = CURDATE()
        GROUP BY HOUR(order_time)
        ORDER BY hour
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    // Fill in missing hours with 0
    $hourly_data = array_fill(0, 24, ['hour' => 0, 'revenue' => 0, 'order_count' => 0]);
    foreach ($results as $row) {
        $hourly_data[$row['hour']] = [
            'hour' => intval($row['hour']),
            'revenue' => floatval($row['revenue']),
            'order_count' => intval($row['order_count'])
        ];
    }

    echo json_encode([
        'success' => true,
        'period' => 'today_by_hour',
        'date' => date('Y-m-d'),
        'data' => array_values($hourly_data)
    ]);
}

function getRevenueByDayThisMonth($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            DAY(order_time) as day,
            DATE(order_time) as date,
            COALESCE(SUM(total), 0) as revenue,
            COUNT(*) as order_count
        FROM `order`
        WHERE YEAR(order_time) = YEAR(CURDATE()) AND MONTH(order_time) = MONTH(CURDATE())
        GROUP BY DATE(order_time)
        ORDER BY day
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'this_month_by_day',
        'month' => date('F Y'),
        'data' => array_map(function ($row) {
            return [
                'day' => intval($row['day']),
                'date' => $row['date'],
                'revenue' => floatval($row['revenue']),
                'order_count' => intval($row['order_count'])
            ];
        }, $results)
    ]);
}

function getRevenueByMonthThisYear($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            MONTH(order_time) as month,
            MONTHNAME(order_time) as month_name,
            COALESCE(SUM(total), 0) as revenue,
            COUNT(*) as order_count
        FROM `order`
        WHERE YEAR(order_time) = YEAR(CURDATE())
        GROUP BY MONTH(order_time), MONTHNAME(order_time)
        ORDER BY month
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'this_year_by_month',
        'year' => date('Y'),
        'data' => array_map(function ($row) {
            return [
                'month' => intval($row['month']),
                'month_name' => $row['month_name'],
                'revenue' => floatval($row['revenue']),
                'order_count' => intval($row['order_count'])
            ];
        }, $results)
    ]);
}

function getRevenueComparison($pdo)
{
    // Today vs Yesterday
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN DATE(order_time) = CURDATE() THEN total ELSE 0 END) as today_revenue,
            SUM(CASE WHEN DATE(order_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN total ELSE 0 END) as yesterday_revenue,
            COUNT(CASE WHEN DATE(order_time) = CURDATE() THEN 1 END) as today_orders,
            COUNT(CASE WHEN DATE(order_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 END) as yesterday_orders
        FROM `order`
        WHERE DATE(order_time) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    ");
    $stmt->execute();
    $daily = $stmt->fetch();

    // This week vs Last week
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN YEARWEEK(order_time, 1) = YEARWEEK(CURDATE(), 1) THEN total ELSE 0 END) as this_week_revenue,
            SUM(CASE WHEN YEARWEEK(order_time, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1) THEN total ELSE 0 END) as last_week_revenue
        FROM `order`
        WHERE YEARWEEK(order_time, 1) >= YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)
    ");
    $stmt->execute();
    $weekly = $stmt->fetch();

    // This month vs Last month
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN YEAR(order_time) = YEAR(CURDATE()) AND MONTH(order_time) = MONTH(CURDATE()) THEN total ELSE 0 END) as this_month_revenue,
            SUM(CASE WHEN YEAR(order_time) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(order_time) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) THEN total ELSE 0 END) as last_month_revenue
        FROM `order`
        WHERE order_time >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
    ");
    $stmt->execute();
    $monthly = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'comparisons' => [
            'daily' => [
                'today' => floatval($daily['today_revenue']),
                'yesterday' => floatval($daily['yesterday_revenue']),
                'change_percent' => $daily['yesterday_revenue'] > 0
                    ? round((($daily['today_revenue'] - $daily['yesterday_revenue']) / $daily['yesterday_revenue']) * 100, 2)
                    : 0,
                'today_orders' => intval($daily['today_orders']),
                'yesterday_orders' => intval($daily['yesterday_orders'])
            ],
            'weekly' => [
                'this_week' => floatval($weekly['this_week_revenue']),
                'last_week' => floatval($weekly['last_week_revenue']),
                'change_percent' => $weekly['last_week_revenue'] > 0
                    ? round((($weekly['this_week_revenue'] - $weekly['last_week_revenue']) / $weekly['last_week_revenue']) * 100, 2)
                    : 0
            ],
            'monthly' => [
                'this_month' => floatval($monthly['this_month_revenue']),
                'last_month' => floatval($monthly['last_month_revenue']),
                'change_percent' => $monthly['last_month_revenue'] > 0
                    ? round((($monthly['this_month_revenue'] - $monthly['last_month_revenue']) / $monthly['last_month_revenue']) * 100, 2)
                    : 0
            ]
        ]
    ]);
}

// ==================== ORDER FUNCTIONS ====================

function getOrdersToday($pdo)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_revenue
        FROM `order`
        WHERE DATE(order_time) = CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'period' => 'today',
        'order_count' => intval($result['order_count']),
        'total_revenue' => floatval($result['total_revenue']),
        'date' => date('Y-m-d')
    ]);
}

function getOrdersThisWeek($pdo)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_revenue
        FROM `order`
        WHERE YEARWEEK(order_time, 1) = YEARWEEK(CURDATE(), 1)
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'period' => 'this_week',
        'order_count' => intval($result['order_count']),
        'total_revenue' => floatval($result['total_revenue'])
    ]);
}

function getOrdersThisMonth($pdo)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_revenue
        FROM `order`
        WHERE YEAR(order_time) = YEAR(CURDATE()) AND MONTH(order_time) = MONTH(CURDATE())
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'period' => 'this_month',
        'order_count' => intval($result['order_count']),
        'total_revenue' => floatval($result['total_revenue'])
    ]);
}

function getOrdersThisYear($pdo)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_revenue
        FROM `order`
        WHERE YEAR(order_time) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo json_encode([
        'success' => true,
        'period' => 'this_year',
        'order_count' => intval($result['order_count']),
        'total_revenue' => floatval($result['total_revenue'])
    ]);
}

function getOrdersByStatus($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as order_count,
            COALESCE(SUM(total), 0) as total_revenue
        FROM `order`
        GROUP BY status
        ORDER BY order_count DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => array_map(function ($row) {
            return [
                'status' => $row['status'],
                'order_count' => intval($row['order_count']),
                'total_revenue' => floatval($row['total_revenue'])
            ];
        }, $results)
    ]);
}

function getOrdersByStatusToday($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as order_count,
            COALESCE(SUM(total), 0) as total_revenue
        FROM `order`
        WHERE DATE(order_time) = CURDATE()
        GROUP BY status
        ORDER BY order_count DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'today',
        'date' => date('Y-m-d'),
        'data' => array_map(function ($row) {
            return [
                'status' => $row['status'],
                'order_count' => intval($row['order_count']),
                'total_revenue' => floatval($row['total_revenue'])
            ];
        }, $results)
    ]);
}

function getAverageOrderValue($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(AVG(total), 0) as avg_today,
            (SELECT COALESCE(AVG(total), 0) FROM `order` WHERE YEARWEEK(order_time, 1) = YEARWEEK(CURDATE(), 1)) as avg_this_week,
            (SELECT COALESCE(AVG(total), 0) FROM `order` WHERE YEAR(order_time) = YEAR(CURDATE()) AND MONTH(order_time) = MONTH(CURDATE())) as avg_this_month,
            (SELECT COALESCE(AVG(total), 0) FROM `order` WHERE YEAR(order_time) = YEAR(CURDATE())) as avg_this_year,
            (SELECT COALESCE(AVG(total), 0) FROM `order`) as avg_all_time
        FROM `order`
        WHERE DATE(order_time) = CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'average_order_value' => [
            'today' => floatval($result['avg_today']),
            'this_week' => floatval($result['avg_this_week']),
            'this_month' => floatval($result['avg_this_month']),
            'this_year' => floatval($result['avg_this_year']),
            'all_time' => floatval($result['avg_all_time'])
        ]
    ]);
}

function getPeakHours($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(order_time) as hour,
            COUNT(*) as order_count,
            COALESCE(SUM(total), 0) as revenue
        FROM `order`
        WHERE order_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY HOUR(order_time)
        ORDER BY order_count DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'last_30_days',
        'data' => array_map(function ($row) {
            return [
                'hour' => intval($row['hour']),
                'hour_formatted' => sprintf('%02d:00', $row['hour']),
                'order_count' => intval($row['order_count']),
                'revenue' => floatval($row['revenue'])
            ];
        }, $results)
    ]);
}

function getOrdersByDateRange($pdo)
{
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT 
            DATE(order_time) as date,
            COUNT(*) as order_count,
            COALESCE(SUM(total), 0) as revenue
        FROM `order`
        WHERE DATE(order_time) BETWEEN :start_date AND :end_date
        GROUP BY DATE(order_time)
        ORDER BY date
    ");
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'data' => array_map(function ($row) {
            return [
                'date' => $row['date'],
                'order_count' => intval($row['order_count']),
                'revenue' => floatval($row['revenue'])
            ];
        }, $results)
    ]);
}

// ==================== PRODUCT FUNCTIONS ====================

function getTopSellingProducts($pdo)
{
    $limit = $_GET['limit'] ?? 10;

    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.price,
            c.name as category_name,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.price * oi.quantity) as total_revenue,
            COUNT(DISTINCT oi.order_id) as order_count
        FROM order_item oi
        JOIN product p ON oi.product_id = p.id
        LEFT JOIN category c ON p.category_id = c.id
        GROUP BY p.id, p.name, p.price, c.name
        ORDER BY total_quantity DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'all_time',
        'limit' => intval($limit),
        'data' => array_map(function ($row) {
            return [
                'product_id' => intval($row['id']),
                'product_name' => $row['name'],
                'price' => floatval($row['price']),
                'category' => $row['category_name'],
                'total_quantity' => intval($row['total_quantity']),
                'total_revenue' => floatval($row['total_revenue']),
                'order_count' => intval($row['order_count'])
            ];
        }, $results)
    ]);
}

function getTopSellingProductsToday($pdo)
{
    $limit = $_GET['limit'] ?? 10;

    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.price,
            c.name as category_name,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.price * oi.quantity) as total_revenue,
            COUNT(DISTINCT oi.order_id) as order_count
        FROM order_item oi
        JOIN product p ON oi.product_id = p.id
        JOIN `order` o ON oi.order_id = o.id
        LEFT JOIN category c ON p.category_id = c.id
        WHERE DATE(o.order_time) = CURDATE()
        GROUP BY p.id, p.name, p.price, c.name
        ORDER BY total_quantity DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'today',
        'date' => date('Y-m-d'),
        'limit' => intval($limit),
        'data' => array_map(function ($row) {
            return [
                'product_id' => intval($row['id']),
                'product_name' => $row['name'],
                'price' => floatval($row['price']),
                'category' => $row['category_name'],
                'total_quantity' => intval($row['total_quantity']),
                'total_revenue' => floatval($row['total_revenue']),
                'order_count' => intval($row['order_count'])
            ];
        }, $results)
    ]);
}

function getTopSellingProductsThisMonth($pdo)
{
    $limit = $_GET['limit'] ?? 10;

    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.price,
            c.name as category_name,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.price * oi.quantity) as total_revenue,
            COUNT(DISTINCT oi.order_id) as order_count
        FROM order_item oi
        JOIN product p ON oi.product_id = p.id
        JOIN `order` o ON oi.order_id = o.id
        LEFT JOIN category c ON p.category_id = c.id
        WHERE YEAR(o.order_time) = YEAR(CURDATE()) AND MONTH(o.order_time) = MONTH(CURDATE())
        GROUP BY p.id, p.name, p.price, c.name
        ORDER BY total_quantity DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', intval($limit), PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'this_month',
        'month' => date('F Y'),
        'limit' => intval($limit),
        'data' => array_map(function ($row) {
            return [
                'product_id' => intval($row['id']),
                'product_name' => $row['name'],
                'price' => floatval($row['price']),
                'category' => $row['category_name'],
                'total_quantity' => intval($row['total_quantity']),
                'total_revenue' => floatval($row['total_revenue']),
                'order_count' => intval($row['order_count'])
            ];
        }, $results)
    ]);
}

function getRevenueByCategory($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.name as category_name,
            COUNT(DISTINCT oi.order_id) as order_count,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.price * oi.quantity) as total_revenue
        FROM order_item oi
        JOIN product p ON oi.product_id = p.id
        JOIN category c ON p.category_id = c.id
        GROUP BY c.id, c.name
        ORDER BY total_revenue DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'all_time',
        'data' => array_map(function ($row) {
            return [
                'category_id' => intval($row['id']),
                'category_name' => $row['category_name'],
                'order_count' => intval($row['order_count']),
                'total_quantity' => intval($row['total_quantity']),
                'total_revenue' => floatval($row['total_revenue'])
            ];
        }, $results)
    ]);
}

function getRevenueByCategoryToday($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.name as category_name,
            COUNT(DISTINCT oi.order_id) as order_count,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.price * oi.quantity) as total_revenue
        FROM order_item oi
        JOIN product p ON oi.product_id = p.id
        JOIN category c ON p.category_id = c.id
        JOIN `order` o ON oi.order_id = o.id
        WHERE DATE(o.order_time) = CURDATE()
        GROUP BY c.id, c.name
        ORDER BY total_revenue DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'today',
        'date' => date('Y-m-d'),
        'data' => array_map(function ($row) {
            return [
                'category_id' => intval($row['id']),
                'category_name' => $row['category_name'],
                'order_count' => intval($row['order_count']),
                'total_quantity' => intval($row['total_quantity']),
                'total_revenue' => floatval($row['total_revenue'])
            ];
        }, $results)
    ]);
}

function getProductPerformance($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.price,
            c.name as category_name,
            COALESCE(SUM(oi.quantity), 0) as total_sold,
            COALESCE(SUM(oi.price * oi.quantity), 0) as total_revenue,
            COUNT(DISTINCT oi.order_id) as times_ordered,
            (SELECT SUM(quantity) FROM stock WHERE product_id = p.id) as stock_quantity
        FROM product p
        LEFT JOIN category c ON p.category_id = c.id
        LEFT JOIN order_item oi ON p.id = oi.product_id
        GROUP BY p.id, p.name, p.price, c.name
        ORDER BY total_revenue DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => array_map(function ($row) {
            return [
                'product_id' => intval($row['id']),
                'product_name' => $row['name'],
                'price' => floatval($row['price']),
                'category' => $row['category_name'],
                'total_sold' => intval($row['total_sold']),
                'total_revenue' => floatval($row['total_revenue']),
                'times_ordered' => intval($row['times_ordered']),
                'stock_quantity' => floatval($row['stock_quantity'] ?? 0)
            ];
        }, $results)
    ]);
}

function getLowStockProducts($pdo)
{
    $threshold = $_GET['threshold'] ?? 10;

    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.price,
            s.quantity,
            s.unit
        FROM product p
        JOIN stock s ON p.id = s.stock_id
        WHERE s.quantity < :threshold
        ORDER BY s.quantity ASC
    ");
    $stmt->bindValue(':threshold', floatval($threshold), PDO::PARAM_STR);
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'threshold' => floatval($threshold),
        'count' => count($results),
        'data' => array_map(function ($row) {
            return [
                'product_id' => intval($row['id']),
                'product_name' => $row['name'],
                'price' => floatval($row['price']),
                'quantity' => floatval($row['quantity']),
                'unit' => $row['unit']
            ];
        }, $results)
    ]);
}

// ==================== EMPLOYEE FUNCTIONS ====================

function getOrdersByEmployee($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total), 0) as total_revenue
        FROM user u
        LEFT JOIN `order` o ON u.id = o.employee_id
        WHERE u.role = 'employee'
        GROUP BY u.id, u.username
        ORDER BY order_count DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'all_time',
        'data' => array_map(function ($row) {
            return [
                'employee_id' => intval($row['id']),
                'employee_name' => $row['username'],
                'order_count' => intval($row['order_count']),
                'total_revenue' => floatval($row['total_revenue'])
            ];
        }, $results)
    ]);
}

function getOrdersByEmployeeToday($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total), 0) as total_revenue
        FROM user u
        LEFT JOIN `order` o ON u.id = o.employee_id AND DATE(o.order_time) = CURDATE()
        WHERE u.role = 'employee'
        GROUP BY u.id, u.username
        ORDER BY order_count DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'today',
        'date' => date('Y-m-d'),
        'data' => array_map(function ($row) {
            return [
                'employee_id' => intval($row['id']),
                'employee_name' => $row['username'],
                'order_count' => intval($row['order_count']),
                'total_revenue' => floatval($row['total_revenue'])
            ];
        }, $results)
    ]);
}

function getRevenueByEmployee($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total), 0) as total_revenue,
            COALESCE(AVG(o.total), 0) as avg_order_value
        FROM user u
        LEFT JOIN `order` o ON u.id = o.employee_id
        WHERE u.role = 'employee'
        GROUP BY u.id, u.username
        ORDER BY total_revenue DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'all_time',
        'data' => array_map(function ($row) {
            return [
                'employee_id' => intval($row['id']),
                'employee_name' => $row['username'],
                'order_count' => intval($row['order_count']),
                'total_revenue' => floatval($row['total_revenue']),
                'avg_order_value' => floatval($row['avg_order_value'])
            ];
        }, $results)
    ]);
}

function getEmployeePerformance($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            COUNT(CASE WHEN DATE(o.order_time) = CURDATE() THEN 1 END) as orders_today,
            COUNT(CASE WHEN YEARWEEK(o.order_time, 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) as orders_this_week,
            COUNT(CASE WHEN YEAR(o.order_time) = YEAR(CURDATE()) AND MONTH(o.order_time) = MONTH(CURDATE()) THEN 1 END) as orders_this_month,
            SUM(CASE WHEN DATE(o.order_time) = CURDATE() THEN o.total ELSE 0 END) as revenue_today,
            SUM(CASE WHEN YEARWEEK(o.order_time, 1) = YEARWEEK(CURDATE(), 1) THEN o.total ELSE 0 END) as revenue_this_week,
            SUM(CASE WHEN YEAR(o.order_time) = YEAR(CURDATE()) AND MONTH(o.order_time) = MONTH(CURDATE()) THEN o.total ELSE 0 END) as revenue_this_month
        FROM user u
        LEFT JOIN `order` o ON u.id = o.employee_id
        WHERE u.role = 'employee'
        GROUP BY u.id, u.username
        ORDER BY revenue_this_month DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => array_map(function ($row) {
            return [
                'employee_id' => intval($row['id']),
                'employee_name' => $row['username'],
                'today' => [
                    'orders' => intval($row['orders_today']),
                    'revenue' => floatval($row['revenue_today'])
                ],
                'this_week' => [
                    'orders' => intval($row['orders_this_week']),
                    'revenue' => floatval($row['revenue_this_week'])
                ],
                'this_month' => [
                    'orders' => intval($row['orders_this_month']),
                    'revenue' => floatval($row['revenue_this_month'])
                ]
            ];
        }, $results)
    ]);
}

// ==================== TABLE FUNCTIONS ====================

function getOrdersByTable($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.table_number,
            t.status,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total), 0) as total_revenue
        FROM `table` t
        LEFT JOIN `order` o ON t.id = o.table_id
        GROUP BY t.id, t.table_number, t.status
        ORDER BY order_count DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'all_time',
        'data' => array_map(function ($row) {
            return [
                'table_id' => intval($row['id']),
                'table_number' => intval($row['table_number']),
                'status' => $row['status'],
                'order_count' => intval($row['order_count']),
                'total_revenue' => floatval($row['total_revenue'])
            ];
        }, $results)
    ]);
}

function getRevenueByTable($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.table_number,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total), 0) as total_revenue,
            COALESCE(AVG(o.total), 0) as avg_order_value
        FROM `table` t
        LEFT JOIN `order` o ON t.id = o.table_id
        GROUP BY t.id, t.table_number
        ORDER BY total_revenue DESC
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'period' => 'all_time',
        'data' => array_map(function ($row) {
            return [
                'table_id' => intval($row['id']),
                'table_number' => intval($row['table_number']),
                'order_count' => intval($row['order_count']),
                'total_revenue' => floatval($row['total_revenue']),
                'avg_order_value' => floatval($row['avg_order_value'])
            ];
        }, $results)
    ]);
}

function getTableUtilization($pdo)
{
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as table_count
        FROM `table`
        GROUP BY status
    ");
    $stmt->execute();
    $results = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM `table`");
    $stmt->execute();
    $total = $stmt->fetch()['total'];

    echo json_encode([
        'success' => true,
        'total_tables' => intval($total),
        'data' => array_map(function ($row) use ($total) {
            return [
                'status' => $row['status'],
                'count' => intval($row['table_count']),
                'percentage' => $total > 0 ? round(($row['table_count'] / $total) * 100, 2) : 0
            ];
        }, $results)
    ]);
}

// ==================== COMPREHENSIVE DASHBOARD ====================

function getDashboardOverview($pdo)
{
    // Today's stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as order_count,
            COALESCE(SUM(total), 0) as revenue,
            COALESCE(AVG(total), 0) as avg_order_value
        FROM `order`
        WHERE DATE(order_time) = CURDATE()
    ");
    $stmt->execute();
    $today = $stmt->fetch();

    // This month's stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as order_count,
            COALESCE(SUM(total), 0) as revenue
        FROM `order`
        WHERE YEAR(order_time) = YEAR(CURDATE()) AND MONTH(order_time) = MONTH(CURDATE())
    ");
    $stmt->execute();
    $this_month = $stmt->fetch();

    // Top products today
    $stmt = $pdo->prepare("
        SELECT 
            p.name,
            SUM(oi.quantity) as quantity
        FROM order_item oi
        JOIN product p ON oi.product_id = p.id
        JOIN `order` o ON oi.order_id = o.id
        WHERE DATE(o.order_time) = CURDATE()
        GROUP BY p.id, p.name
        ORDER BY quantity DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_products = $stmt->fetchAll();

    // Orders by status today
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM `order`
        WHERE DATE(order_time) = CURDATE()
        GROUP BY status
    ");
    $stmt->execute();
    $orders_by_status = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'today' => [
            'date' => date('Y-m-d'),
            'orders' => intval($today['order_count']),
            'revenue' => floatval($today['revenue']),
            'avg_order_value' => floatval($today['avg_order_value'])
        ],
        'this_month' => [
            'month' => date('F Y'),
            'orders' => intval($this_month['order_count']),
            'revenue' => floatval($this_month['revenue'])
        ],
        'top_products_today' => array_map(function ($row) {
            return [
                'name' => $row['name'],
                'quantity' => intval($row['quantity'])
            ];
        }, $top_products),
        'orders_by_status_today' => array_map(function ($row) {
            return [
                'status' => $row['status'],
                'count' => intval($row['count'])
            ];
        }, $orders_by_status)
    ]);
}

function getDashboardStats($pdo)
{
    // Get comprehensive stats
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM `order` WHERE DATE(order_time) = CURDATE()) as orders_today,
            (SELECT COALESCE(SUM(total), 0) FROM `order` WHERE DATE(order_time) = CURDATE()) as revenue_today,
            (SELECT COUNT(*) FROM `order` WHERE YEARWEEK(order_time, 1) = YEARWEEK(CURDATE(), 1)) as orders_this_week,
            (SELECT COALESCE(SUM(total), 0) FROM `order` WHERE YEARWEEK(order_time, 1) = YEARWEEK(CURDATE(), 1)) as revenue_this_week,
            (SELECT COUNT(*) FROM `order` WHERE YEAR(order_time) = YEAR(CURDATE()) AND MONTH(order_time) = MONTH(CURDATE())) as orders_this_month,
            (SELECT COALESCE(SUM(total), 0) FROM `order` WHERE YEAR(order_time) = YEAR(CURDATE()) AND MONTH(order_time) = MONTH(CURDATE())) as revenue_this_month,
            (SELECT COUNT(*) FROM `order` WHERE YEAR(order_time) = YEAR(CURDATE())) as orders_this_year,
            (SELECT COALESCE(SUM(total), 0) FROM `order` WHERE YEAR(order_time) = YEAR(CURDATE())) as revenue_this_year,
            (SELECT COUNT(*) FROM product) as total_products,
            (SELECT COUNT(*) FROM user WHERE role = 'employee') as total_employees,
            (SELECT COUNT(*) FROM `table`) as total_tables,
            (SELECT COUNT(*) FROM category) as total_categories
    ");
    $stmt->execute();
    $stats = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'stats' => [
            'today' => [
                'orders' => intval($stats['orders_today']),
                'revenue' => floatval($stats['revenue_today'])
            ],
            'this_week' => [
                'orders' => intval($stats['orders_this_week']),
                'revenue' => floatval($stats['revenue_this_week'])
            ],
            'this_month' => [
                'orders' => intval($stats['orders_this_month']),
                'revenue' => floatval($stats['revenue_this_month'])
            ],
            'this_year' => [
                'orders' => intval($stats['orders_this_year']),
                'revenue' => floatval($stats['revenue_this_year'])
            ],
            'totals' => [
                'products' => intval($stats['total_products']),
                'employees' => intval($stats['total_employees']),
                'tables' => intval($stats['total_tables']),
                'categories' => intval($stats['total_categories'])
            ]
        ]
    ]);
}

// ==================== HELPER FUNCTIONS ====================

function getAvailableEndpoints()
{
    return [
        'revenue' => [
            'revenue-today',
            'revenue-yesterday',
            'revenue-this-week',
            'revenue-last-week',
            'revenue-this-month',
            'revenue-last-month',
            'revenue-this-year',
            'revenue-last-year',
            'revenue-by-date-range?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD',
            'revenue-by-hour-today',
            'revenue-by-day-this-month',
            'revenue-by-month-this-year',
            'revenue-comparison'
        ],
        'orders' => [
            'orders-today',
            'orders-this-week',
            'orders-this-month',
            'orders-this-year',
            'orders-by-status',
            'orders-by-status-today',
            'average-order-value',
            'peak-hours',
            'orders-by-date-range?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD'
        ],
        'products' => [
            'top-selling-products?limit=10',
            'top-selling-products-today?limit=10',
            'top-selling-products-this-month?limit=10',
            'revenue-by-category',
            'revenue-by-category-today',
            'product-performance',
            'low-stock-products?threshold=10'
        ],
        'employees' => [
            'orders-by-employee',
            'orders-by-employee-today',
            'revenue-by-employee',
            'employee-performance'
        ],
        'tables' => [
            'orders-by-table',
            'revenue-by-table',
            'table-utilization'
        ],
        'dashboard' => [
            'dashboard-overview',
            'dashboard-stats'
        ]
    ];
}
