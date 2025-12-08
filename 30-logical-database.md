# 논리 데이터베이스 설계 (Logical Database Design)
## 📘 학습 개요

이 문서는 요구사항을 분석하여
개체(Entity), 속성(Attribute), 관계(Relationship)을 정의하고,
정규화를 거쳐 논리 ERD를 만드는 과정을 설명한다.

앞에서 만든 users, posts 테이블을 기본 예제로 사용하고,
여기에 comments 테이블을 설계하면서 전체적인 흐름을 학습한다.

## 💡 주요 내용

- 요구사항 분석

- 개체(Entity) / 속성(Attribute) 정의

- 관계 정의 (1:1, 1:N, N:M)

- 정규화 (1NF / 2NF / 3NF, 직관 위주)

- 논리 ERD 작성

---

# 1. 요구사항 분석
> “데이터베이스 설계는 요구사항에서 시작한다.”


### 예시 요구사항:

- 사용자는 회원가입을 할 수 있다.

- 사용자는 게시글을 작성할 수 있다.

- 게시글에는 제목, 내용, 작성시간, 조회수가 있다.

- 게시글에는 여러 댓글이 달릴 수 있다.

- 댓글에는 내용, 작성자, 작성시간이 있다.

## 1-1. 명사 추출 (개체 / 속성 후보)

> 문장에서 명사를 뽑으면 다음과 같다.

### 개체 후보

- 사용자

- 게시글

- 댓글

### 속성 후보

- 제목

- 내용

- 작성시간

- 조회수

- 이메일

- 비밀번호

- 닉네임


## 1-2. 동사 추출 (관계 후보)

> 문장에서 동사를 뽑아 관계를 찾는다.

### 예시

- 사용자가 게시글을 작성한다

- 사용자가 댓글을 작성한다

- 게시글에 댓글이 달린다

### 이것을 관계로 바꾸면 다음과 같다.

- 사용자와 게시글은 작성 관계

- 사용자와 댓글은 작성 관계

- 게시글과 댓글은 연결 관계

---

# 2. 개체(Entity)와 속성(Attribute) 정의

### 개체 목록

- User

- Post

- Comment

## 2-1. User 개체
| 속성명        | 설명       |
| ---------- | -------- |
| id         | 사용자 ID ( PK )   |
| username   | 로그인 아이디  |
| password   | 비밀번호(해시) |
| nickname   | 닉네임      |
| email      | 이메일      |
| created_at | 가입일시     |
| updated_at | 수정일시     |

## 2-2. Post 개체
| 속성명        | 설명               |
| ---------- | ---------------- |
| id         | 게시글 ID ( PK )           |
| user_id    | User 개체 ID |
| title      | 게시글 제목           |
| content    | 게시글 내용           |
| view_count | 조회수              |
| created_at | 작성일시             |
| updated_at | 수정일시             |

## 2-3. Comment 개체
| 속성명        | 설명               |
| ---------- | ---------------- |
| id         | 댓글 ID ( PK )            |
| post_id    | Post 개체 ID  |
| user_id    | User 개체 ID |
| content    | 댓글 내용            |
| created_at | 작성일시             |


---

# 3. 관계 정의

## 3-1. 1:N 관계
> 1:N 관계는 하나의 레코드가 여러 개의 다른 레코드와 연결되는 관계를 의미한다.

### 관계(Cardinality) 표현 기호
```
1 ---< N
1 -----< N
User ---< Post
```

### User ---< Post

- 한 사용자는 여러 개의 게시글을 작성할 수 있다.

- posts.user_id 가 users.id 를 참조한다.

### User ---< Comment

- 한 사용자는 여러 댓글을 작성할 수 있다.

- comments.user_id 가 users.id 를 참조한다.

### Post ---< Comment

- 한 게시글에는 여러 댓글이 달린다.

- comments.post_id 가 posts.id 를 참조한다.


## 3-2. 1:1 관계
> 1:1 관계는 한 레코드가 다른 테이블의 레코드 하나와만 연결되는 구조이다. 대부분은 선택적(optional) 관계로 구현된다.

### 관계(Cardinality) 표현 기호

```
1 --- 0 or 1 ( 선택적 )
User --- UserProfile? ( ? 표시는 존재 여부가 선택적(optional)임을 의미한다. )
```

### User 개체

