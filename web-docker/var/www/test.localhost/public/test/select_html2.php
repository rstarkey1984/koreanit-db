<?php
require_once dirname(__DIR__) . "/lib/db.php";

$pdo = db();

/*
 |----------------------------------------
 | 실습 목표
 |----------------------------------------
 | 1) SQL을 prepare 한다
 | 2) 자리표시자(:user_id, :limit)를 사용한다
 | 3) bindValue로 값을 바인딩한다
 | 4) execute()로 실행한다
 | 5) fetchAll() 결과를 HTML로 출력한다
 |
 | 주의:
 | - $_GET / $_POST 사용 금지
 | - query() 사용 금지
 */

// =============================
// [1] SQL 작성
// =============================
// TODO: 조건에 맞게 SQL을 완성하시오
$sql = "
  SELECT id, user_id, title, created_at
  FROM posts
  WHERE user_id = :user_id
  ORDER BY id DESC
  LIMIT :limit
";

// =============================
// [2] SQL 준비
// =============================
$stmt = $pdo->prepare($sql);

// =============================
// [3] 값 바인딩
// =============================
// TODO: 아래 값들을 SQL에 바인딩하시오
$userId = 1;
$limit  = 5;

$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

// =============================
// [4] SQL 실행
// =============================
$stmt->execute();

// =============================
// [5] 결과 가져오기
// =============================
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>SQL 바인딩 실습</title>
  <style>
    body { font-family: system-ui, sans-serif; padding: 20px; }
    pre  { background: #f5f7fa; padding: 12px; border-radius: 6px; }
    table { border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #ccc; padding: 6px 10px; }
    th { background: #eee; }
    .box { margin-top: 20px; }
  </style>
</head>
<body>

<h1>SQL 바인딩 실습</h1>

<div class="box">
  <h3>실행된 SQL</h3>
  <pre><?= $sql ?></pre>
</div>

<div class="box">
  <h3>SQL 실행 결과</h3>

  <?php if (count($rows) === 0): ?>
    <p>결과 없음</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <?php foreach (array_keys($rows[0]) as $col): ?>
            <th><?= $col ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <?php foreach ($row as $value): ?>
              <td><?= $value ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</div>

<p>
  SQL을 수정하고 새로고침(F5)하면<br>
  → PHP가 다시 실행되고<br>
  → prepare / bind / execute가 다시 수행된다.
</p>

</body>
</html>
