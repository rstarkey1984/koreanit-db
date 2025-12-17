# 물리 ERD → 스키마 생성

## 📘 학습 개요

이 강의에서는
논리 ERD와 물리 ERD를 기반으로 실제 MySQL에서 실행 가능한 CREATE TABLE 스키마를 설계하고 작성하는 것을 목표로 한다.

- MySQL Workbench에서 물리 ERD(EER Diagram)를 작성할 수 있다

- ERD 모델을 실제 DB 스키마로 생성(Forward Engineer)할 수 있다

- 생성된 테이블/인덱스/관계가 의도대로 들어갔는지 검증할 수 있다

## 💡 주요 내용

- Workbench에서 모델 시작하기

- 테이블 관계 설정

---

# 1. Workbench에서 모델 시작하기

## 1-1. 새 모델 생성

### File → New Model

## 1-2. 스키마 이름 수정

### 예: testdb


## 1-3. Add Diagram
### 오른쪽 패널에서 Edit Template

![새로운템플릿](https://lh3.googleusercontent.com/d/12r_fCSGGOBo03F-gO8l7H2gx8xi1MjzN?3)

---

# 2. 테이블 관계 설정

## 2-1. ERD 관점

### users ------< posts

### users ------< comments

### posts ------< comments

### users ------- user_profiles?


## 2-2. Forward Engineer 실행


### Database → Forward Engineer...


## 2-3. 컬럼 설계안

### 1) users

- id INT UNSIGNED PK AI

- username VARCHAR(50) NOT NULL UNIQUE

- password VARCHAR(255) NOT NULL

- nickname VARCHAR(50) NOT NULL

- email VARCHAR(255) NULL

- created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

- updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

### 2) posts

- id INT UNSIGNED PK AI

- user_id INT UNSIGNED NOT NULL (FK → users.id)

- title VARCHAR(200) NOT NULL

- content TEXT NOT NULL

- view_count INT UNSIGNED NOT NULL DEFAULT 0

- created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

- updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

### 3) comments

- id INT UNSIGNED PK AI

- post_id INT UNSIGNED NOT NULL (FK → posts.id)

- user_id INT UNSIGNED NOT NULL (FK → users.id)

- comment VARCHAR(500) NOT NULL

- created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

- updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

### 4) user_profiles (1:1)

- user_id INT UNSIGNED PK (동시에 FK → users.id)

- bio VARCHAR(300) NULL

- phone VARCHAR(20) NULL

- birth_date DATE NULL

- profile_image_url VARCHAR(500) NULL

- created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

- updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP


## 2-3. FK ( Foreign Key ) 옵션

| 옵션              | 의미                                                                |
| --------------- | ------------------------------------------------------------------------------------------------------ |
| **RESTRICT**    | **자식 테이블에서 해당 부모 PK를 참조하는 행이 하나라도 있으면**, 부모 테이블의 **행 삭제(DELETE)** 또는 **PK 값 변경(UPDATE)** 을 **즉시 거부**한다 |
| **NO ACTION**   | MySQL(InnoDB)에서는 RESTRICT와 동일함                   |
| **CASCADE**     | 부모 테이블의 **PK 값이 변경되면 자식 FK도 자동으로 변경**되고, 부모 행이 **삭제되면 자식 행도 함께 삭제**된다                                  |
| **SET NULL**    | 부모 행이 **삭제되거나 PK가 변경되면**, 해당 FK 컬럼 값을 **NULL로 변경**한다 (FK 컬럼은 반드시 NULL 허용이어야 함)                         |
| **SET DEFAULT** | 부모 행이 삭제되거나 PK가 변경되면 FK 값을 **미리 정의된 DEFAULT 값으로 변경**한다 (MySQL에서는 실질적으로 거의 사용되지 않음)                     |



### posts.user_id → ON DELETE RESTRICT (사용자 삭제를 쉽게 못 하게)

### comments.post_id → ON DELETE CASCADE (게시글 삭제되면 댓글 같이 삭제)

### comments.user_id → ON DELETE RESTRICT (댓글 때문에 사용자 삭제 막히게)

### user_profiles.user_id → ON DELETE CASCADE (사용자 삭제 시 프로필도 삭제)