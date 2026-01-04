# SQL 응용 (웹 애플리케이션에서 SQL 실행하기)

## 🎯 이 장의 목표

이 장을 마치면 수강생은 다음을 이해한다.

- SQL은 DB에서 혼자 실행되는 것이 아니라 웹 애플리케이션 요청 흐름 속에서 실행된다는 것

- 웹 요청 → Java 코드 → SQL → DB → 결과 반환 흐름

- “화면이 나왔다”와 “DB가 정상 처리됐다”는 다른 개념이라는 것

- 이 장은 프레임워크를 배우는 장이 아니라 SQL이 실무에서 어떻게 사용되는지를 연결하는 장이다.


---

# 0. VSCode 확장 설치


- Extension Pack for Java


---

# 1. 스프링부트(Spring Boot) 개요 

> Spring Boot는 톰캣과 각종 설정을 내장해, 스프링 애플리케이션을 바로 실행할 수 있게 해주는 자동화된 스프링이다.

### 스프링부트의 역할

- 웹 요청을 받는다

- Java 코드 실행 환경을 제공한다

- DB 연결(DataSource)을 관리한다

- SQL 실행 결과를 사용자에게 전달한다

---

# 2. 웹 요청 → 실행 관점

```
브라우저
  ↓
Spring Boot 내장 Tomcat (WAS)
  ↓
Java 코드 (Spring)
  ↓
JDBC
  ↓
MySQL
```
> 이게 실제로 OS에서 일어나는 흐름

---

# 3. 서버에서 스프링부트 실행 환경 설정

## 3-1. 스프링부트 실행의 본질

스프링부트 애플리케이션은 다음과 같이 실행된다.

```
java 실행
  └─ Spring Boot 애플리케이션
        └─ 내장 Tomcat 기동
              └─ 웹 요청 대기
```
- 실행하면 서버 프로세스가 하나 뜬다

- 이 프로세스가 HTTP 요청을 계속 기다린다

## 3-2. 실행에 필요한 최소 환경

이 강의 기준 환경은 다음과 같다.

- OS: WSL Ubuntu 24.04

- Java: OpenJDK 21

- 빌드 도구: Gradle Wrapper

- IDE: VS Code

- 웹어플리케이션서버: Spring Boot 내장 Tomcat

- Databaes: MySQL 서버


## 3-3. Java 실행 환경 확인

### OpenJDK 21 설치:
```bash
sudo apt install -y openjdk-21-jdk
```
설치되는 구성

- JDK (컴파일러 + 런타임)

- JRE 포함

### 설치확인
```bash
java -version
```

### 정상 예시
```
openjdk version "21"
```

## 3-4. (선택) Java가 여러 개 설치된 경우

여러 버전이 설치되어 있다면 기본 Java를 21로 맞춘다.
```bash
sudo update-alternatives --config java
sudo update-alternatives --config javac
```

목록에서 java-21-openjdk 선택


## 3-5. SDKMAN 설치
> **SDKMAN은 “개발용 런타임/도구 버전 관리자”** 입니다.
```bash
sudo apt install curl zip unzip -y
```
설치 스크립트 실행:
```bash
curl -s "https://get.sdkman.io" | bash
```
터미널에 출력되는 안내에 따라 쉘 초기화:
```bash
source "$HOME/.sdkman/bin/sdkman-init.sh"
```
sdkman 설치 확인:
```bash
sdk version
```
Spring Boot CLI 설치:
```bash
sdk install springboot
```
Spring Boot CLI 설치 확인:
```bash
spring --version
```

---

# 4. 스프링부트 프로젝트 시작하기

## 4-1. 스프링 프로젝트 생성

