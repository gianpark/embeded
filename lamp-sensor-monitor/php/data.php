<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$host     = "localhost";
$user     = "gianpark";
$password = "qwer1234";
$database = "monitordb";

$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => $conn->connect_error]);
    exit;
}

// 통계
$stat = $conn->query("
    SELECT
        ROUND(AVG(temperature), 2) AS avg_temp,
        ROUND(AVG(humidity),    2) AS avg_humid,
        ROUND(AVG(pressure),    2) AS avg_press,
        ROUND(MIN(temperature), 2) AS min_temp,
        ROUND(MAX(temperature), 2) AS max_temp,
        ROUND(MIN(humidity),    2) AS min_humid,
        ROUND(MAX(humidity),    2) AS max_humid
    FROM sensor_data
")->fetch_assoc();

// 테이블 행
$stmt = $conn->prepare(
    "SELECT id, temperature, humidity, pressure, created_at
     FROM sensor_data ORDER BY id DESC LIMIT ? OFFSET ?"
);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
}

// 차트용 최근 20개
$chart_result = $conn->query(
    "SELECT temperature, humidity, pressure, created_at
     FROM sensor_data ORDER BY id DESC LIMIT 20"
);
$chart_labels = $chart_temps = $chart_humids = $chart_press = [];
while ($r = $chart_result->fetch_assoc()) {
    array_unshift($chart_labels, date("H:i:s", strtotime($r['created_at'])));
    array_unshift($chart_temps,  (float)$r['temperature']);
    array_unshift($chart_humids, (float)$r['humidity']);
    array_unshift($chart_press,  (float)$r['pressure']);
}

$conn->close();

echo json_encode([
    'stat' => $stat,
    'rows' => $rows,
    'chart' => [
        'labels' => $chart_labels,
        'temps'  => $chart_temps,
        'humids' => $chart_humids,
        'press'  => $chart_press,
    ]
]);
