# WHERE / ORDER BY / LIMIT 

## 📘 학습 개요

SQL에서 가장 많이 쓰는 3대 조회 문법이다.

WHERE : 조건에 맞는 행만 가져오기

ORDER BY : 정렬하기

LIMIT : 원하는 개수만큼만 가져오기

## 💡 주요 내용

- WHERE - 조건으로 필터링하기

- ORDER BY - 정렬하기

---


# 1. WHERE - 조건으로 필터링하기

> WHERE 는 “행(Row)을 걸러내는 필터(filter)” 역할을 한다.

기본 구조:

```sql
SELECT 컬럼들
FROM 테이블명
WHERE 조건;
```

## 1-1. 기본 비교 연산자

| 연산자    | 의미          | 예시                   |
| ------ | ----------- | -------------------- |
| =      | 값이 같다       | username = 'user001' |
| !=, <> | 같지 않다       | id <> 10             |
| >, >=  | 크다 / 크거나 같다 | id > 100             |
| <, <=  | 작다 / 작거나 같다 | view_count <= 50     |

예시:

```sql
SELECT *
FROM users
WHERE username = 'user010';
```

## 1-2. 문자열 LIKE(포함 검색)

| 패턴     | 의미         |
| ------ | ---------- |
| '%문자'  | `~문자` 로 끝나는 값  |
| '문자%'  | `문자~` 로 시작하는 값 |
| '%문자%' | `~문자~` 포함      |

예:
```sql
SELECT username, email
FROM users
WHERE email LIKE '%003%';
```

## 1-3. AND / OR (조건 조합)
```sql
SELECT *
FROM posts
WHERE view_count >= 100
  AND title LIKE '%테스트%';
```
```sql
SELECT *
FROM users
WHERE username = 'user001'
   OR username = 'user002';
```

---

# 2. ORDER BY - 정렬하기
> ORDER BY 는 조회된 결과를 정렬한다.

기본 구조:
```sql
SELECT 컬럼들
FROM 테이블
ORDER BY 컬럼 [ASC | DESC];
```
| 옵션   | 의미        |
| ---- | --------- |
| ASC  | 오름차순(기본값) |
| DESC | 내림차순      |


## 2-1. id 내림차순
```sql
SELECT id, title
FROM posts
ORDER BY id DESC;
```

## 2-2. 정렬 기준 여러 개
```sql
SELECT id, title, view_count
FROM posts
ORDER BY view_count DESC, id ASC;
```

> 먼저 view_count 기준으로 내림차순,  
> 그 다음 view_count가 같으면 id 오름차순.

## 2-3. 작성자(user_id) + id 기준 정렬
```sql
SELECT id, user_id, title
FROM posts
ORDER BY user_id ASC, id DESC;
```

--- 

# 3. LIMIT - 원하는 개수만 가져오기
> LIMIT는 조회되는 행(Row)의 개수를 제한한다.

기본 구조:
```sql
LIMIT 개수(LIMIT);
```

예:
```sql
SELECT id, title
FROM posts
ORDER BY id DESC
LIMIT 10;
```
위 쿼리는 posts 테이블에서 id 기준 내림차순으로 정렬한 후, 상위 10개의 행만 가져온다.

## 3-1. LIMIT + OFFSET (페이지네이션)
> OFFSET은 몇 번째 행부터 가져올지 지정한다.

기본 구조:
```sql
LIMIT 개수 OFFSET 시작점;
```

예: 101번째~110번째 게시글
```sql
SELECT *
FROM posts
ORDER BY id
LIMIT 10 OFFSET 100;
```

---

# 4. 실전 조합 예제


## 4-1. 특정 단어가 들어간 최신 게시글 5개
```sql
SELECT id, title
FROM posts
WHERE title LIKE '%테스트%'
ORDER BY id DESC
LIMIT 5;
```

## 4-2. 조회수가 200 이상인 게시글 TOP 10
```sql
SELECT id, title, view_count
FROM posts
WHERE view_count >= 200
ORDER BY view_count DESC
LIMIT 10;
```

