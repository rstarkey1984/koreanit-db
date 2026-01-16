<?php
require_once dirname(__DIR__) . "/lib/bootstrap.php";

// DB 연결
require_once dirname(__DIR__) . "/lib/db.php";

$pdo = db();

// 페이지당 게시글 수
$pageSize = 20;

// 현재 페이지
$page = $_GET["page"] ?? 1;
$type = $_GET["type"] ?? "";
$keyword = trim($_GET["keyword"] ?? "");

if (!ctype_digit((string) $page) || $page < 1) {
  $page = 1;
}
$page = (int) $page;

// OFFSET 계산
$offset = ($page - 1) * $pageSize;

$whereSql = "";
$params = [];

if ($keyword !== "") {
  if ($type === "title") {
    $whereSql = "WHERE p.title LIKE :keyword";
    $params[":keyword"] = "%" . $keyword . "%";
  } elseif ($type === "writer") {
    $whereSql = "WHERE u.nickname LIKE :keyword";
    $params[":keyword"] = "%" . $keyword . "%";
  }
}

$q = [];
if ($keyword !== "" && ($type === "title" || $type === "writer")) {
  $q["type"] = $type;
  $q["keyword"] = $keyword;
}

$queryString = $q ? "&" . http_build_query($q) : "";

// --------------------------------------------------
// 게시글 목록 조회 SQL
// --------------------------------------------------
$sql = "
SELECT
  t.id,
  t.title,
  t.view_count,
  t.created_at,
  t.nickname,
  COUNT(c.id) AS comment_cnt
FROM (
  SELECT
    p.id,
    p.title,
    p.view_count,
    p.created_at,
    u.nickname
  FROM posts p
  JOIN users u ON u.id = p.user_id
  " . $whereSql . "
  ORDER BY p.id DESC
  LIMIT :limit OFFSET :offset
) AS t
LEFT JOIN comments c ON t.id = c.post_id
GROUP BY
  t.id, t.title, t.view_count, t.created_at, t.nickname
ORDER BY t.id DESC
";

$stmt = $pdo->prepare($sql);

$stmt->bindValue(":limit", $pageSize, PDO::PARAM_INT);
$stmt->bindValue(":offset", $offset, PDO::PARAM_INT);

if ($whereSql !== "") {
  $stmt->bindValue(":keyword", $params[":keyword"], PDO::PARAM_STR);
}

$stmt->execute();
$posts = $stmt->fetchAll();

// 전체 게시글 수
$countSql = "
SELECT COUNT(*)
FROM posts p
JOIN users u ON u.id = p.user_id
" . $whereSql;

$countStmt = $pdo->prepare($countSql);

if ($whereSql !== "") {
  $countStmt->bindValue(":keyword", $params[":keyword"], PDO::PARAM_STR);
}

$countStmt->execute();
$totalCount = (int) $countStmt->fetchColumn();

// 총 페이지 수 계산
$totalPages = (int) ceil($totalCount / $pageSize);
if ($totalPages < 1) {
  $totalPages = 1;
}

$window = 5;

// 기본 범위
$startPage = $page - $window;
$endPage = $page + $window;

// 앞쪽이 잘렸으면 → 잘린 만큼 뒤로 보정
if ($startPage < 1) {
  $endPage += (1 - $startPage);
  $startPage = 1;
}

// 뒤쪽이 잘렸으면 → 잘린 만큼 앞으로 보정
if ($endPage > $totalPages) {
  $startPage -= ($endPage - $totalPages);
  $endPage = $totalPages;
}

// 다시 한번 하한 보정
if ($startPage < 1) {
  $startPage = 1;
}

$searchInfo = "전체 게시글 목록";

if ($keyword !== "" && ($type === "title" || $type === "writer")) {
  if ($type === "title") {
    $searchInfo = "제목에 '" . $keyword . "' 가 포함된 게시글 검색 결과";
  } elseif ($type === "writer") {
    $searchInfo = "작성자에 '" . $keyword . "' 가 포함된 게시글 검색 결과";
  }
}

// 페이지 제목
$pageTitle = "게시글 목록";

// 공통 레이아웃 헤더
require_once dirname(__DIR__) . "/lib/header.php";

?>
<form method="get" class="d-flex mb-3">
  <select name="type" class="form-select w-auto me-2">
    <option value="title" <?= ($type === "title") ? "selected" : "" ?>>제목</option>
    <option value="writer" <?= ($type === "writer") ? "selected" : "" ?>>작성자</option>
  </select>

  <input type="text" name="keyword" class="form-control me-2" placeholder="검색어 입력"
    value="<?= htmlspecialchars($keyword) ?>">

  <button class="btn btn-primary text-nowrap">검색</button>
</form>
<p class="text-muted mb-2">
  <?= htmlspecialchars($searchInfo) ?>
</p>
<!-- 게시글 목록 테이블 -->
<table class="table table-striped table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th>ID</th>
      <th>제목</th>
      <th>작성자</th>
      <th>조회수</th>
      <th>댓글수</th>
      <th>작성일</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($posts as $post): ?>
      <tr>
        <td><?php echo htmlspecialchars($post["id"]); ?></td>
        <td>
          <a href="/post_view.php?id=<?php echo htmlspecialchars($post["id"]); ?>" class="text-decoration-none">
            <?php echo htmlspecialchars($post["title"]); ?>
          </a>
        </td>
        <td><?php echo htmlspecialchars($post["nickname"]); ?></td>
        <td><?php echo htmlspecialchars($post["view_count"]); ?></td>
        <td>
          <span class="badge bg-secondary">
            <?php echo htmlspecialchars($post["comment_cnt"]); ?>
          </span>
        </td>
        <td><?php echo htmlspecialchars($post["created_at"]); ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>




<nav aria-label="Page navigation">
  <ul class="pagination justify-content-center">

    <!-- 처음 -->
    <li class="page-item <?= ($page <= 1) ? "disabled" : "" ?>">
      <a class="page-link" href="?page=1<?= $queryString ?>">처음</a>
    </li>

    <!-- 이전 -->
    <li class="page-item <?= ($page <= 1) ? "disabled" : "" ?>">
      <a class="page-link" href="?page=<?= $page - 1 ?><?= $queryString ?>">이전</a>
    </li>

    <!-- 페이지 번호 -->
    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
      <li class="page-item <?= ($i === $page) ? "active" : "" ?>">
        <a class="page-link" href="?page=<?= $i ?><?= $queryString ?>">
          <?= $i ?>
        </a>
      </li>
    <?php endfor; ?>

    <!-- 다음 -->
    <li class="page-item <?= ($page >= $totalPages) ? "disabled" : "" ?>">
      <a class="page-link" href="?page=<?= $page + 1 ?><?= $queryString ?>">다음</a>
    </li>

    <!-- 마지막 -->
    <li class="page-item <?= ($page >= $totalPages) ? "disabled" : "" ?>">
      <a class="page-link" href="?page=<?= $totalPages ?><?= $queryString ?>">마지막</a>
    </li>

  </ul>
</nav>


<?php
require_once dirname(__DIR__) . "/lib/footer.php";
