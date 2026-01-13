<?php
include '../conexao.php';
header('Content-Type: application/json');

$axis_id = isset($_GET['axis_id']) ? intval($_GET['axis_id']) : null;
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
$subcategory_id = isset($_GET['subcategory_id']) ? intval($_GET['subcategory_id']) : null;
$ext = isset($_GET['ext']) ? strtolower(trim($conn->real_escape_string($_GET['ext']))) : null;

$sql = "SELECT u.*, 
            ax.nome AS axis_nome,
            cat.nome AS category_nome,
            sub.nome AS subcategory_nome
        FROM flow_ref_upload u
        LEFT JOIN flow_ref_axis ax ON ax.id = u.axis_id
        LEFT JOIN flow_ref_category cat ON cat.id = u.category_id
        LEFT JOIN flow_ref_subcategory sub ON sub.id = u.subcategory_id
        WHERE 1";

if ($axis_id) $sql .= " AND u.axis_id = $axis_id";
if ($category_id) $sql .= " AND u.category_id = $category_id";
if ($subcategory_id) $sql .= " AND u.subcategory_id = $subcategory_id";
if ($ext) $sql .= " AND LOWER(u.ext) = '$ext'";

$sql .= " ORDER BY u.uploaded_at DESC";

$res = $conn->query($sql);
$out = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
}

echo json_encode($out);
$conn->close();