## 4-3. 회원 이메일이 example.com인 사용자 목록
```sql
SELECT username, email
FROM users
WHERE email LIKE '%@example.com'
ORDER BY username;
```

## 4-4. 특정 사용자가 작성한 최신 게시글 5개 
```sql
SELECT id, user_id, title
FROM posts
WHERE user_id = 10
ORDER BY id DESC
LIMIT 5;
```

---

# 🧩 실습 / 과제

## 0. 실습용 더미 데이터(dump data, seed data) 업데이트
```sql
UPDATE posts
SET view_count = 120
WHERE id BETWEEN 1 AND 200;

UPDATE posts
SET view_count = 250
WHERE id BETWEEN 300 AND 400;
```


## 1. WHERE

- `users` 테이블에서 이메일(`email`) 이 `@example.com` 으로 끝나는 사용자만 조회하시오.

- `users` 테이블에서 닉네임(`nickname`) 이 `사용자10` 인 사용자의 모든 정보를 조회하시오.

- `users` 테이블에서 이메일에 숫자 `5` 가 포함된 사용자만 조회하시오. (예: user005@example.com 처럼)

- `posts` 테이블에서 제목(`title`) 에 `타이틀` 라는 단어가 포함된 게시글만 조회하시오.


## 2. ORDER BY + LIMIT

- `posts` 테이블에서 가장 최근에 작성된 게시글 10개를 조회하시오.

- `users` 테이블에서 가장 최근에 가입한 사용자 10명 조회하시오.

- `posts` 테이블에서 조회수(`view_count`) 가 높은 순으로 정렬한 뒤, 상위 20개 게시글의 `id`, `title`, `view_count` 를 조회하시오.

## 3. LIMIT + OFFSET (페이지네이션)
> 페이지 크기 = 10개 ( LIMIT 10 개 기준 )
- `posts` 테이블에서 최신순으로 정렬했을 때, 1페이지(1~10번째) 게시글의 `id`, `title` 을 조회하시오. 

- 위와 동일한 정렬 기준으로, 2페이지(11~20번째) 게시글의 `id`, `title` 을 조회하시오.

- 위와 동일한 정렬 기준으로, 5페이지(41~50번째) 게시글의 `id`, `title` 을 조회하시오.

## 4. WHERE + ORDER BY 조합

- `posts` 테이블에서 조회수(`view_count`)가 100 이상인 게시글의 `id`, `title`, `view_count` 를 조회수 내림차순으로 정렬하시오.

- `posts` 테이블에서 제목(`title`)에 테스트 가 포함되어 있고, 조회수(`view_count`)가 50 이상인 게시글을 최신순으로 정렬 하시오.

- `users` 테이블에서 이메일(`email`) 도메인이 @example.com 이 아닌 사용자들만 조회하고 오름차순으로 정렬하시오.

- `posts` 테이블에서 `user_id` 가 `3`인 사용자가 작성한 게시글 중, 조회수(`view_count`)가 100 이상인 게시글을 최신순으로 정렬하시오.

## 5. WHERE + ORDER BY + LIMIT 조합

- `posts` 테이블에서 조회수(`view_count`)가 200 이상인 게시글들 중에서, 조회수 내림차순으로 정렬했을 때 상위 10개의 `id`, `title`, `view_count` 만 조회하시오.

- `users` 테이블에서 이메일(`email`)이 @example.com 으로 끝나는 사용자들만 대상으로, 가장 최근에 가입한 순서대로 정렬하여 상위 5명의 `id`, `username`, `email`, `created_at` 을 조회하시오.

- `posts` 테이블에서 제목(`title`)에 타이틀 이 포함된 게시글 중, id 기준 최신순으로 정렬했을 때 3페이지(21~30번째)에 해당하는 게시글의 `id`, `title` 을 조회하시오.

- `posts` 테이블에서 `user_id` 가 `7` 인 사용자가 작성한 게시글 중, 조회수(`view_count`)가 `150` 이상인 게시글을 최신순으로 정렬했을 때 상위 5개의 `id`, `title`, `view_count` 를 조회하시오