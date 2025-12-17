# SQL 고급 (Advanced SQL)

## 📘 학습 개요

이 장에서는 실무에서 자주 마주치는 **조금 더 복잡한 SQL 개념들** 을 “완벽하게 외우는 것”이 아니라 **왜 필요한지 이해하는 것** 을 목표로 한다.

## 💡 주요 내용

- JOIN 심화

- 서브쿼리(Subquery)

- 트랜잭션 및 동시성

- SQL 최적화 기초


--- 

# 0. 시드 데이터 준비


## 0-1. 테이블 생성 ( 선택 )

```sql
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================
-- users
-- =========================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '사용자 PK',
    username VARCHAR(50) NOT NULL UNIQUE COMMENT '로그인 아이디',
    email VARCHAR(100) UNIQUE COMMENT '이메일 (선택)',
    password VARCHAR(255) NOT NULL COMMENT '비밀번호 해시',
    nickname VARCHAR(50) NOT NULL COMMENT '닉네임',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '가입일',
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일'
);

-- =========================
-- posts
-- =========================
CREATE TABLE posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '게시글 PK',
    user_id INT UNSIGNED NOT NULL COMMENT '작성자 ID',
    title VARCHAR(200) NOT NULL COMMENT '제목',
    content TEXT NOT NULL COMMENT '내용',
    view_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '조회수',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '작성일',
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일',

    INDEX idx_posts_user_id (user_id),
    INDEX idx_posts_created_at (created_at),

    CONSTRAINT fk_posts_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
);

-- =========================
-- comments
-- =========================
CREATE TABLE comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '댓글 PK',
    post_id INT UNSIGNED NOT NULL COMMENT '게시글 ID',
    user_id INT UNSIGNED NOT NULL COMMENT '댓글 작성자 ID',
    comment VARCHAR(500) NOT NULL COMMENT '댓글 내용',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '작성일',
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일',

    INDEX idx_comments_post_id (post_id),
    INDEX idx_comments_user_id (user_id),

    CONSTRAINT fk_comments_post
        FOREIGN KEY (post_id)
        REFERENCES posts(id),

    CONSTRAINT fk_comments_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
);
```

## 0-2. 데이터 생성
```sql
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE comments;
TRUNCATE posts;
TRUNCATE users;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================
-- seed data
-- =========================
SET SESSION cte_max_recursion_depth = 1000000;

-- ===== users (100)
INSERT INTO users (username, email, password, nickname)
SELECT
  CONCAT('user', n),
  CONCAT('user', n, '@test.com'),
  '$2a$10$abcdefghijklmnopqrstuvwxyz1234567890abcdef', -- 더미 bcrypt 해시
  CONCAT('user_', n)
FROM (
  WITH RECURSIVE seq(n) AS (
    SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n < 100
  ) SELECT n FROM seq
) t;

-- ===== posts (1000k)
INSERT INTO posts (user_id, title, content, view_count, created_at)
SELECT
  FLOOR(1 + RAND() * 100),
  CONCAT('게시글 제목 ', n),
  CONCAT('게시글 내용 ', n),
  FLOOR(RAND() * 50000),
  NOW() - INTERVAL FLOOR(RAND() * 365) DAY
FROM (
  WITH RECURSIVE seq(n) AS (
    SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n < 1000000
  ) SELECT n FROM seq
) t;

-- ===== comments (500k)
INSERT INTO comments (post_id, user_id, comment, created_at)
SELECT
  FLOOR(1 + RAND() * 1000000),
  FLOOR(1 + RAND() * 100),
  CONCAT('댓글 내용 ', n),
  NOW() - INTERVAL FLOOR(RAND() * 365) DAY
FROM (
  WITH RECURSIVE seq(n) AS (
    SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n < 500000
  ) SELECT n FROM seq
) t;
```

---

# 1. JOIN 심화

> JOIN은 여러 테이블에 나뉘어 저장된 데이터를 하나의 결과로 결합하는 방법이다. 정규화된 데이터베이스에서는 JOIN은 필수이며, 실무 SQL의 대부분은 JOIN을 포함한다.

## 1-1. INNER JOIN vs LEFT JOIN 차이

### INNER JOIN
- 양쪽 테이블에 모두 존재하는 데이터만 조회

- FK가 보장된 관계에서 기본적으로 가장 많이 사용
```sql
SELECT
  p.id,
  p.title,
  u.username
FROM posts p
JOIN users u
  ON p.user_id = u.id;
```

### LEFT JOIN

- 왼쪽 테이블 기준

- 오른쪽 테이블에 데이터가 없어도 결과에 포함됨

```sql
SELECT
  p.id,
  p.title,
  c.id AS comment_id
FROM posts p
LEFT JOIN comments c
  ON p.id = c.post_id;
```

## 1-2. 다중 JOIN (users + posts + comments)

```sql
SELECT
  p.id,
  p.title,
  u.username,
  COUNT(c.id) AS comment_count
FROM posts p
JOIN users u
  ON u.id = p.user_id
LEFT JOIN comments c
  ON c.post_id = p.id
GROUP BY
  p.id, p.title, u.username
ORDER BY p.id DESC
LIMIT 10;
```

---

# 2. 서브쿼리 (Subquery)
> 서브쿼리는 쿼리 안에서 실행되는 또 다른 쿼리다. “단계적으로 생각해야 하는 문제”에서 사용된다.

