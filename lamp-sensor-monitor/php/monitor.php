<?php
// ─── DB 설정 ────────────────────────────────────────────────
$host     = "localhost";
$user     = "gianpark";
$password = "qwer1234";
$database = "monitordb";

// ─── 페이지네이션 ────────────────────────────────────────────
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

// ─── DB 연결 ────────────────────────────────────────────────
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("<p style='color:red'>DB 연결 실패: " . htmlspecialchars($conn->connect_error) . "</p>");
}

// ─── 통계 쿼리 ──────────────────────────────────────────────
$stat = $conn->query("
    SELECT
        COUNT(*)                   AS total,
        ROUND(AVG(temperature), 2) AS avg_temp,
        ROUND(AVG(humidity),    2) AS avg_humid,
        ROUND(AVG(pressure),    2) AS avg_press,
        ROUND(MIN(temperature), 2) AS min_temp,
        ROUND(MAX(temperature), 2) AS max_temp,
        ROUND(MIN(humidity),    2) AS min_humid,
        ROUND(MAX(humidity),    2) AS max_humid
    FROM sensor_data
")->fetch_assoc();

$total       = (int)$stat['total'];
$total_pages = max(1, ceil($total / $limit));

// ─── 최근 데이터 ─────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT id, temperature, humidity, pressure, created_at
     FROM sensor_data ORDER BY id DESC LIMIT ? OFFSET ?"
);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$rows = $stmt->get_result();