```bash
spring init \
  --boot-version=4.0.1 \
  --java-version=21 \
  --type=gradle-project \
  --packaging=jar \
  --groupId=com.example \
  --artifactId=demo \
  --name=demo \
  --package-name=com.example.demo \
  --dependencies=web,jdbc \
  demo
```
spring init 옵션 정리 표
| 옵션               | 값                   | 의미                                          |
| ---------------- | ------------------- | ------------------------------------------- |
| `--boot-version` | `4.0.1`             | 사용할 Spring Boot 버전                          |
| `--java-version` | `21`                | 프로젝트에서 사용할 Java 버전                          |
| `--type`         | `gradle-project`    | Gradle 프로젝트 생성 (Groovy DSL, `build.gradle`) |
| `--packaging`    | `jar`               | 빌드 결과물을 JAR 파일로 생성                          |
| `--groupId`      | `com.example`       | 프로젝트의 그룹 ID (기본 패키지 상위 경로)                  |
| `--artifactId`   | `demo`              | 프로젝트(모듈) 이름                                 |
| `--name`         | `demo`              | Spring 애플리케이션 이름                            |
| `--package-name` | `com.example.demo`  | 기본 패키지 이름 (`@SpringBootApplication` 위치)     |
| `--dependencies` | `web,jdbc` | 포함할 Spring Boot 스타터 의존성                     |
| (마지막 인자)         | `demo`              | 생성될 프로젝트 디렉터리 이름                            |


## 4-2. application.yaml 생성
> Spring Boot 애플리케이션의 “실행 설정 파일”

```bash
cd demo
```

```bash
touch src/main/resources/application.yaml && code src/main/resources/application.yaml
```
application.yaml 파일 수정:
```yaml
server:
  port: 9091
  forward-headers-strategy: framework

spring:
  application:
    name: demo

  datasource:
    url: jdbc:mysql://localhost:3308/testdb?serverTimezone=Asia/Seoul&characterEncoding=UTF-8
    username: test
    password: test123
    driver-class-name: com.mysql.cj.jdbc.Driver
```

## 4-3. build.gradle 수정
```bash
code ./build.gradle
```

### dependencies 추가
```
implementation 'org.springframework.security:spring-security-crypto' // Password hashing (BCrypt)
runtimeOnly 'com.mysql:mysql-connector-j' // MySQL JDBC driver
```

### 프로젝트 구조
```
~/projects/web-docker/demo
    ├─ gradlew            # Linux/WSL용 Gradle 실행 파일
    ├─ gradlew.bat        # Windows용 Gradle 실행 파일
    ├─ build.gradle       # 프로젝트 설정 파일 (의존성, Java 버전, 빌드 방식)
    ├─ settings.gradle    # 프로젝트 이름 및 구조 설정
    └─ src                # 실제 애플리케이션 코드 위치
        └─ main/resources/application.yaml # Spring Boot 애플리케이션의 “실행 설정 파일”
```

## 4-4. 서버 실행
> `gradlew` 는 Gradle이 설치되어 있지 않아도, 프로젝트에 포함된 Gradle로 빌드하게 해주는 실행 스크립트
```bash
./gradlew bootRun
```
> bootRun 은 Spring Boot 애플리케이션을 “개발용 실행 상태”로 바로 띄우는 Gradle 작업(Task)

## bootRun이 실제로 하는 일

### bootRun을 실행하면 내부적으로 하는 동작

- Java 컴파일

- 클래스패스 구성

- application.yml / application.properties 로드

- 내장 톰캣(Tomcat) 실행

- main() 메서드 실행

### build/ 디렉토리 전부 삭제 후 bootRun 실행:
```bash
./gradlew clean bootRun
```

## NGINX 리버스 프록시 설정
> 리버스 프록시는 클라이언트 요청을 대신 받아 내부 서버로 전달하고, 응답을 다시 돌려주는 중간 서버 역할이다.

~/projects/web-docker/nginx/test.localhost.conf 파일 VSCode로 열기
```
code ~/projects/web-docker/nginx/test.localhost.conf
```

test.localhost.conf 파일 수정:
```
server {
    listen 80;
    server_name test.localhost;

    # 웹에서 직접 접근 가능한 루트는 public
    root /var/www/test.localhost/public;
    index index.html index.php;

    location / {
        try_files $uri $uri/ /index.html?$query_string;
    }

    # /api/로 시작하는 요청은 백엔드 API 서버로 프록시 패스
    location /api/ {
        proxy_pass http://host.docker.internal:9091;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # PHP 요청을 PHP-FPM으로 전달
    location ~ \.php$ {
        include fastcgi_params;
        
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

        fastcgi_pass php:9000;   # compose의 서비스명 php로 연결
    }
}
```
docker-compose.yml 파일 hosts 부분 추가:
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
      - ./nginx/test.localhost.conf:/etc/nginx/conf.d/test.localhost.conf:ro
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
```

---


# 5. Spring Boot 서버에 URL → 컨트롤러 메서드 매핑


## 5-1. 매핑의 의미 정리

매핑은 특정 URL 요청을 특정 Java 메서드와 연결하는 것이다.

## 5-2. 기본 매핑 하나 만들기

### VSCode 로 파일 생성
```bash
code src/main/java/com/example/demo/controller/HelloController.java
```

### 컨트롤러 예제
```java
package com.example.demo.controller;

