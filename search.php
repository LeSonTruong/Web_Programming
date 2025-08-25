<?php
include 'includes/header.php';
?>
<div class="container my-4">
    <form method="get" action="search_advanced.php" class="row g-2 mb-4">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control" placeholder="🔍 Tìm tài liệu..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        </div>

        <div class="col-md-2">
            <select name="subject" class="form-select">
                <option value="">📚 Tất cả môn học</option>
                <?php
                include 'includes/db.php';
                $subStmt = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
                while ($s = $subStmt->fetch(PDO::FETCH_ASSOC)) {
                    $sel = (isset($_GET['subject']) && $_GET['subject'] == $s['subject_id']) ? 'selected' : '';
                    echo "<option value='{$s['subject_id']}' $sel>" . htmlspecialchars($s['subject_name']) . "</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="department" class="form-select">
                <option value="">🏫 Tất cả khoa</option>
                <?php
                $depStmt = $conn->query("SELECT DISTINCT department FROM subjects WHERE department IS NOT NULL ORDER BY department");
                while ($d = $depStmt->fetch(PDO::FETCH_ASSOC)) {
                    $sel = (isset($_GET['department']) && $_GET['department'] === $d['department']) ? 'selected' : '';
                    echo "<option value='" . htmlspecialchars($d['department']) . "' $sel>" . htmlspecialchars($d['department']) . "</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="filetype" class="form-select">
                <option value="">📂 Tất cả định dạng</option>
                <?php
                $types = ['pdf' => 'PDF', 'doc' => 'Word', 'ppt' => 'PowerPoint', 'image' => 'Ảnh', 'code' => 'Code', 'other' => 'Khác'];
                foreach ($types as $k => $v) {
                    $sel = (isset($_GET['filetype']) && $_GET['filetype'] === $k) ? 'selected' : '';
                    echo "<option value='$k' $sel>$v</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-2">
            <select name="sortby" class="form-select">
                <option value="">🔽 Sắp xếp theo...</option>
                <option value="likes" <?= (isset($_GET['sortby']) && $_GET['sortby'] == 'likes') ? 'selected' : '' ?>>Lượt thích nhiều nhất</option>
                <option value="views" <?= (isset($_GET['sortby']) && $_GET['sortby'] == 'views') ? 'selected' : '' ?>>Người xem nhiều nhất</option>
                <option value="downloads" <?= (isset($_GET['sortby']) && $_GET['sortby'] == 'downloads') ? 'selected' : '' ?>>Người tải nhiều nhất</option>
            </select>
        </div>

        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Tìm kiếm</button>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>