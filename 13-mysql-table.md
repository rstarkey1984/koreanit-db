# 개발환경 구축 ( MySQL 테이블 생성 및 초기 데이터 적재 )

## 📘 학습 개요

이 문서는 **MySQL 데이터베이스를 생성하고, 테이블을 만들고, 초기 테스트 데이터를 자동으로 넣는 방법**을 다룹니다.  
실습이 끝나면 다음 내용을 스스로 할 수 있게 됩니다.

## 💡 주요 내용

- MySQL Workbench 설치

- 데이터베이스(Database)와 테이블(Table)의 개념

- `users`, `posts` 테이블 생성 SQL

- 프로시저를 이용한 사용자 50명 / 게시글 1000개 자동 입력

---

# 0. MySQL Workbench 설치

다운로드 `https://www.npackd.org/p/com.mysql.dev.MySQLWorkbench/8.0.25?utm_source=chatgpt.com` 

( 선택 ) Microsoft Visual C++ 2015-2022 Redistributable (x64) 다운로드 `https://aka.ms/vc14/vc_redist.x64.exe`

## 0-1. 접속하기

### 1. Database 메뉴에서 **Manage Connections** 선택

### 2. 왼쪽아래 **[ New ]** 버튼 클릭

### 3. **Connection Name** : wsl-ubuntu24

### 4. **Hostname** : 127.0.0.1

### 5. **Port** : 3308

### 6. **Username** : test

### 7. **Password** : [ Store in Vault ] 클릭해서 비밀번호 test123 입력 후 엔터

### 8. **Default Schema** : testdb

--- 

# 1. 데이터베이스 및 테이블 관계
> MySQL에서는 **데이터베이스(Database) = 스키마(Schema)** 같은 의미 입니다.
    

## 1-1. Database (데이터베이스)란?
> 여러 테이블을 묶는 폴더 같은 개념
```
MySQL 서버 (DBMS)
        └─ Database (Schema)
              └─ Table (테이블)
              └─ Table (테이블)
              └─ Table (테이블)
```
- Database/Schema는 테이블들을 보관하는 논리적 공간

## 1-2. Table (테이블) 이란?
> 행(Row)과 열(Column)로 이루어진 2차원 구조의 데이터 저장소

users 테이블 예시:
```
+-----+---------+----------+-------------+---------------------+---------------------+------------+
| id  | username| password | nickname    | email               | created_at          | updated_at |
+-----+---------+----------+-------------+---------------------+---------------------+------------+
|   1 | user001 | user1234 | 사용자1      | user001@example.com | 2025-12-01 19:51:04 | NULL       |
|   2 | user002 | user1234 | 사용자2      | user002@example.com | 2025-12-01 19:51:04 | NULL       |
|   3 | user003 | user1234 | 사용자3      | user003@example.com | 2025-12-01 19:51:04 | NULL       |
|   4 | user004 | user1234 | 사용자4      | user004@example.com | 2025-12-01 19:51:04 | NULL       |
|   5 | user005 | user1234 | 사용자5      | user005@example.com | 2025-12-01 19:51:04 | NULL       |
|   6 | user006 | user1234 | 사용자6      | user006@example.com | 2025-12-01 19:51:04 | NULL       |
|   7 | user007 | user1234 | 사용자7      | user007@example.com | 2025-12-01 19:51:04 | NULL       |
|   8 | user008 | user1234 | 사용자8      | user008@example.com | 2025-12-01 19:51:04 | NULL       |
|   9 | user009 | user1234 | 사용자9      | user009@example.com | 2025-12-01 19:51:04 | NULL       |
|  10 | user010 | user1234 | 사용자10     | user010@example.com | 2025-12-01 19:51:04 | NULL       |
+-----+---------+----------+-------------+---------------------+---------------------+------------+
```

- 테이블 = 엑셀 시트 느낌

- 한 테이블은 하나의 주제(회원, 게시글 등)만 저장하도록 설계

## 1-3. 테이블 컬럼 데이터 타입(Data Types) 기본 이해
> 테이블을 만들기 전에, MySQL에서 자주 사용하는 데이터 타입을 간단히 이해하면 테이블 구조를 더 쉽게 읽을 수 있다.


| 분류                        | 타입               | 설명                                                      |
| ------------------------- | ---------------- | ------------------------------------------------------- |
| **정수형 (Integer)**         | **INT UNSIGNED** | 음수 없이 0~42억까지 저장. PK, 조회수, user_id 등에 가장 많이 사용          |
|                           | **BIGINT**       | 매우 큰 숫자 저장. 대규모 데이터 서비스에서 사용                            |
| **문자열 (String)**          | **VARCHAR(n)**   | 길이가 변하는 문자열. username, email, title 등에 사용               |
|                           | **TEXT**         | 긴 글 저장. 게시글 content, 댓글 내용에 사용                          |
| **날짜 / 시간 (Date & Time)** | **DATETIME**     | ‘YYYY-MM-DD HH:MM:SS’. INSERT 시 CURRENT_TIMESTAMP 사용 가능 |
| **논리형 (Boolean)**         | **TINYINT(1)**   | MySQL의 boolean 대체 타입                                    |