import org.springframework.security.crypto.bcrypt.BCrypt;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RestController;

@RestController
public class HelloController {

  @GetMapping("/api/hello")
  public String hello() {

    // bcrypt 해시 생성 (salt 포함)
    String hash = BCrypt.hashpw("password", BCrypt.gensalt());

    return "hello : " + hash;
  }
}
```

이 코드의 의미:

- /hello 요청이 오면

- hello() 메서드가 실행되고

- 반환값이 HTTP 응답으로 내려간다


## 5-3. 서버 실행 후 확인

```bash
./gradlew bootRun
```


## 5-4. 브라우저에서 접속:

`http://localhost:9091/api/hello`

`http://test.localhost/api/hello`


---


# 6. DB 연결 테스트

### ApiController.java 파일 생성
```
code src/main/java/com/example/demo/controller/ApiController.java
```

### ApiController.java 파일 수정
```java
// 이 클래스가 속한 패키지 경로
// 보통 controller 패키지에는 HTTP 요청을 처리하는 클래스들이 들어간다
package com.example.demo.controller;

// JDBC 관련 클래스들
// DB 연결, SQL 실행, 결과 조회에 사용
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.ResultSetMetaData;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

// 커넥션 풀(DataSource)을 사용하기 위한 인터페이스
import javax.sql.DataSource;

// HTTP 요청 매핑 관련 스프링 애노테이션
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RestController;

// --------------------------------------------------
// @RestController
// - 이 클래스가 "컨트롤러"임을 스프링에게 알림
// - return 값이 View(html)가 아니라
//   HTTP 응답 바디로 바로 나가도록 설정
// --------------------------------------------------
@RestController
@RequestMapping("/api") // 모든 엔드포인트에 /api prefix 부여
public class ApiController {

  // --------------------------------------------------
  // DataSource
  // - DB 커넥션을 관리하는 객체 (커넥션 풀)
  // - 스프링이 미리 생성해서 주입해줌
  // --------------------------------------------------
  private final DataSource dataSource;

  // --------------------------------------------------
  // 생성자 주입
  // - 스프링이 DataSource 객체를 자동으로 넣어줌
  // - 이 컨트롤러는 DB 연결이 필요하다는 의미
  // --------------------------------------------------
  public ApiController(DataSource dataSource) {
    this.dataSource = dataSource;
  }

  // --------------------------------------------------
  // GET /db-debug 요청을 처리하는 메서드
  // - 브라우저, 프론트엔드, API 테스트 도구에서 호출 가능
  // --------------------------------------------------
  @GetMapping("/db-debug")
  public List<Map<String, Object>> dbDebug() throws Exception {

    String sql = "SELECT * FROM users LIMIT 5";

    List<Map<String, Object>> result = new ArrayList<>();

    try (Connection conn = dataSource.getConnection();
        PreparedStatement ps = conn.prepareStatement(sql);
        ResultSet rs = ps.executeQuery()) {

      ResultSetMetaData meta = rs.getMetaData();
      int columnCount = meta.getColumnCount();

      while (rs.next()) {
        Map<String, Object> row = new HashMap<>();

        for (int i = 1; i <= columnCount; i++) {
          String columnName = meta.getColumnLabel(i); // alias 있으면 alias
          Object value = rs.getObject(i); // 타입 신경 안 씀
          row.put(columnName, value);
        }

        result.add(row);
      }
    }

    return result;
  }
}
```
> @RestController에서는 return 객체를 하면, 스프링이 그 객체를 JSON 형태로 바꿔서 응답 바디로 보낸다.

> 응답 바디 = 클라이언트가 실제로 받는 내용물(HTML, JSON 같은 데이터)

응답 예:
```json
[{"1":1}]
```
---


