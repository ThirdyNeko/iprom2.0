<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$pdo = qa_db();

$draw   = $_POST['draw'] ?? 0;
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 25);
$name   = trim($_POST['name'] ?? '');

$columns = [
    0 => 'promodiser',
    1 => 'agency',
    2 => 'employment_status',
    3 => 'sub_status',
    4 => 'effectivity_date',
];

$orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
$orderDir = ($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$orderColumn = $columns[$orderColumnIndex] ?? 'promodiser';

// Promodiser is a computed column so we need the alias for ordering
$orderExpr = ($orderColumn === 'promodiser')
    ? "LTRIM(RTRIM(first_name + ' ' + ISNULL(middle_name, '') + ' ' + last_name + ' ' + ISNULL(suffix, '')))"
    : $orderColumn;

/* SEARCH */
$where = "WHERE 1=1";
$params = [];

if (!empty($name)) {
    $where .= " AND (
        first_name       LIKE :name1 OR
        last_name        LIKE :name2 OR
        middle_name      LIKE :name3 OR
        agency           LIKE :name4 OR
        employment_status LIKE :name5 OR
        sub_status       LIKE :name6
    )";
    $params[':name1'] = "%$name%";
    $params[':name2'] = "%$name%";
    $params[':name3'] = "%$name%";
    $params[':name4'] = "%$name%";
    $params[':name5'] = "%$name%";
    $params[':name6'] = "%$name%";
}

/* TOTAL */
$totalStmt = $pdo->query("SELECT COUNT(*) FROM letters_of_advice");
$recordsTotal = $totalStmt->fetchColumn();

/* FILTERED COUNT */
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM letters_of_advice $where");
$countStmt->execute($params);
$recordsFiltered = $countStmt->fetchColumn();

/* DATA (SQL Server 2012 ROW_NUMBER pagination) */
$sql = "
SELECT *
FROM (
    SELECT
        LTRIM(RTRIM(
            first_name + ' ' +
            ISNULL(middle_name, '') + ' ' +
            last_name + ' ' +
            ISNULL(suffix, '')
        )) AS promodiser,
        agency,
        employment_status,
        sub_status,
        effectivity_date,
        ROW_NUMBER() OVER (ORDER BY $orderExpr $orderDir) AS rownum
    FROM letters_of_advice
    $where
) AS t
WHERE t.rownum > :start
  AND t.rownum <= :end
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':end', $start + $length, PDO::PARAM_INT);

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($data as &$row) {
    unset($row['rownum']);
    // Format date for display
    if (!empty($row['effectivity_date'])) {
        $row['effectivity_date'] = date('M d, Y', strtotime($row['effectivity_date']));
    }
}

echo json_encode([
    "draw"            => intval($draw),
    "recordsTotal"    => intval($recordsTotal),
    "recordsFiltered" => intval($recordsFiltered),
    "data"            => $data
]);