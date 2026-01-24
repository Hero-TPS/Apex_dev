<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$response = ['success' => false, 'data' => []];

try {
    $action = $_GET['action'] ?? '';

    if ($action === 'weekly_bookings') {
        $weeksBack = 4;
        $weeksAhead = 4;
        $reportData = [];

        for ($i = -$weeksBack; $i <= $weeksAhead; $i++) {
            // Monday of target week
            $monday = new DateTime('monday this week', new DateTimeZone(TIME_ZONE));
            $monday->modify("{$i} weeks");
            $mondayStr = $monday->format('Y-m-d');

            // Sunday of target week
            $sunday = clone $monday;
            $sunday->modify('+6 days');
            $sundayStr = $sunday->format('Y-m-d');

            $sql = "
                SELECT 
                    COUNT(*) as booking_count,
                    SUM(cost) as total_income
                FROM bookings 
                WHERE trip_date >= ?
                  AND trip_date <= ?
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$mondayStr, $sundayStr]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $reportData[] = [
                'week_label' => $monday->format('j M') . ' – ' . $sunday->format('j M Y'),
                'booking_count' => (int)($row['booking_count'] ?? 0),
                'total_income' => (float)($row['total_income'] ?? 0)
            ];
        }

        $response = ['success' => true, 'data' => $reportData];

    } elseif ($action === 'monthly_bookings') {
        $monthsBack = 6;
        $monthsAhead = 3;
        $reportData = [];

        for ($i = -$monthsBack; $i <= $monthsAhead; $i++) {
            $date = new DateTime('first day of this month', new DateTimeZone(TIME_ZONE));
            $date->modify("{$i} months");
            
            $startDate = $date->format('Y-m-01');
            $endDate = $date->format('Y-m-t'); // last day of month

            $sql = "
                SELECT 
                    COUNT(*) as booking_count,
                    SUM(cost) as total_income
                FROM bookings 
                WHERE trip_date >= ?
                  AND trip_date <= ?
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $reportData[] = [
                'month_label' => $date->format('F Y'),
                'booking_count' => (int)($row['booking_count'] ?? 0),
                'total_income' => (float)($row['total_income'] ?? 0)
            ];
        }

        $response = ['success' => true, 'data' => $reportData];
    } else {
        $response['message'] = 'Unsupported report.';
    }

} catch (Exception $e) {
    error_log('Reports error: ' . $e->getMessage());
    $response['message'] = 'Report generation failed.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>