<!doctype html>
<html lang="ko">

<head>
  <meta charset="utf-8" />
  <title>회원가입</title>
</head>

<body>

  <h1>회원가입</h1>

  <form method="post" action="/register_action.php">
    <p>
      아이디 :
      <input type="text" name="username" required>
    </p>

    <p>
      비밀번호 :
      <input type="password" name="password" required>
    </p>

    <p>
      닉네임 :
      <input type="text" name="nickname" required>
    </p>

    <p>
      이메일 :
      <input type="email" name="email">
    </p>

    <p>
      <button type="submit">회원가입</button>
    </p>
  </form>

  <p>
    <a href="/">메인페이지로 돌아가기</a>
  </p>

</body>

</html>