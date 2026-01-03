# SQL 프로시저 (Stored Procedure)

## 📘 학습 개요

이 장에서는 MySQL 프로시저(Stored Procedure) 를 통해
여러 SQL 문을 하나의 논리적 작업으로 묶는 방법을 학습한다.

단순히 문법을 외우는 것이 아니라,

- 왜 프로시저가 필요한지

- 어떤 상황에서 써야 하고, 언제 쓰면 안 되는지

- 애플리케이션 코드와 DB 로직의 역할 분리

를 이해하는 것을 목표로 한다.

## 🎯 학습 목표

이 강의를 마치면 다음을 할 수 있다.

- 프로시저가 무엇인지 설명할 수 있다

- 여러 SQL을 하나의 트랜잭션 단위로 묶어 실행할 수 있다

- 반복문, 조건문을 이용해 DB 내부 로직을 작성할 수 있다

- “이 로직은 애플리케이션에서 할지, DB에서 할지” 판단할 수 있다

---


# 0. 왜 프로시저가 필요한가?

## 0-1. 애플리케이션에서만 SQL을 처리할 때의 한계

예: 댓글 작성 시 해야 할 작업

1. comments 테이블에 INSERT

2. posts 테이블의 댓글 수 증가

3. 실패 시 롤백 처리

애플리케이션 코드에서는 보통 다음처럼 처리한다.

- SQL 여러 개 실행

- 중간에 하나라도 실패하면 예외 처리

- 트랜잭션 직접 관리

문제점:

- 로직이 여러 곳에 흩어짐

- 동일한 로직을 여러 서비스에서 중복 구현

- 실수로 트랜잭션 누락 시 데이터 불일치 발생

## 0-2. 프로시저의 역할
> 프로시저는 “DB 안에 저장해 두는 로직 함수”다.

- 여러 SQL을 하나의 이름으로 묶음

- DB 서버 내부에서 실행

- 트랜잭션, 반복, 조건 처리를 DB가 직접 담당

즉,

> “데이터와 가장 가까운 곳에서 실행해야 하는 로직”을 DB에 맡긴다

---

# 1. 프로시저란 무엇인가?
## 1-1. 기본 개념

- Stored Procedure = 저장된 SQL 묶음

- 미리 정의해두고 필요할 때 CALL로 실행

- 함수와 비슷하지만 값 반환이 목적이 아님

```sql
CALL 프로시저이름();
```

## 1-2. 언제 사용하는가?

적합한 경우:

- 여러 SQL을 항상 함께 실행해야 할 때

- 트랜잭션 단위로 묶어야 할 때

- 대량 데이터 처리 (배치, 초기 데이터 생성)

- DB 무결성과 강하게 결합된 로직

부적합한 경우:

- 화면 출력 로직

- 복잡한 비즈니스 정책

- 자주 변경되는 로직


---

# 2. 프로시저 기본 문법
## 2-1. DELIMITER 개념

MySQL은 기본적으로 `;` 를 SQL 종료로 인식한다.

하지만 프로시저 안에는 `;` 가 여러 번 등장하므로 구분자가 필요하다.

> DELIMITER로 정의한 문자는 MySQL 서버 설정이 아니라  
> 클라이언트의 입력 컨텍스트에만 적용되며,   
> 입력 컨텍스트가 바뀌면 초기화된다.

```sql
DELIMITER // -- 이제부터 문장 끝은 //로 판단해라

CREATE PROCEDURE test_proc()
BEGIN
    SELECT NOW();
END// -- 프로시저 정의가 여기서 끝났다는 표시

DELIMITER ; -- 다시 기본 구분자 ;로 돌아가라
```

## 2-2. 가장 단순한 프로시저
```sql
DELIMITER //

CREATE PROCEDURE hello_proc()
BEGIN
    SELECT 'Hello Procedure';
END//

DELIMITER ;
```

---

# 3. 변수 사용하기
## 3-1. DECLARE 변수
```sql
CREATE PROCEDURE var_test()
BEGIN
    DECLARE cnt INT DEFAULT 0;

    SET cnt = 10;

    SELECT cnt;
END
```

- DECLARE 는 BEGIN 바로 아래에서만 가능

- 지역 변수 개념

---

# 4. 조건문 (IF)
## 4-1. IF 기본 구조
```sql
IF 조건 THEN
    SQL;
ELSE
    SQL;
END IF;
```


## 4-2. 예제: 조회수 증가 로직
```sql
CREATE PROCEDURE increase_view(p_post_id INT)
BEGIN
    IF p_post_id IS NOT NULL THEN
        UPDATE posts
        SET view_count = view_count + 1
        WHERE id = p_post_id;
    END IF;
END
```

