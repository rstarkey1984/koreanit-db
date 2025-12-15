# 데이터 타입 매핑 (MySQL 기준)

## 📘 학습 개요

이 문서는 MySQL에서 자주 사용하는 데이터 타입을 이해하고,
“어떤 속성에 어떤 타입을 선택해야 하는지” 기준을 잡아주는 것을 목표로 한다.

## 💡 주요 내용

- 문자열 타입 (CHAR / VARCHAR / TEXT)

- 숫자 타입 (정수 / 실수 / 소수형)

- 날짜/시간 타입 (DATETIME / TIMESTAMP)

- 논리값(Boolean)

---


# 1. 문자열 타입 (CHAR / VARCHAR / TEXT)


## 1-1. CHAR vs VARCHAR

| 타입         | 특징                   | 용도                  |
| ---------- | -------------------- | ------------------- |
| CHAR(n)    | 고정 길이. 항상 n “문자 길이”만큼 공간 사용 | 국가코드, 고정길이 코드값      |
| VARCHAR(n) | 가변 길이. 실제 길이만큼 저장    | 아이디, 닉네임, 이메일 등 대부분 |

### 선택 기준

- 길이가 항상 일정하다 → CHAR

- 길이 제한은 있지만 입력 값이 다양하다 → VARCHAR

- 검색·정렬이 많고 인덱스를 활용해야 한다 → VARCHAR

### 예제
```sql
username VARCHAR(50) NOT NULL
country_code CHAR(2)
```


## 1-2. TEXT 계열
> TEXT는 길이가 매우 길거나 예측할 수 없는 문자열 데이터를 저장할 때 사용한다. CHAR/VARCHAR처럼 테이블의 row 공간에 직접 저장되지 않고, 별도의 영역에 저장된 후 포인터만 row에 저장되는 구조라 대용량 텍스트 데이터에 적합하다.

| 타입             | 최대 크기     | 용도                         |
| -------------- | --------- | -------------------------- |
| **TINYTEXT**   | 255 bytes | 매우 짧은 코멘트, 메모, 간단한 설정값     |
| **TEXT**       | 65 KB     | 일반 게시판 본문, 설명(description) |
| **MEDIUMTEXT** | 16 MB     | 블로그 본문, 뉴스 기사, Markdown 문서 |
| **LONGTEXT**   | 4 GB      | HTML 콘텐츠, JSON 로그, 대용량 문서  |



### 예제
```sql
content TEXT
```

--- 

# 2. 숫자 타입 (정수 / 실수 / 소수형)

> MySQL에서 숫자는 크게 **정확한 숫자(정수·DECIMAL)**와 근사값 숫자(FLOAT·DOUBLE) 로 나뉜다. 필요한 정밀도와 용도에 따라 타입을 선택해야 한다.

## 2-1. 정수 타입 (Integer)
> 소수점이 없는 정확한 숫자를 저장한다. 주로 ID, 개수, 카운트, 상태값처럼 오차가 절대 발생하면 안 되는 값에 사용한다.

| 타입      | 범위 (UNSIGNED 기준) | 용도                     |
| ------- | ---------------- | ---------------------- |
| TINYINT | 0 ~ 255          | 상태값, Boolean 대체        |
| INT     | 0 ~ 약 43억        | PK, user_id, 조회수 등 대부분 |
| BIGINT  | 매우 큼             | 주문번호, 대규모 서비스 ID       |

> 실무에서는 INT UNSIGNED가 가장 많이 사용된다.


### 예제:
```sql
id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
stock INT UNSIGNED NOT NULL DEFAULT 0
```

## 2-2. 실수 타입( FLOAT, DOUBLE )
> 소수점을 표현하지만 부동소수점 방식으로 저장되므로 정밀도 오차가 발생한다. 즉, 정확한 계산이 필요 없는 경우에만 사용해야 한다.

주로 다음과 같은 용도에 적합하다:

- GPS 좌표(위도/경도)

- 대략적인 통계 수치

| 타입     | 특징             | 추천 용도        |
| ------ | -------------- | ------------ |
| FLOAT  | 4바이트, 정밀도 낮음   | 대충 계산되는 값    |
| DOUBLE | 8바이트, 정밀도 더 높음 | GPS |


```sql
latitude  DOUBLE   -- 37.566535 위도
longitude DOUBLE   -- 126.9779692 경도
```

## 2-3. 소수 타입 ( DECIMAL )
> 정확한 소수 값을 저장하는 타입으로, 내부적으로 정수를 저장하고 소수점 위치만 따로 관리하는 방식이라 오차가 없다.  
> 금융/회계 분야에서 필수적으로 사용된다.