| 속성명        | 설명        |
| ---------- | --------- |
| id         | 사용자 고유 ID ( PK ) |
| username   | 로그인 아이디   |
| password   | 비밀번호(해시)  |
| nickname   | 사용자 닉네임   |
| email      | 이메일       |
| created_at | 가입 시간     |
| updated_at | 수정 시간     |


### UserProfile 개체
| 속성명               | 설명                     |
| ----------------- | ---------------------- |
| user_id           | User 개체 ID ( PK ) |
| bio               | 소개글                    |
| profile_image_url | 프로필 이미지                |
| birthday          | 생일                     |

회원가입 때 필요 없는 정보들(프로필, 생일, 소개글 등)을
필수 컬럼과 분리해서
핵심 테이블(users)을 가볍게 유지할 수 있다.

## 3-3. N:M 관계
> N:M 관계는 교차 테이블을 사용해 1:N + 1:N 구조로 분해한다

### 관계(Cardinality) 표현 기호

```
N >---< N
Order >---< Product
```

하나의 주문(Order)에는 여러 상품(Product)이 포함될 수 있고,
하나의 상품(Product)도 여러 주문(Order)에 등장할 수 있다.

이것은 직접 저장할 수 없기 때문에 교차 테이블 OrderProducts 를 만들어 다음처럼 표현한다.

### Order 개체

| 속성명         | 설명         |
| ----------- | ---------- |
| id          | 주문 고유 ID ( PK )  |
| user_id     | User 개체 ID |
| total_price | 총 주문 금액    |
| created_at  | 주문 생성 시간   |


### Product 개체
| 속성명   | 설명       |
| ----- | -------- |
| id    | 상품 고유 ID ( PK ) |
| name  | 상품 이름    |
| price | 상품 가격    |

### OrderProducts 개체 (교차 테이블, N:M 분해)

| 속성명        | 설명                 |
| ---------- | ------------------ |
| order_id   | Order 개체 ID ( PK )  |
| product_id | Product 개체 ID ( PK ) |
| quantity   | 주문한 상품 수량          |
| price      | 주문 당시 상품 가격(스냅샷)   |


### 관계 표현

```
Order ---< OrderProducts >--- Product
```

--- 

# 4. 정규화

> 정규화는 중복을 줄이고 이상 현상을 방지하는 테이블 구조 설계 방법이다.

실무에서는 다음 3가지를 가장 많이 사용한다.

- 제1정규형(1NF): 컬럼에는 원자값(더 나눌 수 없는 값)만 저장

- 제2정규형(2NF): 복합키일 때 부분 종속 제거

- 제3정규형(3NF): 키가 아닌 속성이 다른 속성에 종속되지 않도록 분리

## 4-1. 제1정규형 (1NF)

> 한 컬럼에는 한 값만 저장해야 한다.


### User 개체 - 잘못된 예 ( 정규화 이전 구조 )
| 속성명        | 설명        |
| ---------- | --------- |
| id         | 사용자 고유 ID ( PK ) |
| username   | 로그인 아이디   |
| ...   | ...  |
| interests  | 관심사 ( 예: "축구,농구,야구" ) |

### 문제점

- 정규화 원칙 위반 - 콤마로 묶인 값은 1개의 컬럼에 여러 개의 값을 저장하는 것이기 때문에 정규화 규칙을 깨고 유지보수 어려움.

- 인덱스 미사용 ( 성능 나쁨 ) - LIKE '%축구%' 같은 쿼리를 사용했을때 데이터 많아지면 풀 스캔이 일어나면서 성능이 느려짐. (단, '축구%'처럼 접두(prefix) 검색이면 인덱스를 사용 가능)

### 올바른 구조

- 사용자별로 관심사(Interest) 내용을 저장하는 테이블을 따로 분리한다.

### UserInterest 개체
| 속성명        | 설명        |
| ---------- | --------- |
| user_id    | User 개체 ID |
| interest  | 관심사 ( 예: "축구" ) |
```
user_interests 테이블 내용 
------------------------------------
| user_id | interest |
------------------------------------
|    1    | 축구      |
|    1    | 농구      |
|    1    | 야구      |
------------------------------
```

이렇게 하면:

- 특정 관심사 사용자 조회 가능

- 관심사 추가/삭제 쉬움

- 인덱스 성능 향상

## 4-2. 제2정규형 (2NF)

> 복합키(PK가 여러 컬럼)일 때, 키의 일부에 종속된 속성을 제거해야 한다.