## 4-2. 예제: 게시물 조회
```sql
CREATE PROCEDURE select_posts(p_post_id INT)
BEGIN
    IF p_post_id IS NOT NULL THEN
        SELECT * FROM posts
        WHERE id = 1;
    END IF;
END
```


---

# 5. 반복문 (WHILE)
## 5-1. WHILE 기본 구조
```sql
WHILE 조건 DO
    SQL;
END WHILE;
```


## 5-2. 예제: 테스트 데이터 생성
```sql
CREATE PROCEDURE seed_simple_posts()
BEGIN
    DECLARE i INT DEFAULT 1;

    WHILE i <= 10 DO
        INSERT INTO posts (user_id, title, content)
        VALUES (1, CONCAT('테스트', i), '내용');

        SET i = i + 1;
    END WHILE;
END
```

---


# 6. 프로시저 + 트랜잭션
## 6-1. 트랜잭션이 필요한 이유

- 여러 SQL 중 하나라도 실패하면 전체 취소

- 데이터 무결성 보장

## 6-2. 트랜잭션 포함 프로시저 예제

```sql
CREATE PROCEDURE add_comment(
    p_post_id INT,
    p_user_id INT,
    p_comment VARCHAR(500)
)
BEGIN
    START TRANSACTION;

    INSERT INTO comments (post_id, user_id, comment)
    VALUES (p_post_id, p_user_id, p_comment);

    COMMIT;
END
```


---

# 7. 프로시저 관리

## 7-1. 프로시저 확인 방법 (Workbench 기준)

프로시저 생성 후에는
SQL로 목록을 조회하지 않고, Workbench UI에서 확인한다.

확인 경로
```
MySQL Workbench
→ Schemas(testdb)
  → Stored Procedures
```

## 7-2. 삭제
```sql
DROP PROCEDURE IF EXISTS 프로시저이름;
```

## 7-3. 프로시저 이름 관리 규칙 (중요)

프로시저는 DB에 영구적으로 남는 객체이므로
이름이 곧 문서 역할을 한다.

권장 규칙:

- 동사로 시작

- 하는 일을 명확히 표현

예:

- add_comment

- increase_view

- seed_simple_posts

피해야 할 이름:

- proc1

- test_proc

- temp

> 프로시저 이름만 보고도    
> “이게 무슨 작업인지” 알 수 있어야 한다.

---


# 8. 프로시저 vs 애플리케이션 로직

| 기준      | 프로시저      | 애플리케이션  |
| ------- | --------- | ------- |
| 데이터 무결성 | 매우 강함     | 실수 가능   |
| 유지보수    | 상대적으로 어려움 | 쉬움      |
| 성능      | DB 내부 실행  | 네트워크 왕복 |
| 변경 빈도   | 낮은 로직     | 높은 로직   |

결론

> 프로시저는 “DB의 책임”인 영역에만 사용한다.

---

# 🧩 실습 / 과제

## 1. 게시글 조회 시 조회수를 증가시키는 프로시저 작성

## 2. 10분 제한 조회수 증가 프로시저 작성

### 조회 로그 테이블 설계
```sql
CREATE TABLE post_view_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  viewer_key VARCHAR(100) NOT NULL,
  viewed_at DATETIME NOT NULL,
  UNIQUE KEY uq_post_view (post_id, viewer_key)
);
```

### 프로시저 만들기 
```sql
CREATE PROCEDURE increase_post_view_with_interval (
  IN p_post_id INT,
  IN p_viewer_key VARCHAR(100),
  IN p_interval_minute INT
)
BEGIN
  DECLARE last_viewed DATETIME;

  -- 마지막 조회 시각 조회
  SELECT viewed_at
  INTO last_viewed
  FROM post_view_logs
  WHERE post_id = p_post_id
    AND viewer_key = p_viewer_key;

  -- 처음 조회이거나, 설정된 분(minute) 이상 경과한 경우
  IF last_viewed IS NULL
     OR TIMESTAMPDIFF(MINUTE, last_viewed, NOW()) >= p_interval_minute THEN

    -- 조회수 증가
    UPDATE posts
    SET view_count = view_count + 1
    WHERE id = p_post_id;

    -- 조회 로그 기록
    INSERT INTO post_view_logs (post_id, viewer_key, viewed_at)
    VALUES (p_post_id, p_viewer_key, NOW())
    ON DUPLICATE KEY UPDATE
      viewed_at = NOW();

  END IF;
END
```