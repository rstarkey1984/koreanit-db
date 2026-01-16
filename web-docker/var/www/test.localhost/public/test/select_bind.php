<?php
require_once dirname(__DIR__) . "/lib/db.php";

$pdo = db();

// SQL 준비 (아직 실행 안 됨)
$sql = "
  SELECT id, title
  FROM posts
  WHERE user_id = :user_id
  AND id > :min_id
  ORDER BY id DESC
  LIMIT :limit
";
$stmt = $pdo->prepare($sql);

// 값 바인딩
$stmt->bindValue(':user_id', 3, PDO::PARAM_INT);
$stmt->bindValue(':min_id', 100, PDO::PARAM_INT);
$stmt->bindValue(':limit', 5, PDO::PARAM_INT);

// SQL 실행
$stmt->execute();

// 결과 가져오기
$row = $stmt->fetch();

echo '<pre>';
var_dump($row);
echo '</pre>';
?>