### OrderProducts 개체 - 잘못된 예 (정규화 이전 구조)
| 속성명           | 설명              |
| ------------- | --------------- |
| order_id      | 주문 ID ( PK ) |
| product_id    | 상품 ID ( PK ) |
| user_id       | 주문한 사용자 ID      |
| product_name  | 주문된 상품 이름       |
| product_price | 주문된 상품 가격       |
| quantity      | 수량 |
| created_at    | 주문 생성 시간        |

테이블 내용 예시:
| order_id | product_id | user_id | product_name | product_price | quantity | created_at |
| -------- | ---------- | ------- | ------------ | ------------- | ---------- | --- |
| 1        | 11         | 7       | 키보드          | 50000         | 1 | 1월 1일      |
| 1        | 12         | 7       | 마우스          | 20000         | 2 | 1월 1일      |
| 1        | 13         | 7       | 패드           | 10000         | 1 |  1월 1일      |
| 1        | 14         | 7       | 스피커          | 15000         | 1 | 1월 1일      |
| 2        | 11         | 8       | 키보드          | 50000         | 1 | 1월 1일      |
| 2        | 12         | 8       | 마우스          | 20000         | 2 | 1월 1일      |
| 2        | 13         | 8       | 패드           | 10000         | 1 |  1월 1일      |
| 2        | 14         | 8       | 스피커          | 15000         | 1 | 1월 1일      |


### 문제점

- user_id, created_at 이 order_id 기준으로 종속되어서 반복 저장됨

- product_name, product_price 도 product_id 기준으로 종속됨

  즉, 복합키 전체에 완전 종속되지 않고 일부 키에만 종속될 경우 2NF 위반


### 올바른 구조

- 주문 정보, 상품 정보, 주문-상품 정보를 각각 Order / Product / OrderProducts 로 분리해 중복을 제거한다.

- 상품 이름·가격은 Product 에 한 번만 저장하여 수정 시 일관성이 유지된다.

- 주문 당시 금액은 OrderProducts 에 별도로 저장해 과거 주문 기록이 변경되지 않도록 보존한다.

### Order 개체

| 속성명         | 설명         |
| ----------- | ---------- |
| id          | 주문 고유 ID ( PK )  |
| user_id     | 주문한 사용자 ID |
| total_price | 총 주문 금액    |
| created_at  | 주문 생성 시간   |


### Product 개체
| 속성명   | 설명       |
| ----- | -------- |
| id    | 상품 고유 ID ( PK ) |
| name  | 상품 이름    |
| price | 상품 가격    |

### OrderProducts 개체 (교차 테이블, N:M 분해)

| 속성명        | 설명                 |
| ---------- | ------------------ |
| order_id   | Order 개체 ID ( PK )  |
| product_id | Product 개체 ID ( PK )  |
| quantity   | 주문한 상품 수량          |
| price      | 주문 당시 상품 가격(스냅샷)   |

## 4-3. 제3정규형 (3NF)

> 키가 아닌 속성이 또 다른 속성에 종속되면 분리한다.

### UserAddress - 잘못된 예 ( 정규화 이전 구조 )
| 속성명     | 설명          |
| ------- | ----------- |
| user_id | User 개체 ID  |
| zipcode | 우편번호        |
| address | 상세 주소       |
| city    | 도시(시/군/구)   |


### 문제점

- zipcode 에 따라 city 가 결정됨

- city 를 수정하려면 모든 row 를 업데이트해야 함

- zipcode → city 종속 관계가 존재 → 3NF 위반

### 올바른 구조

- 우편번호(zipcode)와 도시/도로명(address_base)을 별도 zipcodes 테이블로 분리해 데이터 중복과 불일치를 제거한다.

- users_address 테이블은 zipcode_id 만 참조하여 주소 구조를 단순하고 일관되게 유지한다.

- 도시나 도로명 정보 변경 시 zipcodes 테이블만 수정하면 되어 유지보수성이 향상된다.


### Zipcodes 개체
| 속성명          | 설명               |
| ------------ | ---------------- |
| id           | 우편번호 ID (PK) |
| zipcode      | 우편번호             |
| address_base | 시/군/구·도로명 기본 주소  |


### UserAddress 개체
| 속성명        | 설명                  |
| ---------- | ------------------- |
| user_id    | User 개체 ID     |
| zipcode_id | Zipcodes 개체 ID  |
| address    | 상세 주소               |

---

# 5. 논리 ERD ( Entity–Relationship Diagram ) 작성