## 2-1. WHERE 절 서브쿼리
평균 조회수보다 조회수가 높은 게시글
```sql
SELECT id, title, view_count
FROM posts
WHERE view_count > (
  SELECT AVG(view_count)
  FROM posts
);
```

## 2-2. FROM 절 서브쿼리

최신 게시글 10개 + 작성자 + 댓글수
```sql
SELECT
  p.id,
  p.title,
  u.nickname,
  count(c.id) AS comment_cnt
FROM (
  SELECT id, user_id, title
  FROM posts
  ORDER BY id desc
  LIMIT 100
) AS p
JOIN users u ON p.user_id = u.id
LEFT JOIN comments AS c ON p.id = c.post_id
GROUP BY p.id
```

### 위 쿼리에서 p.title 이랑 u.nickname 을 조회할수 있는 이유
GROUP BY 컬럼에 직접 묶이지 않아도 p.id → user_id → nickname 으로 간접적으로 하나로 결정돼서 가능함.

쉽게 말해서 GROUP BY 로 묶였을 때 해당 컬럼 값이 애매해지지 않고 구조적으로 하나로 결정될 수 있으면 허용된다.


| 컬럼         | 왜 OK인가                   |
| ---------- | ------------------------ |
| p.title    | 같은 p.id → 같은 title       |
| u.nickname | p.id → p.user_id → u.nickname |

---


# 3. SQL 최적화 기초

## 3-1. EXPLAIN 활용 
> EXPLAIN 은 MySQL 옵티마이저의 ‘결정 결과 보고서’  
> 옵티마이저(Optimizer)란? SQL을 실행하기 전에 “이 쿼리를 가장 싸게(빠르게) 실행하는 방법”을 결정하는 MySQL 내부의 판단 로직(엔진) 이다.
```sql
EXPLAIN
SELECT *
FROM posts
WHERE user_id = 10
ORDER BY created_at DESC;
```

| 컬럼                | 값                   | 의미               | 이 결과에서의 해석                                  |
| ----------------- | ------------------- | ---------------- | ------------------------------------------- |
| **id**            | 1                   | 실행 계획 내 쿼리 블록 번호 | 단일 SELECT 쿼리라서 하나의 실행 블록만 존재                |
| **select_type**   | SIMPLE              | 쿼리 유형            | 서브쿼리/UNION 없는 단순 SELECT                     |
| **table**         | posts               | 접근 대상 테이블        | 이 단계에서 `posts` 테이블을 직접 조회                   |
| **partitions**    | NULL                | 사용된 파티션          | 파티션 테이블이 아님                                 |
| **type**          | ref                 | 테이블 접근 방식        | 인덱스를 사용해 조건에 맞는 여러 행을 조회 (풀 스캔 아님)          |
| **possible_keys** | user_id_created_at  | 사용 가능 후보 인덱스     | 옵티마이저가 고려한 인덱스가 정확히 존재                      |
| **key**           | user_id_created_at  | 실제 사용 인덱스        | `(user_id, created_at)` 복합 인덱스를 실제로 사용      |
| **key_len**       | 4                   | 사용한 인덱스 컬럼 길이    | user_id(INT, 4byte)까지만 사용됨                  |
| **ref**           | const               | 인덱스 비교 값         | `WHERE user_id = 상수값` 조건으로 조회               |
| **rows**          | 18,132              | 예상 읽기 행 수        | 해당 user_id에 대해 약 18,132건의 게시글이 있을 것으로 예상    |
| **filtered**      | 100.00              | 조건 통과 비율(%)      | 인덱스로 이미 필터링 완료 → 추가 조건 없음                   |
| **Extra**         | Backward index scan | 추가 실행 정보         | `ORDER BY created_at DESC`를 인덱스를 역순으로 읽어 처리 |


### type

- ALL → 풀 스캔 (가장 비용 큼)

- ref, eq_ref → 인덱스 기반 접근

### rows

- 쿼리 실행 단계에서 실제 읽은 행 수가 아니라, 실행 전에 계산된 예상치다

- 숫자가 클수록 처리할 데이터가 많을 가능성이 크므로 보통 느려진다

- 단, LIMIT / 인덱스 / 실행 방식에 따라 체감 속도는 달라질 수 있다

### Extra

- Using temporary ( 쿼리 실행 중 중간 결과를 임시 테이블에 저장했다는 뜻. 즉 성능저하 )

- Using filesort ( ORDER BY를 인덱스로 처리하지 못하고, 별도의 정렬 단계가 필요했다는 뜻. 즉 성능저하 )

- 위에 두개가 동시에 나오면 매우 느려질 수 있는 패턴



## 3-2. 느린 쿼리의 전형적인 원인

- 인덱스 없는 WHERE 조건

- 불필요한 SELECT *

- JOIN 순서 문제

- LIMIT 없이 대량 조회

## 3-3. 최적화 기본 원칙 정리

- 필요한 컬럼만 SELECT

- WHERE → JOIN → GROUP BY → ORDER BY 순서 이해

- 인덱스는 읽기 성능, 쓰기는 비용

---

# 🧩 실습 / 과제


## 1. SQL 최적화 & EXPLAIN 실습
특정 사용자가 작성한 댓글을 최신순으로 조회하는 다음 쿼리가 있다.
```sql
SELECT *
FROM comments
WHERE user_id = 1
ORDER BY created_at DESC;
```

위 쿼리를 EXPLAIN으로 확인해보고, 어떤 문제가 있는지 파악하고 최적화를 위헤 인덱스를 생성해본다.