# MySQL 준비

### posts 테이블에 comments_cnt 컬럼 없으면 생성

```sql
ALTER TABLE `testdb`.`posts` 
ADD COLUMN `comments_cnt` INT NOT NULL DEFAULT 0 AFTER `updated_at`;
```


---

# 게시판 API 엔드포인트 정리

| HTTP Method | Endpoint                   | 설명                                                                                   |
| ----------- | -------------------------- | ------------------------------------------------------------------------------------ |
| `POST`      | `/signup`                  | 회원 가입<br>// users 테이블만 INSERT<br>// user_profiles는 생성하지 않음                           |
| `POST`      | `/login`                   | 로그인<br>// 비밀번호 검증<br>// session에 user_id 저장                                          |
| `POST`      | `/logout`                  | 로그아웃<br>// 세션 무효화                                                                    |
| `GET`       | `/me`                      | 로그인 상태 확인<br>// session 기반 사용자 식별                                                    |
|             |                            |                                                                                      |
| `GET`       | `/me/profile`              | 내 프로필 조회<br>// users + user_profiles LEFT JOIN<br>// 프로필이 없어도 조회 가능                  |
| `PUT`       | `/me/profile`              | **프로필 저장 (UPSERT)**<br>// 로그인 필요<br>// UPDATE 먼저 실행<br>// affectedRows = 0 이면 INSERT |
|             |                            |                                                                                      |
| `GET`       | `/users/{userId}`          | 사용자 프로필 조회<br>// 게시글/댓글 작성자 정보 표시용                                                   |
|             |                            |                                                                                      |
| `GET`       | `/posts`                   | 게시글 목록 조회<br>// 최신순 정렬<br>// (확장) 페이징 / 검색                                           |
| `GET`       | `/posts/{postId}`          | 게시글 상세 조회<br>// 조회수 증가 (post_view_logs 중복 방지)                                        |
| `POST`      | `/posts`                   | 게시글 작성<br>// 로그인 필요                                                                  |
| `PUT`       | `/posts/{postId}`          | 게시글 수정<br>// 작성자 본인만 가능                                                              |
| `DELETE`    | `/posts/{postId}`          | 게시글 삭제<br>// 작성자 본인만 가능<br>// 댓글은 FK로 자동 삭제                                          |
|             |                            |                                                                                      |
| `GET`       | `/posts/{postId}/comments` | 댓글 목록 조회                                                                             |
| `POST`      | `/posts/{postId}/comments` | 댓글 작성<br>// 로그인 필요<br>// comments INSERT<br>// posts.comments_cnt +1                 |
| `PUT`       | `/comments/{commentId}`    | 댓글 수정<br>// 댓글 작성자만 가능                                                               |
| `DELETE`    | `/comments/{commentId}`    | 댓글 삭제<br>// 댓글 작성자만 가능<br>// posts.comments_cnt -1                                   |
|             |                            |                                                                                      |
| `GET`       | `/db-debug`                | DB 연결 테스트<br>// 실습/디버그용                                                              |
---


# REST Client용 api-test.http

