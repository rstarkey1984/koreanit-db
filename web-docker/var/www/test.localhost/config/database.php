<?php
// DB 접속 설정을 배열 형태로 반환
// 이 파일은 "실행"이 목적이 아니라
// 다른 파일에서 값을 가져다 쓰는 용도
return [
  // Docker Compose / .env 에서 전달된 환경 변수 값 읽기
  'host'    => getenv('DB_HOST'),
  'port'    => getenv('DB_PORT'),
  'dbname'  => getenv('DB_NAME'),
  'user'    => getenv('DB_USER'),
  'pass'    => getenv('DB_PASS'),
  'charset' => getenv('DB_CHARSET'),
];
?>