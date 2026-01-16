<?php
// DB 접속을 담당하는 함수
// 호출할 때마다 PDO 객체를 반환
function db(): PDO
{
  // 설정 파일을 불러와서 반환값(배열)을 $config에 저장
  // “값을 받는 설정 파일”이므로 require 사용
  $config = require dirname(__DIR__) . '/config/database.php';

  // PDO에서 사용하는 DSN 문자열 생성
  // host, port, dbname, charset 정보를 문자열로 조합
  $dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $config['host'],
    $config['port'],
    $config['dbname'],
    $config['charset']
  );

  // PDO 객체 생성 및 반환
  return new PDO(
    $dsn,
    $config['user'],   // DB 사용자명
    $config['pass'],   // DB 비밀번호
    [
      // SQL 오류 발생 시 Exception으로 처리
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

      // fetch() 결과를 연관 배열 형태로 반환
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

      // 값 바인딩을 PHP가 SQL을 흉내내지 않고 DB가 직접 처리하도록 설정
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );
}