> 논리 ERD는 시스템을 구성하는 개체, 속성, 관계를 개념적으로 표현한다.  
> 아래는 이 문서에서 등장한 모든 개체를 도메인(기능 단위) 별로 묶어 정리한 것이다.

## 5-1. 게시판 도메인 (User, Post, Comment)

### User 개체
| 속성명        | 설명          |
| ---------- | ----------- |
| id         | 사용자 ID (PK) |
| username   | 로그인 아이디     |
| password   | 비밀번호(해시)    |
| nickname   | 닉네임         |
| email      | 이메일         |
| created_at | 가입일시        |
| updated_at | 수정일시        |


### Post 개체
| 속성명        | 설명          |
| ---------- | ----------- |
| id         | 게시글 ID (PK) |
| user_id    | User 개체 ID  |
| title      | 게시글 제목      |
| content    | 내용          |
| view_count | 조회수         |
| created_at | 작성일시        |
| updated_at | 수정일시        |

### Comment 개체
| 속성명        | 설명          |
| ---------- | ----------- |
| id         | 댓글 ID (PK)  |
| post_id    | Post 개체 ID  |
| user_id    | User 개체 ID |
| content    | 댓글 내용       |
| created_at | 댓글 작성일시     |


### 게시판 도메인 텍스트 ERD
```
User ---< Post
User ---< Comment
Post ---< Comment
```

## 5-2. 프로필 도메인 (User, UserProfile)

### User 개체

| 속성명      | 설명          |
| -------- | ----------- |
| id       | 사용자 ID (PK) |
| username | 로그인 아이디     |
| password | 비밀번호(해시)    |


### UserProfile 개체

| 속성명               | 설명                       |
| ----------------- | ------------------------ |
| user_id           | User 개체 ID |
| bio               | 소개글                      |
| profile_image_url | 프로필 이미지 URL              |
| birthday          | 생일                       |


### 프로필 도메인 텍스트 ERD
> UserProfile 은 선택적(없을 수도 있음).
```
User --- UserProfile?
```


## 5-3. 관심사 도메인 (User, UserInterest)

### User 개체

| 속성명        | 설명          |
| ---------- | ----------- |
| id         | 사용자 ID (PK) |
| username   | 로그인 아이디     |
| password   | 비밀번호        |
| nickname   | 닉네임         |
| email      | 이메일         |
| created_at | 가입일시        |
| updated_at | 수정일시        |

### UserInterest 개체

| 속성명      | 설명          |
| -------- | ----------- |
| user_id  | User 개체 ID |
| interest | 관심사         |


### 관심사 도메인 텍스트 ERD

```
User ---< UserInterest
```

## 5-4. 주문 도메인 (Order, Product, OrderProducts)


### Order 개체

| 속성명         | 설명         |
| ----------- | ---------- |
| id          | 주문 ID (PK) |
| user_id     | 주문한 사용자 ID |
| total_price | 총 금액       |
| created_at  | 주문 생성 시간   |


### Product 엔티티

| 속성명   | 설명         |
| ----- | ---------- |
| id    | 상품 ID (PK) |
| name  | 상품 이름      |
| price | 상품 가격      |


### OrderProducts 개체 (교차 테이블)

| 속성명        | 설명               |
| ---------- | ---------------- |
| order_id   | Order 개체 ID       |
| product_id | Product 개체 ID       |
| quantity   | 주문한 상품 수량        |
| price      | 주문 당시 상품 가격(스냅샷) |

### 주문 도메인 텍스트 ERD

```
Order ---< OrderProducts >--- Product
```



## 5-5. 전체 도메인 요약 텍스트 ERD
```
User ---< Post
User ---< Comment
Post ---< Comment

User --- UserProfile?

User ---< UserInterest

Order ---< OrderProducts >--- Product
```

---

# 🧩 실습 / 과제

## 게시판에 “좋아요(likes)” 기능을 추가하려고 합니다.

### 요구사항:

- 사용자는 여러 게시글에 좋아요를 누를 수 있다.

- 하나의 게시글은 여러 사용자에게 좋아요를 받을 수 있다.

- 한 사용자는 같은 게시글에 좋아요를 한 번만 누를 수 있다.

- 좋아요 정보는 post_likes 테이블에 저장한다.

### 1. 이 요구사항을 만족하는 텍스트 ERD를 작성해보세요. ( User 개체, Post 개체, PostLike 개체 )

### 2. PostLike 개체에 들어가야 할 컬럼들을 써보고, 어떤 컬럼 조합이 PK가 되어야 하는지 적어보세요.