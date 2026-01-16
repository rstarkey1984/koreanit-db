<?php
session_start();

require_once dirname(__DIR__) . "/lib/db.php";

// 로그인 체크
if (!isset($_SESSION["user"])) {
  echo "로그인 후 이용 가능합니다.";
  exit;
}

$pdo = db();

// GET 파라미터
$id = $_GET["id"] ?? null;

// 파라미터 검증
if ($id === null || !ctype_digit((string) $id)) {
  echo "잘못된 접근입니다.";
  exit;
}

$postId = (int) $id;
$userId = (int) $_SESSION["user"]["id"];

// 게시글 조회 (본인 글만)
$sql = "
  SELECT id, title, content
  FROM posts
  WHERE id = :id
    AND user_id = :user_id
  LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(":id", $postId, PDO::PARAM_INT);
$stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);
$stmt->execute();

$post = $stmt->fetch();

if (!$post) {
  echo "수정할 수 없는 게시글입니다.";
  exit;
}
?>
<!doctype html>
<html lang="ko">

<head>
  <meta charset="utf-8">
  <title>게시글 수정</title>
</head>

<body>

  <h1>게시글 수정</h1>

  <form method="post" action="/post_edit_action.php">
    <input type="hidden" name="id" value="<?= htmlspecialchars((string) $post["id"]) ?>">

    <p>
      제목:
      <input type="text" name="title" value="<?= htmlspecialchars($post["title"]) ?>" required>
    </p>

    <p>
      내용:<br>
      <textarea name="content" rows="8" cols="60" required><?= htmlspecialchars($post["content"]) ?></textarea>
    </p>

    <p>
      <button type="submit">수정 완료</button>
    </p>
  </form>

  <p>
    <a href="/post_view.php?id=<?= htmlspecialchars((string) $post["id"]) ?>">취소</a>
  </p>

</body>

</html>