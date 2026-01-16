<?php
session_start();

require_once dirname(__DIR__) . "/lib/db.php";

// 로그인 체크
if (!isset($_SESSION["user"])) {
  echo "로그인 후 이용 가능합니다.";
  exit;
}

$pdo = db();

// POST 데이터 수신
$id = $_POST["id"] ?? null;
$title = $_POST["title"] ?? "";
$content = $_POST["content"] ?? "";

// 검증
if (
  $id === null || !ctype_digit((string) $id) ||
  $title === "" || $content === ""
) {
  echo "입력값이 올바르지 않습니다.";
  exit;
}

$postId = (int) $id;
$userId = (int) $_SESSION["user"]["id"];

// UPDATE 실행
$sql = "
  UPDATE posts
  SET title = :title,
      content = :content
  WHERE id = :id
    AND user_id = :user_id
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(":title", $title, PDO::PARAM_STR);
$stmt->bindValue(":content", $content, PDO::PARAM_STR);
$stmt->bindValue(":id", $postId, PDO::PARAM_INT);
$stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);
$stmt->execute();

// 수정 결과 확인
if ($stmt->rowCount() === 0) {
  echo "수정할 수 없습니다.";
  exit;
}

// 수정 완료 후 상세 페이지로 이동
header("Location: /post_view.php?id=" . $postId);
exit;
