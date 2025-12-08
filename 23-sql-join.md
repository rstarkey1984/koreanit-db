# JOIN / 집계 함수 / GROUP BY / HAVING

## 📘 학습 개요

이 문서는 SQL에서 **여러 테이블을 묶어 조회하는 방법(JOIN)** 과  
**데이터를 그룹으로 묶어 요약하는 방법(GROUP BY / HAVING)** 을 다룬다.


## 💡 주요 내용

- JOIN - 여러 테이블을 묶어서 조회하기  

- 집계 함수(Aggregate Functions) - COUNT / SUM / AVG / MAX / MIN  
- GROUP BY - 그룹별로 묶어서 요약하기  
- HAVING - 그룹 결과에 조건 걸기  

---

# 1. JOIN - 여러 테이블 묶어서 조회하기

> JOIN 은 두 테이블을 연결해서 하나의 결과로 조회하는 문법이다.   

기본 구조 (INNER JOIN):

```sql
SELECT 컬럼들
FROM 첫번째_테이블 AS A
JOIN 두번째_테이블 AS B
  ON A.공통컬럼 = B.공통컬럼;
```

## 1-1. INNER JOIN - 매칭되는 것만 조회

posts 에는 작성자 번호(user_id),
users 에는 사용자 기본키(id) 가 있다.

```sql
SELECT 
    p.id,
    p.user_id,
    p.title,
    u.username,
    u.nickname
FROM posts AS p
JOIN users AS u
  ON p.user_id = u.id;
```
p.user_id = u.id 인 행만 결과로 나온다.
(작성자가 존재하는 게시글만 조회)


## 1-2. WHERE 와 함께 사용하기

특정 사용자가 작성한 게시글만 보고 싶을 때:
```sql
SELECT 
    p.id,
    p.title,
    u.username
FROM posts AS p
JOIN users AS u
  ON p.user_id = u.id
WHERE u.username = 'user010';
```

user010 이 쓴 게시글만 조회.

## 1-3. LEFT JOIN - 왼쪽 테이블을 기준으로 모두 가져오기

LEFT JOIN 은 왼쪽 테이블은 전부 살리고,
오른쪽 테이블은 매칭되는 경우만 채운다.

```sql
SELECT 
    p.id,
    p.title,
    p.user_id,
    u.username
FROM posts AS p
LEFT JOIN users AS u
  ON p.user_id = u.id;
```

만약 어떤 게시글의 user_id 에 해당하는 사용자가 users 에 없다면,
그 행의 u.username 은 NULL 로 조회된다.

---

# 2. 집계 함수(Aggregate Functions)
> 집계 함수는 여러 행(Row)을 “요약”해서 하나의 값으로 만드는 함수이다.

| 함수              | 설명  | 예제            |
| --------------- | --- | ------------- |
| COUNT(*)        | 개수  | 전체 게시글 수      |
| SUM(view_count) | 합계  | 전체 조회수 합      |
| AVG(view_count) | 평균  | 게시글 평균 조회수    |
| MAX(view_count) | 최대값 | 가장 많이 조회된 게시글 |
| MIN(view_count) | 최소값 | 가장 적게 조회된 게시글 |

## 2-1. 전체 게시글 수

```sql
SELECT COUNT(*) AS 전체_게시글수
FROM posts;
```

## 2-2. 전체 조회수 합계
```sql
SELECT SUM(view_count) AS 조회수_합계
FROM posts;
```

## 2-3. 평균 조회수
```sql
SELECT AVG(view_count) AS 평균_조회수
FROM posts;
```

## 2-4. 최대값 / 최소값
```sql
SELECT 
    MAX(view_count) AS 최대_조회수,
    MIN(view_count) AS 최소_조회수
FROM posts;
```

## 2-5. 특정 사용자 게시글 수
```sql
SELECT 
    COUNT(*) AS 게시글수
FROM posts
WHERE user_id = 10;
```

---

# 3. GROUP BY - 그룹별로 묶어서 요약하기

## 3-1. 작성자별 게시글 수
```sql
SELECT 
    user_id,
    COUNT(*) AS 게시글수
FROM posts
GROUP BY user_id;
```

## 3-2. JOIN + 작성자별 게시글 수
```sql
SELECT 
    u.id,
    u.username,
    COUNT(p.id) AS 게시글수
FROM users AS u
LEFT JOIN posts AS p
    ON u.id = p.user_id
GROUP BY u.id, u.username;
```

## 3-3. 작성자별 평균 조회수
```sql
SELECT 
    user_id,
    AVG(view_count) AS 평균조회수
FROM posts
GROUP BY user_id;
```

---

# 4. HAVING - 그룹 조건 필터링

> WHERE 는 그룹핑 이전 조건   
> HAVING 은 GROUP BY로 묶은 뒤의 집계 결과 조건

## 4-1. 게시글을 3개 이상 작성한 사용자.
```sql
SELECT 
    user_id,
    COUNT(*) AS 게시글수
FROM posts
GROUP BY user_id
HAVING COUNT(*) >= 3;
```

