<?php
session_start();
header('Content-Type: application/json');

// DB connection and security
require_once '../db-connection.php';
require_once '../auth-helper.php';

// Only admins can access
requireAdminAuth();

// Get the period from request (default to weekly)
$period = isset($_GET['period']) ? $_GET['period'] : 'weekly';

$labels = [];
$data = [];

switch ($period) {
    case 'weekly':
        // Last 8 weeks
        $query = "
            SELECT 
                CONCAT('Week ', WEEK(created_at)) as period,
                COALESCE(SUM(amount), 0) as total
            FROM bills
            WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 8 WEEK)
            GROUP BY YEAR(created_at), WEEK(created_at)
            ORDER BY YEAR(created_at), WEEK(created_at)
        ";
        break;

    case 'monthly':
        // Last 6 months
        $query = "
            SELECT 
                DATE_FORMAT(created_at, '%b %Y') as period,
                COALESCE(SUM(amount), 0) as total
            FROM bills
            WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
            GROUP BY YEAR(created_at), MONTH(created_at)
            ORDER BY YEAR(created_at), MONTH(created_at)
        ";
        break;

    case 'biannual':
        // Last 2 years, grouped by 6 months
        $query = "
            SELECT 
                CONCAT(
                    CASE 
                        WHEN MONTH(created_at) <= 6 THEN 'H1 '
                        ELSE 'H2 '
                    END,
                    YEAR(created_at)
                ) as period,
                COALESCE(SUM(amount), 0) as total
            FROM bills
            WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 2 YEAR)
            GROUP BY 
                YEAR(created_at),
                CASE 
                    WHEN MONTH(created_at) <= 6 THEN 1
                    ELSE 2
                END
            ORDER BY 
                YEAR(created_at),
                CASE 
                    WHEN MONTH(created_at) <= 6 THEN 1
                    ELSE 2
                END
        ";
        break;

    case 'annual':
        // Last 5 years
        $query = "
            SELECT 
                YEAR(created_at) as period,
                COALESCE(SUM(amount), 0) as total
            FROM bills
            WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 5 YEAR)
            GROUP BY YEAR(created_at)
            ORDER BY YEAR(created_at)
        ";
        break;

    default:
        $query = "
            SELECT 
                DATE_FORMAT(created_at, '%b %Y') as period,
                COALESCE(SUM(amount), 0) as total
            FROM bills
            WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
            GROUP BY YEAR(created_at), MONTH(created_at)
            ORDER BY YEAR(created_at), MONTH(created_at)
        ";
}

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['period'];
        $data[] = floatval($row['total']);
    }
} else {
    // Return default empty data based on period
    switch ($period) {
        case 'weekly':
            $labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7', 'Week 8'];
            $data = [0, 0, 0, 0, 0, 0, 0, 0];
            break;
        case 'monthly':
            $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
            $data = [0, 0, 0, 0, 0, 0];
            break;
        case 'biannual':
            $labels = ['H1 2024', 'H2 2024', 'H1 2025', 'H2 2025'];
            $data = [0, 0, 0, 0];
            break;
        case 'annual':
            $labels = ['2021', '2022', '2023', '2024', '2025'];
            $data = [0, 0, 0, 0, 0];
            break;
    }
}

echo json_encode([
    'success' => true,
    'labels' => $labels,
    'data' => $data,
    'period' => $period
]);
