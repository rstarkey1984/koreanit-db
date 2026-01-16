<?php
require_once dirname(__DIR__) . "/lib/db.php";

session_start();

// 로그인 체크 (댓글 작성은 로그인 필수)
if (!isset($_SESSION["user"])) {
  echo "로그인 후 이용 가능합니다.";
  exit;
}

$pdo = db();

// --------------------------------------------------
// 세션에서 로그인 사용자 ID 가져오기
// --------------------------------------------------
$userId = (int) $_SESSION["user"]["id"];

// --------------------------------------------------
// POST 데이터 수신
// --------------------------------------------------
$postId = $_POST["post_id"] ?? null;
$comment = $_POST["comment"] ?? "";

// --------------------------------------------------
// 입력값 검증
// --------------------------------------------------
if ($postId === null || !ctype_digit((string) $postId)) {
  echo "잘못된 접근입니다.";
  exit;
}

if ($comment === "") {
  echo "댓글 내용을 입력하세요.";
  exit;
}

// --------------------------------------------------
// SQL 준비 (INSERT + 2중 FK)
// --------------------------------------------------
$sql = "
  INSERT INTO comments (post_id, user_id, comment)
  VALUES (:post_id, :user_id, :comment)
";

$stmt = $pdo->prepare($sql);

// --------------------------------------------------
// 바인딩
// --------------------------------------------------
$stmt->bindValue(":post_id", (int) $postId, PDO::PARAM_INT);
$stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);
$stmt->bindValue(":comment", $comment, PDO::PARAM_STR);

// --------------------------------------------------
// 실행
// --------------------------------------------------
$stmt->execute();

// --------------------------------------------------
// 댓글 작성 후 원래 게시글 상세로 이동
// --------------------------------------------------
header("Location: /post_view.php?id=" . (int) $postId);
exit;