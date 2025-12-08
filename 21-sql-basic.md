# SELECT / INSERT / UPDATE / DELETE

## 📘 학습 개요

이 문서는 **가장 기본적인 네 가지 SQL 문법**인  

> `SELECT` / `INSERT` / `UPDATE` / `DELETE`  

를 MySQL 기준으로 연습하는 내용을 다룹니다.

앞에서 만든 `testdb` 데이터베이스와  
`users`, `posts` 테이블을 그대로 사용해서 실습합니다.

## 💡 주요 내용

- SELECT : 데이터 조회하기  
- INSERT : 새 데이터 추가하기  
- UPDATE : 기존 데이터 수정하기  
- DELETE : 데이터 삭제하기  

---

# 1. SELECT – 데이터 조회하기
> SELECT 는 “테이블에서 데이터를 읽어오는 명령”입니다. 어떤 열(컬럼)을, 어느 테이블에서 가져올지 지정합니다.

기본형태:
```sql
SELECT 컬럼1, 컬럼2, ... 
FROM 테이블명;
```

모든 컬럼 조회: ( * 는 “이 테이블의 모든 컬럼”을 의미합니다. )
```sql
SELECT * 
FROM 테이블명;
```

## 1-1. users 테이블 전체 조회
```sql
SELECT * 
FROM users;
```

## 1-2. 필요한 컬럼만 골라서 조회
```sql
SELECT username, nickname, email 
FROM users;
```

## 1-3. 별칭(alias) 사용하기
| 키워드  | 역할               |
| ---- | ---------------- |
| column `AS` 별칭 | 컬럼에 별칭(다른 이름) 부여 |

```sql
SELECT 
    username AS 아이디,
    nickname AS 닉네임,
    email AS 이메일
FROM users;
```

## 1-4. posts 테이블 조회 예시
```sql
SELECT 
    id,
    title,
    view_count
FROM posts;
```

## 1-5. WHERE / ORDER BY / LIMIT 맛보기
```sql
-- (1) WHERE : 조건에 맞는 행만 가져오기
SELECT * 
FROM users
WHERE username = 'user001';

-- (2) ORDER BY : 정렬하기
SELECT id, title, view_count
FROM posts
ORDER BY id DESC;

-- (3) LIMIT : 상위 N개만 보기
SELECT id, title
FROM posts
ORDER BY id DESC
LIMIT 10;
```

---

# 2. INSERT – 새 데이터 추가하기
> INSERT 는 테이블에 새로운 행(Row) 을 추가하는 명령입니다.

기본 형태:
```sql
INSERT INTO 테이블명 (컬럼1, 컬럼2, 컬럼3, ...)
VALUES (값1, 값2, 값3, ...);
```

## 2-1. users 테이블에 행(Row) 추가하기
| 부분                                     | 의미                |
| -------------------------------------- | ----------------- |
| `INSERT INTO` users                    | users 테이블에 새 행 추가 |
| (username, password, nickname, email)      | 값을 넣을 컬럼 목록       |
| `VALUES` ('newuser', 'pass1234', ... ) | 실제로 들어가는 값        |
```sql
INSERT INTO users (username, password, nickname, email)
VALUES ('newuser', 'pass1234', '새로운유저', 'newuser@example.com');
```

## 2-2. 여러 행 한 번에 INSERT
```sql
INSERT INTO users (username, password, nickname, email)
VALUES 
    ('batch01', 'user1234', '배치유저1', 'batch01@example.com'),
    ('batch02', 'user1234', '배치유저2', 'batch02@example.com'),
    ('batch03', 'user1234', '배치유저3', 'batch03@example.com');
```

## 2-3. posts 에 새 게시글 추가하기
```sql
INSERT INTO posts (user_id, title, content)
VALUES (1, '첫 타이틀 테스트 글', '이것은 수동으로 INSERT 한 첫 번째 게시글입니다.');
```

--- 

# 3. UPDATE – 기존 데이터 수정하기

> UPDATE 는 테이블에 이미 있는 행의 값을 수정할 때 사용합니다.

기본 형태:
```sql
UPDATE 테이블명
SET 컬럼1 = 값1, 컬럼2 = 값2, ...
WHERE 조건;
```

> UPDATE 는 절대 WHERE 없이 사용하면 안됨. WHERE 를 빼면 모든 행 수정

## 3-1. users 닉네임 수정하기
```sql
UPDATE users
SET nickname = '닉네임수정'
WHERE username = 'newuser';
```

## 3-2. posts 조회수(view_count) 증가시키기
예: id 가 1번인 게시글의 조회수를 1 올리기
```sql
UPDATE posts
SET view_count = view_count + 1
WHERE id = 1;
```

## 3-3. WHERE 없이 UPDATE 하면?
```sql
UPDATE posts
SET view_count = view_count + 1
```
> 이렇게 하면 모든 게시물의 조회수가 1 오름 ( UPDATE 는 절대 WHERE 없이 사용하면 안됨 )

```sql
UPDATE posts
SET view_count = view_count + 1
WHERE id > 0;
```

## 3-4. 먼저 어떤 행이 수정될지 SELECT 로 확인
```sql
SELECT * FROM users WHERE username = 'newuser';
```

## 3-5. 그 다음에 같은 WHERE 조건으로 UPDATE 실행
```sql
UPDATE users
SET nickname = '닉네임수정'
WHERE username = 'newuser';
```

--- 

# 4. DELETE – 데이터 삭제하기
> DELETE 는 테이블에서 행 자체를 삭제하는 명령

기본 형태:
```sql
DELETE FROM 테이블명
WHERE 조건;
```
> DELETE 는 절대 WHERE 없이 사용하면 안됨. WHERE 를 빼면 모든 행 삭제

## 4-1. 특정 사용자 삭제하기
```sql
DELETE FROM users
WHERE username = 'batch01';
```

## 4-2. view_count 가 10인 게시글 삭제 (예시)
```sql
DELETE FROM posts
WHERE view_count = 10;
```

## 4-3. WHERE 없이 DELETE 하면?
```sql
DELETE FROM posts;
```
> 이렇게 하면 posts 테이블의 모든 데이터가 삭제. (테이블 구조는 남지만, 행이 0개가 됨)

# 5. DML 요약 – 한 번에 보기
> DML(Data Manipulation Language) : 데이터를 조작하는 명령어

| 구분 | 키워드    | 역할         | 예시                                      |
| -- | ------ | ---------- | --------------------------------------- |
| 조회 | SELECT | 데이터 읽기     | `SELECT * FROM users;`                  |
| 추가 | INSERT | 새 행 추가     | `INSERT INTO users (...) VALUES (...);` |
| 수정 | UPDATE | 기존 행의 값 변경 | `UPDATE users SET ... WHERE ...;`       |
| 삭제 | DELETE | 행 삭제       | `DELETE FROM users WHERE ...;`          |

> ###  UPDATE / DELETE 는 반드시 WHERE 와 함께 작성해야함.

---

# 🧩 실습 / 과제


### 1. users 테이블의 username, nickname, email 만 조회하기

### 2. posts 테이블에서 id, user_id, title, view_count 만 조회하기

### 3. users 테이블에 자신의 계정 1개를 추가해보기

### 4. 방금 추가한 계정의 nickname 을 다른 값으로 수정하기

### 5. posts 테이블에 새로운 게시글 2개를 INSERT 해보기

### 6. posts 테이블에서 (id 를 하나 정해서) view_count 를 10 증가시키는 UPDATE 작성하기

### 7. 연습용으로 만든 계정/게시글 몇 개를 DELETE 로 지워보기