# 7. API 서버 구성하기

> 이 장은 UI를 만드는 장이 아니라,    
> SQL 결과를 API로 전달하는 구조를 이해하는 장이다.

---

## API 서버란 무엇인가

> API 서버는 요청을 받아 필요한 처리 후 데이터를(JSON 등) 응답하는 서버이다.

## API 서버의 특징

- HTML 화면을 반환하지 않는다

- 필요한 로직을 수행하고(DB 조회, 계산 등) 데이터(JSON, 문자열 등)를 반환한다

- UI와 DB 사이의 중간 계층 역할을 한다

## API 서버의 요청/응답 흐름

API 서버의 기본 흐름은 다음과 같다.
```
클라이언트가 HTTP 요청
  ↓
Spring Boot 내장 Tomcat 이 요청 수신
  ↓
Spring (Controller 호출)
  ↓
SQL 실행 (JDBC)
  ↓
MySQL
  ↓
Controller에서 객체 반환(List/Map) -> Spring Boot가 객체를 JSON으로 변환해서 응답 바디에 담음
  ↓
Spring Boot 내장 Tomcat 이 HTTP 응답
```

HTTP 요청 수신 / 응답 전송
→ 내장 Tomcat의 역할

요청 처리 로직
→ Spring Framework의 역할

---

## 7-1. 게시글 조회 ( SELECT )

조회할 SQL
```sql
SELECT id, user_id, title, content, view_count, created_at
FROM posts
ORDER BY id DESC
LIMIT 20;
```

의미:

- 최신 글부터

- 최대 20건

- 게시판 목록에 자주 쓰이는 형태

## ApiController.java 
```java
@GetMapping("/posts")
public List<Map<String, Object>> postList() throws Exception {

    String sql = """
        SELECT id, user_id, title, content, view_count, created_at
        FROM posts
        ORDER BY id DESC
        LIMIT 20;
    """;

    List<Map<String, Object>> result = new ArrayList<>();

    try (Connection conn = dataSource.getConnection();
         PreparedStatement ps = conn.prepareStatement(sql);
         ResultSet rs = ps.executeQuery()) {

        while (rs.next()) {
            Map<String, Object> row = new HashMap<>();

            row.put("id", rs.getInt("id"));
            row.put("user_id", rs.getInt("user_id"));
            row.put("title", rs.getString("title"));
            row.put("content", rs.getString("content"));
            row.put("view_count", rs.getInt("view_count"));
            row.put("created_at", rs.getTimestamp("created_at"));

            result.add(row);
        }
    }

    return result;
}
```

### 이 코드에서 중요한 점

- ResultSet은 여러 행이므로 while (rs.next()) 사용

- 한 행 → Map<String, Object>

- 여러 행 → List<Map<String, Object>>

- SQL 결과가 그대로 JSON 배열로 변환된다

- 화면 처리 로직은 전혀 없다


### API 응답 예시
```json
[
    {   
        "id": 1048596,
        "user_id": 1,
        "title": "테스트10",
        "content": "내용",
        "view_count": 0,
        "created_at": "2025-12-30T13:00:56.000Z"
    },
    {
        "id": 1048592,
        "user_id": 1,
        "title": "테스트6",
        "content": "내용",
        "view_count": 0,
        "created_at": "2025-12-30T13:00:56.000Z"
    }
]
```

(선택) curl로 POST 요청 테스트
```bash
curl http://localhost:9091/api/posts
```
```bash
curl http://test.localhost/api/hello
```

## VSCode 에서 REST Client 확장 설치

- VSCode 왼쪽 확장(Extention) 에서 REST Client 검색 후 설치

- api-test.http 파일 생성

### REST Client 테스트 추가 (api-test.http)
```
### 게시글 조회
GET http://127.0.0.1/api/posts
Host: test.localhost
```

---

## 7-2. 회원가입 (INSERT + 비밀번호 해시 + 중복검사)

> 회원가입은 “사용자 입력값을 받아 DB에 저장하는 INSERT”의 대표 사례다.   
> 핵심은 1) 입력값 검증 2) 아이디 중복 확인 3) 비밀번호는 반드시 해시로 저장 4) INSERT 결과 확인이다.

