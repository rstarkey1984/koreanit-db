<?php
// DB 접속 함수 포함
require_once dirname(__DIR__) . "/lib/db.php";

// DB 접속
$pdo = db();

/*
 |----------------------------------------
 | 여기 SQL만 바꿔가면서 테스트
 |----------------------------------------
 */

// 예제 1: 단일 행
// $sql = "SELECT id, username FROM users WHERE id = 1";

// 예제 2: 여러 행
$sql = "SELECT id, title FROM posts ORDER BY id DESC LIMIT 5";

// 예제 3: 집계
// $sql = "SELECT COUNT(*) AS cnt FROM posts";

// 기본값 (처음 실행용)
//$sql = "SELECT 1 AS result";

// SQL 실행
$stmt = $pdo->query($sql);

// 결과 전체 가져오기
$rows = $stmt->fetchAll();
echo '<pre>';
var_dump($rows);
echo '</pre>';

?>