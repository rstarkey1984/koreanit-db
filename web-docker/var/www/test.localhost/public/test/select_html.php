<?php
// ================================
// DB 접속 함수 포함
// ================================
// 이 파일은 DB 연결만 담당하는 db() 함수를 제공
require_once dirname(__DIR__) . "/lib/db.php";

// ================================
// DB 접속
// ================================
// db() 호출 시점에 실제 DB 연결 발생
$pdo = db();

/*
 |----------------------------------------
 | 여기 SQL만 바꿔가면서 테스트
 |----------------------------------------
 | 페이지 새로고침(F5)
 | → 이 PHP 파일이 다시 실행됨
 | → SQL도 다시 실행됨
 */

// 예제 1: 단일 행 조회
// $sql = "SELECT id, username FROM users WHERE id = 1";

// 예제 2: 여러 행 조회
// $sql = "SELECT id, title FROM posts ORDER BY id DESC LIMIT 5";

// 예제 3: 집계 결과
// $sql = "SELECT COUNT(*) AS cnt FROM posts";

// 기본값 (처음 실행 확인용)
$sql = "SELECT 1 AS result";

// ================================
// SQL 실행
// ================================
// query(): SQL을 즉시 실행하는 메서드
$stmt = $pdo->query($sql);

// ================================
// 결과 전체 가져오기
// ================================
// fetchAll(): 결과를
// [ [컬럼=>값], [컬럼=>값], ... ] 형태로 반환
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="ko">
<head>
  <!-- 문서 인코딩 설정 (한글 깨짐 방지) -->
  <meta charset="utf-8" />

  <!-- 브라우저 탭에 표시될 제목 -->
  <title>SQL 실행 테스트</title>

  <!--
    화면을 보기 좋게 하기 위한 최소한의 스타일
    (CSS 설명이 목적이 아니라 결과 확인이 목적)
  -->
  <style>
    body {
      font-family: system-ui, sans-serif;
      padding: 20px;
    }

    /* SQL 문자열, 디버깅 출력용 */
    pre {
      background: #f5f7fa;
      padding: 12px;
      border-radius: 6px;
    }

    /* 결과 테이블 기본 스타일 */
    table {
      border-collapse: collapse;
      margin-top: 10px;
    }

    th, td {
      border: 1px solid #ccc;
      padding: 6px 10px;
    }

    th {
      background: #eee;
    }

    /* 영역 구분용 */
    .box {
      margin-top: 20px;
    }
  </style>
</head>
<body>

<!-- 페이지 제목 -->
<h1>SQL 실행 테스트</h1>

<!-- ============================= -->
<!-- 실행된 SQL 출력 영역 -->
<!-- ============================= -->
<div class="box">
  <h3>실행된 SQL</h3>

  <!--
    htmlspecialchars():
    - <, >, ", ' 같은 HTML 특수문자를
      문자 그대로 출력하도록 변환
    - 브라우저가 태그나 스크립트로
      해석하지 못하게 막음
    - XSS (스크립트 삽입 공격) 방지
    - 화면 출력 직전에만 사용
  -->
  <pre><?= htmlspecialchars($sql) ?></pre>
</div>

<!-- ============================= -->
<!-- SQL 실행 결과 출력 영역 -->
<!-- ============================= -->
<div class="box">
  <h3>SQL 실행 결과</h3>

  <?php if (count($rows) === 0): ?>
    <!-- 조회 결과가 하나도 없을 경우 -->
    <p>결과 없음</p>

  <?php else: ?>
    <!-- 조회 결과가 있을 경우 테이블로 출력 -->
    <table>
      <thead>
        <tr>
          <?php
          /*
            첫 번째 행($rows[0])의 key 목록을 이용해
            테이블 헤더(컬럼명) 자동 생성
            예: id, title, username ...
          */
          foreach (array_keys($rows[0]) as $col):
          ?>
            <!-- 컬럼명도 HTML로 해석되지 않도록 처리 -->
            <th><?= htmlspecialchars($col) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>

      <tbody>
        <?php
        /*
          $rows는 "목록"
          foreach 한 번 = 한 행(row)
        */
        foreach ($rows as $row):
        ?>
          <tr>
            <?php
            /*
              $row는 "한 행"
              각 컬럼의 값을 하나씩 출력
            */
            foreach ($row as $value):
            ?>
              <!--
                DB 값은 신뢰할 수 없으므로
                반드시 htmlspecialchars()로 출력
              -->
              <td><?= htmlspecialchars($value) ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</div>

<!-- ============================= -->
<!-- 학습용 핵심 메시지 -->
<!-- ============================= -->
<p>
  SQL을 수정하고 새로고침(F5)하면<br>
  → PHP 파일이 다시 실행되고<br>
  → 그 안의 SQL도 다시 실행된다.
</p>

</body>
</html>
