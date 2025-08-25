<?php
// includes/search_helper.php
function searchDocuments($pdo, $keyword = '', $filters = [])
{
    $sql = "SELECT d.doc_id, d.title, d.description, d.file_type, d.upload_date,
                   u.username, s.subject_name, s.department
            FROM documents d
            JOIN users u ON d.user_id = u.user_id
            JOIN subjects s ON d.subject_id = s.subject_id
            WHERE 1=1";

    $params = [];

    // Tìm kiếm theo từ khóa (LIKE cho cơ bản + nâng cao khi không có filter)
    if (!empty($keyword)) {
        $sql .= " AND (d.title LIKE :kw OR d.description LIKE :kw OR s.subject_name LIKE :kw)";
        $params[':kw'] = "%$keyword%";
    }

    // Nếu có filter (nâng cao)
    if (!empty($filters['department'])) {
        $sql .= " AND s.department = :department";
        $params[':department'] = $filters['department'];
    }
    if (!empty($filters['file_type'])) {
        $sql .= " AND d.file_type = :file_type";
        $params[':file_type'] = $filters['file_type'];
    }
    if (!empty($filters['user_id'])) {
        $sql .= " AND u.user_id = :user_id";
        $params[':user_id'] = $filters['user_id'];
    }

    $sql .= " ORDER BY d.upload_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
