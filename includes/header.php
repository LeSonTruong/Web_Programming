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
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">StudyShare</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link" href="documents.php">Tài liệu</a></li>

                    <?php if (isset($_SESSION['user_id'])):
                        $display_name = $_SESSION['display_name'] ?? $_SESSION['username'];
                        $avatar = $_SESSION['avatar'] ?? 'default.png';
                    ?>
                        <li class="nav-item"><a class="nav-link" href="upload.php">Tải lên</a></li>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="approve.php">Duyệt tài liệu</a></li>
                        <?php endif; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="uploads/avatars/<?= htmlspecialchars($avatar) ?>" alt="Avatar" width="30" height="30" class="rounded-circle me-2">
                                <?= htmlspecialchars($display_name) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php">Quản lý tài khoản</a></li>
                                <li><a class="dropdown-item" href="logout.php">Đăng xuất</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Đăng nhập</a></li>
                        <li class="nav-item"><a class="nav-link" href="register.php">Đăng ký</a></li>
                    <?php endif; ?>

                    <li class="nav-item ms-2">
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

    <main class="container my-4">