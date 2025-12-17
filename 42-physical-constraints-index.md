# 제약 조건 정의와 인덱스 설계 (Constraints and Index Design)
## 📘 학습 개요

논리 설계에서는 “어떤 데이터가 필요한가”를 정의했다면,
물리 설계에서는 “그 데이터를 어떤 규칙으로 저장하고, 어떻게 빠르게 찾을 것인가”를 결정한다.

제약 조건과 인덱스는
물리 데이터베이스 설계의 핵심 요소이며,
잘못 설계하면 데이터 오류와 성능 문제를 동시에 유발한다.

## 💡 주요 내용

- 제약 조건의 역할과 종류

- NOT NULL, UNIQUE, PRIMARY KEY

- 제약 조건과 데이터 무결성

- 인덱스의 개념과 필요성

- PK 인덱스와 보조 인덱스 설계 기준

--- 


# 1. 제약 조건이란 무엇인가
## 1-1. 제약 조건의 개념

제약 조건(Constraint)은
테이블에 저장되는 데이터가 반드시 지켜야 할 규칙이다.

제약 조건이 없는 테이블은
형식은 테이블이지만,
어떤 값이 들어와도 오류 없이 저장되는, 규칙 없는 저장소에 가깝다.

즉,
제약 조건은 데이터의 최소한의 품질 기준을 데이터베이스 차원에서 보장한다.

## 1-2. 제약 조건이 필요한 이유

> 제약 조건이 필요한 이유는 데이터 오류를 사후에 처리하지 않기 위해서이다.

- 잘못된 데이터 입력을 저장 단계에서 즉시 차단

- 모든 행이 동일한 규칙을 따르도록 하여 데이터 일관성 유지

- 검증 로직을 애플리케이션마다 반복 작성하지 않아도 되어 코드 의존도 감소

- 어떤 프로그램이 접근하더라도 규칙이 유지되어 데이터베이스 자체의 신뢰성 확보

애플리케이션에서만 검증할 경우,
여러 서비스·배치·관리 도구에서
같은 검증 로직을 각각 구현해야 한다.

제약 조건을 데이터베이스에 정의하면,
어떤 경로로 데이터가 저장되더라도
항상 동일한 기준으로 데이터 무결성이 보장된다.

따라서 제약 조건은
문제가 발생한 뒤에 수정하는 수단이 아니라,
문제가 발생하지 않도록 미리 차단하는 예방 장치이다.

--- 

# 2. 주요 제약 조건 종류
## 2-1. PRIMARY KEY

> PK는 사람이 이해하려고 쓰는 값이 아니라, DB가 빠르고 안전하게 구분하려고 쓰는 번호다.   
> 테이블의 각 행을 유일하게 식별하는 컬럼

특징:

- NOT NULL

- UNIQUE

- 테이블 당 하나만 존재

- 자동으로 인덱스 생성

예시:
```sql
id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
```



## 2-2. UNIQUE

> UNIQUE 제약은 특정 컬럼의 값이 테이블 내에서 중복되지 않도록 보장하는 제약 조건이다.

대표적인 사용 예:

- 이메일

- 로그인 아이디

- 휴대폰 번호


특징:

- (MySQL 기준) UNIQUE 컬럼은 NULL을 여러 개 허용할 수 있다.

- 중복 방지가 목적이면 UNIQUE 제약 조건을 사용한다

예시:
```sql
email VARCHAR(100) UNIQUE
```

---

# 3. 인덱스란 무엇인가

## 3-1. 인덱스 개념
> 인덱스(Index)는 데이터를 빠르게 조회하기 위해 정렬된 자료 구조(B+Tree) 를 사용한다.   
> B+Tree는 “어디에 데이터가 있는지”를 빠르게 찾는 지도다.

인덱스가 없으면:

- 테이블 전체를 순차 탐색

인덱스가 있으면:

- 필요한 위치로 바로 접근

B+Tree 구조


## 3-2. 인덱스의 장단점

장점:

- 조회 성능 향상

- JOIN 성능 향상

- ORDER BY, GROUP BY 최적화

단점:

- 저장 공간 추가 사용

- INSERT, UPDATE, DELETE 성능 저하

따라서 인덱스는 전략적으로 설계해야 한다.

--- 

# 4. 기본 키 인덱스 (PK 인덱스)
## 4-1. PK 인덱스 특징

> PRIMARY KEY는 자동으로 인덱스가 생성된다.

- 중복 불가

- NULL 불가

## 4-2. PK 설계 기준

- 값이 변하지 않아야 한다

- 길이가 짧을수록 좋다

- 숫자형 AUTO_INCREMENT 권장

비권장 예:

- 이메일

- 주민번호

- 의미 있는 코드

--- 

# 5. 보조 인덱스 (Secondary Index)
## 5-1. 보조 인덱스란

