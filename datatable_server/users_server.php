<?php
// users_server.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . "/db.php";

/**
 * DataTables Server-side (simples e profissional):
 * - paging: start/length
 * - search: search[value]
 * - ordering: order[0][column] + order[0][dir]
 * - output: draw, recordsTotal, recordsFiltered, data
 */

// mapeia colunas do DataTables (índice) -> colunas reais no BD
$columns = [
  0 => "id",
  1 => "nome",
  2 => "email",
  3 => "departamento",
  4 => "cargo",
  5 => "status",
  6 => "data_registo",
  7 => "ultimo_login",
  8 => "telefone",
  9 => "cidade"
];

$draw   = isset($_POST["draw"]) ? (int)$_POST["draw"] : 0;
$start  = isset($_POST["start"]) ? max(0, (int)$_POST["start"]) : 0;
$length = isset($_POST["length"]) ? (int)$_POST["length"] : 10;
if ($length < 1 || $length > 500) $length = 10; // limite seguro

$searchValue = $_POST["search"]["value"] ?? "";
$searchValue = trim((string)$searchValue);

$orderColumnIndex = isset($_POST["order"][0]["column"]) ? (int)$_POST["order"][0]["column"] : 0;
$orderDir = $_POST["order"][0]["dir"] ?? "asc";
$orderDir = strtolower((string)$orderDir) === "desc" ? "DESC" : "ASC";

$orderColumn = $columns[$orderColumnIndex] ?? "id";

// 1) recordsTotal (sem filtros)
$recordsTotal = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// 2) Montar WHERE do search (filtro global)
$where = "";
$params = [];

if ($searchValue !== "") {
  // pesquisa em múltiplas colunas (ajuste conforme necessidade)
  $where = " WHERE nome LIKE :s
             OR email LIKE :s
             OR departamento LIKE :s
             OR cargo LIKE :s
             OR status LIKE :s
             OR cidade LIKE :s ";
  $params[":s"] = "%" . $searchValue . "%";
}

// 3) recordsFiltered (com filtros)
$sqlFiltered = "SELECT COUNT(*) FROM users" . $where;
$stmt = $pdo->prepare($sqlFiltered);
$stmt->execute($params);
$recordsFiltered = (int)$stmt->fetchColumn();

// 4) Buscar dados paginados
$sqlData = "
  SELECT id, nome, email, departamento, cargo, status, data_registo, ultimo_login, telefone, cidade
  FROM users
  {$where}
  ORDER BY {$orderColumn} {$orderDir}
  LIMIT :start, :length
";

$stmt = $pdo->prepare($sqlData);

// bind do search se existir
foreach ($params as $k => $v) {
  $stmt->bindValue($k, $v, PDO::PARAM_STR);
}

// bind do limit com ints (importante!)
$stmt->bindValue(":start", $start, PDO::PARAM_INT);
$stmt->bindValue(":length", $length, PDO::PARAM_INT);

$stmt->execute();
$rows = $stmt->fetchAll();

// 5) Formatar saída (pode transformar status em badge no front)
$response = [
  "draw" => $draw,
  "recordsTotal" => $recordsTotal,
  "recordsFiltered" => $recordsFiltered,
  "data" => $rows
];

echo json_encode($response);
