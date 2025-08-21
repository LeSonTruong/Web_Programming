<?php
include 'includes/header.php';
include 'includes/db.php';
include 'includes/ai.php'; // Gọi client $openai ở đây

// ====== HÀM LOG ======
function logAI($conn, $doc_id, $action, $status, $message = '')
{
    $stmt = $conn->prepare("INSERT INTO ai_logs (doc_id, action, status, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$doc_id, $action, $status, $message]);
}

// ====== HÀM TẠO TÓM TẮT ======
function generateSummary($client, $conn, $doc_id, $text)
{
    try {
        $response = $client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Bạn là AI chuyên tóm tắt tài liệu ngắn gọn, súc tích.'],
                ['role' => 'user', 'content' => "Tóm tắt nội dung sau:\n\n$text"]
            ],
            'max_tokens' => 200,
        ]);
        $summary = $response->choices[0]->message->content ?? '';
        logAI($conn, $doc_id, 'summary', 'success', 'Tóm tắt thành công');
        return $summary;
    } catch (Exception $e) {
        logAI($conn, $doc_id, 'summary', 'fail', $e->getMessage());
        return '';
    }
}

// ====== HÀM TẠO EMBEDDING ======
function generateEmbedding($client, $conn, $doc_id, $text)
{
    try {
        $response = $client->embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ]);
        $embedding = $response->data[0]->embedding ?? [];
        logAI($conn, $doc_id, 'embedding', 'success', 'Embedding thành công');
        return $embedding;
    } catch (Exception $e) {
        logAI($conn, $doc_id, 'embedding', 'fail', $e->getMessage());
        return [];
    }
}

// ====== KIỂM TRA QUYỀN ADMIN ======
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo '<div class="alert alert-danger">Bạn không có quyền truy cập trang này!</div>';
    include 'includes/footer.php';
    exit();
}

// ====== DUYỆT ======
if (isset($_GET['approve'])) {
    $doc_id = (int) $_GET['approve'];

    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();

    if ($doc) {
        if ($doc['status_id'] != 0) {
            echo '<div class="alert alert-info">⚠️ Tài liệu này đã được xử lý trước đó.</div>';
        } else {
            // Ưu tiên mô tả, fallback sang tiêu đề
            $textContent = $doc['description'] ?: $doc['title'];
            $textContent = mb_substr($textContent, 0, 5000);

            $summary = generateSummary($openai, $conn, $doc_id, $textContent);
            $embedding = generateEmbedding($openai, $conn, $doc_id, $textContent);

            // Cập nhật documents
            $stmt = $conn->prepare("UPDATE documents SET status_id=1, summary=? WHERE doc_id=?");
            $stmt->execute([$summary, $doc_id]);

            // Lưu embedding nếu có
            if (!empty($embedding)) {
                $stmt = $conn->prepare("INSERT INTO document_embeddings (doc_id, vector) VALUES (?, ?)");
                $stmt->execute([$doc_id, json_encode($embedding)]);
            }

            echo '<div class="alert alert-success">✅ Tài liệu đã được duyệt, tóm tắt & embedding đã lưu.</div>';
        }
    }
}

// ====== TỪ CHỐI ======
if (isset($_GET['reject'])) {
    $doc_id = (int) $_GET['reject'];
    $stmt = $conn->prepare("UPDATE documents SET status_id=2 WHERE doc_id=?");
    $stmt->execute([$doc_id]);
    echo '<div class="alert alert-danger">❌ Tài liệu đã bị từ chối.</div>';
}

// ====== DANH SÁCH CHỜ DUYỆT ======
$docs = $conn->query("
    SELECT documents.*, users.username 
    FROM documents 
    JOIN users ON documents.user_id = users.user_id 
    WHERE status_id=0 
    ORDER BY upload_date DESC
")->fetchAll();
?>

<div class="container my-4">
    <h2 class="mb-4">📝 Duyệt tài liệu</h2>

    <?php if (!$docs): ?>
        <div class="alert alert-info">Hiện tại không có tài liệu nào chờ duyệt.</div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($docs as $doc): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($doc['title']) ?></h5>
                            <p class="card-text"><strong>Người đăng:</strong> <?= htmlspecialchars($doc['username']) ?></p>
                            <?php if (!empty($doc['description'])): ?>
                                <p class="card-text"><strong>Mô tả:</strong> <?= nl2br(htmlspecialchars($doc['description'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer d-flex justify-content-between">
                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-info btn-sm">Xem/Tải</a>
                            <div>
                                <a href="?approve=<?= $doc['doc_id'] ?>" class="btn btn-success btn-sm">Duyệt</a>
                                <a href="?reject=<?= $doc['doc_id'] ?>" class="btn btn-danger btn-sm">Từ chối</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>