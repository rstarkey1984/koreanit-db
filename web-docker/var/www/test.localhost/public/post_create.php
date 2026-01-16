<?php
session_start();

// 로그인하지 않은 경우 접근 차단
if (!isset($_SESSION["user"])) {
  echo "로그인 후 이용 가능합니다.<br>";
  echo "<a href='/login.php'>로그인 페이지로 이동</a>";
  exit;
}
?>
<!doctype html>
<html lang="ko">

<head>
  <meta charset="utf-8" />
  <title>게시글 작성</title>
</head>

<body>

  <h1>게시글 작성</h1>

  <form method="post" action="/post_create_action.php">
    <p>
      제목 :
      <input type="text" name="title" required>
    </p>

    <p>
      내용 :<br>
      <textarea name="content" rows="8" cols="60" required></textarea>
    </p>

    <p>
      <button type="submit">등록</button>
    </p>
  </form>

  <p>
    <a href="/">메인페이지로</a>
  </p>

</body>

</html>