<?php
// ====== BẮT BUỘC LUÔN ĐẦU FILE ======
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Xác định base URL của ứng dụng (ví dụ: '' nếu chạy ở domain root, hoặc '/aaa' nếu chạy ở subfolder)
// Dùng dirname($_SERVER['SCRIPT_NAME']) để lấy thư mục chứa script hiện tại
$BASE_URL = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($BASE_URL === '' || $BASE_URL === '.') {
    $BASE_URL = '';
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyShare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Dùng base URL động để hỗ trợ deploy trong subfolder hoặc root -->
    <link rel="stylesheet" href="<?php echo $BASE_URL; ?>/css/style.css">
    <style>
        /* Chừa khoảng cho navbar fixed-top */
        body {
            padding-top: 70px;
            /* = chiều cao navbar (56px default Bootstrap, tăng thêm để an toàn) */
        }

        /* Animation chuông rung */
        .bell-animate {
            animation: bell-shake 0.7s cubic-bezier(.36, .07, .19, .97) both;
        }

        @keyframes bell-shake {
            0% {
                transform: rotate(0deg);
            }

            10% {
                transform: rotate(-15deg);
            }

            20% {
                transform: rotate(10deg);
            }

            30% {
                transform: rotate(-10deg);
            }

            40% {
                transform: rotate(6deg);
            }

            50% {
                transform: rotate(-4deg);
            }

            60% {
                transform: rotate(2deg);
            }

            70% {
                transform: rotate(-1deg);
            }

            80% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(0deg);
            }
        }

        /* ==== Dark Mode CSS Biến ==== */
        :root {
            --bg-color: #ffffff;
            --text-color: #000000;
            --navbar-bg: #007bff;
            --card-bg: #ffffff;
            --btn-bg: #007bff;
            --btn-text: #ffffff;
            --dropdown-bg: #ffffff;
            --dropdown-text: #000000;
        }

        body.dark-mode {
            --bg-color: #121212;
            --text-color: #e0e0e0;
            --navbar-bg: #1f1f1f;
            --card-bg: #1e1e1e;
            --btn-bg: #333333;
            --btn-text: #ffffff;
            --dropdown-bg: #2a2a2a;
            --dropdown-text: #e0e0e0;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        .navbar {
            background-color: var(--navbar-bg) !important;
        }

        .card,
        .document-card {
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        button {
            background-color: var(--btn-bg);
            color: var(--btn-text);
        }

        .dropdown-menu {
            background-color: var(--dropdown-bg);
            color: var(--dropdown-text);
        }

        .dropdown-menu a {
            color: var(--dropdown-text);
        }

        .dropdown-menu a:hover {
            background-color: #444444;
            color: #ffffff;
        }

        /* ==== Ô tìm kiếm ==== */
        .search-box {
            max-width: 250px;
        }

        .search-box input {
            border-radius: 20px;
            padding: 4px 12px;
        }

        body.dark-mode .search-box input {
            background-color: #2a2a2a;
            color: #e0e0e0;
            border: 1px solid #555;
        }

        body.dark-mode .search-box input::placeholder {
            color: #aaa;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm fixed-top">
        <div class="container d-flex align-items-center justify-content-between">
            <!-- Logo -->
            <a class="navbar-brand fw-bold me-3" href="/">StudyShare</a>

            <!-- Ô tìm kiếm lớn -->
            <!-- Dùng action với $BASE_URL để form hoạt động đúng khi app chạy trong subfolder -->
            <form class="d-flex search-box flex-grow-1 mx-3 d-none d-lg-flex" role="search" action="<?php echo $BASE_URL; ?>/search_advanced.php" method="get">
                <input class="form-control form-control-sm me-2" type="search" name="q" placeholder="Tìm tài liệu..." aria-label="Search">
                <button class="btn btn-sm btn-outline-light" type="submit">🔍</button>
            </form>

            <!-- Nút menu mobile -->
            <button class="navbar-toggler ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menu chính -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="<?php echo $BASE_URL; ?>/">🏠 Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $BASE_URL; ?>/documents.php">📄 Tài liệu</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $BASE_URL; ?>/search_advanced.php">🔎 Tìm kiếm nâng cao</a></li>

                    <?php if (isset($_SESSION['user_id'])):
                        $display_name = $_SESSION['display_name'] ?? $_SESSION['username'];
                        $avatar = $_SESSION['avatar'] ?? 'default.png';

                        // Dùng __DIR__ để require DB ngay cả khi header được include từ subfolder
                        require_once __DIR__ . '/db.php';
                        $notifications_count = 0;
                        $pending_docs = 0;
                        $pending_edits = 0;
                        if ($_SESSION['role'] === 'admin') {
                            $pending_docs = $conn->query("SELECT COUNT(*) FROM documents WHERE status_id=1")->fetchColumn();
                            $pending_edits = $conn->query("SELECT COUNT(*) FROM document_edits WHERE status='pending'")->fetchColumn();
                        }
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
                        $stmt->execute([$_SESSION['user_id']]);
                        $normal_notifications = $stmt->fetchColumn();
                        $tailieu_notifications = $pending_docs + $pending_edits;
                        $notifications_count = $normal_notifications + $tailieu_notifications;
                    ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $BASE_URL; ?>/upload.php">📤 Tải tài liệu lên</a></li>
                        <li class="nav-item dropdown position-relative">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <!-- Avatar dùng base URL để hiển thị đúng khi chạy trong subfolder -->
                                <img src="<?php echo $BASE_URL; ?>/uploads/avatars/<?= htmlspecialchars($avatar) ?>" alt="Avatar" width="30" height="30" class="rounded-circle me-2">
                                <?= htmlspecialchars($display_name) ?>
                                <span class="ms-2 bell-icon<?= ($notifications_count > 0 ? ' bell-animate' : '') ?>" id="dropdown-bell">🔔</span>
                                <?php if ($notifications_count > 0): ?>
                                    <span class="badge bg-danger position-absolute" style="top:8px; right:2px;"><?= $notifications_count ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" style="min-width:260px;">
                                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/profile.php?user=<?= htmlspecialchars($_SESSION['username']) ?>">👤 Trang cá nhân</a></li>
                                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/settings_profile.php">⚙️ Cài đặt tài khoản</a></li>
                                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/my_documents.php">📄 Quản lý tài liệu</a></li>
                                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/notifications.php">🔔 Thông báo (<?= $normal_notifications ?>)</a></li>
                                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/downloads.php">📥 Lịch sử tải về</a></li>

                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/approve.php">📄 Duyệt tài liệu (<?= $tailieu_notifications ?>)</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/user.php">👥 Quản lý tài khoản</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/ai_logs.php">📜 AI Logs</a></li>
                                <?php endif; ?>

                                <li><a class="dropdown-item" href="<?php echo $BASE_URL; ?>/logout.php">🚪 Đăng xuất</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $BASE_URL; ?>/login.php">🔑 Đăng nhập</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $BASE_URL; ?>/register.php">📝 Đăng ký</a></li>
                    <?php endif; ?>

                    <!-- Ô tìm kiếm cho mobile -->
                    <li class="nav-item d-lg-none mt-2">
                        <form class="d-flex search-box" role="search" action="search_advanced.php" method="get">
                            <input class="form-control form-control-sm me-2" type="search" name="q" placeholder="Tìm tài liệu..." aria-label="Search">
                            <button class="btn btn-sm btn-outline-light" type="submit">🔍</button>
                        </form>
                    </li>

                    <!-- Nút dark mode -->
                    <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                        <button id="theme-toggle" class="btn btn-sm btn-light">🌙</button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <script>
        // Hiệu ứng chuông
        document.addEventListener('DOMContentLoaded', function() {
            var bell = document.querySelector('.bell-icon');
            if (bell && bell.classList.contains('bell-animate')) {
                setTimeout(() => bell.classList.remove('bell-animate'), 1200);
            }
        });
        // Dark mode toggle
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('theme-toggle');
            if (localStorage.getItem('dark-mode') === 'true') {
                document.body.classList.add('dark-mode');
                toggleBtn.textContent = '☀️';
            }
            toggleBtn.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                const isDark = document.body.classList.contains('dark-mode');
                localStorage.setItem('dark-mode', isDark);
                toggleBtn.textContent = isDark ? '☀️' : '🌙';
            });
        });
    </script>

    <!-- Mở main ở đây, khi include footer sẽ đóng -->
    <main class="container my-4">