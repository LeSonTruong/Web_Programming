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
            // Try simple local removal: files are stored locally in project (no URL handling)
            if (!empty($doc['file_path'])) {
                $fp = $doc['file_path'];
                // Candidate local locations (in order): stored value, relative to script, project root
                $candidates = [
                    $fp,
                    __DIR__ . '/' . ltrim($fp, '/\\'),
                    '/var/www/studyshare/' . ltrim($fp, '/\\'),
                ];

                $deleted = false;
                foreach ($candidates as $cand) {
                    if (!$cand) continue;
                    if (file_exists($cand) && is_file($cand)) {
                        if (@unlink($cand)) {
                            $deleted = true;
                            break;
                        }
                    }
                }

                if ($deleted) {
                    set_flash('<div class="alert alert-secondary">ℹ️ Xóa thành công.</div>');
                } else {
                    // log for investigation; not fatal
                    error_log('action_approve: failed to remove local file for doc_id ' . $doc_id . ' path: ' . $fp);
                }
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
