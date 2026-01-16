<!doctype html>
<html lang="ko">

<head>
  <meta charset="utf-8" />
  <title><?php echo htmlspecialchars($pageTitle); ?></title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
  <div class="container py-4">

    <!-- 상단 제목 + 메뉴 -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="h3 mb-0"><?php echo htmlspecialchars($pageTitle); ?></h1>

      <div>
        <?php if (isset($_SESSION["user"])): ?>
          <span class="me-2">
            <?php echo htmlspecialchars($_SESSION["user"]["nickname"]); ?> 님
          </span>
          <a href="/post_create.php" class="btn btn-primary btn-sm">글쓰기</a>
          <a href="/logout.php" class="btn btn-outline-secondary btn-sm">로그아웃</a>
        <?php else: ?>
          <a href="/login.php" class="btn btn-outline-primary btn-sm">로그인</a>
          <a href="/register.php" class="btn btn-outline-secondary btn-sm">회원가입</a>
          <a href="/post_create.php" class="btn btn-primary btn-sm">글쓰기</a>
        <?php endif; ?>
      </div>
    </div>