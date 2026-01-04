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


# ApiController.java
```java
package com.example.demo.controller;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.ResultSetMetaData;
import java.sql.SQLIntegrityConstraintViolationException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

import javax.sql.DataSource;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;

import org.springframework.web.bind.annotation.DeleteMapping;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpSession;

import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.security.crypto.password.PasswordEncoder;

/**
 * 게시판 API 컨트롤러 (JDBC + Session 기반)
 *
 * - @RestController: return 값이 View(html)가 아니라 응답 바디(JSON)로 내려감
 * - DB 접근은 DataSource -> Connection -> PreparedStatement 순서로 수행
 * - 로그인 상태는 HttpSession("user_id")로 관리
 */
@RestController
@RequestMapping("/api") // 모든 엔드포인트에 /api prefix 부여
public class ApiController {

  private final DataSource dataSource;

  // 비밀번호 해시/검증 도구(BCrypt)
  private final PasswordEncoder passwordEncoder = new BCryptPasswordEncoder();

  public ApiController(DataSource dataSource) {
    this.dataSource = dataSource;
  }

  // --------------------------------------------------
  // 공통 유틸: 응답 포맷
  // - 모든 API는 { ok: boolean, data?: any, message?: string } 형태로 통일
  // --------------------------------------------------
  private Map<String, Object> ok(Object data) {
    Map<String, Object> r = new HashMap<>();
    r.put("ok", true);
    r.put("data", data);
    return r;
  }

  private Map<String, Object> fail(String message) {
    Map<String, Object> r = new HashMap<>();
    r.put("ok", false);
    r.put("message", message);
    return r;
  }

  // --------------------------------------------------
  // 공통 유틸: 로그인 확인
  // - 세션에 user_id가 있으면 로그인 상태로 간주
  // --------------------------------------------------
  private Integer requireLogin(HttpSession session) {
    Object userIdObj = session.getAttribute("user_id");
    if (userIdObj == null)
      return null;
    // login()에서 int를 넣었으므로 Integer로 들어옴(일반적)
    return (Integer) userIdObj;
  }

  // --------------------------------------------------
  // 공통 유틸: viewer_key 생성(조회수 중복 방지용)
  // - 로그인: "u:{id}"
  // - 비로그인: ip + ua를 sha256 해시하여 "g:{hash}" 형태로 고정값 생성
  //
  // viewer_key를 클라이언트가 직접 보내는 것도 허용(실습/테스트 편의)
  // --------------------------------------------------
  private String buildViewerKey(HttpSession session, HttpServletRequest req, String viewerKeyParam) {
    // 1) 클라이언트가 viewer_key를 직접 보내면 우선 사용
    if (viewerKeyParam != null && !viewerKeyParam.isBlank()) {
      return viewerKeyParam.trim();
    }

    // 2) 로그인 사용자면 user_id 기반으로 고정
    Integer uid = requireLogin(session);
    if (uid != null)
      return "u:" + uid;

    // 3) 비로그인이면 ip + user-agent 해시
    String ip = req.getRemoteAddr();
    String ua = req.getHeader("User-Agent");
    if (ua == null)
      ua = "";
    String raw = ip + "|" + ua;

    // 길이 제한 고려: post_view_logs.viewer_key가 100 이내라면 32글자 정도로 충분
    return "g:" + sha256Hex(raw).substring(0, 32);
  }

  private String sha256Hex(String s) {
    try {
      MessageDigest md = MessageDigest.getInstance("SHA-256");
      byte[] dig = md.digest(s.getBytes(StandardCharsets.UTF_8));
      StringBuilder sb = new StringBuilder();
      for (byte b : dig)
        sb.append(String.format("%02x", b));
      return sb.toString();
    } catch (Exception e) {
      // 해시 실패 시 fallback(실습 안전망)
      return Integer.toHexString(s.hashCode());
    }
  }

  // --------------------------------------------------
  // 디버그: DB 연결 확인
  // - 실습 환경에서 "DB 연결이 되나?" 를 빠르게 확인하는 용도
  // --------------------------------------------------
  @GetMapping("/db-debug")
  public List<Map<String, Object>> dbDebug() throws Exception {
    String sql = "SELECT 1 AS one";

    List<Map<String, Object>> result = new ArrayList<>();

    try (Connection conn = dataSource.getConnection();
        PreparedStatement ps = conn.prepareStatement(sql);
        ResultSet rs = ps.executeQuery()) {

      ResultSetMetaData meta = rs.getMetaData();
      int columnCount = meta.getColumnCount();

      while (rs.next()) {
        Map<String, Object> row = new HashMap<>();
        for (int i = 1; i <= columnCount; i++) {
          String columnName = meta.getColumnLabel(i);
          Object value = rs.getObject(i);
          row.put(columnName, value);
        }
        result.add(row);
      }
    }

    return result;
  }

  // --------------------------------------------------
  // 인증/세션
  // --------------------------------------------------

  /**
   * POST /signup
   * - users 테이블에만 INSERT (user_profiles는 생성하지 않음)
   * - username 중복 체크
   * - password는 BCrypt로 해시 저장
   */
  @PostMapping("/signup")
  public Map<String, Object> signup(@RequestBody Map<String, Object> body) throws Exception {
    String username = (String) body.get("username");
    String password = (String) body.get("password");
    String nickname = (String) body.get("nickname");

    // 기본 유효성 검사(실습용 최소)
    if (username == null || username.isBlank()
        || password == null || password.isBlank()
        || nickname == null || nickname.isBlank()) {
      return fail("입력값 오류");
    }

    // 길이 제한(테이블 varchar 기준에 맞춰 조정 가능)
    if (username.length() > 50 || nickname.length() > 50 || password.length() > 100) {
      return fail("입력값 오류");
    }

    String checkSql = """
        SELECT id
        FROM users
        WHERE username = ?
        LIMIT 1
        """;

    String insertSql = """
        INSERT INTO users (username, password, nickname)
        VALUES (?, ?, ?)
        """;

    try (Connection conn = dataSource.getConnection()) {

      // username 중복 확인
      try (PreparedStatement ps = conn.prepareStatement(checkSql)) {
        ps.setString(1, username);
        try (ResultSet rs = ps.executeQuery()) {
          if (rs.next())
            return fail("이미 존재하는 아이디");
        }
      }

      // 비밀번호 해시
      String hash = passwordEncoder.encode(password);

      // users INSERT (생성된 PK 반환)
      try (PreparedStatement ps = conn.prepareStatement(insertSql, Statement.RETURN_GENERATED_KEYS)) {
        ps.setString(1, username);
        ps.setString(2, hash);
        ps.setString(3, nickname);

        int affected = ps.executeUpdate();
        if (affected != 1)
          return fail("입력 실패");

        try (ResultSet keys = ps.getGeneratedKeys()) {
          if (!keys.next())
            return fail("생성된 user_id 키 없음");
          int userId = keys.getInt(1);

          Map<String, Object> data = new HashMap<>();
          data.put("user_id", userId);
          return ok(data);
        }
      }
    }
  }

  /**
   * POST /login
   * - username 조회 -> BCrypt 검증
   * - 성공 시 session에 user_id 저장
   */
  @PostMapping("/login")
  public Map<String, Object> login(@RequestBody Map<String, Object> body, HttpSession session) throws Exception {
    String username = (String) body.get("username");
    String password = (String) body.get("password");

    if (username == null || username.isBlank() || password == null || password.isBlank()) {
      return fail("입력값 오류");
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
        if (!rs.next())
          return fail("아이디 없음");

        int userId = rs.getInt("id");
        String hash = rs.getString("password");

        boolean ok = passwordEncoder.matches(password, hash);
        if (!ok)
          return fail("비밀번호 오류");

        // 세션에 로그인 정보 저장
        session.setAttribute("user_id", userId);

        Map<String, Object> data = new HashMap<>();
        data.put("user_id", userId);
        return ok(data);
      }
    }
  }

  /**
   * POST /logout
   * - 세션 무효화
   */
  @PostMapping("/logout")
  public Map<String, Object> logout(HttpSession session) {
    session.invalidate();
    return ok(Map.of());
  }

  /**
   * GET /me
   * - 로그인 상태 확인(세션 기반)
   */
  @GetMapping("/me")
  public Map<String, Object> me(HttpSession session) {
    Integer userId = requireLogin(session);
    if (userId == null)
      return ok(Map.of("logged_in", false));

    Map<String, Object> data = new HashMap<>();
    data.put("logged_in", true);
    data.put("user_id", userId);
    return ok(data);
  }

  // --------------------------------------------------
  // 프로필
  // - GET /me/profile : 내 프로필 조회 (LEFT JOIN)
  // - PUT /me/profile : UPSERT (UPDATE 먼저, 없으면 INSERT)
  // --------------------------------------------------

  @GetMapping("/me/profile")
  public Map<String, Object> myProfile(HttpSession session) throws Exception {
    Integer userId = requireLogin(session);
    if (userId == null)
      return fail("로그인 필요");

    String sql = """
        SELECT u.id, u.username, u.nickname, u.email, u.created_at,
               p.bio, p.phone, p.birth_date, p.profile_image_url, p.updated_at
        FROM users u
        LEFT JOIN user_profiles p ON u.id = p.user_id
        WHERE u.id = ?
        LIMIT 1
        """;

    try (Connection conn = dataSource.getConnection();
        PreparedStatement ps = conn.prepareStatement(sql)) {

      ps.setInt(1, userId);

      try (ResultSet rs = ps.executeQuery()) {
        if (!rs.next())
          return fail("사용자 없음");

        Map<String, Object> data = new HashMap<>();
        data.put("id", rs.getInt("id"));
        data.put("username", rs.getString("username"));
        data.put("nickname", rs.getString("nickname"));
        data.put("email", rs.getString("email"));
        data.put("created_at", rs.getString("created_at"));

        // profile 컬럼들(LEFT JOIN이라 null일 수 있음)
        data.put("bio", rs.getString("bio"));
        data.put("phone", rs.getString("phone"));
        data.put("birth_date", rs.getString("birth_date"));
        data.put("profile_image_url", rs.getString("profile_image_url"));
        data.put("profile_updated_at", rs.getString("updated_at"));

        return ok(data);
      }
    }
  }

  @PutMapping("/me/profile")
  public Map<String, Object> upsertMyProfile(@RequestBody Map<String, Object> body, HttpSession session)
      throws Exception {
    Integer userId = requireLogin(session);
    if (userId == null)
      return fail("로그인 필요");

    String bio = (String) body.get("bio");
    String phone = (String) body.get("phone");
    String birthDate = (String) body.get("birth_date"); // "YYYY-MM-DD"
    String profileImageUrl = (String) body.get("profile_image_url");

    // 공백 문자열은 null로 정리(특히 DATE)
    if (birthDate != null && birthDate.isBlank())
      birthDate = null;

    // 간단한 형식 검증(원하면 더 강화 가능)
    // YYYY-MM-DD 형태가 아니면 에러 처리
    if (birthDate != null && !birthDate.matches("^\\d{4}-\\d{2}-\\d{2}$")) {
      return fail("입력값 오류");
    }

    // 길이 검증(테이블 스펙에 맞춰 조절)
    if (bio != null && bio.length() > 300)
      return fail("입력값 오류");
    if (phone != null && phone.length() > 20)
      return fail("입력값 오류");
    if (profileImageUrl != null && profileImageUrl.length() > 500)
      return fail("입력값 오류");

    /**
     * UPDATE 먼저 실행
     * - MySQL은 "값이 동일"하면 updatedRows가 0이 될 수 있음
     * - 그래서 updated_at을 CURRENT_TIMESTAMP로 강제로 갱신해 "존재하면 1"에 가깝게 만듦
     */
    String updateSql = """
        UPDATE user_profiles
        SET bio = ?,
            phone = ?,
            birth_date = ?,
            profile_image_url = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE user_id = ?
        """;

    String insertSql = """
        INSERT INTO user_profiles (user_id, bio, phone, birth_date, profile_image_url)
        VALUES (?, ?, ?, ?, ?)
        """;

    try (Connection conn = dataSource.getConnection()) {
      conn.setAutoCommit(false);

      try {
        int updated;
        try (PreparedStatement ps = conn.prepareStatement(updateSql)) {
          ps.setString(1, bio);
          ps.setString(2, phone);
          ps.setString(3, birthDate); // DATE 컬럼에 "YYYY-MM-DD" 문자열 가능
          ps.setString(4, profileImageUrl);
          ps.setInt(5, userId);
          updated = ps.executeUpdate();
        }

        // 존재하지 않아서 업데이트가 0건이면 INSERT
        if (updated == 0) {
          try (PreparedStatement ps = conn.prepareStatement(insertSql)) {
            ps.setInt(1, userId);
            ps.setString(2, bio);
            ps.setString(3, phone);
            ps.setString(4, birthDate);
            ps.setString(5, profileImageUrl);
            ps.executeUpdate();
          }
        }

        conn.commit();
        return ok(Map.of("user_id", userId, "upserted", true));

      } catch (Exception e) {
        conn.rollback();
        throw e;
      } finally {
        conn.setAutoCommit(true);
      }
    }
  }

  /**
   * GET /users/{userId}
   * - 작성자 정보 표시에 사용
   */
  @GetMapping("/users/{userId}")
  public Map<String, Object> userProfile(@PathVariable("userId") int userId) throws Exception {
    String sql = """
        SELECT u.id, u.username, u.nickname, u.email, u.created_at,
               p.bio, p.phone, p.birth_date, p.profile_image_url
        FROM users u
        LEFT JOIN user_profiles p ON u.id = p.user_id
        WHERE u.id = ?
        LIMIT 1
        """;

    try (Connection conn = dataSource.getConnection();
        PreparedStatement ps = conn.prepareStatement(sql)) {

      ps.setInt(1, userId);

      try (ResultSet rs = ps.executeQuery()) {
        if (!rs.next())
          return fail("사용자 없음");

        Map<String, Object> data = new HashMap<>();
        data.put("id", rs.getInt("id"));
        data.put("username", rs.getString("username"));
        data.put("nickname", rs.getString("nickname"));
        data.put("email", rs.getString("email"));
        data.put("created_at", rs.getString("created_at"));

        data.put("bio", rs.getString("bio"));
        data.put("phone", rs.getString("phone"));
        data.put("birth_date", rs.getString("birth_date"));
        data.put("profile_image_url", rs.getString("profile_image_url"));

        return ok(data);
      }
    }
  }

  // --------------------------------------------------
  // 게시글
  // --------------------------------------------------

  /**
   * GET /posts
   * - page/pageSize 기본 페이징
   * - type(title|content|both) + keyword로 검색 지원
   */
  @GetMapping("/posts")
  public Map<String, Object> postList(
      @RequestParam(value = "page", required = false, defaultValue = "1") int page,
      @RequestParam(value = "pageSize", required = false, defaultValue = "20") int pageSize,
      @RequestParam(value = "type", required = false) String type,
      @RequestParam(value = "keyword", required = false) String keyword) throws Exception {

    if (page < 1)
      page = 1;
    if (pageSize < 1)
      pageSize = 20;
    if (pageSize > 50)
      pageSize = 50;

    int offset = (page - 1) * pageSize;

    boolean hasSearch = (keyword != null && !keyword.isBlank());

    // type 허용 범위 제한(그 외는 both로 처리)
    if (type == null || type.isBlank())
      type = "both";
    type = type.trim();
    if (!("title".equals(type) || "content".equals(type) || "both".equals(type)))
      type = "both";

    String where = "";
    if (hasSearch) {
      if ("title".equals(type)) {
        where = "WHERE title LIKE ?";
      } else if ("content".equals(type)) {
        where = "WHERE content LIKE ?";
      } else {
        where = "WHERE (title LIKE ? OR content LIKE ?)";
      }
    }

    String listSql = """
        SELECT id, user_id, title, content, view_count, created_at, comments_cnt
        FROM posts
        %s
        ORDER BY id DESC
        LIMIT ? OFFSET ?
        """.formatted(where);

    String countSql = hasSearch
        ? ("SELECT COUNT(*) AS cnt FROM posts " + where)
        : "SELECT COUNT(*) AS cnt FROM posts";

    List<Map<String, Object>> items = new ArrayList<>();
    long total = 0;

    try (Connection conn = dataSource.getConnection()) {

      // 1) total count 조회
      try (PreparedStatement ps = conn.prepareStatement(countSql)) {
        if (hasSearch && keyword != null && !keyword.isBlank()) {
          String like = "%" + keyword.trim() + "%";
          if ("both".equals(type)) {
            ps.setString(1, like);
            ps.setString(2, like);
          } else {
            ps.setString(1, like);
          }
        }
        try (ResultSet rs = ps.executeQuery()) {
          if (rs.next())
            total = rs.getLong("cnt");
        }
      }

      // 2) 목록 조회
      try (PreparedStatement ps = conn.prepareStatement(listSql)) {
        int idx = 1;

        if (hasSearch && keyword != null && !keyword.isBlank()) {
          String like = "%" + keyword.trim() + "%";
          if ("both".equals(type)) {
            ps.setString(idx++, like);
            ps.setString(idx++, like);
          } else {
            ps.setString(idx++, like);
          }
        }

        ps.setInt(idx++, pageSize);
        ps.setInt(idx++, offset);

        try (ResultSet rs = ps.executeQuery()) {
          while (rs.next()) {
            Map<String, Object> row = new HashMap<>();
            row.put("id", rs.getInt("id"));
            row.put("user_id", rs.getInt("user_id"));
            row.put("title", rs.getString("title"));
            row.put("content", rs.getString("content"));
            row.put("view_count", rs.getInt("view_count"));
            row.put("comments_cnt", rs.getInt("comments_cnt"));
            row.put("created_at", rs.getString("created_at"));
            items.add(row);
          }
        }
      }
    }

    return ok(Map.of(
        "page", page,
        "pageSize", pageSize,
        "total", total,
        "items", items));
  }

  /**
   * GET /posts/{id}
   * - 조회수 증가(중복 방지): post_view_logs에 (post_id, viewer_key) UNIQUE
   * - insert 성공했을 때만 posts.view_count +1
   */
  @GetMapping("/posts/{id}")
  public Map<String, Object> postDetail(
      @PathVariable("id") int id,
      @RequestParam(value = "viewer_key", required = false) String viewerKeyParam,
      HttpSession session,
      HttpServletRequest req) throws Exception {

    String viewerKey = buildViewerKey(session, req, viewerKeyParam);

    String insertLogSql = """
        INSERT INTO post_view_logs (post_id, viewer_key, viewed_at)
        VALUES (?, ?, NOW())
        """;

    String incSql = """
        UPDATE posts
        SET view_count = view_count + 1
        WHERE id = ?
        """;

    String selectSql = """
        SELECT id, user_id, title, content, view_count, comments_cnt, created_at
        FROM posts
        WHERE id = ?
        LIMIT 1
        """;

    try (Connection conn = dataSource.getConnection()) {
      conn.setAutoCommit(false);

      try {
        // 1) 조회 로그 INSERT 시도
        boolean inserted = false;
        try (PreparedStatement ps = conn.prepareStatement(insertLogSql)) {
          ps.setInt(1, id);
          ps.setString(2, viewerKey);
          ps.executeUpdate();
          inserted = true;
        } catch (SQLIntegrityConstraintViolationException dupOrFk) {
          // - UNIQUE 충돌(이미 본 사용자) -> inserted=false
          // - FK 오류(게시글 없음) -> 아래 select에서 fail 처리되도록 유도
          inserted = false;
        }

        // 2) 로그가 새로 들어갔을 때만 view_count 증가
        if (inserted) {
          try (PreparedStatement ps = conn.prepareStatement(incSql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
          }
        }

        // 3) 게시글 조회
        try (PreparedStatement ps = conn.prepareStatement(selectSql)) {
          ps.setInt(1, id);
          try (ResultSet rs = ps.executeQuery()) {
            if (!rs.next()) {
              conn.rollback();
              return fail("게시글 없음");
            }

            Map<String, Object> post = new HashMap<>();
            post.put("id", rs.getInt("id"));
            post.put("user_id", rs.getInt("user_id"));
            post.put("title", rs.getString("title"));
            post.put("content", rs.getString("content"));
            post.put("view_count", rs.getInt("view_count"));
            post.put("comments_cnt", rs.getInt("comments_cnt"));
            post.put("created_at", rs.getString("created_at"));

            // 디버그/테스트용으로 viewer_key도 같이 내려줌
            post.put("viewer_key", viewerKey);

            conn.commit();
            return ok(post);
          }
        }

      } catch (Exception e) {
        conn.rollback();
        throw e;
      } finally {
        conn.setAutoCommit(true);
      }
    }
  }

  /**
   * POST /posts
   * - 로그인 필요
   * - posts INSERT 후 생성된 post_id 반환
   */
  @PostMapping("/posts")
  public Map<String, Object> createPost(@RequestBody Map<String, Object> body, HttpSession session) throws Exception {
    Integer userId = requireLogin(session);
    if (userId == null)
      return fail("로그인 필요");

    String title = (String) body.get("title");
    String content = (String) body.get("content");

    if (title == null || title.isBlank() || content == null || content.isBlank())
      return fail("입력값 오류");

    // posts.title이 varchar(45)라면 그에 맞게 제한
    if (title.length() > 45)
      return fail("입력값 오류");

    String sql = """
        INSERT INTO posts (user_id, title, content)
        VALUES (?, ?, ?)
        """;

    try (Connection conn = dataSource.getConnection();
        PreparedStatement ps = conn.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {

      ps.setInt(1, userId);
      ps.setString(2, title);
      ps.setString(3, content);

      int affected = ps.executeUpdate();
      if (affected != 1)
        return fail("입력 실패");

      try (ResultSet keys = ps.getGeneratedKeys()) {
        if (!keys.next())
          return fail("생성된 post_id 키 없음");
        int postId = keys.getInt(1);
        return ok(Map.of("post_id", postId));
      }
    }
  }

  /**
   * PUT /posts/{id}
   * - 로그인 필요
   * - 작성자 본인만 수정 가능
   */
  @PutMapping("/posts/{id}")
  public Map<String, Object> updatePost(
      @PathVariable("id") int id,
      @RequestBody Map<String, Object> body,
      HttpSession session) throws Exception {

    Integer userId = requireLogin(session);
    if (userId == null)
      return fail("로그인 필요");

    String title = (String) body.get("title");
    String content = (String) body.get("content");

    if (title == null || title.isBlank() || content == null || content.isBlank())
      return fail("입력값 오류");
    if (title.length() > 45)
      return fail("입력값 오류");

    String ownerSql = """
        SELECT user_id
        FROM posts
        WHERE id = ?
        """;

    String updateSql = """
        UPDATE posts
        SET title = ?, content = ?
        WHERE id = ?
        """;

    try (Connection conn = dataSource.getConnection()) {

      // 1) 작성자 확인
      Integer ownerId = null;
      try (PreparedStatement ps = conn.prepareStatement(ownerSql)) {
        ps.setInt(1, id);
        try (ResultSet rs = ps.executeQuery()) {
          if (!rs.next())
            return fail("게시글 없음");
          ownerId = rs.getInt("user_id");
        }
      }

      if (ownerId == null || ownerId.intValue() != userId.intValue())
        return fail("권한 없음");

      // 2) UPDATE
      try (PreparedStatement ps = conn.prepareStatement(updateSql)) {
        ps.setString(1, title);
        ps.setString(2, content);
        ps.setInt(3, id);

        int updated = ps.executeUpdate();
        return ok(Map.of("updated", updated));
      }
    }
  }

  /**
   * DELETE /posts/{id}
   * - 로그인 필요
   * - 작성자 본인만 삭제 가능
   * - comments는 FK ON DELETE CASCADE로 자동 삭제
   */
  @DeleteMapping("/posts/{id}")
  public Map<String, Object> deletePost(@PathVariable("id") int id, HttpSession session) throws Exception {
    Integer userId = requireLogin(session);
    if (userId == null)
      return fail("로그인 필요");

    String ownerSql = """
        SELECT user_id
        FROM posts
        WHERE id = ?
        """;

    String deleteSql = "DELETE FROM posts WHERE id = ?";

    try (Connection conn = dataSource.getConnection()) {

      // 1) 작성자 확인
      Integer ownerId = null;
      try (PreparedStatement ps = conn.prepareStatement(ownerSql)) {
        ps.setInt(1, id);
        try (ResultSet rs = ps.executeQuery()) {
          if (!rs.next())
            return fail("게시글 없음");
          ownerId = rs.getInt("user_id");
        }
      }

      if (ownerId == null || ownerId.intValue() != userId.intValue())
        return fail("권한 없음");

      // 2) DELETE
      try (PreparedStatement ps = conn.prepareStatement(deleteSql)) {
        ps.setInt(1, id);
        int deleted = ps.executeUpdate();
        return ok(Map.of("deleted", deleted));
      }
    }
  }

  // --------------------------------------------------
  // 댓글
  // --------------------------------------------------

  /**
   * GET /posts/{postId}/comments
   * - 해당 게시글의 댓글 목록
   */
  @GetMapping("/posts/{postId}/comments")
  public Map<String, Object> commentList(@PathVariable("postId") int postId) throws Exception {

    String sql = """
        SELECT id, post_id, user_id, comment, created_at
        FROM comments
        WHERE post_id = ?
        ORDER BY id ASC
        """;

    List<Map<String, Object>> items = new ArrayList<>();

    try (Connection conn = dataSource.getConnection();
        PreparedStatement ps = conn.prepareStatement(sql)) {

      ps.setInt(1, postId);

      try (ResultSet rs = ps.executeQuery()) {
        while (rs.next()) {
          Map<String, Object> row = new HashMap<>();
          row.put("id", rs.getInt("id"));
          row.put("post_id", rs.getInt("post_id"));
          row.put("user_id", rs.getInt("user_id"));
          row.put("comment", rs.getString("comment"));
          row.put("created_at", rs.getString("created_at"));
          items.add(row);
        }
      }
    }

    return ok(Map.of("items", items));
  }

  /**
   * POST /posts/{postId}/comments
   * - 로그인 필요
   * - comments INSERT
   * - posts.comments_cnt +1
   * - 트랜잭션으로 정합성 유지
   */
  @PostMapping("/posts/{postId}/comments")
  public Map<String, Object> createComment(
      @PathVariable("postId") int postId,
      @RequestBody Map<String, Object> body,
      HttpSession session) throws Exception {

    Integer userId = requireLogin(session);
    if (userId == null)
      return fail("로그인 필요");

    String comment = (String) body.get("comment");
    if (comment == null || comment.isBlank())
      return fail("입력값 오류");
    if (comment.length() > 255)
      return fail("입력값 오류");

    String insertSql = """
        INSERT INTO comments (post_id, user_id, comment)
        VALUES (?, ?, ?)
        """;

    String incSql = """
        UPDATE posts
        SET comments_cnt = comments_cnt + 1
        WHERE id = ?
        """;

    try (Connection conn = dataSource.getConnection()) {
      conn.setAutoCommit(false);

      try {
        int commentId;

        // 1) 댓글 INSERT
        try (PreparedStatement ps = conn.prepareStatement(insertSql, Statement.RETURN_GENERATED_KEYS)) {
          ps.setInt(1, postId);
          ps.setInt(2, userId);
          ps.setString(3, comment);

          int affected = ps.executeUpdate();
          if (affected != 1) {
            conn.rollback();
            return fail("입력 실패");
          }

          try (ResultSet keys = ps.getGeneratedKeys()) {
            if (!keys.next()) {
              conn.rollback();
              return fail("생성된 comment_id 키 없음");
            }
            commentId = keys.getInt(1);
          }
        } catch (SQLIntegrityConstraintViolationException fk) {
          // post_id가 없거나(user FK 등) 무결성 오류인 경우
          conn.rollback();
          return fail("게시글 없음");
        }

        // 2) posts.comments_cnt +1
        try (PreparedStatement ps = conn.prepareStatement(incSql)) {
          ps.setInt(1, postId);
          ps.executeUpdate();
        }

        conn.commit();
        return ok(Map.of("comment_id", commentId));

      } catch (Exception e) {
        conn.rollback();
        throw e;
      } finally {
        conn.setAutoCommit(true);
      }
    }
  }

  /**
   * PUT /comments/{commentId}
   * - 로그인 필요
   * - 댓글 작성자만 수정 가능
   */
  @PutMapping("/comments/{commentId}")
  public Map<String, Object> updateComment(
      @PathVariable("commentId") int commentId,
      @RequestBody Map<String, Object> body,
      HttpSession session) throws Exception {

    Integer userId = requireLogin(session);
    if (userId == null)
      return fail("로그인 필요");

    String comment = (String) body.get("comment");
    if (comment == null || comment.isBlank())
      return fail("입력값 오류");
    if (comment.length() > 255)
      return fail("입력값 오류");

    String ownerSql = """
        SELECT user_id
        FROM comments
        WHERE id = ?
        """;

    String updateSql = """
        UPDATE comments
        SET comment = ?
        WHERE id = ?
        """;

    try (Connection conn = dataSource.getConnection()) {

      // 1) 작성자 확인
      Integer ownerId = null;
      try (PreparedStatement ps = conn.prepareStatement(ownerSql)) {
        ps.setInt(1, commentId);
        try (ResultSet rs = ps.executeQuery()) {
          if (!rs.next())
            return fail("댓글 없음");
          ownerId = rs.getInt("user_id");
        }
      }

      if (ownerId == null || ownerId.intValue() != userId.intValue())
        return fail("권한 없음");

      // 2) UPDATE
      try (PreparedStatement ps = conn.prepareStatement(updateSql)) {
        ps.setString(1, comment);
        ps.setInt(2, commentId);
        int updated = ps.executeUpdate();
        return ok(Map.of("updated", updated));
      }
    }
  }

  /**
   * DELETE /comments/{commentId}
   * - 로그인 필요
   * - 댓글 작성자만 삭제 가능
   * - 삭제 후 posts.comments_cnt -1
   */
  @DeleteMapping("/comments/{commentId}")
  public Map<String, Object> deleteComment(@PathVariable("commentId") int commentId, HttpSession session)
      throws Exception {
    Integer userId = requireLogin(session);
    if (userId == null)
      return fail("로그인 필요");

    // 삭제 시 post_id가 필요(댓글 수 -1)
    String selectSql = """
        SELECT post_id, user_id
        FROM comments
        WHERE id = ?
        """;

    String deleteSql = "DELETE FROM comments WHERE id = ?";

    String decSql = """
        UPDATE posts
        SET comments_cnt = CASE WHEN comments_cnt > 0 THEN comments_cnt - 1 ELSE 0 END
        WHERE id = ?
        """;

    try (Connection conn = dataSource.getConnection()) {
      conn.setAutoCommit(false);

      try {
        Integer ownerId = null;
        Integer postId = null;

        // 1) 댓글 존재 + 작성자 확인 + post_id 확보
        try (PreparedStatement ps = conn.prepareStatement(selectSql)) {
          ps.setInt(1, commentId);
          try (ResultSet rs = ps.executeQuery()) {
            if (!rs.next()) {
              conn.rollback();
              return fail("댓글 없음");
            }
            postId = rs.getInt("post_id");
            ownerId = rs.getInt("user_id");
          }
        }

        if (ownerId == null || ownerId.intValue() != userId.intValue()) {
          conn.rollback();
          return fail("권한 없음");
        }

        // 2) 댓글 DELETE
        int deleted;
        try (PreparedStatement ps = conn.prepareStatement(deleteSql)) {
          ps.setInt(1, commentId);
          deleted = ps.executeUpdate();
          if (deleted != 1) {
            conn.rollback();
            return fail("삭제 실패");
          }
        }

        // 3) posts.comments_cnt -1
        try (PreparedStatement ps = conn.prepareStatement(decSql)) {
          ps.setInt(1, postId);
          ps.executeUpdate();
        }

        conn.commit();
        return ok(Map.of("deleted", deleted));

      } catch (Exception e) {
        conn.rollback();
        throw e;
      } finally {
        conn.setAutoCommit(true);
      }
    }
  }
}
```