## 1-4. 테이블 설계에서 중요한 제약조건 (PK / UNIQUE)

### 1) Primary Key (PK) - 기본 키
> 테이블에서 각 행(Row)을 유일하게 구분하는 컬럼. 한 테이블에 단 하나만 존재할 수 있다.


### 2) UNIQUE - 중복 불가 조건
> 중복을 허용하지 않지만 NULL은 허용하는 제약조건. 한 테이블에 여러 개 존재할 수 있다.

| 항목       | PK        | UNIQUE                       |
| -------- | --------- | ---------------------------- |
| 중복 가능 여부 | 중복 불가   | 중복 불가                      |
| NULL 허용  | 허용 안 됨  | 허용됨 (MySQL에서는 다중 NULL도 가능) |
| 개수       | 테이블당 1개   | 여러 개 가능                      |
| 용도       | 행의 고유 식별자 | 특정 컬럼의 중복 방지 (이메일, 아이디 등)    |


--- 

# 2. 테이블 생성

```sql
USE testdb;

-- 기존 테이블 삭제
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS users;

-- 1. users 테이블 생성 
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
        COMMENT '사용자 고유 PK. 자동 증가 일련번호.',
        
    username VARCHAR(50) NOT NULL UNIQUE
        COMMENT '로그인 ID. 시스템 내에서 UNIQUE.',
        
    password VARCHAR(255) NOT NULL
        COMMENT '비밀번호 해시 값(평문 저장 금지).',
        
    nickname VARCHAR(50) NOT NULL
        COMMENT '사용자 표시 이름(별명). 화면에 노출되는 이름.',
        
    email VARCHAR(100) NOT NULL
        COMMENT '사용자 이메일 주소. 계정 식별 및 알림용.',
        
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT '계정 생성 시각. DEFAULT CURRENT_TIMESTAMP.',
        
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        COMMENT '계정 수정 시각. UPDATE 시 자동 갱신.'
)
COMMENT = '사용자 계정 정보를 저장하는 테이블';

-- 2. posts(게시글) 테이블 생성 
CREATE TABLE posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
        COMMENT '게시글 고유 PK. 자동 증가 일련번호.',
        
    user_id INT UNSIGNED NOT NULL
        COMMENT '게시글 작성자 ID. users.id 키 참조.',
        
    title VARCHAR(200) NOT NULL
        COMMENT '게시글 제목.',
        
    content TEXT NOT NULL
        COMMENT '게시글 본문 내용.',
        
    view_count INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT '게시글 조회수. 기본값 0.',
        
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT '게시글 생성 시각.',
        
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        COMMENT '게시글 수정 시각. UPDATE 시 자동 갱신.'
)
COMMENT = '사용자가 작성한 게시글 정보를 저장하는 테이블';
```

## 2-1. users 테이블 구조 
```
DESC users;
```
> **Field** = DESC 명령 결과에서 MySQL은 컬럼명을 Field로 표시하지만, SQL 표준 용어는 Column 입니다. 
```
+------------+--------------+------+-----+-------------------+-----------------------------+
| Field      | Type         | Null | Key | Default           | Extra                       |
+------------+--------------+------+-----+-------------------+-----------------------------+
| id         | int unsigned | NO   | PRI | NULL              | auto_increment              |
| username   | varchar(50)  | NO   | UNI | NULL              |                             |
| password   | varchar(255) | NO   |     | NULL              |                             |
| nickname   | varchar(50)  | NO   |     | NULL              |                             |
| email      | varchar(100) | NO   |     | NULL              |                             |
| created_at | datetime     | NO   |     | CURRENT_TIMESTAMP | DEFAULT_GENERATED           |
| updated_at | datetime     | YES  |     | NULL              | on update CURRENT_TIMESTAMP |
+------------+--------------+------+-----+-------------------+-----------------------------+
```
> **Extra** = 해당 컬럼에 적용된 추가적인 특수 동작을 표시하는 영역. 즉 컬럼에 적용된 특별한 동작(AUTO_INCREMENT, DEFAULT_GENERATED, on update CURRENT_TIMESTAMP 등)을 표시하는 MySQL의 추가 정보 칼럼.

| Field(컬럼명)         | Type(타입)          | Null | Key | Default           | Extra                       | 상세 설명                    |
| -------------- | -------------- |  ---- | --- | ----------------- | --------------------------- | ------------------------ |
| **id**        | `INT UNSIGNED` | NO   | PRI | NULL              | auto_increment              | 사용자 고유 PK. 자동 증가         |
| **username**         | `VARCHAR(50)`  | NO   | UNI | NULL              |                             | 로그인 아이디. 중복 불가           |
| **password**   | `VARCHAR(255)` |NO   |     | NULL              |                             | Bcrypt 등 해시 저장 시 충분한 길이  |
| **nickname**   | `VARCHAR(50)`  |  NO   |     | NULL              |                             | 닉네임                      |
| **email**      | `VARCHAR(100)` | NO   |     | NULL              |                             | 이메일                      |
| **created_at** | `DATETIME`     |  NO   |     | CURRENT_TIMESTAMP | DEFAULT_GENERATED           | 계정 생성 시간. INSERT 시 자동 기록 |
| **updated_at** | `DATETIME`     | YES  |     | NULL              | on update CURRENT_TIMESTAMP | UPDATE 시 자동으로 현재 시간으로 갱신      |