## 회원가입 흐름 정리
```
클라이언트 요청 (POST /signup)
  ↓
Controller 메서드 실행
  ↓
요청 데이터 파싱 (username, password, name 등)
  ↓
입력값 최소 검증
  ↓
username 중복 체크 (SELECT)
  ↓
비밀번호 해시 생성 (BCrypt)
  ↓
INSERT 실행 (users)
  ↓
처리 결과(JSON) 반환
```

## 요청/응답 형태
### POST test.localhost/api/signup

요청(JSON)
```json
{
  "username": "test",
  "password": "1234",
  "nickname": "홍길동"
}
```

성공 응답 예
```json
{
  "ok": true,
  "user_id": 105
}
```

실패 응답 예(아이디 중복)
```json
{
  "ok": false,
  "message": "아이디 중복"
}
```

실패 응답 예(입력값 오류)
```json
{
  "ok": false,
  "message": "입력값 오류"
}
```

## ApiController.java

import 추가
```
import org.springframework.security.crypto.bcrypt.BCrypt;
```

회원가입 메서드 추가
```java
@PostMapping("/signup")
public Map<String, Object> signup(@RequestBody Map<String, Object> body) throws Exception {

  String username = (String) body.get("username");
  String password = (String) body.get("password");
  String nickname = (String) body.get("nickname");

  Map<String, Object> result = new HashMap<>();

  // 1) 입력값 최소 검증
  if (username == null || username.isBlank() ||
      password == null || password.isBlank() ||
      nickname == null || nickname.isBlank()) {
    result.put("ok", false);
    result.put("message", "입력값 오류");
    return result;
  }

  // (선택) 길이 제한 예시
  if (username.length() > 50 || password.length() > 100 || nickname.length() > 50) {
    result.put("ok", false);
    result.put("message", "입력값 오류");
    return result;
  }

  // 2) 중복 체크
  String checkSql = """
      SELECT id
      FROM users
      WHERE username = ?
      LIMIT 1
      """;

  // 3) INSERT
  String insertSql = """
      INSERT INTO users (username, password, nickname)
      VALUES (?, ?, ?)
      """;

  try (Connection conn = dataSource.getConnection()) {

    // 2) username 중복 확인
    try (PreparedStatement ps = conn.prepareStatement(checkSql)) {
      ps.setString(1, username);

      try (ResultSet rs = ps.executeQuery()) {
        if (rs.next()) {
          result.put("ok", false);
          result.put("message", "이미 존재하는 아이디");
          return result;
        }
      }
    }

    // 4) 비밀번호 해시 생성 (평문 저장 금지)
    String hash = BCrypt.hashpw(password, BCrypt.gensalt());

    // 5) users INSERT
    try (PreparedStatement ps = conn.prepareStatement(insertSql, java.sql.Statement.RETURN_GENERATED_KEYS)) {
      ps.setString(1, username);
      ps.setString(2, hash);
      ps.setString(3, nickname);

      int affectedRows = ps.executeUpdate();

      if (affectedRows != 1) {
        result.put("ok", false);
        result.put("message", "입력 실패");
        return result;
      }

      // 6) 생성된 user_id 얻기
      try (ResultSet keys = ps.getGeneratedKeys()) {
        if (keys.next()) {
          int userId = keys.getInt(1);
          result.put("ok", true);
          result.put("user_id", userId);
          return result;
        } else {
          result.put("ok", false);
          result.put("message", "생성된 user_id 키 없음");
          return result;
        }
      }
    }
  }
}
```


### REST Client 테스트 추가 (api-test.http)
```
### 회원가입
POST http://127.0.0.1/api/signup
Host: test.localhost
Content-Type: application/json

{
  "username": "test2",
  "password": "1234",
  "nickname": "홍길동"
}
```


---

## 7-3. 세션(Session)으로 로그인 사용자 식별하기

## 로그인 API 만들기 (세션 저장)

### 로그인 요청/응답 형태

### POST test.localhost/api/login
```json
{
  "username": "test",
  "password": "1234"
}
```

성공 응답 예:
```json
{
  "user_id": 104,
  "ok": true
}
```


실패 응답 예:
```json
{
  "ok": false,
  "message": "로그인 실패"
}
```




## ApiController.java

import 추가
```java
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.security.crypto.password.PasswordEncoder;
```