---

# REST Client용 api-test.http

```
### ------------------------------------------------------------
### (REST Client용) 127.0.0.1로 접속 + Host 헤더로 test.localhost 매칭
### ------------------------------------------------------------

@baseUrl = http://127.0.0.1:9091/api
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


# HTML 페이지
### index.html
```html
<!doctype html>
<html lang="ko">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Board</title>

  <!-- Vue 3 (CDN) -->
  <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>

  <style>
    :root {
      --bg: #0b0c10;
      --panel: #111319;
      --panel2: #0f1116;
      --border: #232633;
      --text: #e9ecf1;
      --muted: #a4adbb;
      --accent: #6aa9ff;
      --danger: #ff6b6b;
      --shadow: 0 10px 30px rgba(0, 0, 0, .35);
      --radius: 16px;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, "Noto Sans KR", Arial, sans-serif;
      background: radial-gradient(1200px 800px at 20% 0%, #111a2b 0%, var(--bg) 55%);
      color: var(--text);
    }

    a {
      color: inherit;
      text-decoration: none;
    }

    .container {
      width: min(1180px, 92vw);
      margin: 0 auto;
    }

    header {
      position: sticky;
      top: 0;
      z-index: 10;
      background: rgba(11, 12, 16, .72);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid var(--border);
    }

    .top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 0;
      gap: 12px;
      flex-wrap: wrap;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .dot {
      width: 10px;
      height: 10px;
      border-radius: 99px;
      background: var(--accent);
      box-shadow: 0 0 0 6px rgba(106, 169, 255, .12);
    }

    .brand h1 {
      margin: 0;
      font-size: 16px;
      letter-spacing: .2px;
    }

    .right {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: 1px solid var(--border);
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 12px;
      color: var(--muted);
      background: rgba(255, 255, 255, .02);
    }

    main {
      padding: 16px 0 28px;
    }

    .grid {
      display: grid;
      grid-template-columns: 420px 1fr;
      gap: 16px;
    }

    @media (max-width: 980px) {
      .grid {
        grid-template-columns: 1fr;
      }
    }

    .card {
      background: linear-gradient(180deg, var(--panel) 0%, var(--panel2) 100%);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 16px;
      box-shadow: var(--shadow);
    }

    .card h2 {
      margin: 0 0 10px;
      font-size: 15px;
    }

    .muted {
      color: var(--muted);
      font-size: 13px;
    }

    .divider {
      height: 1px;
      background: var(--border);
      margin: 14px 0;
    }

    .row {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }

    .spacer {
      flex: 1;
    }

    .btn {
      padding: 10px 12px;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: rgba(106, 169, 255, .14);
      color: var(--text);
      cursor: pointer;
      user-select: none;
    }

    .btn:hover {
      border-color: rgba(106, 169, 255, .45);
    }

    .btn.ghost {
      background: transparent;
    }

    .btn.danger {
      background: rgba(255, 107, 107, .12);
      border-color: rgba(255, 107, 107, .35);
    }

    .btn.danger:hover {
      border-color: rgba(255, 107, 107, .6);
    }

    .form {
      display: grid;
      gap: 10px;
    }

    label {
      display: grid;
      gap: 6px;
      font-size: 13px;
      color: var(--muted);
    }

    input,
    textarea,
    select {
      width: 100%;
      padding: 10px 10px;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: #0b0d12;
      color: var(--text);
      outline: none;
    }

    textarea {
      resize: vertical;
    }

    .cols {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    @media (max-width: 520px) {
      .cols {
        grid-template-columns: 1fr;
      }
    }

    .box {
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 12px;
      background: rgba(255, 255, 255, .02);
    }

    .box h3 {
      margin: 0 0 10px;
      font-size: 13px;
      color: var(--muted);
      font-weight: 600;
    }

    .postsLayout {
      display: grid;
      grid-template-columns: 360px 1fr;
      gap: 14px;
    }

    @media (max-width: 980px) {
      .postsLayout {
        grid-template-columns: 1fr;
      }
    }

    .list {
      list-style: none;
      padding: 0;
      margin: 10px 0 0;
      display: grid;
      gap: 10px;
    }

    .item {
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 10px;
      background: rgba(255, 255, 255, .02);
    }

    .item:hover {
      border-color: rgba(106, 169, 255, .4);
    }

    .titleRow {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 10px;
    }

    .link {
      border: none;
      background: none;
      color: var(--accent);
      cursor: pointer;
      padding: 0;
      text-align: left;
      font-size: 14px;
      line-height: 1.25;
    }

    .metaRow {
      margin-top: 7px;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      font-size: 12px;
      color: var(--muted);
    }

    .panel {
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 12px;
      background: rgba(255, 255, 255, .02);
    }

    .kv {
      display: grid;
      gap: 6px;
      font-size: 13px;
      margin-top: 8px;
    }

    .kv b {
      color: #dfe7f5;
    }

    .small {
      font-size: 12px;
      color: var(--muted);
      word-break: break-all;
    }

    .pager {
      margin-top: 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .pager input {
      width: 72px;
      text-align: center;
    }

    .toast {
      position: fixed;
      right: 16px;
      bottom: 16px;
      width: min(460px, 92vw);
      background: rgba(17, 19, 25, .9);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 12px;
      box-shadow: var(--shadow);
    }

    .toast .t1 {
      font-size: 13px;
    }

    .toast .t2 {
      font-size: 12px;
      color: var(--muted);
      margin-top: 4px;
      white-space: pre-wrap;
    }

    .toast .x {
      float: right;
      border: none;
      background: transparent;
      color: var(--muted);
      cursor: pointer;
      padding: 0;
      font-size: 14px;
    }
  </style>
</head>

<body>
  <div id="app">
    <header>
      <div class="container top">
        <div class="brand">
          <span class="dot"></span>
          <h1>Board</h1>
          <span class="badge">
            <span>API</span>
            <span class="small">{{ apiBaseLabel }}</span>
          </span>
        </div>

        <div class="right">
          <span class="badge">
            <span>세션</span>
            <span v-if="me.logged_in">로그인됨 (user_id={{ me.user_id }})</span>
            <span v-else>비로그인</span>
          </span>
          <button class="btn ghost" @click="refreshMe">상태 새로고침</button>
        </div>
      </div>
    </header>

    <main class="container">
      <div class="grid">
        <!-- LEFT -->
        <section class="card">
          <h2>인증</h2>
          <div class="cols">
            <div class="box">
              <h3>회원가입</h3>
              <div class="form">
                <label>아이디
                  <input v-model.trim="signupForm.username" placeholder="username" />
                </label>
                <label>비밀번호
                  <input v-model.trim="signupForm.password" type="password" placeholder="password" />
                </label>
                <label>닉네임
                  <input v-model.trim="signupForm.nickname" placeholder="nickname" />
                </label>
                <button class="btn" @click="signup">회원가입</button>
              </div>
            </div>

            <div class="box">
              <h3>로그인</h3>
              <div class="form">
                <label>아이디
                  <input v-model.trim="loginForm.username" placeholder="username" />
                </label>
                <label>비밀번호
                  <input v-model.trim="loginForm.password" type="password" placeholder="password" />
                </label>
                <div class="row">
                  <button class="btn" @click="login">로그인</button>
                  <button class="btn danger" @click="logout">로그아웃</button>
                </div>
              </div>
            </div>
          </div>

          <div class="divider"></div>

          <h2>내 프로필</h2>
          <div class="row">
            <button class="btn ghost" @click="loadMyProfile">조회</button>
            <button class="btn" @click="saveMyProfile">저장(UPSERT)</button>
          </div>

          <div class="form" style="margin-top:10px;">
            <label>bio
              <textarea rows="3" v-model="profileForm.bio" placeholder="자기소개"></textarea>
            </label>
            <label>phone
              <input v-model.trim="profileForm.phone" placeholder="010-1234-5678" />
            </label>
            <label>birth_date (YYYY-MM-DD)
              <input v-model.trim="profileForm.birth_date" placeholder="2000-01-02" />
            </label>
            <label>profile_image_url
              <input v-model.trim="profileForm.profile_image_url" placeholder="https://..." />
            </label>
          </div>

          <div class="divider"></div>

          <h2>최근 로그</h2>
          <div class="panel">
            <div class="small" style="white-space:pre-wrap;">{{ lastLog }}</div>
          </div>
        </section>

        <!-- RIGHT -->
        <section class="card">
          <div class="row" style="justify-content:space-between;">
            <h2 style="margin:0;">게시글</h2>
            <div class="row">
              <button class="btn ghost" @click="loadPosts(true)">목록 새로고침</button>
              <button class="btn" @click="openNewPost">새 글</button>
            </div>
          </div>

          <div class="row" style="margin-top:10px;">
            <select v-model="postsQuery.type" style="width:140px;">
              <option value="both">제목+내용</option>
              <option value="title">제목</option>
              <option value="content">내용</option>
            </select>
            <input v-model.trim="postsQuery.keyword" placeholder="검색어" />
            <button class="btn ghost" @click="search">검색</button>

            <div class="spacer"></div>

            <label class="muted" style="display:flex; gap:8px; align-items:center;">
              pageSize
              <select v-model.number="postsQuery.pageSize" style="width:110px;">
                <option :value="5">5</option>
                <option :value="20">20</option>
                <option :value="30">30</option>
                <option :value="50">50</option>
              </select>
            </label>
          </div>

          <div class="postsLayout" style="margin-top:12px;">
            <!-- list -->
            <div>
              <div class="muted">total={{ posts.total }}, items={{ posts.items.length }}</div>

              <ul class="list">
                <li class="item" v-for="p in posts.items" :key="p.id">
                  <div class="titleRow">
                    <button class="link" @click="openPost(p.id)">
                      #{{ p.id }} {{ p.title }}
                    </button>
                    <span class="badge">v:{{ p.view_count }} c:{{ p.comments_cnt }}</span>
                  </div>
                  <div class="metaRow">
                    <span>user_id={{ p.user_id }}</span>
                    <span class="small">{{ p.created_at }}</span>
                  </div>
                </li>
              </ul>

              <div class="pager">
                <button class="btn ghost" @click="prevPage">이전</button>
                <div class="row">
                  <span class="muted">page</span>
                  <input type="number" min="1" v-model.number="postsQuery.page" @change="loadPosts(false)" />
                  <span class="muted">/ {{ totalPages }}</span>
                </div>
                <button class="btn ghost" @click="nextPage">다음</button>
              </div>
            </div>

            <!-- detail -->
            <div class="panel">
              <h2 style="margin:0;">상세</h2>

              <div v-if="!selectedPost" class="muted" style="margin-top:10px;">
                왼쪽에서 게시글을 선택하세요.
              </div>

              <div v-else class="kv">
                <div><b>id</b>: {{ selectedPost.id }}</div>
                <div><b>user_id</b>: {{ selectedPost.user_id }}</div>
                <div><b>title</b>: {{ selectedPost.title }}</div>
                <div><b>content</b>: <div class="small">{{ selectedPost.content }}</div>
                </div>
                <div><b>view_count</b>: {{ selectedPost.view_count }}</div>
                <div><b>comments_cnt</b>: {{ selectedPost.comments_cnt }}</div>
                <div><b>created_at</b>: <span class="small">{{ selectedPost.created_at }}</span></div>
                <div><b>viewer_key</b>: <span class="small">{{ selectedPost.viewer_key }}</span></div>
              </div>

              <div class="divider"></div>

              <h2 style="margin:0;">글 작성/수정</h2>
              <div class="form" style="margin-top:10px;">
                <label>title (최대 45)
                  <input maxlength="45" v-model.trim="postForm.title" placeholder="제목" />
                </label>
                <label>content
                  <textarea rows="5" v-model="postForm.content" placeholder="내용"></textarea>
                </label>
                <div class="row">
                  <button class="btn" @click="savePost">저장</button>
                  <button class="btn ghost" @click="cancelPostEdit">취소</button>
                  <button class="btn danger" @click="deletePost" :disabled="!postForm.id">삭제</button>
                </div>
                <div class="muted" v-if="postForm.id">수정 모드: post_id={{ postForm.id }}</div>
              </div>

              <div class="divider"></div>

              <h2 style="margin:0;">댓글</h2>
              <div class="form" style="margin-top:10px;">
                <label>comment (최대 255)
                  <textarea rows="3" v-model="commentForm.comment" placeholder="댓글"></textarea>
                </label>
                <div class="row">
                  <button class="btn" @click="addComment" :disabled="!selectedPost">작성</button>
                  <button class="btn ghost" @click="updateComment" :disabled="!commentForm.id">수정</button>
                  <button class="btn ghost" @click="cancelCommentEdit">취소</button>
                </div>
                <div class="muted" v-if="commentForm.id">수정 모드: comment_id={{ commentForm.id }}</div>
              </div>

              <ul class="list" style="margin-top:12px;">
                <li class="item" v-for="c in comments" :key="c.id">
                  <div class="titleRow">
                    <div class="muted">#{{ c.id }} (user_id={{ c.user_id }})</div>
                    <div class="small">{{ c.created_at }}</div>
                  </div>
                  <div class="small" style="margin-top:8px;">{{ c.comment }}</div>
                  <div class="row" style="margin-top:10px;">
                    <button class="btn ghost" @click="editComment(c)">수정</button>
                    <button class="btn danger" @click="removeComment(c.id)">삭제</button>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </section>
      </div>

      <div class="toast" v-if="toast.show">
        <button class="x" @click="toast.show=false">✕</button>
        <div class="t1">{{ toast.title }}</div>
        <div class="t2">{{ toast.body }}</div>
      </div>
    </main>
  </div>

  <script>
    const { createApp } = Vue;

    createApp({
      data() {
        return {
          // 1) CORS 없이 쓰려면 Nginx에서 /api 프록시 구성 후 apiBase를 "" 로 두고,
          //    아래 apiPrefix="/api" 로 호출하면 됩니다.
          // 2) 지금처럼 백엔드가 9091이면 apiBase를 "http://localhost:9091" 로 두세요.
          apiBase: "http://test.localhost",
          apiPrefix: "/api",

          me: { logged_in: false, user_id: null },

          signupForm: { username: "", password: "", nickname: "" },
          loginForm: { username: "", password: "" },

          profileForm: { bio: "", phone: "", birth_date: "", profile_image_url: "" },

          postsQuery: { page: 1, pageSize: 5, type: "both", keyword: "" },
          posts: { total: 0, items: [] },

          selectedPost: null,
          postForm: { id: null, title: "", content: "" },

          comments: [],
          commentForm: { id: null, comment: "" },

          lastLog: "",
          toast: { show: false, title: "", body: "" },
        };
      },

      computed: {
        totalPages() {
          const t = this.posts.total || 0;
          const ps = this.postsQuery.pageSize || 20;
          return Math.max(1, Math.ceil(t / ps));
        },
        apiBaseLabel() {
          return (this.apiBase || "(same-origin)") + (this.apiPrefix || "");
        }
      },

      methods: {
        notify(title, body) {
          this.toast = { show: true, title, body: body ?? "" };
          // 너무 거슬리면 자동 닫기 제거해도 됨
          setTimeout(() => { this.toast.show = false; }, 2500);
        },

        async request(path, options = {}) {
          const url = (this.apiBase || "") + (this.apiPrefix || "") + path;
          const opts = {
            method: options.method || "GET",
            headers: {
              "Content-Type": "application/json",
              ...(options.headers || {}),
            },
            credentials: "include",
            ...options,
          };
          if (opts.body && typeof opts.body !== "string") {
            opts.body = JSON.stringify(opts.body);
          }

          const res = await fetch(url, opts);
          const text = await res.text();
          let json = null;

          try { json = text ? JSON.parse(text) : null; }
          catch { json = { ok: false, message: "JSON 파싱 실패", raw: text }; }

          if (!res.ok) {
            return { ok: false, message: `HTTP ${res.status}`, data: json };
          }
          return json;
        },

        log(label, payload) {
          const line = typeof payload === "string"
            ? payload
            : (() => { try { return JSON.stringify(payload, null, 2); } catch { return String(payload); } })();
          this.lastLog = `[${label}]\n${line}`;
        },

        // -------------------------
        // Auth
        // -------------------------
        async refreshMe() {
          const r = await this.request("/me");
          if (r?.ok) {
            this.me = r.data;
          } else {
            this.log("/me fail", r);
            this.notify("세션 상태 확인 실패", r?.message || "");
          }
        },

        async signup() {
          const body = {
            username: this.signupForm.username?.trim(),
            password: this.signupForm.password?.trim(),
            nickname: this.signupForm.nickname?.trim(),
          };
          const r = await this.request("/signup", { method: "POST", body });
          if (r?.ok) {
            this.log("signup ok", r.data);
            this.notify("회원가입 완료", `user_id=${r.data.user_id}`);
          } else {
            this.log("signup fail", r?.message || "");
            this.notify("회원가입 실패", r?.message || "");
          }
        },

        async login() {
          const body = {
            username: this.loginForm.username?.trim(),
            password: this.loginForm.password?.trim(),
          };
          const r = await this.request("/login", { method: "POST", body });
          if (r?.ok) {
            this.log("login ok", r.data);
            this.notify("로그인 완료", `user_id=${r.data.user_id}`);
            await this.refreshMe();
          } else {
            this.log("login fail", r?.message || "");
            this.notify("로그인 실패", r?.message || "");
          }
        },

        async logout() {
          const r = await this.request("/logout", { method: "POST", body: {} });
          if (r?.ok) {
            this.log("logout ok", "");
            this.notify("로그아웃", "");
            await this.refreshMe();
          } else {
            this.log("logout fail", r?.message || "");
            this.notify("로그아웃 실패", r?.message || "");
          }
        },

        // -------------------------
        // Profile
        // -------------------------
        async loadMyProfile() {
          const r = await this.request("/me/profile");
          if (r?.ok) {
            const d = r.data || {};
            this.profileForm.bio = d.bio ?? "";
            this.profileForm.phone = d.phone ?? "";
            this.profileForm.birth_date = d.birth_date ?? "";
            this.profileForm.profile_image_url = d.profile_image_url ?? "";
            this.log("myProfile ok", d);
            this.notify("프로필 조회", "");
          } else {
            this.log("myProfile fail", r?.message || "");
            this.notify("프로필 조회 실패", r?.message || "");
          }
        },

        async saveMyProfile() {
          const body = {
            bio: this.profileForm.bio?.trim() || null,
            phone: this.profileForm.phone?.trim() || null,
            birth_date: this.profileForm.birth_date?.trim() || null,
            profile_image_url: this.profileForm.profile_image_url?.trim() || null,
          };
          const r = await this.request("/me/profile", { method: "PUT", body });
          if (r?.ok) {
            this.log("profile upsert ok", r.data);
            this.notify("프로필 저장", "");
            await this.loadMyProfile();
          } else {
            this.log("profile upsert fail", r?.message || "");
            this.notify("프로필 저장 실패", r?.message || "");
          }
        },

        // -------------------------
        // Posts
        // -------------------------
        buildPostsQuery() {
          const q = new URLSearchParams();
          q.set("page", String(this.postsQuery.page || 1));
          q.set("pageSize", String(this.postsQuery.pageSize || 20));

          const keyword = (this.postsQuery.keyword || "").trim();
          if (keyword) {
            q.set("type", this.postsQuery.type || "both");
            q.set("keyword", keyword);
          }
          return q.toString();
        },

        async loadPosts(resetPage) {
          if (resetPage) this.postsQuery.page = 1;
          const qs = this.buildPostsQuery();
          const r = await this.request(`/posts?${qs}`);
          if (r?.ok) {
            this.posts.total = r.data.total || 0;
            this.posts.items = r.data.items || [];
          } else {
            this.log("posts fail", r?.message || "");
            this.notify("목록 조회 실패", r?.message || "");
          }
        },

        async openPost(id) {
          const r = await this.request(`/posts/${id}`);
          if (r?.ok) {
            this.selectedPost = r.data;
            this.postForm.id = r.data.id;
            this.postForm.title = r.data.title || "";
            this.postForm.content = r.data.content || "";
            await this.loadComments();
          } else {
            this.log("post detail fail", r?.message || "");
            this.notify("상세 조회 실패", r?.message || "");
          }
        },

        openNewPost() {
          this.selectedPost = null;
          this.postForm = { id: null, title: "", content: "" };
          this.comments = [];
          this.commentForm = { id: null, comment: "" };
          this.notify("새 글", "작성 모드로 전환");
        },

        async savePost() {
          const title = (this.postForm.title || "").trim();
          const content = (this.postForm.content || "").trim();
          if (!title || !content) {
            this.notify("입력값 오류", "title/content는 필수입니다");
            return;
          }

          if (!this.postForm.id) {
            // create
            const r = await this.request("/posts", { method: "POST", body: { title, content } });
            if (r?.ok) {
              this.log("create post ok", r.data);
              this.notify("글 작성 완료", `post_id=${r.data.post_id}`);
              await this.loadPosts(false);
              await this.openPost(r.data.post_id);
            } else {
              this.log("create post fail", r?.message || "");
              this.notify("글 작성 실패", r?.message || "");
            }
          } else {
            // update
            const id = this.postForm.id;
            const r = await this.request(`/posts/${id}`, { method: "PUT", body: { title, content } });
            if (r?.ok) {
              this.log("update post ok", r.data);
              this.notify("글 수정 완료", "");
              await this.loadPosts(false);
              await this.openPost(id);
            } else {
              this.log("update post fail", r?.message || "");
              this.notify("글 수정 실패", r?.message || "");
            }
          }
        },

        async deletePost() {
          const id = this.postForm.id;
          if (!id) {
            this.notify("삭제 불가", "삭제할 글이 선택되지 않았습니다");
            return;
          }
          const r = await this.request(`/posts/${id}`, { method: "DELETE" });
          if (r?.ok) {
            this.log("delete post ok", r.data);
            this.notify("글 삭제", "");
            this.openNewPost();
            await this.loadPosts(false);
          } else {
            this.log("delete post fail", r?.message || "");
            this.notify("글 삭제 실패", r?.message || "");
          }
        },

        cancelPostEdit() {
          if (this.selectedPost?.id) {
            // 선택된 글이 있으면 편집폼을 상세값으로 되돌림
            this.postForm.id = this.selectedPost.id;
            this.postForm.title = this.selectedPost.title || "";
            this.postForm.content = this.selectedPost.content || "";
          } else {
            this.openNewPost();
          }
        },

        search() {
          this.postsQuery.page = 1;
          this.loadPosts(false);
        },
        prevPage() {
          this.postsQuery.page = Math.max(1, (this.postsQuery.page || 1) - 1);
          this.loadPosts(false);
        },
        nextPage() {
          this.postsQuery.page = (this.postsQuery.page || 1) + 1;
          this.loadPosts(false);
        },

        // -------------------------
        // Comments
        // -------------------------
        async loadComments() {
          if (!this.selectedPost?.id) return;
          const r = await this.request(`/posts/${this.selectedPost.id}/comments`);
          if (r?.ok) {
            this.comments = (r.data.items || []);
          } else {
            this.comments = [];
            this.log("comments fail", r?.message || "");
          }
        },

        async addComment() {
          if (!this.selectedPost?.id) {
            this.notify("댓글 작성", "게시글을 먼저 선택하세요");
            return;
          }
          const comment = (this.commentForm.comment || "").trim();
          if (!comment) {
            this.notify("입력값 오류", "comment는 필수입니다");
            return;
          }
          const r = await this.request(`/posts/${this.selectedPost.id}/comments`, {
            method: "POST",
            body: { comment }
          });
          if (r?.ok) {
            this.notify("댓글 작성 완료", `comment_id=${r.data.comment_id}`);
            this.commentForm = { id: null, comment: "" };
            await this.openPost(this.selectedPost.id); // 상세/카운트 갱신
          } else {
            this.notify("댓글 작성 실패", r?.message || "");
          }
        },

        editComment(c) {
          this.commentForm.id = c.id;
          this.commentForm.comment = c.comment || "";
        },

        async updateComment() {
          const id = this.commentForm.id;
          if (!id) {
            this.notify("수정 불가", "수정할 댓글을 선택하세요");
            return;
          }
          const comment = (this.commentForm.comment || "").trim();
          if (!comment) {
            this.notify("입력값 오류", "comment는 필수입니다");
            return;
          }
          const r = await this.request(`/comments/${id}`, {
            method: "PUT",
            body: { comment }
          });
          if (r?.ok) {
            this.notify("댓글 수정 완료", "");
            this.commentForm = { id: null, comment: "" };
            await this.loadComments();
          } else {
            this.notify("댓글 수정 실패", r?.message || "");
          }
        },

        async removeComment(id) {
          const r = await this.request(`/comments/${id}`, { method: "DELETE" });
          if (r?.ok) {
            this.notify("댓글 삭제", "");
            this.commentForm = { id: null, comment: "" };
            // 댓글 수 반영 위해 상세 재조회
            if (this.selectedPost?.id) await this.openPost(this.selectedPost.id);
          } else {
            this.notify("댓글 삭제 실패", r?.message || "");
          }
        },

        cancelCommentEdit() {
          this.commentForm = { id: null, comment: "" };
        },
      },

      async mounted() {
        // 초기 로드
        await this.refreshMe();
        await this.loadPosts(true);
      }
    }).mount("#app");
  </script>
</body>

</html>
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
  port: 9090
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

EXPOSE 9090

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
docker run --rm -p 9090:9090 --env-file .env --add-host host.docker.internal:host-gateway web-docker-api:1.0
```

브라우저 확인
```
http://localhost:9090/api/hello
```


# 2. Docker Compose 에 서비스 추가

## docker-compose.yml 파일 수정
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
      - api
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

  api:
    image: web-docker-api:1.0
    container_name: web-api
    restart: unless-stopped
    expose:
      - "9090"
    env_file:
      - .env
    extra_hosts:
      - "host.docker.internal:host-gateway"

  redis:
    image: redis:7-alpine
    container_name: redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    command: ["redis-server", "--appendonly", "yes"]
    volumes:
      - redis-data:/data

volumes:
  redis-data:
```

## Nginx 설정 변경

## test.localhost.conf 파일 수정
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

    # /api-test/ 로 시작하는 요청은 테스트 백엔드 API 서버로 프록시 패스
    location /api-test/ {
        proxy_pass http://host.docker.internal:9091;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # /api/로 시작하는 요청은 도커 서비스 백엔드 API 서버로 프록시
    location /api/ {
        proxy_pass http://api:9090;
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

### docker compose 중지 
```bash
docker compose down
```


## docker compose 시작
```bash
docker compose up -d
```