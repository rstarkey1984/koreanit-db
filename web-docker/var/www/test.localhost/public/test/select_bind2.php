<?php
require_once dirname(__DIR__) . "/lib/db.php";

$pdo = db();

$userId = 1;

$sql = "
  SELECT id, title
  FROM posts
  WHERE user_id = :user_id
  ORDER BY id DESC
  LIMIT 5
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <title>게시글 목록</title>
</head>
<body>

<h1>게시글 목록</h1>

<ul>
<?php foreach ($rows as $row): ?>
  <li>
    <?= htmlspecialchars($row['id']) ?> -
    <?= htmlspecialchars($row['title']) ?>
  </li>
<?php endforeach; ?>
</ul>

</body>
</html>