```
### ------------------------------------------------------------
### (REST Client용) 127.0.0.1로 접속 + Host 헤더로 test.localhost 매칭
### ------------------------------------------------------------

@baseUrl = http://127.0.0.1:9092/api
@vhost = test.localhost

@username = user01
@password = pass1234!
@nickname = 닉네임01


### 0) DB 연결 테스트
GET {{baseUrl}}/db-debug
Host: {{vhost}}


### 1) 회원가입
POST {{baseUrl}}/signup
Host: {{vhost}}
Content-Type: application/json

{
  "username": "{{username}}",
  "password": "{{password}}",
  "nickname": "{{nickname}}"
}


### 2) 로그인
# REST Client에서 쿠키 유지:
# settings.json에 아래 옵션 켜면 이후 요청에 JSESSIONID가 자동 포함됩니다.
# "rest-client.rememberCookiesForSubsequentRequests": true
POST {{baseUrl}}/login
Host: {{vhost}}
Content-Type: application/json

{
  "username": "{{username}}",
  "password": "{{password}}"
}


### 3) 로그인 상태 확인
GET {{baseUrl}}/me
Host: {{vhost}}


### 4) 내 프로필 조회(LEFT JOIN)
GET {{baseUrl}}/me/profile
Host: {{vhost}}


### 5) 내 프로필 UPSERT (UPDATE 먼저 -> 없으면 INSERT)
PUT {{baseUrl}}/me/profile
Host: {{vhost}}
Content-Type: application/json

{
  "bio": "자기소개입니다",
  "phone": "010-1234-5678",
  "birth_date": "2000-01-02",
  "profile_image_url": "https://example.com/profile.png"
}


### 6) 내 프로필 재조회
GET {{baseUrl}}/me/profile
Host: {{vhost}}


### 7) 다른 사용자 프로필 조회(예: 1번 사용자)
GET {{baseUrl}}/users/1
Host: {{vhost}}


### 8) 게시글 목록(기본)
GET {{baseUrl}}/posts
Host: {{vhost}}


### 9) 게시글 목록(페이징)
GET {{baseUrl}}/posts?page=1000&pageSize=5
Host: {{vhost}}


### 10) 게시글 목록(검색: both)
GET {{baseUrl}}/posts?page=1&pageSize=5&type=both&keyword=테스트
Host: {{vhost}}


### 11) 게시글 작성
# 응답의 data.post_id 값을 아래에 복사해서 사용
POST {{baseUrl}}/posts
Host: {{vhost}}
Content-Type: application/json

{
  "title": "테스트 글 제목",
  "content": "테스트 글 내용"
}


### 12) 게시글 상세 조회(조회수 증가 + viewer_key 반환)
GET {{baseUrl}}/posts/1
Host: {{vhost}}


### 13) 게시글 상세 조회(같은 viewer_key로 조회수 중복 방지 확인)
GET {{baseUrl}}/posts/1?viewer_key=g:YOUR_VIEWER_KEY_HERE
Host: {{vhost}}


### 14) 게시글 수정(작성자 본인만)
PUT {{baseUrl}}/posts/1
Host: {{vhost}}
Content-Type: application/json

{
  "title": "수정된 제목",
  "content": "수정된 내용"
}


### 15) 댓글 목록
GET {{baseUrl}}/posts/1/comments
Host: {{vhost}}


### 16) 댓글 작성(로그인 필요)
POST {{baseUrl}}/posts/1/comments
Host: {{vhost}}
Content-Type: application/json

{
  "comment": "첫 댓글입니다"
}


### 17) 댓글 수정(작성자 본인만)
PUT {{baseUrl}}/comments/500057
Host: {{vhost}}
Content-Type: application/json

{
  "comment": "댓글 수정본"
}


### 18) 댓글 삭제(작성자 본인만)
DELETE {{baseUrl}}/comments/1
Host: {{vhost}}


### 19) 게시글 삭제(작성자 본인만)
DELETE {{baseUrl}}/posts/1
Host: {{vhost}}


### 20) 로그아웃
POST {{baseUrl}}/logout
Host: {{vhost}}


### 21) 로그인 상태 확인(로그아웃 후)
GET {{baseUrl}}/me
Host: {{vhost}}
```

---


# 세션(HttpSession)을 Redis로 저장하기

## 1. Docker 컨테이너로 Redis 서버 실행하기
## docker-compose.yml
```
name: web-docker

services:
  nginx:
    image: nginx:1.27-alpine
    container_name: web-nginx
    restart: unless-stopped
    ports:
      - "80:80"
    volumes:
      - ./var/www/test.localhost:/var/www/test.localhost:ro
      - ./nginx:/etc/nginx/conf.d:ro
    depends_on:
      - php
    extra_hosts:
      - "host.docker.internal:host-gateway"
  php:
    image: custom-php-fpm:8.3-alpine
    container_name: web-php
    restart: unless-stopped
    volumes:
      - ./var/www/test.localhost:/var/www/test.localhost:ro
    env_file:
      - .env
    extra_hosts:
      - "host.docker.internal:host-gateway"
  redis:
    image: redis:7-alpine
    container_name: redis
    ports:
      - "6379:6379"
    command: ["redis-server", "--appendonly", "yes"]
    volumes:
      - redis-data:/data

volumes:
  redis-data:
```

## 2. 스프링부트 설정

## 2-1. Gradle 의존성 추가
### demo/build.gradle 수정:
```
plugins {
	id 'java'
	id 'org.springframework.boot' version '4.0.1'
	id 'io.spring.dependency-management' version '1.1.7'
}

group = 'com.example'
version = '0.0.1-SNAPSHOT'
description = 'Demo project for Spring Boot'

java {
	toolchain {
		languageVersion = JavaLanguageVersion.of(21)
	}
}

repositories {
	mavenCentral()
}

dependencies {
  implementation 'org.springframework.boot:spring-boot-starter-jdbc' // JDBC support
  implementation 'org.springframework.boot:spring-boot-starter-webmvc' // Web MVC framework
  implementation 'org.springframework.security:spring-security-crypto' // Password hashing (BCrypt)
  implementation 'org.springframework.boot:spring-boot-starter-data-redis' // Redis support
  implementation 'org.springframework.session:spring-session-data-redis' // Spring Session with Redis

  runtimeOnly 'com.mysql:mysql-connector-j' // MySQL JDBC driver
  testImplementation 'org.springframework.boot:spring-boot-starter-test'
  testRuntimeOnly 'org.junit.platform:junit-platform-launcher'
}

tasks.named('test') {
	useJUnitPlatform()
}
```

