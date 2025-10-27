<?php
// Centralized action endpoints for approval flows (documents and document_edits)
include 'includes/db.php';
session_start();

// role helpers
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$current_user = $_SESSION['user_id'] ?? null;

// simple flash helper
function set_flash($html) {
    if (!isset($_SESSION['approve_flash'])) $_SESSION['approve_flash'] = [];
    $_SESSION['approve_flash'][] = $html;
}

$redirect = $_POST['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? 'approve.php');

// Allow non-admin users to perform certain actions like delete or enqueue AI for their own documents.
// Admin-only actions will still check $is_admin where appropriate.

// Delete document (owner or admin)
if (isset($_POST['delete'])) {
    $doc_id = (int)$_POST['delete'];
    try {
        $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
        $stmt->execute([$doc_id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) {
            set_flash('<div class="alert alert-danger">⚠️ Không tìm thấy tài liệu.</div>');
        } elseif (! $is_admin && $doc['user_id'] != $current_user) {
            set_flash('<div class="alert alert-danger">⚠️ Bạn không có quyền xóa tài liệu này.</div>');
        } else {
            if (!empty($doc['file_path']) && file_exists($doc['file_path'])) {
                @unlink($doc['file_path']);
            }
            $stmt = $conn->prepare("DELETE FROM documents WHERE doc_id=?");
            $stmt->execute([$doc_id]);
            set_flash('<div class="alert alert-success">✅ Tài liệu đã được xóa.</div>');
        }
    } catch (Exception $e) {
        set_flash('<div class="alert alert-danger">❌ Lỗi khi xóa tài liệu: ' . htmlspecialchars($e->getMessage()) . '</div>');
    }
    header('Location: ' . $redirect);
    exit();
}

// Enqueue AI (owner or admin) - replicates logic from my_documents.php but via flash + redirect
if (isset($_POST['enqueue_ai'])) {
    // Only admins may enqueue AI jobs. Owners no longer allowed to enqueue directly.
    if (! $is_admin) {
        set_flash('<div class="alert alert-danger">⚠️ Bạn không có quyền thực hiện hành động này.</div>');
        header('Location: ' . $redirect);
        exit();
    }
    $doc_id = (int)$_POST['enqueue_ai'];
    try {
        $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
        $stmt->execute([$doc_id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc || (! $is_admin && $doc['user_id'] != $current_user)) {
            set_flash('<div class="alert alert-danger">⚠️ Không tìm thấy tài liệu hoặc bạn không có quyền thực hiện.</div>');
            header('Location: ' . $redirect);
            exit();
        }

        // 1) Check for existing pending or processing jobs
        $stmt = $conn->prepare("SELECT COUNT(*) FROM ai_queue WHERE document_id = ? AND status IN ('pending','processing')");
        $stmt->execute([$doc_id]);
        $count = (int)$stmt->fetchColumn();

        if ($count > 0) {
            set_flash('<div class="alert alert-warning">⚠️ Tài liệu này đã có tiến trình đang chạy (pending/processing). Không thể thêm vào hàng đợi.</div>');
            header('Location: ' . $redirect);
            exit();
        }

        // 2) Get the most recent ai_queue row for this document (if any)
        $stmt = $conn->prepare("SELECT * FROM ai_queue WHERE document_id = ? ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 1");
        $stmt->execute([$doc_id]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);

        $need_confirm = false;
        if ($last) {
            $last_status = $last['status'];
            if ($last_status === 'done') {
                $last_time = $last['updated_at'] ?? $last['created_at'];
                $last_ts = $last_time ? strtotime($last_time) : 0;
                if ($last_ts >= time() - 30 * 60) {
                    $need_confirm = true;
                }
            }
        }

        // If confirmation is required, caller should repost with a confirm=1 field. We'll show a flash message indicating that.
        if ($need_confirm && empty($_POST['confirm'])) {
            // store a message that the UI should show a confirm button; but since we cannot render interactive form here,
            // instruct the user to re-submit (UI should have done this). We'll show a warning and not enqueue.
            set_flash('<div class="alert alert-warning">⚠️ Tài liệu này đã được xử lý gần đây (trong vòng 30 phút). Nếu bạn chắc chắn muốn gửi lại, hãy nhấn lại nút AI để xác nhận.</div>');
            header('Location: ' . $redirect);
            exit();
        }

        // Insert queue row
        $stmt = $conn->prepare("INSERT INTO ai_queue (document_id, status, created_at) VALUES (?, 'pending', NOW())");
        $stmt->execute([$doc_id]);
        set_flash('<div class="alert alert-success">✅ Đã thêm tài liệu vào hàng đợi AI (pending).</div>');
    } catch (Exception $e) {
        set_flash('<div class="alert alert-danger">❌ Lỗi khi thêm vào ai_queue: ' . htmlspecialchars($e->getMessage()) . '</div>');
    }
    header('Location: ' . $redirect);
    exit();
}

// Approve new document
if (isset($_POST['approve'])) {
    if (! $is_admin) {
        set_flash('<div class="alert alert-danger">⚠️ Bạn không có quyền thực hiện hành động này.</div>');
        header('Location: ' . $redirect);
        exit();
    }
    $doc_id = (int)$_POST['approve'];
    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();
    if ($doc) {
        $stmt = $conn->prepare("UPDATE documents SET status_id=2 WHERE doc_id=?");
        $stmt->execute([$doc_id]);
        $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $message = "✅ Tài liệu '{$doc['title']}' của bạn đã được duyệt!";
        $stmt_notif->execute([$doc['user_id'], $message]);
        set_flash('<div class="alert alert-success">✅ Tài liệu đã được duyệt.</div>');
    }
    header('Location: ' . $redirect);
    exit();
}

// Reject new document
if (isset($_POST['reject'])) {
    if (! $is_admin) {
        set_flash('<div class="alert alert-danger">⚠️ Bạn không có quyền thực hiện hành động này.</div>');
        header('Location: ' . $redirect);
        exit();
    }
    $doc_id = (int)$_POST['reject'];
    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();
    if ($doc && $doc['status_id'] == 1) {
        $stmt = $conn->prepare("UPDATE documents SET status_id=3 WHERE doc_id=?");
        $stmt->execute([$doc_id]);
        $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $message = "❌ Tài liệu '{$doc['title']}' của bạn đã bị từ chối!";
        $stmt_notif->execute([$doc['user_id'], $message]);
        set_flash('<div class="alert alert-danger">❌ Đã từ chối tài liệu.</div>');
    }
    header('Location: ' . $redirect);
    exit();
}

// Accept user edit
if (isset($_POST['accept_edit'])) {
    if (! $is_admin) {
        set_flash('<div class="alert alert-danger">⚠️ Bạn không có quyền thực hiện hành động này.</div>');
        header('Location: ' . $redirect);
        exit();
    }
    $edit_id = (int)$_POST['accept_edit'];
    $stmt = $conn->prepare("SELECT * FROM document_edits WHERE edit_id=? AND status='pending'");
    $stmt->execute([$edit_id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit) {
        $apply = $conn->prepare("UPDATE documents SET title=?, author_name=?, description=?, subject_id=?, file_path=?, file_size=?, document_type=?, updated_at=NOW() WHERE doc_id=?");
        $apply->execute([
            $edit['title'],
            $edit['author_name'],
            $edit['description'],
            $edit['subject_id'],
            $edit['file_path'],
            $edit['file_size'],
            $edit['document_type'],
            $edit['doc_id'],
        ]);
        $up = $conn->prepare("UPDATE document_edits SET status='approved', updated_at=NOW() WHERE edit_id=?");
        $up->execute([$edit_id]);
        // notify owner if possible
        try {
            $stmt_doc = $conn->prepare("SELECT user_id FROM documents WHERE doc_id=? LIMIT 1");
            $stmt_doc->execute([$edit['doc_id']]);
            $owner_id = $stmt_doc->fetchColumn();
            if ($owner_id) {
                $msg = "✅ Sửa đổi cho tài liệu đã được chấp nhận.";
                $stmt_not = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $stmt_not->execute([$owner_id, $msg]);
            }
        } catch (Exception $e) {
            // ignore
        }
        set_flash('<div class="alert alert-success">✅ Đã chấp nhận sửa đổi và cập nhật vào tài liệu.</div>');
    } else {
        set_flash('<div class="alert alert-info">⚠️ Không tìm thấy bản sửa đang chờ với edit_id này.</div>');
    }
    header('Location: ' . $redirect);
    exit();
}

// Reject user edit
if (isset($_POST['reject_edit'])) {
    if (! $is_admin) {
        set_flash('<div class="alert alert-danger">⚠️ Bạn không có quyền thực hiện hành động này.</div>');
        header('Location: ' . $redirect);
        exit();
    }
    $edit_id = (int)$_POST['reject_edit'];
    $stmt = $conn->prepare("SELECT * FROM document_edits WHERE edit_id=? AND status='pending'");
    $stmt->execute([$edit_id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit) {
        $up = $conn->prepare("UPDATE document_edits SET status='rejected', updated_at=NOW() WHERE edit_id=?");
        $up->execute([$edit_id]);
        set_flash('<div class="alert alert-danger">❌ Đã từ chối sửa đổi.</div>');
    } else {
        set_flash('<div class="alert alert-info">⚠️ Không tìm thấy bản sửa đang chờ với edit_id này.</div>');
    }
    header('Location: ' . $redirect);
    exit();
}

// If nothing matched, just redirect back
header('Location: ' . $redirect);
exit();