## 2-2. posts 테이블 구조 
```
DESC posts;
```
```
+------------+--------------+------+-----+-------------------+-----------------------------+
| Field      | Type         | Null | Key | Default           | Extra                       |
+------------+--------------+------+-----+-------------------+-----------------------------+
| id         | int unsigned | NO   | PRI | NULL              | auto_increment              |
| user_id    | int unsigned | NO   |     | NULL              |                             |
| title      | varchar(200) | NO   |     | NULL              |                             |
| content    | text         | NO   |     | NULL              |                             |
| view_count | int unsigned | NO   |     | 0                 |                             |
| created_at | datetime     | NO   |     | CURRENT_TIMESTAMP | DEFAULT_GENERATED           |
| updated_at | datetime     | YES  |     | NULL              | on update CURRENT_TIMESTAMP |
+------------+--------------+------+-----+-------------------+-----------------------------+
```


| 컬럼명          | 타입           | Null | Key | 기본값           | Extra                       | 상세 설명                     |
| -------------- | -------------- |  ---- | --- | ----------------- | --------------------------- | ------------------------- |
| **id**        | `INT UNSIGNED` |  NO   | PRI | NULL              | auto_increment              | 게시글 고유 PK. 자동 증가          |
| **user_id** | `INT UNSIGNED` | NO | | | | users 테이블 id | 
| **title**      | `VARCHAR(200)` |  NO   |     | NULL              |                             | 게시글 제목                    |
| **content**    | `TEXT`         |  NO   |     | NULL              |                             | 게시글 본문 내용                 |
| **view_count** | `INT UNSIGNED` |  NO   |     | 0                 |                             | 조회수. 기본값 0                |
| **created_at** | `DATETIME`     |  NO   |     | CURRENT_TIMESTAMP | DEFAULT_GENERATED           | 글 작성 시간. INSERT 시 자동 기록   |
| **updated_at** | `DATETIME`     | YES  |     | NULL              | on update CURRENT_TIMESTAMP | UPDATE될 때 자동으로 현재 시간으로 갱신 |




# 3. 초기 데이터 생성 ( PROCEDURE 이용 )
> PROCEDURE 는 여러 SQL 명령을 하나로 묶어 저장해두었다가, 필요할 때 한 번에 실행하는 ‘저장된 SQL 함수’이다.

## 3-1. 유저 50명 자동 생성 (user001 ~ user050)
```sql
-- 1. 유저 50명 시딩용 프로시저
DELIMITER //

CREATE PROCEDURE seed_users()
BEGIN
    DECLARE i INT DEFAULT 1;

    WHILE i <= 50 DO
        INSERT INTO users (username, password, nickname, email)
        VALUES (
            CONCAT('user', LPAD(i, 3, '0')),           -- id: user001, user002 ...
            'user1234',                                -- 비밀번호 (교육용)
            CONCAT('사용자', i),                       -- 닉네임: 사용자1, 사용자2 ...
            CONCAT('user', LPAD(i, 3, '0'), '@example.com')  -- 이메일
        );
        SET i = i + 1;
    END WHILE;
END//
DELIMITER ;

-- 2. 실제 실행
CALL seed_users();

-- 3. (선택) 프로시저 정리
DROP PROCEDURE seed_users;
```

## 3-2. 게시글 1000개 시딩 (title, content만 있을 때)
```sql
DELIMITER //

CREATE PROCEDURE seed_posts()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE uid INT DEFAULT 1;

    WHILE i <= 1000 DO

        -- user_id 1~50 순환
        SET uid = ((i - 1) % 50) + 1;

        INSERT INTO posts (user_id, title, content)
        VALUES (
            uid,
            CONCAT('테스트 게시글 #', i),
            CONCAT('이것은 테스트용 게시글 데이터입니다. 게시글 번호: ', i)
        );

        SET i = i + 1;
    END WHILE;
END//

DELIMITER ;

-- 실행
CALL seed_posts();

-- 프로시저 제거
DROP PROCEDURE seed_posts;
```

# 4. MySQL Workbench GUI 로 조회하기

## 4-1. posts 테이블 조회
![워크벤치1](https://lh3.googleusercontent.com/d/1pgIMvB-IcRvz8FnTFF9kE7avehz-NvZS)

## 4-2. users 테이블 조회
![워크벤치2](https://lh3.googleusercontent.com/d/1ODqEDQLyyP50Z8fkYMw5THilbIbZtLTt)


