<?php
require_once dirname(__DIR__) . "/lib/bootstrap.php";

// DB 연결
require_once dirname(__DIR__) . "/lib/db.php";
$pdo = db();

// --------------------------------------------------
// GET 파라미터 수신
// --------------------------------------------------
$id = $_GET["id"] ?? null;

// --------------------------------------------------
// 파라미터 검증
// --------------------------------------------------
if ($id === null || !ctype_digit((string) $id)) {
  echo "잘못된 접근입니다.";
  exit;
}
$postId = (int) $id;

// --------------------------------------------------
// 게시글 상세 조회 (SELECT + WHERE + JOIN)
// --------------------------------------------------
$sql = "
  SELECT
    p.id,
    p.user_id,
    p.title,
    p.content,
    p.view_count,
    p.created_at,
    u.nickname
  FROM posts p
  JOIN users u ON u.id = p.user_id
  WHERE p.id = :id
  LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(":id", $postId, PDO::PARAM_INT);
$stmt->execute();

$post = $stmt->fetch();

if (!$post) {
  echo "게시글이 존재하지 않습니다.";
  exit;
}

$pageTitle = (string) $post["title"];

// --------------------------------------------------
// 조회수 증가 (UPDATE + WHERE) - 쿠키로 중복 방지
// --------------------------------------------------
// $cookieName = "viewed_post_" . (string) $postId;
// $cookieTtlSeconds = 60 * 10; // 10분

// if (!isset($_COOKIE[$cookieName])) {
//   $updateSql = "
//     UPDATE posts
//     SET view_count = view_count + 1
//     WHERE id = :id
//   ";
//   $updateStmt = $pdo->prepare($updateSql);
//   $updateStmt->bindValue(":id", $postId, PDO::PARAM_INT);
//   $updateStmt->execute();

//   setcookie($cookieName, "1", time() + $cookieTtlSeconds, "/");

//   // 화면 표시용 조회수도 즉시 +1 반영 (새로고침 없이도 숫자 일치)
//   $post["view_count"] = (int) $post["view_count"] + 1;
// }

// --------------------------------------------------
// 조회수 증가 - 프로시저 사용
// --------------------------------------------------
$viewer_key = '';
if (isset($_SESSION['user']['id'])) {
  $viewer_key = (string) $_SESSION['user']['id'];
} elseif (isset($_COOKIE['PHPSESSID'])) {
  $viewer_key = $_COOKIE['PHPSESSID'];
}

$increase_post_view_with_interval_sql = "call increase_post_view_with_interval(:p_post_id, :p_viewer_key, 1)";
$updateStmt = $pdo->prepare($increase_post_view_with_interval_sql);
$updateStmt->bindValue(":p_post_id", $postId, PDO::PARAM_INT);
$updateStmt->bindValue(":p_viewer_key", $viewer_key, PDO::PARAM_STR);
$updateStmt->execute();

// --------------------------------------------------
// 댓글 목록 조회 (SELECT + WHERE + JOIN)
// --------------------------------------------------
$commentSql = "
  SELECT
    c.id,
    c.user_id,
    c.comment,
    c.created_at,
    u.nickname
  FROM comments c
  JOIN users u ON u.id = c.user_id
  WHERE c.post_id = :post_id
  ORDER BY c.id DESC
";

$commentStmt = $pdo->prepare($commentSql);
$commentStmt->bindValue(":post_id", $postId, PDO::PARAM_INT);
$commentStmt->execute();
$comments = $commentStmt->fetchAll();

// --------------------------------------------------
// 댓글 개수 조회 (COUNT)
// --------------------------------------------------
$countSql = "
  SELECT COUNT(*) AS comment_count
  FROM comments
  WHERE post_id = :post_id
";

$countStmt = $pdo->prepare($countSql);
$countStmt->bindValue(":post_id", $postId, PDO::PARAM_INT);
$countStmt->execute();

$commentCount = (int) $countStmt->fetchColumn();

