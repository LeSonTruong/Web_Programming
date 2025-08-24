<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyShare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
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
            <a class="navbar-brand fw-bold me-3" href="index.php">StudyShare</a>

            <!-- Ô tìm kiếm (nằm ngang với logo và menu, không bị kéo lên/xuống) -->
            <form class="d-flex search-box flex-grow-1 mx-3 d-none d-lg-flex" role="search" action="search.php" method="get">
                <input
                    class="form-control form-control-sm me-2"
                    type="search"
                    name="q"
                    placeholder="Tìm tài liệu..."
                    aria-label="Search">
                <button class="btn btn-sm btn-outline-light" type="submit">🔍</button>
            </form>

            <!-- Nút thu gọn menu (hiện trên mobile) -->
            <button class="navbar-toggler ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Các mục menu -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">🏠 Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link" href="documents.php">📄 Tài liệu</a></li>
                    <li class="nav-item"><a class="nav-link" href="search_advanced.php">🔎 Tìm kiếm nâng cao</a></li>

                    <?php if (isset($_SESSION['user_id'])):
                        $display_name = $_SESSION['display_name'] ?? $_SESSION['username'];
                        $avatar = $_SESSION['avatar'] ?? 'default.png';

                        require_once 'includes/db.php';
                        $notifications_count = 0;
                        if ($_SESSION['role'] === 'admin') {
                            $stmt = $conn->query("SELECT COUNT(*) FROM documents WHERE status_id=1");
                            $notifications_count = $stmt->fetchColumn();
                        } else {
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
                            $stmt->execute([$_SESSION['user_id']]);
                            $notifications_count = $stmt->fetchColumn();
                        }
                    ?>
                        <li class="nav-item"><a class="nav-link" href="upload.php">📤 Tải tài liệu lên</a></li>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="approve.php">✅ Duyệt tài liệu <?php if ($notifications_count > 0) echo "($notifications_count)"; ?></a></li>
                        <?php endif; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="uploads/avatars/<?= htmlspecialchars($avatar) ?>" alt="Avatar" width="30" height="30" class="rounded-circle me-2">
                                <?= htmlspecialchars($display_name) ?>
                                <?php if ($notifications_count > 0): ?>
                                    <span class="badge bg-danger ms-2"><?= $notifications_count ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php?user=<?= htmlspecialchars($user['username'] ?? $_SESSION['username']) ?>">👤 Trang cá nhân</a></li>
                                <li><a class="dropdown-item" href="settings_profile.php">⚙️ Cài đặt tài khoản</a></li>
                                <li><a class="dropdown-item" href="my_documents.php">📄 Quản lý tài liệu</a></li>

                                <?php if ($_SESSION['role'] !== 'admin'): ?>
                                    <li>
                                        <a class="dropdown-item" href="notifications.php">
                                            🔔 Thông báo <?php if ($notifications_count > 0) echo "($notifications_count)"; ?>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="user.php">👥 Quản lý tài khoản</a></li>
                                    <li><a class="dropdown-item text-warning" href="ai_logs.php">📜 AI Logs</a></li>
                                    <li><a class="dropdown-item" href="downloads.php">📥 Lịch sử tải về</a></li>
                                <?php endif; ?>

                                <li><a class="dropdown-item" href="logout.php">🚪 Đăng xuất</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">🔑 Đăng nhập</a></li>
                        <li class="nav-item"><a class="nav-link" href="register.php">📝 Đăng ký</a></li>
                    <?php endif; ?>

                    <!-- Ô tìm kiếm cho mobile (hiện khi mở menu) -->
                    <li class="nav-item d-lg-none mt-2">
                        <form class="d-flex search-box" role="search" action="search.php" method="get">
                            <input class="form-control form-control-sm me-2" type="search" name="q" placeholder="Tìm tài liệu..." aria-label="Search">
                            <button class="btn btn-sm btn-outline-light" type="submit">🔍</button>
                        </form>
                    </li>

                    <!-- Nút dark mode (luôn ở cuối menu trên mobile, bên phải header trên PC) -->
                    <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                        <button id="theme-toggle" class="btn btn-sm btn-light">🌙</button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <script>
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

    <!-- Thêm khoảng trống phía trên để tránh bị che bởi navbar cố định -->
    <div style="height: 70px;"></div>
    <main class="container my-4">