### application.yaml에 Redis 접속 정보 추가 :
```
server:
  port: 9092
  forward-headers-strategy: framework

spring:
  application:
    name: demo

  datasource:
    url: jdbc:mysql://localhost:3308/testdb?serverTimezone=Asia/Seoul&characterEncoding=UTF-8
    username: test
    password: test123
    driver-class-name: com.mysql.cj.jdbc.Driver

  data:
    redis:
      host: localhost
      port: 6379
```


### DemoApplication.java 파일 수정:
```
package com.example.demo;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.session.data.redis.config.annotation.web.http.EnableRedisHttpSession; // Import for Redis HTTP session support

@EnableRedisHttpSession // Enable Redis-backed HTTP sessions
@SpringBootApplication
public class DemoApplication {

  public static void main(String[] args) {
    SpringApplication.run(DemoApplication.class, args);
  }

}
```

---

# (선택) 스프링부트 API 프로젝트 도커 컨테이너로 배포하기

## .env 파일 열기:
```
code ~/projects/web-docker/.env
```

## .env 파일 수정
```
# =========================
# Spring Profile
# =========================
SPRING_PROFILES_ACTIVE=docker

# =========================
# MySQL (host / WSL)
# =========================
DB_HOST=host.docker.internal
DB_PORT=3308
DB_NAME=testdb
DB_USER=test
DB_PASS=test123
DB_CHARSET=utf8mb4

# =========================
# Redis (host / WSL)
# =========================
REDIS_HOST=host.docker.internal
REDIS_PORT=6379
```

## Docker Compose 에서 사용할 스프링부트 전용 설정 파일 생성:

### application-docker.yaml 파일 열기
```bash
code ~/projects/web-docker/demo/src/main/resources/application-docker.yaml
```

### application-docker.yaml 파일 수정
```
server:
  port: 9092
  forward-headers-strategy: framework

spring:
  application:
    name: demo

  datasource:
    url: jdbc:mysql://${DB_HOST}:${DB_PORT}/${DB_NAME}?serverTimezone=Asia/Seoul&characterEncoding=UTF-8
    username: ${DB_USER}
    password: ${DB_PASS}
    driver-class-name: com.mysql.cj.jdbc.Driver

  data:
    redis:
      host: ${REDIS_HOST}
      port: ${REDIS_PORT}
```

## 1. 스프링부트 API 서버 Docker 이미지 만들기

### Dockerfile 파일 열기
```bash
code ~/projects/web-docker/demo/Dockerfile
```

### Dockerfile 작성
```dockerfile
# ----------------------------------------
# 1단계: 빌드 스테이지
# ----------------------------------------
FROM eclipse-temurin:21-jdk AS builder

WORKDIR /app

# Gradle 관련 파일 복사
COPY gradlew .
COPY gradle gradle
COPY build.gradle settings.gradle ./
COPY src src

# 실행 가능한 Spring Boot JAR 생성
RUN ./gradlew clean bootJar -x test


# ----------------------------------------
# 2단계: 실행 스테이지
# ----------------------------------------
FROM eclipse-temurin:21-jre

WORKDIR /app

# 빌드 결과물 복사
COPY --from=builder /app/build/libs/*.jar app.jar

EXPOSE 9092

# 컨테이너 시작 시 Spring Boot 실행
ENTRYPOINT ["java", "-jar", "app.jar"]
```
### Docker 이미지 빌드:
```bash
docker build -t web-docker-api:1.0 .
```

빌드 성공시:
```
docker images | grep web-docker-api
```

단독 실행 테스트:
```
cd ~/projects/web-docker
```
```
docker run --rm -p 9092:9092 --env-file .env --add-host host.docker.internal:host-gateway web-docker-api:1.0
```

브라우저 확인
```
http://localhost:9092/api/hello
```


# 2. Docker Compose 에 서비스 추가

