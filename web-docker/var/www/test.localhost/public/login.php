<!doctype html>
<html lang="ko">

<head>
  <meta charset="utf-8" />
  <title>로그인</title>
</head>

<body>

  <h1>로그인</h1>

  <form method="post" action="/login_action.php">
    <p>
      아이디 :
      <input type="text" name="username" required>
    </p>

    <p>
      비밀번호 :
      <input type="password" name="password" required>
    </p>

    <p>
      <button type="submit">로그인</button>
    </p>
  </form>

  <p>
    <a href="/">메인페이지로</a> |
    <a href="/register.php">회원가입</a>
  </p>

</body>

</html>