멤버 변수 선언
```java
private final PasswordEncoder passwordEncoder = new BCryptPasswordEncoder();
```

로그인/로그아웃 메서드 추가
```java
@PostMapping("/login")
public Map<String, Object> login(@RequestBody Map<String, Object> body, HttpSession session)
    throws Exception {

  String username = (String) body.get("username");
  String password = (String) body.get("password");

  Map<String, Object> result = new HashMap<>();

  // 입력값 최소 검증
  if (username == null || username.isBlank() || password == null || password.isBlank()) {
    result.put("ok", false);
    result.put("message", "입력값 오류");
    return result;
  }

  String sql = """
      SELECT id, password
      FROM users
      WHERE username = ?
      LIMIT 1
      """;

  try (Connection conn = dataSource.getConnection();
        PreparedStatement ps = conn.prepareStatement(sql)) {

    ps.setString(1, username);

    try (ResultSet rs = ps.executeQuery()) {
      if (!rs.next()) {
        // 아이디가 없음
        result.put("ok", false);
        result.put("message", "아이디 없음");
        return result;
      }

      int userId = rs.getInt("id");
      String hash = rs.getString("password");

      boolean ok = passwordEncoder.matches(password, hash);
      if (!ok) {
        result.put("ok", false);
        result.put("message", "비밀번호 오류");
        return result;
      }

      // 로그인 성공: 세션에 사용자 식별자 저장
      session.setAttribute("user_id", userId);

      result.put("ok", true);
      result.put("user_id", userId);
      return result;
    }
  }
}

@PostMapping("/logout")
public Map<String, Object> logout(HttpSession session) {
  session.invalidate(); // 세션 파기
  Map<String, Object> result = new HashMap<>();
  result.put("ok", true);
  return result;
}
```

### REST Client 테스트 추가 (api-test.http)
```
### 로그인
POST http://127.0.0.1/api/login
Host: test.localhost
Content-Type: application/json

{
    "username": "test",
    "password": "123"
}

### 로그아웃
POST http://127.0.0.1/api/logout
Host: test.localhost
Content-Type: application/json
```

---

## 7-4. 게시글 등록 ( INSERT )

> 이번에는 조회가 아니라 DB에 데이터를 저장해본다. 즉, SQL의 INSERT가 API 요청을 통해 실행되는 흐름이다.

## INSERT 흐름 정리
```
클라이언트 요청 (POST)
  ↓
Controller 메서드 실행
  ↓
요청 데이터 파싱
  ↓
INSERT SQL 실행
  ↓
MySQL
  ↓
처리 결과 반환 (JSON)
```
조회와 다른 점:

- ResultSet이 없다

- 영향받은 행 수(row count)를 확인한다

## ApiController.java
```java
@PostMapping("/posts")
public Map<String, Object> createPost(@RequestBody Map<String, Object> body, HttpSession session)
    throws Exception {

  Map<String, Object> result = new HashMap<>();

  // 1) 로그인 여부 확인
  Object userIdObj = session.getAttribute("user_id");
  if (userIdObj == null) {
    result.put("ok", false);
    result.put("message", "로그인 필요");
    return result;
  }

  int user_id = (int) userIdObj;

  String sql = """
      INSERT INTO posts (user_id, title, content)
      VALUES (?, ?, ?)
      """;

  try (Connection conn = dataSource.getConnection();
      PreparedStatement ps = conn.prepareStatement(sql, java.sql.Statement.RETURN_GENERATED_KEYS)) {

    ps.setInt(1, user_id);
    ps.setString(2, (String) body.get("title"));
    ps.setString(3, (String) body.get("content"));

    int affectedRows = ps.executeUpdate();

    if (affectedRows != 1) {
      result.put("ok", false);
      result.put("message", "입력 실패");
      return result;
    }

    // 6) 생성된 post_id 얻기
    try (ResultSet keys = ps.getGeneratedKeys()) {
      if (keys.next()) {
        int postId = keys.getInt(1);
        result.put("ok", true);
        result.put("post_id", postId);
        return result;
      } else {
        result.put("ok", false);
        result.put("message", "생성된 post_id 키 없음");
        return result;
      }
    }
  }
}
```

### REST Client 테스트 추가 (api-test.http)