> PRIMARY KEY를 제외한 모든 인덱스를 보조 인덱스라고 한다.  
> 보조 인덱스(Secondary Index)는 인덱스를 통해 PK 값을 찾고, PK(클러스터드 인덱스)를 통해 실제 레코드를 찾는다.

주 사용 목적:

- WHERE 조건 검색

- JOIN 성능 향상

- 정렬 성능 개선

## 5-2. 보조 인덱스 설계 대상

다음 조건을 만족하는 컬럼에 주로 생성한다.

- 조회가 매우 잦은 컬럼

- JOIN 조건에 자주 사용되는 컬럼

- ORDER BY, GROUP BY에 사용되는 컬럼

## 5-3. 보조 인덱스 예시

게시글 목록 조회 쿼리:
```sql
SELECT *
FROM posts
WHERE user_id = 10
ORDER BY created_at DESC
LIMIT 10;
```

추천 인덱스:
```sql
CREATE INDEX user_id_created_at
ON posts(user_id, created_at);
```

## 5-4. 복합 인덱스 컬럼 순서 규칙

복합 인덱스는 왼쪽 컬럼부터 사용된다.

인덱스 (A, B)

- WHERE A = ? 가능

- WHERE A = ? AND B = ? 가능

- WHERE B = ? 만 사용하면 인덱스 사용 불가





--- 

# 6. 제약 조건과 인덱스 관계 정리

## 6-1. 제약 조건 정리

> 제약 조건은 데이터가 저장될 수 있는지를 결정한다.

| 제약 조건       | 역할      | 보장하는 것        | 인덱스 생성    |
| ----------- | ------- | ------------- | --------- |
| PRIMARY KEY | 행 식별    | 유일성 + NULL 불가 | 자동 생성     |
| UNIQUE      | 중복 방지   | 값 중복 금지       | 자동 생성     |
| NOT NULL    | 필수 값    | NULL 입력 차단    | 생성 안 됨    |

## 6-2. 인덱스 정리
> 인덱스는 어디를 빨리 찾을 것인가를 결정한다.

| 인덱스 종류          | 역할         | 특징        | 개수      |
| --------------- | ---------- | --------- | ------- |
| PRIMARY KEY 인덱스 | 기본 조회 기준   | 클러스터드 인덱스 | 테이블당 1개 |
| UNIQUE 인덱스      | 중복 검사 + 조회 | 보조 인덱스    | 여러 개 가능 |
| 보조 인덱스          | 조회 성능 향상   | 논클러스터드    | 여러 개 가능 |
| 복합 인덱스          | 다중 조건 최적화  | 컬럼 순서 중요  | 여러 개 가능 |


---

# 🧩 실습 / 과제

## 1. 제약 조건이 없는 테이블의 문제점 체험

### 1-1. 제약 조건 없는 테이블 생성
```sql
CREATE TABLE users_test (
    id INT,
    email VARCHAR(100),
    username VARCHAR(50)
);
```

### 1-2. 잘못된 데이터 삽입
```sql
INSERT INTO users_test VALUES
(NULL, NULL, NULL),
(1, 'a@test.com', 'kim'),
(1, 'a@test.com', 'lee'),
(1, 'b@test.com', 'park');
```


## 2. 제약 조건 적용 후 데이터 차단 확인


### 2-1. 제약 조건과 함꼐 테이블 생성
```sql
CREATE TABLE users_raw (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL
);
```

### 2-2. 데이터 삽입
```sql
INSERT INTO users_raw (email, username) VALUES
('a@test.com', 'kim'),
('a@test.com', 'lee');
```


## 3. 보조 인덱스 없을 때 vs 있을 때 비교

### 3-0. 시드 데이터 추가 ( 100만건 )
```sql
SET SESSION cte_max_recursion_depth = 1000000;

-- ===== posts (1000k)
INSERT INTO posts (user_id, title, content, view_count, created_at)
SELECT
  FLOOR(1 + RAND() * 50),
  CONCAT('게시글 제목 ', n),
  CONCAT('게시글 내용 ', n),
  FLOOR(RAND() * 50000),
  NOW() - INTERVAL FLOOR(RAND() * 365) DAY
FROM (
  WITH RECURSIVE seq(n) AS (
    SELECT 1 UNION ALL SELECT n+1 FROM seq WHERE n < 1000000
  ) SELECT n FROM seq
) t;
```

### 3-1. (선택) posts 테이블 보조 인덱스 ( 1. user_id 2. created_at ) 삭제

### 3-2. 아래 쿼리 실행
```sql
SELECT * FROM testdb.posts where user_id = 100 order by created_at desc
```


### 3-3. 쿼리가 인덱스 사용하는지 
```sql
explain SELECT * FROM testdb.posts where user_id = 100 order by created_at desc
```


### 3-4. user_id, created_at 복합 인덱스 생성 후 실행결과 보기
```sql
explain SELECT * FROM testdb.posts where user_id = 100 order by created_at desc
```