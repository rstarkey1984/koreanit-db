# 반정규화 전략 (Denormalization Strategy)

## 📘 학습 개요

정규화는 데이터 중복을 제거하고 무결성을 보장하는 데 매우 중요하지만, 조회 성능이 중요한 상황에서는 오히려 성능 저하의 원인이 될 수 있다.


반정규화는 정규화된 구조를 의도적으로 일부 깨뜨려 조회 성능을 개선하는 전략이다.

## 💡 주요 내용


- 반정규화가 필요한 이유

- 언제, 왜 사용하는지

- 대표적인 반정규화 유형과 주의점을 실무 예제 중심으로 이해한다.


---

# 1. 반정규화란 무엇인가?
> **반정규화(Denormalization)** 란 정규화를 통해 분리한 테이블 구조를, 성능이나 실무 편의성을 위해 의도적으로 일부 중복을 허용하는 설계 전략이다.

| 구분     | 정규화      | 반정규화     |
| ------ | -------- | -------- |
| 목표     | 데이터 무결성  | 조회 성능    |
| 데이터 중복 | 제거     | 허용     |
| JOIN   | 많음       | 줄어듦      |
| 쓰기 비용  | 낮음       | 높아질 수 있음 |
| 읽기 성능  | 상대적으로 느림 | 빠름       |

--- 

# 2. 왜 반정규화를 할까?

> 정규화의 목적은 데이터 무결성   
> 반정규화의 목적은 조회 성능 & 실무 효율

## 2-1. 현실에서는 읽기(SELECT) 가 훨씬 많다.

최신 게시물 10개 조회하는 쿼리
```sql
SELECT
    p.id,
    p.title,
    u.username,
    COUNT(c.id) AS comment_count
FROM (
    SELECT id, user_id, title
    FROM posts
    ORDER BY id DESC
    LIMIT 10
) p
JOIN users u
    ON u.id = p.user_id
LEFT JOIN comments c
    ON c.post_id = p.id
GROUP BY
    p.id,
    p.title,
    u.username
ORDER BY p.id DESC;
```


## 2-2. 반정규화 고려 시점

- 조회(Read)가 쓰기(Write)보다 훨씬 많다

- 특정 JOIN 쿼리가 매우 자주 실행된다

- 데이터 변경 빈도가 낮다

### 하지만 “미리 반정규화”는 하면안됨. 성능 문제가 실제로 확인된 이후 적용

---

# 3. 대표적인 반정규화 유형

## 3-1. 중복 컬럼 추가 (가장 흔함)

### 정규화 구조
```
users
- id
- nickname

posts
- id
- user_id (FK users.id)
```

### 반정규화 구조
```
posts
- id
- user_id
- nickname ← users 테이블에도 있는 중복 컬럼
```

```sql
SELECT id, nickname FROM posts;
```


## 3-2. 집계 결과 컬럼 저장

### 정규화 구조
```
posts
- id

comments
- id
- user_id (FK users.id)
- post_id (FK posts.id)
```

### 예시: 댓글 수
```sql
SELECT COUNT(*) FROM comments WHERE post_id = 1;
```

### 반정규화 구조
```
posts
- id
- user_id
- nickname ← users 테이블에도 있는 중복 컬럼
- comments_cnt ← 집계 결과 저장 컬럼
```

```sql
SELECT id, nickname, comments_cnt FROM posts;
```

---

# 4. 반정규화의 장단점

| 구분     | 항목            | 설명                                   |
| ------ | ------------- | ------------------------------------ |
| **장점** | JOIN 감소       | 여러 테이블을 조인하지 않아도 되어 쿼리가 단순해짐         |
|        | 조회 성능 향상      | JOIN 비용 감소로 SELECT 성능이 크게 개선됨        |
|        | 쿼리 단순화        | 복잡한 JOIN / 서브쿼리 없이 직관적인 SQL 작성 가능    |
|        | 대량 트래픽 처리에 유리 | 읽기 위주의 서비스에서 DB 부하를 효과적으로 줄일 수 있음    |
| **단점** | 데이터 중복        | 동일한 데이터가 여러 테이블에 저장됨                 |
|        | 무결성 관리 어려움    | 한쪽 데이터 수정 시 다른 테이블도 함께 갱신 필요         |
|        | UPDATE 로직 복잡  | INSERT / UPDATE / DELETE 시 추가 로직이 필요 |
|        | 데이터 불일치 위험    | 갱신 누락 시 서로 다른 값이 저장될 수 있음            |


### “쓰기보다 읽기가 훨씬 많은 서비스”에서만 의미 있음


## 4-1. 반정규화를 했을때 실서비스 단계에서 쿼리 실행이 어떻게 추가되고 복잡해지나?

### 댓글 쓰기
```sql
insert into comments (post_id, user_id, comment) values (1, 1, '댓글');

select count(*) as cnt from comments where post_id = 1;

update posts set comments_cnt = ? where post_id = 1;
```

### 댓글 삭제
```sql
delete from comments where id = 1

select count(*) as cnt from comments where post_id = 1;

update posts set comments_cnt = ? where post_id = 1;
```

---

# 💡 요약정리

- 반정규화는 “쓰기보다 읽기가 훨씬 많은 서비스”에서만 의미가 있으며, 반드시 정규화를 충분히 이해한 뒤 선택해야 한다.

- 반정규화는 설계 단계가 아니라 튜닝 단계에서 사용하며, JOIN 쿼리가 느리면 선택할 수 있다.

- 자주 바뀌는 데이터나 무결성이 매우 중요한 데이터는 반정규화를 사용하면 안됨.

- 반정규화는 **“조회할 때마다 계산하거나 JOIN할 값을, 미리 저장해두는 것”** 이고, 쓰기 로직이 복잡해지는 책임을 개발자가 진다.