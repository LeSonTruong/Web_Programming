<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Lấy tiêu đề trang nếu được truyền vào
$page_title = $page_title ?? "Code Viewer";
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            color: #212529;
        }

        header {
            margin-bottom: 20px;
        }

        header h1 {
            font-size: 1.8rem;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <header>
        <h1><?= htmlspecialchars($page_title) ?></h1>
    </header>
    <main>