| 항목                | 설명                                           |
| ----------------- | -------------------------------------------- |
| **p (precision)** | 전체 자릿수(정수 + 소수). 예: `DECIMAL(10,2)` → 총 10자리 |
| **s (scale)**     | 소수점 아래 자릿수. 예: `DECIMAL(10,2)` → 소숫점 2자리     |

### 용도

- 가격(price), 금액

- 포인트, 마일리지

- 할인율, 수수료(rate)

- 정확한 측정값 저장

### 예시:
```sql
price DECIMAL(10,2)
discount_rate DECIMAL(5,2)
```

--- 

# 3. 날짜/시간 타입 (DATETIME / TIMESTAMP)

## 3-1. DATETIME

- 범위: 1000-01-01 00:00:00 ~ 9999-12-31 23:59:59 (MySQL 스펙 기준. 실제로는 일부 버전에서 더 이른 값도 저장되지만 보장되지 않음)

- 장기 보존이 필요한 값(created_at, deleted_at)에 적합

- 타임존 영향을 받지 않음 ( 서버 타임존을 바꿔도 값 자체가 변하지 않음 )

- MySQL 5.6.5+ 부터는 DEFAULT CURRENT_TIMESTAMP, ON UPDATE CURRENT_TIMESTAMP 기능을 TIMESTAMP처럼 사용할 수 있음

### 예:
```sql
updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

## 3-2. DATE

- 년/월/일만 필요할 때 사용 (예: 생년월일)
- 범위: 1000-01-01 ~ 9999-12-31

### 예:
```sql
birthday DATE
```

## 3-3. TIMESTAMP

- 범위: 1970년 ~ 2038년 (내부적으로 32bit UNIX time 사용 → 2038년 이후 저장 불가)

- 저장 시 UTC로 변환하고, 조회 시 세션 타임존 기준으로 다시 변환됨 ( 타임존 영향을 받음 )

- DEFAULT CURRENT_TIMESTAMP, ON UPDATE CURRENT_TIMESTAMP 자동 갱신 기능 지원 (하지만 DATETIME도 이제 동일 기능 가능)

- 2038 문제 때문에 장기간 데이터 보존에는 부적합

### 예:
```sql
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

> 2038 문제는 시간이 32bit로 저장될 때 발생하는 날짜 오버플로 버그이며, MySQL TIMESTAMP는 이 한계를 그대로 가지므로 장기 데이터에는 DATETIME을 쓰는 것이 안전하다.

--- 

# 4. 논리값(Boolean)

> MySQL에는 Boolean 타입이 없으므로 TINYINT 를 사용한다.

### 예:
```sql
is_active TINYINT NOT NULL DEFAULT 1
```

--- 

# 5. 실제 설계에서의 선택 기준 정리

| 데이터     | 추천 타입               | 이유        |
| ------- | ------------------- | --------- |
| 이메일     | VARCHAR(100) UNIQUE | 중복 방지 필요  |
| 닉네임 | VARCHAR(50)         | 가변 길이 문자열  |
| 게시글 제목  | VARCHAR(200)        | 가변 길이 문자열 |
| 본문      | TEXT                | 길이 예측 불가  |
| 금액      | DECIMAL(10,2)       | 오차 없는 계산  |
| 조회수     | INT UNSIGNED        | 음수 필요 없음  |
| 생성일시    | DATETIME            | 장기 기록 적합  |
| 수정일시    | DATETIME           | 장기 기록 적합   |


---

# 🧩 실습 / 과제

## 1. 데이터 타입 골라보기

아래 속성들에 맞는 **가장 적절한 MySQL 데이터 타입**을 골라보세요.

| 속성       | 설명                             | 내가 선택한 타입 |
| ---------- | -------------------------------- | --------------- |
| birthday   | 생년월일                         |                 |
| phone      | 전화번호 (010-xxxx-xxxx 형식)   |                 |
| bio        | 자기소개 (약 500~2000자)         |                 |
| rating     | 평점(0~5, 소수점 1자리, 예: 4.5) |                 |
| status     | 회원 상태(활성/비활성)           |                 |
| joined_at  | 가입 일시                        |                 |

## 2. 타입이 잘못 선택된 테이블 수정하기

> 아래 잘못된 테이블을 보고,  
> **적절한 데이터 타입과 제약조건**을 사용해서 `CREATE TABLE` 문을 다시 작성해보세요.

```sql
CREATE TABLE wrong_users (
    id BIGINT,
    username TEXT,
    phone INT,
    price FLOAT,
    created_at TIMESTAMP,
    memo VARCHAR(30000)
);