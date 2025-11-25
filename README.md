# 📘 데이터베이스 설계 & SQL 강의

본 강의는 **데이터베이스 설계의 전 과정(논리 → 물리)부터 SQL 기본/고급/응용까지** 실제 프로젝트 중심으로 학습하도록 구성되어 있습니다.  
데이터 구조를 이해하고, 효율적인 SQL을 작성하여 실무에서 바로 활용할 수 있는 능력을 목표로 합니다.

---

# 📦 0. 개발환경 준비

## 🔸 [WSL + Ubuntu 24.04 + Oracle Database 설치](00-wsl-setup.md)

--- 

# 📚 1. 논리 데이터베이스 설계

논리 설계 단계에서는 **업무 요구사항을 기반으로 데이터 구조의 논리적인 형태**를 정의합니다.

## 🔸 1-1. [개체 상세화하기](11-entity.md)
## 🔸 관계(Relationship) 상세화하기
- 개체 간 관계(1:1, 1:N, N:M) 정의  
- 참여도/카디널리티 결정  
- 식별/비식별 관계 모델링

## 🔸 논리 E-R 다이어그램 작성하기 (draw.io 활용)
- 논리 ERD 모델링  
- 개체/속성/관계 시각화  
- 식별자(PK) 정의  

## 🔸 데이터베이스 정규화하기
- 1NF ~ 3NF  
- 이상 현상 제거  
- 함수적 종속성 분석  
- 필요 시 BCNF 적용

---

# 🧩 2. 물리 데이터베이스 설계

## 🔸 물리요소 조사 분석하기
- DBMS(MySQL/Oracle 등) 특성 분석  
- 데이터 타입/인덱스/파티션 구조 이해  

## 🔸 데이터베이스 물리속성 설계하기
- 물리적 데이터 타입 매핑  
- 인덱스 설계  
- 제약 조건(PK/FK/UNIQUE/CHECK) 설계  
- 테이블 저장 옵션 및 파티셔닝 정의

## 🔸 물리 E-R 다이어그램 작성하기 (DBeaver 활용)
- 논리 ERD 기반 스키마 설계  
- DBeaver로 테이블 생성  
- 자동 생성 ERD 확인  
- 스키마/테이블/컬럼 명세 작성

## 🔸 데이터베이스 반정규화하기
- 테이블 병합/분할  
- 중복 컬럼 추가  
- Summary Table 구축  
- 반정규화 장단점 분석

---

# 📝 3. SQL 활용

## 🔸 기본 SQL 작성하기
- SELECT / INSERT / UPDATE / DELETE  
- WHERE / ORDER BY / GROUP BY / HAVING  
- JOIN(내부/외부/자체 조인)

## 🔸 고급 SQL 작성하기
- 집계 함수  
- 윈도우 함수(OVER)  
- 서브쿼리 / EXISTS  
- VIEW  
- UNION / INTERSECT / MINUS

---

# ⚙️ 4. SQL 응용

## 🔸 절차형 SQL 작성하기
- Stored Procedure  
- Function  
- Trigger  
- IF/LOOP  
- Exception Handling

## 🔸 응용 SQL 작성하기
- 자동화 SQL  
- 비즈니스 로직 SQL  
- 배치 작업  
- 대량 처리 기법  
- 실행 계획 및 튜닝 개요

---

# 🎯 강의 목표

- 요구사항을 데이터 모델로 정확하게 설계  
- 논리·물리 모델링 능력 강화  
- 효율적인 SQL 작성 능력 확보  
- 실무형 Stored Procedure / Trigger 구현 가능  
- 운영 환경 수준의 데이터 처리 및 튜닝 역량 확보

---

# 📥 제공 자료
- Oracle 설치 가이드  
- DBeaver 연결 메뉴얼  
- draw.io 논리 ERD 템플릿  
- SQL 실습 파일(DDL/DML/JOIN/Trigger 등)  
