<?php
session_start();

require_once dirname(__DIR__) . "/lib/db.php";

// --------------------------------------------------
// 로그인 체크
// --------------------------------------------------
if (!isset($_SESSION["user"])) {
  echo "로그인 후 이용 가능합니다.";
  exit;
}

$pdo = db();

// --------------------------------------------------
// POST 파라미터 수신
// --------------------------------------------------
$commentId = $_POST["comment_id"] ?? null;
$postId = $_POST["post_id"] ?? null;

// --------------------------------------------------
// 파라미터 검증
// --------------------------------------------------
if (
  $commentId === null || !ctype_digit((string) $commentId) ||
  $postId === null || !ctype_digit((string) $postId)
) {
  echo "잘못된 요청입니다.";
  exit;
}

$commentId = (int) $commentId;
$postId = (int) $postId;
$userId = (int) $_SESSION["user"]["id"];

// --------------------------------------------------
// 댓글 삭제 (DELETE + WHERE + user_id)
// --------------------------------------------------
$sql = "
  DELETE FROM comments
  WHERE id = :comment_id
    AND user_id = :user_id
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(":comment_id", $commentId, PDO::PARAM_INT);
$stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);
$stmt->execute();

// --------------------------------------------------
// 삭제 결과 확인
// --------------------------------------------------
if ($stmt->rowCount() === 0) {
  echo "삭제할 수 없습니다.";
  exit;
}

// --------------------------------------------------
// 게시글 상세 페이지로 이동
// --------------------------------------------------
header("Location: /post_view.php?id=" . $postId);
exit;
