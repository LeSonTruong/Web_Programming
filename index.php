<?php
include 'includes/header.php';
?>

<div class="container my-5">
    <!-- Phần chào mừng -->
    <div class="text-center mb-5">
        <h2 class="display-5">
            <?php if (isset($_SESSION['fullname'])): ?>
                👋 Xin chào, <?= htmlspecialchars($_SESSION['fullname']) ?>!
            <?php else: ?>
                Chào mừng đến với StudyShare
            <?php endif; ?>
        </h2>
        <p class="lead">Nơi bạn có thể tải lên và chia sẻ tài liệu học tập theo môn học hoặc ngành học.</p>
    </div>

    <!-- Form tìm kiếm -->
    <div class="mb-5">
        <h3>Tìm kiếm tài liệu</h3>
        <form action="search.php" method="get" class="row g-2">
            <div class="col-md-8">
                <input type="text" name="q" class="form-control" placeholder="Nhập tiêu đề hoặc môn học...">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Tìm kiếm</button>
            </div>
        </form>
    </div>

    <!-- Tài liệu mới nhất -->
    <div>
        <h3>Tài liệu mới nhất</h3>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-2">
            <?php
            include 'includes/db.php';

            $stmt = $conn->query("
                SELECT d.*, s.subject_name
                FROM documents d
                JOIN document_status ds ON d.status_id = ds.status_id
                JOIN subjects s ON d.subject_id = s.subject_id
                WHERE ds.status_name = 'approved'
                ORDER BY d.upload_date DESC
                LIMIT 5
            ");
            $docs = $stmt->fetchAll();

            foreach ($docs as $doc):
            ?>
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($doc['title']) ?></h5>
                            <p class="card-text"><strong>Môn học:</strong> <?= htmlspecialchars($doc['subject_name']) ?></p>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-success w-100">Tải xuống</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>