```
### 게시글 등록
POST http://127.0.0.1/api/posts
Host: test.localhost
Content-Type: application/json

{
    "title": "제목",
    "content": "내용"
}
```

---

## 7-5. 글삭제 ( DELETE )

> 이번에는 게시글을 “삭제”한다. 삭제는 SELECT처럼 결과를 돌려주는 게 아니라, DB에 변화가 생겼는지(영향받은 행 수) 로 성공/실패를 판단한다.

## DELETE 흐름 정리

```
클라이언트 요청 (DELETE /posts/{id})
  ↓
Controller 메서드 실행
  ↓
(로그인 확인) 세션에서 user_id 꺼냄
  ↓
(권한 확인) 이 글의 작성자인지 확인
  ↓
DELETE SQL 실행
  ↓
MySQL (ON DELETE CASCADE로 comments 같이 삭제될 수 있음)
  ↓
처리 결과(JSON) 반환
```

### URL 설계
삭제는 보통 리소스 기반으로 URL을 잡는다.
- DELETE /posts/10 → id=10 게시글 삭제

## ApiController.java

```java
import org.springframework.web.bind.annotation.DeleteMapping;
import org.springframework.web.bind.annotation.PathVariable;
```

```java
@DeleteMapping("/posts/{id}")
public Map<String, Object> deletePost(@PathVariable("id") int id, HttpSession session)
    throws Exception {

  Map<String, Object> result = new HashMap<>();

  // 1) 로그인 확인
  Object userIdObj = session.getAttribute("user_id");
  if (userIdObj == null) {
    result.put("ok", false);
    result.put("message", "로그인 필요");
    return result;
  }

  int userId = (int) userIdObj;

  // 2) 권한 확인: 글 작성자인지 검사
  String ownerSql = """
      SELECT user_id
      FROM posts
      WHERE id = ?
      """;

  Integer ownerId = null;

  try (Connection conn = dataSource.getConnection();
       PreparedStatement ps = conn.prepareStatement(ownerSql)) {

    ps.setInt(1, id);

    try (ResultSet rs = ps.executeQuery()) {
      if (!rs.next()) {
        // 삭제할 글이 없음
        result.put("ok", false);
        result.put("message", "게시글 없음");
        return result;
      }
      ownerId = rs.getInt("user_id");
    }

    if (ownerId == null || ownerId != userId) {
      // 남의 글 삭제 시도
      result.put("ok", false);
      result.put("message", "권한 없음");
      return result;
    }

    // 3) DELETE 실행
    String deleteSql = "DELETE FROM posts WHERE id = ?";

    try (PreparedStatement ps2 = conn.prepareStatement(deleteSql)) {
      ps2.setInt(1, id);

      int affectedRows = ps2.executeUpdate();

      result.put("ok", true);
      result.put("deleted", affectedRows); // 정상이라면 1
      return result;
    }
  }
}
```


### REST Client 테스트 추가 (api-test.http)

```
### 게시글 삭제
DELETE http://127.0.0.1/api/posts/1
Host: test.localhost
Content-Type: application/json
```

---

## 7-6. 게시글 수정 ( UPDATE )
> 이번에는 게시글을 “수정”한다.   
> 수정은 SELECT처럼 결과를 돌려주는 게 아니라,    
> DB에 실제로 변경이 일어났는지(영향받은 행 수) 로 성공/실패를 판단한다.


### UPDATE 흐름 정리
```
클라이언트 요청 (PUT /posts/{id})
  ↓
Controller 메서드 실행
  ↓
(로그인 확인) 세션에서 user_id 꺼냄
  ↓
(입력값 확인) title/content 유효성 검사
  ↓
(권한 확인) 이 글의 작성자인지 확인
  ↓
UPDATE SQL 실행
  ↓
MySQL
  ↓
처리 결과(JSON) 반환
```

### URL 설계

- 수정은 보통 리소스 기반으로 URL을 잡는다.

- PUT /posts/10 → id=10 게시글 수정

## 요청/응답 형태
### PUT test.localhost/api/posts/{id}

요청(JSON)
```json
{
  "title": "수정된 제목",
  "content": "수정된 내용"
}
```
성공 응답 예
```json
{
  "ok": true,
  "updated": 1
}
```

실패 응답 예(로그인 필요)
```json
{
  "ok": false,
  "message": "로그인 필요"
}
```