// --------------------------------------------------
// 권한 체크 (본인 글 여부)
// --------------------------------------------------
$isOwner = false;
$loginUserId = null;

if (isset($_SESSION["user"])) {
  $loginUserId = (int) $_SESSION["user"]["id"];
  $postUserId = (int) $post["user_id"];
  $isOwner = ($loginUserId === $postUserId);
}


require_once dirname(__DIR__) . "/lib/header.php";

?>

<!-- 게시글 카드 -->
<div class="card mb-4">
  <div class="card-body">

    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <h2 class="h4 mb-2"><?php echo htmlspecialchars((string) $post["title"]); ?></h2>
        <div class="text-muted small">
          작성자: <?php echo htmlspecialchars((string) $post["nickname"]); ?>
          <span class="mx-2">|</span>
          조회수: <?php echo htmlspecialchars((string) $post["view_count"]); ?>
          <span class="mx-2">|</span>
          작성일: <?php echo htmlspecialchars((string) $post["created_at"]); ?>
        </div>
      </div>

      <?php if ($isOwner): ?>
        <div class="d-flex align-items-center gap-2">
          <a href="/post_edit.php?id=<?php echo htmlspecialchars((string) $post["id"]); ?>"
            class="btn btn-outline-primary btn-sm">
            수정
          </a>

          <form method="post" action="/post_delete_action.php" onsubmit="return confirm('정말 삭제할까요?');" class="m-0">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $post["id"]); ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm">삭제</button>
          </form>
        </div>
      <?php endif; ?>
    </div>

    <hr>

    <div class="lh-lg">
      <?php echo nl2br(htmlspecialchars((string) $post["content"])); ?>
    </div>

    <hr>

    <div class="d-flex justify-content-end">
      <a href="/" class="btn btn-secondary btn-sm">목록으로</a>
    </div>
  </div>
</div>

<!-- 댓글 작성 -->
<div class="card mb-4">
  <div class="card-body">
    <h3 class="h6 mb-3">댓글 작성</h3>

    <?php if (isset($_SESSION["user"])): ?>
      <form method="post" action="/comment_create_action.php">
        <input type="hidden" name="post_id" value="<?php echo htmlspecialchars((string) $post["id"]); ?>">

        <div class="mb-3">
          <textarea name="comment" rows="4" class="form-control" required></textarea>
        </div>

        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary btn-sm">댓글 등록</button>
        </div>
      </form>
    <?php else: ?>
      <div class="alert alert-secondary mb-0">
        댓글 작성은 로그인 후 이용 가능합니다.
        <a href="/login.php" class="alert-link">로그인</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- 댓글 목록 -->
<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="h6 mb-0">댓글 목록</h3>
      <span class="badge bg-secondary">
        <?php echo htmlspecialchars((string) $commentCount); ?>
      </span>
    </div>

    <?php if (count($comments) === 0): ?>
      <p class="text-muted mb-0">아직 댓글이 없습니다.</p>
    <?php else: ?>
      <div class="list-group">
        <?php foreach ($comments as $c): ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
              <div>
                <div class="fw-semibold">
                  <?php echo htmlspecialchars((string) $c["nickname"]); ?>
                  <span class="text-muted small ms-2">
                    <?php echo htmlspecialchars((string) $c["created_at"]); ?>
                  </span>
                </div>

                <div class="mt-2">
                  <?php echo nl2br(htmlspecialchars((string) $c["comment"])); ?>
                </div>
              </div>

              <?php if ($loginUserId !== null && $loginUserId === (int) $c["user_id"]): ?>
                <form method="post" action="/comment_delete_action.php" onsubmit="return confirm('댓글을 삭제할까요?');" class="m-0">
                  <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars((string) $c["id"]); ?>">
                  <input type="hidden" name="post_id" value="<?php echo htmlspecialchars((string) $postId); ?>">
                  <button type="submit" class="btn btn-outline-danger btn-sm">삭제</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
require_once dirname(__DIR__) . "/lib/footer.php";