// ─── 차트용 최근 20개 ────────────────────────────────────────
$chart_result = $conn->query(
    "SELECT temperature, humidity, pressure, created_at
     FROM sensor_data ORDER BY id DESC LIMIT 20"
);
$chart_labels = $chart_temps = $chart_humids = $chart_press = [];
while ($r = $chart_result->fetch_assoc()) {
    array_unshift($chart_labels, date("H:i:s", strtotime($r['created_at'])));
    array_unshift($chart_temps,  $r['temperature']);
    array_unshift($chart_humids, $r['humidity']);
    array_unshift($chart_press,  $r['pressure']);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>실시간 센서 모니터링</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }

  header {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    padding: 20px 30px;
    border-bottom: 1px solid #334155;
    display: flex; align-items: center; justify-content: space-between;
  }
  header h1 { font-size: 1.4rem; color: #38bdf8; letter-spacing: 1px; }
  .live-badge {
    display: flex; align-items: center; gap: 6px;
    background: #052e16; color: #4ade80;
    border: 1px solid #166534; border-radius: 20px;
    padding: 4px 12px; font-size: 0.8rem;
  }
  .dot { width: 8px; height: 8px; background: #4ade80; border-radius: 50%; animation: pulse 1.2s infinite; }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

  .wrap { max-width: 1100px; margin: 0 auto; padding: 24px 20px; }

  /* 통계 카드 */
  .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
  .card {
    background: #1e293b; border: 1px solid #334155; border-radius: 10px;
    padding: 18px 20px;
  }
  .card .label { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
  .card .value { font-size: 2rem; font-weight: 700; }
  .card .sub   { font-size: 0.78rem; color: #64748b; margin-top: 4px; }
  .temp  .value { color: #f97316; }
  .humid .value { color: #38bdf8; }
  .press .value { color: #a78bfa; }

  /* 차트 */
  .chart-box {
    background: #1e293b; border: 1px solid #334155; border-radius: 10px;
    padding: 20px; margin-bottom: 24px;
  }
  .chart-box h2 { font-size: 0.95rem; color: #94a3b8; margin-bottom: 16px; }

  /* 테이블 */
  .table-box {
    background: #1e293b; border: 1px solid #334155; border-radius: 10px;
    overflow: hidden; margin-bottom: 20px;
  }
  .table-box h2 { font-size: 0.95rem; color: #94a3b8; padding: 16px 20px; border-bottom: 1px solid #334155; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #0f172a; color: #64748b; font-size: 0.78rem; text-transform: uppercase;
       letter-spacing: 1px; padding: 10px 16px; text-align: left; }
  td { padding: 10px 16px; border-bottom: 1px solid #1e293b; font-size: 0.88rem; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #263348; }
  .badge-temp  { color: #f97316; font-weight: 600; }
  .badge-humid { color: #38bdf8; font-weight: 600; }
  .badge-press { color: #a78bfa; font-weight: 600; }

  /* 페이지네이션 */
  .pagination { display: flex; gap: 6px; justify-content: center; flex-wrap: wrap; }
  .pagination a, .pagination span {
    padding: 6px 13px; border-radius: 6px; font-size: 0.85rem; text-decoration: none;
  }
  .pagination a { background: #1e293b; color: #38bdf8; border: 1px solid #334155; }
  .pagination a:hover { background: #38bdf8; color: #0f172a; }
  .pagination span.cur { background: #38bdf8; color: #0f172a; border: 1px solid #38bdf8; }
  .pagination span.dis { background: #0f172a; color: #475569; border: 1px solid #1e293b; }

  .info-bar { font-size: 0.8rem; color: #64748b; text-align: center; margin-bottom: 14px; }
  .no-data  { text-align: center; padding: 40px; color: #475569; }

  @media (max-width: 640px) {
    .cards { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<header>
  <h1>실시간 센서 모니터링</h1>
  <div class="live-badge"><span class="dot"></span> LIVE &nbsp;· &nbsp;1초 자동 갱신</div>
</header>

<div class="wrap">

  <!-- 통계 카드 -->
  <div class="cards">
    <div class="card temp">
      <div class="label">온도 (Temperature)</div>
      <div class="value" id="avg_temp"><?= $stat['avg_temp'] ?? '—' ?>°C</div>
      <div class="sub"><span id="min_temp">Min <?= $stat['min_temp'] ?>°C</span> &nbsp;/&nbsp; <span id="max_temp">Max <?= $stat['max_temp'] ?>°C</span></div>
    </div>
    <div class="card humid">
      <div class="label">습도 (Humidity)</div>
      <div class="value" id="avg_humid"><?= $stat['avg_humid'] ?? '—' ?>%</div>
      <div class="sub"><span id="min_humid">Min <?= $stat['min_humid'] ?>%</span> &nbsp;/&nbsp; <span id="max_humid">Max <?= $stat['max_humid'] ?>%</span></div>
    </div>
    <div class="card press">
      <div class="label">기압 (Pressure)</div>
      <div class="value" id="avg_press"><?= $stat['avg_press'] ?? '—' ?></div>
      <div class="sub">hPa &nbsp;(전체 평균)</div>
    </div>
  </div>

  <!-- 차트 -->
  <div class="chart-box">
    <h2>최근 20개 데이터 추이</h2>
    <canvas id="sensorChart" height="90"></canvas>
  </div>

  <!-- 테이블 -->
  <div class="table-box">
    <h2>최근 데이터 목록</h2>
    <?php if ($rows->num_rows > 0): ?>
    <table>
      <thead>
        <tr>
          <th>#</th><th>ID</th><th>온도 (°C)</th><th>습도 (%)</th><th>기압 (hPa)</th><th>저장 시각</th>
        </tr>
      </thead>
      <tbody>
      <?php $n = $offset + 1; while ($row = $rows->fetch_assoc()): ?>
        <tr>
          <td><?= $n++ ?></td>
          <td><?= $row['id'] ?></td>
          <td class="badge-temp"><?= $row['temperature'] ?></td>
          <td class="badge-humid"><?= $row['humidity'] ?></td>
          <td class="badge-press"><?= $row['pressure'] ?></td>
          <td><?= $row['created_at'] ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
    <?php else: ?>
      <div class="no-data">저장된 데이터가 없습니다. injector.py를 실행해 주세요.</div>
    <?php endif; ?>
  </div>

  <!-- 페이지네이션 -->
  <?php if ($total_pages > 1): ?>
  <p class="info-bar">전체 <?= $total ?>건 &nbsp;·&nbsp; 페이지 <?= $page ?> / <?= $total_pages ?></p>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page-1 ?>">&laquo;</a>
    <?php else: ?>
      <span class="dis">&laquo;</span>
    <?php endif; ?>

    <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
      <?php if ($i === $page): ?>
        <span class="cur"><?= $i ?></span>
      <?php else: ?>
        <a href="?page=<?= $i ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
      <a href="?page=<?= $page+1 ?>">&raquo;</a>
    <?php else: ?>
      <span class="dis">&raquo;</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div><!-- /wrap -->

<script>
// ─── 초기 차트 데이터 (PHP 렌더링) ───────────────────────────
let labels = <?= json_encode($chart_labels) ?>;
let temps  = <?= json_encode($chart_temps)  ?>;
let humids = <?= json_encode($chart_humids) ?>;
let press  = <?= json_encode($chart_press)  ?>;

const chart = new Chart(document.getElementById('sensorChart'), {
  type: 'line',
  data: {
    labels,
    datasets: [
      {
        label: '온도 (°C)',
        data: temps,
        borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,.1)',
        tension: 0.4, pointRadius: 3, fill: true, yAxisID: 'y1'
      },
      {
        label: '습도 (%)',
        data: humids,
        borderColor: '#38bdf8', backgroundColor: 'rgba(56,189,248,.1)',
        tension: 0.4, pointRadius: 3, fill: true, yAxisID: 'y1'
      },
      {
        label: '기압 (hPa)',
        data: press,
        borderColor: '#a78bfa', backgroundColor: 'rgba(167,139,250,.05)',
        tension: 0.4, pointRadius: 3, fill: false, yAxisID: 'y2'
      }
    ]
  },
  options: {
    responsive: true,
    animation: { duration: 300 },
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { labels: { color: '#94a3b8', font: { size: 12 } } } },
    scales: {
      x:  { ticks: { color: '#64748b', maxTicksLimit: 10 }, grid: { color: '#1e293b' } },
      y1: { ticks: { color: '#94a3b8' }, grid: { color: '#334155' }, position: 'left' },
      y2: { ticks: { color: '#a78bfa' }, grid: { display: false }, position: 'right' }
    }
  }
});

// ─── 1초마다 API 폴링 ─────────────────────────────────────────
const page = new URLSearchParams(location.search).get('page') || 1;

async function refresh() {
  try {
    const res  = await fetch(`data.php?page=${page}&_=${Date.now()}`);
    const data = await res.json();

    // 통계 카드 업데이트
    document.getElementById('avg_temp').textContent  = data.stat.avg_temp  + '°C';
    document.getElementById('min_temp').textContent  = 'Min ' + data.stat.min_temp + '°C';
    document.getElementById('max_temp').textContent  = 'Max ' + data.stat.max_temp + '°C';
    document.getElementById('avg_humid').textContent = data.stat.avg_humid + '%';
    document.getElementById('min_humid').textContent = 'Min ' + data.stat.min_humid + '%';
    document.getElementById('max_humid').textContent = 'Max ' + data.stat.max_humid + '%';
    document.getElementById('avg_press').textContent = data.stat.avg_press;

    // 차트 업데이트
    chart.data.labels         = data.chart.labels;
    chart.data.datasets[0].data = data.chart.temps;
    chart.data.datasets[1].data = data.chart.humids;
    chart.data.datasets[2].data = data.chart.press;
    chart.update();

    // 테이블 업데이트
    const tbody = document.querySelector('tbody');
    if (tbody) {
      tbody.innerHTML = data.rows.map((r, i) => `
        <tr>
          <td>${(page - 1) * 10 + i + 1}</td>
          <td>${r.id}</td>
          <td class="badge-temp">${r.temperature}</td>
          <td class="badge-humid">${r.humidity}</td>
          <td class="badge-press">${r.pressure}</td>
          <td>${r.created_at}</td>
        </tr>`).join('');
    }
  } catch (e) {
    console.error('갱신 오류:', e);
  }
}

setInterval(refresh, 1000);
</script>
</body>
</html>
