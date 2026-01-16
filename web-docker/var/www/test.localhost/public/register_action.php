<?php
require_once dirname(__DIR__) . "/lib/db.php";

$pdo = db();

// --------------------------------------------------
// POST 데이터 수신
// --------------------------------------------------
$username = $_POST["username"] ?? "";
$password = $_POST["password"] ?? "";
$nickname = $_POST["nickname"] ?? "";
$email = $_POST["email"] ?? null;   // email은 선택값

// --------------------------------------------------
// 필수값 검증 (email 제외)
// --------------------------------------------------
if ($username === "" || $nickname === "" || $password === "") {
  echo "필수 입력값이 누락되었습니다.";
  exit;
}

// --------------------------------------------------
// 비밀번호 해시 처리
// --------------------------------------------------
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// --------------------------------------------------
// SQL 준비
// --------------------------------------------------
$sql = "
  INSERT INTO users (username, email, nickname, password)
  VALUES (:username, :email, :nickname, :password)
";

$stmt = $pdo->prepare($sql);

// --------------------------------------------------
// 바인딩
// --------------------------------------------------
$stmt->bindValue(":username", $username, PDO::PARAM_STR);
$stmt->bindValue(":email", $email, PDO::PARAM_STR);
$stmt->bindValue(":nickname", $nickname, PDO::PARAM_STR);
$stmt->bindValue(":password", $hashedPassword, PDO::PARAM_STR);

// --------------------------------------------------
// 실행
// --------------------------------------------------
try {
  // 여기서 쿼리 실행
  $stmt->execute();

} catch (PDOException $e) {

  // MySQL 중복 키 에러 코드: 1062
  if ($e->errorInfo[1] === 1062) {
    echo "이미 사용 중인 아이디입니다.";
    exit;
  }

  // 그 외 DB 에러는 그대로 출력 (개발 단계)
  echo "DB 오류가 발생했습니다.";
  exit;
}

header("Location: /login.php");
exit;
