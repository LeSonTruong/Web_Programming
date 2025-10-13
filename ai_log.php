<?php
session_start();

include 'includes/header.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


include 'includes/db.php';

// ====== KIỂM TRA QUYỀN ADMIN ======
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo '<div class="container my-5">
            <div class="alert alert-danger text-center">
                ❌ Bạn không có quyền truy cập trang này!
            </div>
          </div>';
    include 'includes/footer.php';
    exit();
}

// Thay vì khởi tạo $pdo mới, dùng biến $conn đã có
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Lấy filter trạng thái
$status_filter = $_GET['status'] ?? 'all';

// Chuẩn bị truy vấn
if ($status_filter == 'all') {
    $sql = "SELECT aq.id, aq.status, aq.summary, aq.log, aq.updated_at, d.title, d.file_path 
            FROM ai_logs aq 
            LEFT JOIN documents d ON aq.doc_id = d.doc_id 
            ORDER BY aq.updated_at DESC LIMIT 100";
    $params = [];
} else {
    $sql = "SELECT aq.id, aq.status, aq.summary, aq.log, aq.updated_at, d.title, d.file_path 
            FROM ai_logs aq 
            LEFT JOIN documents d ON aq.doc_id = d.doc_id 
            WHERE aq.status = ? 
            ORDER BY aq.updated_at DESC LIMIT 100";
    $params = [ $status_filter ];
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function esc($str) {
    return htmlspecialchars($str);
}
?>

<h1>Logs Worker AI - Console</h1>
<form method="GET"><label>Chọn trạng thái:</label>
<select name="status">
  <option value="all" <?=($status_filter=='all')?'selected':''?>>Tất cả</option>
  <option value="pending" <?=($status_filter=='pending')?'selected':''?>>Pending</option>
  <option value="processing" <?=($status_filter=='processing')?'selected':''?>>Processing</option>
  <option value="done" <?=($status_filter=='done')?'selected':''?>>Done</option>
  <option value="failed" <?=($status_filter=='failed')?'selected':''?>>Failed</option>
</select>
<button type="submit">Lọc</button></form>

<table>
<tr><th>ID</th><th>Trạng thái</th><th>Tiêu đề</th><th>Tóm tắt</th><th>Log chi tiết</th><th>Cập nhật</th></tr>
<?php foreach($logs as $log): ?>
<tr>
  <td><?=esc($log['id'])?></td>
  <td><span class="status-badge <?=esc($log['status'])?>"><?=esc($log['status'])?></span></td>
  <td><?=esc($log['title'])?></td>
  <td><?=nl2br(esc($log['summary']))?></td>
  <td><pre><?=esc($log['log'])?></pre></td>
  <td><?=esc($log['updated_at'])?></td>
</tr>
<?php endforeach; ?>
</table>

<?php
include 'includes/footer.php';
?>