실패 응답 예(게시글 없음)
```json
{
  "ok": false,
  "message": "게시글 없음"
}
```

실패 응답 예(권한 없음)
```json
{
  "ok": false,
  "message": "권한 없음"
}
```

실패 응답 예(입력값 오류)
```json
{
  "ok": false,
  "message": "입력값 오류"
}
```

## ApiController.java

```java
@PutMapping("/posts/{id}")
public Map<String, Object> updatePost(
    @PathVariable("id") int id,
    @RequestBody Map<String, Object> body,
    HttpSession session
) throws Exception {

  Map<String, Object> result = new HashMap<>();

  // 1) 로그인 확인
  Object userIdObj = session.getAttribute("user_id");
  if (userIdObj == null) {
    result.put("ok", false);
    result.put("message", "로그인 필요");
    return result;
  }
  int userId = (int) userIdObj;

  // 2) 입력값 파싱 + 최소 검증
  String title = (String) body.get("title");
  String content = (String) body.get("content");

  if (title == null || title.isBlank() || content == null || content.isBlank()) {
    result.put("ok", false);
    result.put("message", "입력값 오류");
    return result;
  }

  // (선택) 길이 제한
  if (title.length() > 200 || content.length() > 5000) {
    result.put("ok", false);
    result.put("message", "입력값 오류");
    return result;
  }

  // 3) 권한 확인: 글 작성자인지 검사
  String ownerSql = """
      SELECT user_id
      FROM posts
      WHERE id = ?
      """;

  // 4) UPDATE 실행
  String updateSql = """
      UPDATE posts
      SET title = ?, content = ?
      WHERE id = ?
      """;

  try (Connection conn = dataSource.getConnection()) {

    Integer ownerId = null;

    // 3) 작성자 확인
    try (PreparedStatement ps = conn.prepareStatement(ownerSql)) {
      ps.setInt(1, id);

      try (ResultSet rs = ps.executeQuery()) {
        if (!rs.next()) {
          result.put("ok", false);
          result.put("message", "게시글 없음");
          return result;
        }
        ownerId = rs.getInt("user_id");
      }
    }

    if (ownerId == null || ownerId != userId) {
      result.put("ok", false);
      result.put("message", "권한 없음");
      return result;
    }

    // 4) UPDATE 실행
    try (PreparedStatement ps = conn.prepareStatement(updateSql)) {
      ps.setString(1, title);
      ps.setString(2, content);
      ps.setInt(3, id);

      int affectedRows = ps.executeUpdate();

      result.put("ok", true);
      result.put("updated", affectedRows); // 정상이라면 1
      return result;
    }
  }
}
```

이 코드에서 중요한 점

- 수정은 executeQuery()가 아니라 executeUpdate()를 사용한다

- 로그인 체크(세션) 후에만 수정 가능하게 한다

- “게시글 없음 / 권한 없음 / 입력값 오류”를 구분해준다

- update는 “몇 행이 바뀌었는지”로 성공을 판단한다


### REST Client 테스트 추가 (api-test.http)
```
### 게시글 수정
PUT http://127.0.0.1/api/posts/1
Host: test.localhost
Content-Type: application/json

{
  "title": "수정된 제목",
  "content": "수정된 내용"
}
```

---


# 🧩 실습 / 과제
## 1.  게시글 목록에서 작성자 정보 JOIN 해서 같이 내려주기 (SELECT + JOIN)

게시글 테이블(posts)에는 user_id만 들어 있다.

화면(또는 API 응답)에서는 보통 “작성자 닉네임/아이디” 같은 사용자 정보가 필요하다.

이럴 때 JOIN으로 posts와 users를 붙여 한 번에 조회한다.

### 목표 응답 예시:
```json
[
    {   
        "id": 1048596,
        "user_id": 1,
        "nickname": "nickname",
        "title": "테스트10",
        "content": "내용",
        "view_count": 0,
        "created_at": "2025-12-30T13:00:56.000Z"
    },
    {
        "id": 1048592,
        "user_id": 1,
        "nickname": "nickname",
        "title": "테스트6",
        "content": "내용",
        "view_count": 0,
        "created_at": "2025-12-30T13:00:56.000Z"
    }
]
```