## docker-compose.yml 파일 수정
```
# Docker Compose 프로젝트 이름
name: web-docker

services:
  # ----------------------------
  # Nginx (웹 서버 / 리버스 프록시)
  # ----------------------------
  nginx:
    image: nginx:1.27-alpine          # 경량 Alpine 기반 Nginx 이미지
    container_name: web-nginx         # 컨테이너 이름 고정
    restart: unless-stopped           # 수동 중지 전까지 자동 재시작
    ports:
      - "80:80"                       # 호스트 80 → 컨테이너 80 포트 매핑
    volumes:
      - ./var/www:/var/www:ro         # 웹 루트(정적/프론트 리소스), 읽기 전용
      - ./nginx:/etc/nginx/conf.d:ro  # Nginx 가상호스트 설정, 읽기 전용
    depends_on:
      - php                           # php-fpm 컨테이너 먼저 실행
      - api                           # api 컨테이너 먼저 실행
    extra_hosts:
      - "host.docker.internal:host-gateway"
      # 컨테이너에서 host.docker.internal → 도커 호스트 IP로 접근 가능

  # ----------------------------
  # PHP-FPM (PHP 실행 전용)
  # ----------------------------
  php:
    image: custom-php-fpm:8.3-alpine  # 커스텀 PHP-FPM 이미지
    container_name: web-php
    restart: unless-stopped
    volumes:
      - ./var/www/test.localhost:/var/www/test.localhost:ro
      # PHP 소스 코드 마운트 (읽기 전용)
    env_file:
      - .env                          # DB 정보 등 환경변수 로딩
    extra_hosts:
      - "host.docker.internal:host-gateway"
      # PHP에서 호스트(MySQL, 로컬 API 등) 접근용

  # ----------------------------
  # API 서버 (Spring Boot 등)
  # ----------------------------
  api:
    image: web-docker-api:1.0         # API 서버 이미지
    container_name: web-api
    restart: unless-stopped
    expose:
      - "9092"                        # 컨테이너 내부 통신용 포트
      # 외부 직접 접근 불가, nginx/php 에서만 접근
    env_file:
      - .env                          # DB, Redis 등 설정 공유
    extra_hosts:
      - "host.docker.internal:host-gateway"
      # API에서 호스트 자원 접근 가능

  # ----------------------------
  # Redis (캐시 / 세션 저장소)
  # ----------------------------
  redis:
    image: redis:7-alpine             # Redis 7 Alpine 이미지
    container_name: redis
    restart: unless-stopped
    ports:
      - "6379:6379"                   # 호스트에서 직접 Redis 접속 가능
    command: ["redis-server", "--appendonly", "yes"]
      # AOF(Append Only File) 활성화 → 데이터 영속성 보장
    volumes:
      - redis-data:/data              # Redis 데이터 영구 저장

# ----------------------------
# Named Volume 정의
# ----------------------------
volumes:
  redis-data:
    # docker volume 으로 관리되는 Redis 데이터 저장소
```

## Nginx 설정 변경

### www.localhost.conf 파일 생성:
```
code ~/projects/web-docker/nginx/www.localhost.conf
```

## www.localhost.conf 파일 수정
```
server {
    listen 80;
    server_name www.localhost;

    # 웹에서 직접 접근 가능한 루트는 public
    root /var/www/www.localhost/public;
    index index.html index.php;

    location / {
        try_files $uri $uri/ /index.html?$query_string;
    }

    # /api/로 시작하는 요청은 도커 서비스 백엔드 API 서버로 프록시
    location /api/ {
        proxy_pass http://api:9092;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

}
```

### docker compose 중지 
```bash
docker compose down
```


## docker compose 시작
```bash
docker compose up -d
```


# HTML 페이지

### index.html 파일 생성:
```
code ~/projects/web-docker/var/www/www.localhost/public/index.html
```

## index.html 파일 수정

# 서비스 흐름
```
(브라우저/클라이언트)
        |
        |  http://localhost:80
        v
+---------------------------+
|        HOST(PC/WSL)       |
|  Port Publish: 80, 6379   |
+---------------------------+
        | 80:80
        v
+---------------------------+        (compose network: web-docker)
|   nginx  (web-nginx)      |------------------------------------+
|   :80 (reverse proxy)     |                                    |
+---------------------------+                                    |
        | proxy_pass /api -> http://api:9092                     |
        | fastcgi_pass -> php:9000 (예: PHP 처리)                |
        v                                                        v
+---------------------------+                         +---------------------------+
|   api (web-api)           |                         |   php-fpm (web-php)       |
|   :9092 (expose only)     |                         |   :9000 (내부통신)        |
|   외부 직접접근 X         |                         |   외부 직접접근 X         |
+---------------------------+                         +---------------------------+
        |                                                        |
        | (캐시/세션 등)                                         | (세션/캐시 등)
        +----------------------------+---------------------------+
                                     v
                         +---------------------------+
                         |     redis (redis)         |
                         |     :6379                 |
                         |     volume: redis-data    |
                         +---------------------------+
                                     ^
                                     |
                          host 6379:6379 (외부에서 redis-cli 가능)


추가: 컨테이너 내부에서 "host.docker.internal" 사용 가능
- nginx/php/api -> host.docker.internal == 도커 호스트(PC/WSL)의 IP
```