## 4-2. 평균 조회수가 200 이상인 사용자.
```sql
SELECT 
    user_id,
    AVG(view_count) AS 평균조회수
FROM posts
GROUP BY user_id
HAVING AVG(view_count) >= 200;
```

--- 


# 5. 실전 예제

## 5-1. 사용자별 게시글 수 TOP 3
> 글을 가장 많이 작성한 사용자 3명 조회.
```sql
SELECT 
    u.id,
    u.username,
    COUNT(p.id) AS 게시글수
FROM users AS u
LEFT JOIN posts AS p
  ON u.id = p.user_id
GROUP BY u.id
ORDER BY 게시글수 DESC
LIMIT 3;
```


## 5-2. 사용자별 평균 조회수
```sql
SELECT 
    u.id,
    u.username,
    AVG(p.view_count) AS 평균조회수
FROM users AS u
JOIN posts AS p
  ON u.id = p.user_id
GROUP BY u.id;
```

## 5-3. 평균 조회수 50 이상인 사용자
```sql
SELECT 
    u.id,
    u.username,
    AVG(p.view_count) AS 평균조회수
FROM users AS u
JOIN posts AS p
  ON u.id = p.user_id
GROUP BY u.id
HAVING AVG(p.view_count) >= 50;
```

## 5-4. 게시글이 하나도 없는 사용자 찾기 (LEFT JOIN 활용)
```sql
SELECT 
    u.id,
    u.username
FROM users AS u
LEFT JOIN posts AS p
  ON u.id = p.user_id
WHERE p.id IS NULL;
```

## 5-5. user_id 별 최대 조회수
```sql
SELECT 
    user_id,
    MAX(view_count) AS 최대조회수
FROM posts
GROUP BY user_id;
```


--- 

# 🧩 실습 / 과제

## 1. JOIN

- `posts` 테이블과 `users` 테이블을 `p.user_id = u.id` 기준으로 INNER JOIN 하여  
  게시글의 `id`, `title`, 작성자의 `username`, `nickname` 을 조회하시오.

- 위 결과에서 `username` 이 `'user010'` 인 사용자가 작성한 게시글만 조회하시오.

- `users` 테이블을 기준으로 LEFT JOIN 을 사용하여  
  **모든 사용자의 `id`, `username`** 과  
  해당 사용자가 작성한 **게시글의 `id`, `title`** 을 함께 조회하시오.

- LEFT JOIN 을 사용하여 **게시글이 하나도 없는 사용자만 조회**하시오.  
  (컬럼: `id`, `username`)

## 2. 집계 함수 (Aggregate Functions)

- `posts` 테이블에서 전체 게시글 수를 조회하시오.

- `posts` 테이블에서 전체 조회수(`view_count`)의 합계를 조회하시오.

- `posts` 테이블에서 평균 조회수를 조회하시오.

- `posts` 테이블에서 가장 높은 조회수와 가장 낮은 조회수를 각각 조회하시오.

- `user_id = 10` 인 사용자가 작성한 게시글 수를 조회하시오.


## 3. GROUP BY

- `posts` 테이블에서 `user_id` 별 게시글 수를 조회하시오.  
  (컬럼: `user_id`, `게시글수`)

- `users` 와 `posts` 를 JOIN 한 뒤,  
  `username` 기준으로 그룹핑하여 각 사용자의 게시글 수를 조회하시오.  
  (컬럼: `username`, `게시글수`)

- `posts` 테이블에서 `user_id` 별 평균 조회수를 조회하시오.  
  (컬럼: `user_id`, `평균조회수`)

## 4. WHERE + GROUP BY

- 조회수(`view_count`)가 100 이상인 게시글만 대상으로,  
  `user_id` 별 게시글 수를 조회하시오.

- `user_id` 가 5 또는 10인 게시글만 대상으로,  
  `user_id` 별 게시글 수를 조회하시오.


## 5. HAVING

- `posts` 테이블에서 `user_id` 기준으로 그룹핑한 뒤,  
  **게시글 수가 5개 이상**인 사용자만 조회하시오.  
  (컬럼: `user_id`, `게시글수`)

- `posts` 테이블에서 `user_id` 기준으로 그룹핑한 뒤,  
  **평균 조회수(`AVG(view_count)`)가 200 이상**인 사용자만 조회하시오.  
  (컬럼: `user_id`, `평균조회수`)


## 6. 종합 (JOIN + GROUP BY + HAVING)

- `users` 와 `posts` 를 JOIN 한 뒤,  
  `username` 기준으로 그룹핑하여 **게시글을 3개 이상 작성한 사용자**만 조회하시오.  
  (컬럼: `username`, `게시글수`)

- `users` 와 `posts` 를 JOIN 한 뒤,  
  `username` 기준으로 그룹핑하여 **평균 조회수가 150 이상**인 사용자만 조회하시오.  
  (컬럼: `username`, `평균조회수`)
