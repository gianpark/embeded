<?php
$host="localhost"; $user="gianpark"; $password="qwer1234"; $database="jungjudb";
$limit=10;
$page=isset($_GET['page'])&&is_numeric($_GET['page'])?(int)$_GET['page']:1;
$offset=($page-1)*$limit;
$conn=new mysqli($host,$user,$password,$database);
if($conn->connect_error) die("<p style='color:red;'>DB 연결 실패: ".$conn->connect_error."</p>");
$totalResult=$conn->query("SELECT COUNT(*) AS cnt FROM sensor");
$totalRow=$totalResult->fetch_assoc();
$totalCount=$totalRow['cnt'];
$totalPages=ceil($totalCount/$limit);
$stmt=$conn->prepare("SELECT id, humid, created_at FROM sensor ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii",$limit,$offset);
$stmt->execute();
$result=$stmt->get_result();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>Sensor 데이터</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f4f8;color:#333;padding:30px}
h1{text-align:center;margin-bottom:10px;font-size:1.8rem;color:#2c3e50}
.subtitle{text-align:center;color:#888;margin-bottom:25px}
.info-bar{display:flex;justify-content:space-between;background:#fff;border-radius:10px;padding:12px 20px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);font-size:.9rem;color:#555}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.08)}
thead{background:linear-gradient(135deg,#3498db,#2980b9);color:#fff}
thead th{padding:14px 20px;text-align:center}
tbody tr{border-bottom:1px solid #f0f0f0;transition:background .2s}
tbody tr:hover{background:#eaf4fb}
tbody td{padding:13px 20px;text-align:center;font-size:.92rem}
.bar-wrap{display:flex;align-items:center;gap:10px}
.bar{flex:1;background:#e8f4fb;border-radius:20px;height:10px;overflow:hidden}
.bar-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,#3498db,#2ecc71)}
.val{min-width:55px;font-weight:bold;color:#2980b9}
.pagination{display:flex;justify-content:center;gap:8px;margin-top:25px;flex-wrap:wrap}
.pagination a,.pagination span{display:inline-block;padding:8px 14px;border-radius:8px;text-decoration:none;font-size:.9rem}
.pagination a{background:#fff;color:#3498db;border:1px solid #d0e8f7;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.pagination a:hover{background:#3498db;color:#fff}
.pagination .current{background:#3498db;color:#fff;border:1px solid #3498db;font-weight:bold}
.badge{background:#eaf4fb;color:#2980b9;border-radius:20px;padding:2px 10px;font-size:.8rem}
</style>
</head>
<body>
<h1>🌡️ Sensor 습도 데이터</h1>
<p class="subtitle">jungjudb &gt; sensor 테이블</p>
<div class="info-bar">
  <span>전체 데이터: <strong><?= number_format($totalCount) ?>건</strong></span>
  <span>페이지: <strong><?= $page ?> / <?= $totalPages ?></strong></span>
  <span>10개씩 표시</span>
</div>
<table>
<thead><tr><th>ID</th><th>습도 (humid)</th><th>저장 시각</th></tr></thead>
<tbody>
<?php if($result->num_rows>0): while($row=$result->fetch_assoc()): ?>
<tr>
  <td><span class="badge">#<?= $row['id'] ?></span></td>
  <td>
    <div class="bar-wrap">
      <div class="bar"><div class="bar-fill" style="width:<?= $row['humid'] ?>%"></div></div>
      <span class="val"><?= $row['humid'] ?>%</span>
    </div>
  </td>
  <td><?= $row['created_at'] ?? '-' ?></td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="3" style="text-align:center;padding:40px;color:#aaa">데이터가 없습니다.</td></tr>
<?php endif; ?>
</tbody>
</table>
<div class="pagination">
<?php if($page>1): ?>
  <a href="?page=1">«</a>
  <a href="?page=<?= $page-1 ?>">‹ 이전</a>
<?php endif; ?>
<?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?>
  <?php if($i==$page): ?><span class="current"><?= $i ?></span>
  <?php else: ?><a href="?page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
<?php endfor; ?>
<?php if($page<$totalPages): ?>
  <a href="?page=<?= $page+1 ?>">다음 ›</a>
  <a href="?page=<?= $totalPages ?>">»</a>
<?php endif; ?>
</div>
</body></html>
<?php $stmt->close(); $conn->close(); ?>
