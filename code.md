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


## ApiController.java
```java
// 이 클래스가 속한 패키지 경로
// 보통 controller 패키지에는 HTTP 요청을 처리하는 클래스들이 들어간다
package com.example.demo.controller;

// JDBC 관련 클래스들
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.ResultSetMetaData;
import java.sql.SQLIntegrityConstraintViolationException;
import java.sql.Statement;
import java.sql.Timestamp;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

import javax.sql.DataSource;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;

// 스프링 웹
import org.springframework.web.bind.annotation.DeleteMapping;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

// 세션/요청
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpSession;

// 비밀번호 해시/검증
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.security.crypto.password.PasswordEncoder;

// --------------------------------------------------
// @RestController
// - return 값이 View(html)가 아니라 HTTP 응답 바디(JSON)로 나감
// --------------------------------------------------
@RestController
public class ApiController {

  private final DataSource dataSource;

  // 비밀번호 해시/검증 도구
  private final PasswordEncoder passwordEncoder = new BCryptPasswordEncoder();

  public ApiController(DataSource dataSource) {
    this.dataSource = dataSource;
  }

  // --------------------------------------------------
  // 공통 유틸: 응답 포맷
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
  // --------------------------------------------------
  private Integer requireLogin(HttpSession session) {
    Object userIdObj = session.getAttribute("user_id");
    if (userIdObj == null) return null;
    return (Integer) userIdObj;
  }

  // --------------------------------------------------
  // 공통 유틸: viewer_key 생성(비로그인 사용자)
  // - user_id 있으면 u:{id}
  // - 없으면 IP + UA 기반으로 해시해서 g:{hash}
  // --------------------------------------------------
  private String buildViewerKey(HttpSession session, HttpServletRequest req, String viewerKeyParam) {
    // 1) 클라이언트가 viewer_key를 직접 보내면 우선 사용
    if (viewerKeyParam != null && !viewerKeyParam.isBlank()) {
      return viewerKeyParam.trim();
    }

    // 2) 로그인 사용자면 user_id 기반으로 고정
    Integer uid = requireLogin(session);
    if (uid != null) return "u:" + uid;

    // 3) 비로그인이면 ip + user-agent 해시
    String ip = req.getRemoteAddr();
    String ua = req.getHeader("User-Agent");
    if (ua == null) ua = "";
    String raw = ip + "|" + ua;

    return "g:" + sha256Hex(raw).substring(0, 32); // 길이 제한 고려(100 이내)
  }

  private String sha256Hex(String s) {
    try {
      MessageDigest md = MessageDigest.getInstance("SHA-256");
      byte[] dig = md.digest(s.getBytes(StandardCharsets.UTF_8));
      StringBuilder sb = new StringBuilder();
      for (byte b : dig) sb.append(String.format("%02x", b));
      return sb.toString();
    } catch (Exception e) {
      // 실패 시 fallback
      return Integer.toHexString(s.hashCode());
    }
  }

  // --------------------------------------------------
  // 디버그: DB 연결 확인
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

  @PostMapping("/signup")
  public Map<String, Object> signup(@RequestBody Map<String, Object> body) throws Exception {
    String username = (String) body.get("username");
    String password = (String) body.get("password");
    String nickname = (String) body.get("nickname");

    if (username == null || username.isBlank()
        || password == null || password.isBlank()
        || nickname == null || nickname.isBlank()) {
      return fail("입력값 오류");
    }

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
          if (rs.next()) return fail("이미 존재하는 아이디");
        }
      }

      // 비밀번호 해시 (BCryptPasswordEncoder)
      String hash = passwordEncoder.encode(password);

      // users INSERT
      try (PreparedStatement ps = conn.prepareStatement(insertSql, Statement.RETURN_GENERATED_KEYS)) {
        ps.setString(1, username);
        ps.setString(2, hash);
        ps.setString(3, nickname);

        int affected = ps.executeUpdate();
        if (affected != 1) return fail("입력 실패");

        try (ResultSet keys = ps.getGeneratedKeys()) {
          if (!keys.next()) return fail("생성된 user_id 키 없음");
          int userId = keys.getInt(1);

          Map<String, Object> data = new HashMap<>();
          data.put("user_id", userId);
          return ok(data);
        }
      }
    }
  }

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
        if (!rs.next()) return fail("아이디 없음");

        int userId = rs.getInt("id");
        String hash = rs.getString("password");

        boolean ok = passwordEncoder.matches(password, hash);
        if (!ok) return fail("비밀번호 오류");

        session.setAttribute("user_id", userId);

        Map<String, Object> data = new HashMap<>();
        data.put("user_id", userId);
        return ok(data);
      }
    }
  }

  @PostMapping("/logout")
  public Map<String, Object> logout(HttpSession session) {
    session.invalidate();
    return ok(Map.of());
  }

  @GetMapping("/me")
  public Map<String, Object> me(HttpSession session) {
    Integer userId = requireLogin(session);
    if (userId == null) return ok(Map.of("logged_in", false));

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
    if (userId == null) return fail("로그인 필요");

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
        if (!rs.next()) return fail("사용자 없음");

        Map<String, Object> data = new HashMap<>();
        data.put("id", rs.getInt("id"));
        data.put("username", rs.getString("username"));
        data.put("nickname", rs.getString("nickname"));
        data.put("email", rs.getString("email"));
        data.put("created_at", rs.getTimestamp("created_at"));

        // profile (없을 수 있음)
        data.put("bio", rs.getString("bio"));
        data.put("phone", rs.getString("phone"));
        data.put("birth_date", rs.getDate("birth_date"));
        data.put("profile_image_url", rs.getString("profile_image_url"));
        data.put("profile_updated_at", rs.getTimestamp("updated_at"));

        return ok(data);
      }
    }
  }

  @PutMapping("/me/profile")
  public Map<String, Object> upsertMyProfile(@RequestBody Map<String, Object> body, HttpSession session) throws Exception {
    Integer userId = requireLogin(session);
    if (userId == null) return fail("로그인 필요");

    String bio = (String) body.get("bio");
    String phone = (String) body.get("phone");
    String birthDate = (String) body.get("birth_date"); // "YYYY-MM-DD" 문자열로 받는 방식
    String profileImageUrl = (String) body.get("profile_image_url");

    // 최소 길이 검증(원하면 더 강화 가능)
    if (bio != null && bio.length() > 300) return fail("입력값 오류");
    if (phone != null && phone.length() > 20) return fail("입력값 오류");
    if (profileImageUrl != null && profileImageUrl.length() > 500) return fail("입력값 오류");

    // UPDATE 먼저
    // 주의: MySQL은 값이 동일하면 affectedRows=0이 될 수 있음
    // 그래서 updated_at을 CURRENT_TIMESTAMP로 강제로 갱신해서 "존재하면 1"이 되도록 처리
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
          ps.setString(3, birthDate); // DATE 컬럼이므로 "YYYY-MM-DD" 문자열 가능
          ps.setString(4, profileImageUrl);
          ps.setInt(5, userId);

          updated = ps.executeUpdate();
        }

        if (updated == 0) {
          // 존재하지 않는 경우에만 INSERT
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

        Map<String, Object> data = new HashMap<>();
        data.put("user_id", userId);
        data.put("upserted", true);
        return ok(data);

      } catch (Exception e) {
        conn.rollback();
        throw e;
      } finally {
        conn.setAutoCommit(true);
      }
    }
  }

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
        if (!rs.next()) return fail("사용자 없음");

        Map<String, Object> data = new HashMap<>();
        data.put("id", rs.getInt("id"));
        data.put("username", rs.getString("username"));
        data.put("nickname", rs.getString("nickname"));
        data.put("email", rs.getString("email"));
        data.put("created_at", rs.getTimestamp("created_at"));

        data.put("bio", rs.getString("bio"));
        data.put("phone", rs.getString("phone"));
        data.put("birth_date", rs.getDate("birth_date"));
        data.put("profile_image_url", rs.getString("profile_image_url"));

        return ok(data);
      }
    }
  }

  // --------------------------------------------------
  // 게시글
  // --------------------------------------------------

  // 게시글 목록(기본 + 선택: page/pageSize/search)
  @GetMapping("/posts")
  public Map<String, Object> postList(
      @RequestParam(value = "page", required = false, defaultValue = "1") int page,
      @RequestParam(value = "pageSize", required = false, defaultValue = "20") int pageSize,
      @RequestParam(value = "type", required = false) String type,
      @RequestParam(value = "keyword", required = false) String keyword) throws Exception {

    if (page < 1) page = 1;
    if (pageSize < 1) pageSize = 20;
    if (pageSize > 50) pageSize = 50;

    int offset = (page - 1) * pageSize;

    // 검색 조건
    String where = "";
    boolean hasSearch = (keyword != null && !keyword.isBlank());

    if (hasSearch) {
      // type은 title / content / both 정도만 허용
      if (type == null || type.isBlank()) type = "both";
      type = type.trim();

      if ("title".equals(type)) {
        where = "WHERE title LIKE ?";
      } else if ("content".equals(type)) {
        where = "WHERE content LIKE ?";
      } else {
        where = "WHERE (title LIKE ? OR content LIKE ?)";
      }
    }

    String listSql;
    if (!hasSearch) {
      listSql = """
          SELECT id, user_id, title, content, view_count, created_at, comments_cnt
          FROM posts
          ORDER BY id DESC
          LIMIT ? OFFSET ?
          """;
    } else {
      if ("both".equals(type)) {
        listSql = """
            SELECT id, user_id, title, content, view_count, created_at, comments_cnt
            FROM posts
            %s
            ORDER BY id DESC
            LIMIT ? OFFSET ?
            """.formatted(where);
      } else {
        listSql = """
            SELECT id, user_id, title, content, view_count, created_at, comments_cnt
            FROM posts
            %s
            ORDER BY id DESC
            LIMIT ? OFFSET ?
            """.formatted(where);
      }
    }

    String countSql;
    if (!hasSearch) {
      countSql = "SELECT COUNT(*) AS cnt FROM posts";
    } else {
      countSql = ("both".equals(type))
          ? ("SELECT COUNT(*) AS cnt FROM posts " + where)
          : ("SELECT COUNT(*) AS cnt FROM posts " + where);
    }

    List<Map<String, Object>> items = new ArrayList<>();
    long total = 0;

    try (Connection conn = dataSource.getConnection()) {

      // total count
      try (PreparedStatement ps = conn.prepareStatement(countSql)) {
        if (hasSearch) {
          String like = "%" + keyword.trim() + "%";
          if ("both".equals(type)) {
            ps.setString(1, like);
            ps.setString(2, like);
          } else {
            ps.setString(1, like);
          }
        }
        try (ResultSet rs = ps.executeQuery()) {
          if (rs.next()) total = rs.getLong("cnt");
        }
      }

      // list
      try (PreparedStatement ps = conn.prepareStatement(listSql)) {
        int idx = 1;
        if (hasSearch) {
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
            row.put("created_at", rs.getTimestamp("created_at"));
            items.add(row);
          }
        }
      }
    }

    Map<String, Object> data = new HashMap<>();
    data.put("page", page);
    data.put("pageSize", pageSize);
    data.put("total", total);
    data.put("items", items);

    return ok(data);
  }

  // 게시글 상세 + 조회수 중복 방지 증가
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
        // 1) 로그 INSERT 시도
        boolean inserted = false;
        try (PreparedStatement ps = conn.prepareStatement(insertLogSql)) {
          ps.setInt(1, id);
          ps.setString(2, viewerKey);
          ps.executeUpdate();
          inserted = true;
        } catch (SQLIntegrityConstraintViolationException dup) {
          // (post_id, viewer_key) UNIQUE 충돌이면 조회수 증가 안 함
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
        Map<String, Object> post = null;
        try (PreparedStatement ps = conn.prepareStatement(selectSql)) {
          ps.setInt(1, id);
          try (ResultSet rs = ps.executeQuery()) {
            if (!rs.next()) {
              conn.rollback();
              return fail("게시글 없음");
            }
            post = new HashMap<>();
            post.put("id", rs.getInt("id"));
            post.put("user_id", rs.getInt("user_id"));
            post.put("title", rs.getString("title"));
            post.put("content", rs.getString("content"));
            post.put("view_count", rs.getInt("view_count"));
            post.put("comments_cnt", rs.getInt("comments_cnt"));
            post.put("created_at", rs.getTimestamp("created_at"));
            post.put("viewer_key", viewerKey);
          }
        }

        conn.commit();
        return ok(post);

      } catch (Exception e) {
        conn.rollback();
        throw e;
      } finally {
        conn.setAutoCommit(true);
      }
    }
  }

  @PostMapping("/posts")
  public Map<String, Object> createPost(@RequestBody Map<String, Object> body, HttpSession session) throws Exception {
    Integer userId = requireLogin(session);
    if (userId == null) return fail("로그인 필요");

    String title = (String) body.get("title");
    String content = (String) body.get("content");

    if (title == null || title.isBlank() || content == null || content.isBlank()) return fail("입력값 오류");

    // posts.title은 varchar(45)
    if (title.length() > 45) return fail("입력값 오류");

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
      if (affected != 1) return fail("입력 실패");

      try (ResultSet keys = ps.getGeneratedKeys()) {
        if (!keys.next()) return fail("생성된 post_id 키 없음");
        int postId = keys.getInt(1);

        Map<String, Object> data = new HashMap<>();
        data.put("post_id", postId);
        return ok(data);
      }
    }
  }

  @PutMapping("/posts/{id}")
  public Map<String, Object> updatePost(
      @PathVariable("id") int id,
      @RequestBody Map<String, Object> body,
      HttpSession session) throws Exception {

    Integer userId = requireLogin(session);
    if (userId == null) return fail("로그인 필요");

    String title = (String) body.get("title");
    String content = (String) body.get("content");

    if (title == null || title.isBlank() || content == null || content.isBlank()) return fail("입력값 오류");
    if (title.length() > 45) return fail("입력값 오류");

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

      // 작성자 확인
      Integer ownerId = null;
      try (PreparedStatement ps = conn.prepareStatement(ownerSql)) {
        ps.setInt(1, id);
        try (ResultSet rs = ps.executeQuery()) {
          if (!rs.next()) return fail("게시글 없음");
          ownerId = rs.getInt("user_id");
        }
      }

      if (ownerId == null || ownerId.intValue() != userId.intValue()) return fail("권한 없음");

      // UPDATE
      try (PreparedStatement ps = conn.prepareStatement(updateSql)) {
        ps.setString(1, title);
        ps.setString(2, content);
        ps.setInt(3, id);

        int updated = ps.executeUpdate();
        return ok(Map.of("updated", updated));
      }
    }
  }

  @DeleteMapping("/posts/{id}")
  public Map<String, Object> deletePost(@PathVariable("id") int id, HttpSession session) throws Exception {
    Integer userId = requireLogin(session);
    if (userId == null) return fail("로그인 필요");

    String ownerSql = """
        SELECT user_id
        FROM posts
        WHERE id = ?
        """;

    String deleteSql = "DELETE FROM posts WHERE id = ?";

    try (Connection conn = dataSource.getConnection()) {

      Integer ownerId = null;
      try (PreparedStatement ps = conn.prepareStatement(ownerSql)) {
        ps.setInt(1, id);
        try (ResultSet rs = ps.executeQuery()) {
          if (!rs.next()) return fail("게시글 없음");
          ownerId = rs.getInt("user_id");
        }
      }

      if (ownerId == null || ownerId.intValue() != userId.intValue()) return fail("권한 없음");

      try (PreparedStatement ps = conn.prepareStatement(deleteSql)) {
        ps.setInt(1, id);
        int deleted = ps.executeUpdate();
        // comments는 FK ON DELETE CASCADE로 자동 삭제
        return ok(Map.of("deleted", deleted));
      }
    }
  }

  // --------------------------------------------------
  // 댓글
  // --------------------------------------------------

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
          row.put("created_at", rs.getTimestamp("created_at"));
          items.add(row);
        }
      }
    }

    return ok(Map.of("items", items));
  }

  @PostMapping("/posts/{postId}/comments")
  public Map<String, Object> createComment(
      @PathVariable("postId") int postId,
      @RequestBody Map<String, Object> body,
      HttpSession session) throws Exception {

    Integer userId = requireLogin(session);
    if (userId == null) return fail("로그인 필요");

    String comment = (String) body.get("comment");
    if (comment == null || comment.isBlank()) return fail("입력값 오류");
    if (comment.length() > 255) return fail("입력값 오류");

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

  @PutMapping("/comments/{commentId}")
  public Map<String, Object> updateComment(
      @PathVariable("commentId") int commentId,
      @RequestBody Map<String, Object> body,
      HttpSession session) throws Exception {

    Integer userId = requireLogin(session);
    if (userId == null) return fail("로그인 필요");

    String comment = (String) body.get("comment");
    if (comment == null || comment.isBlank()) return fail("입력값 오류");
    if (comment.length() > 255) return fail("입력값 오류");

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

      Integer ownerId = null;
      try (PreparedStatement ps = conn.prepareStatement(ownerSql)) {
        ps.setInt(1, commentId);
        try (ResultSet rs = ps.executeQuery()) {
          if (!rs.next()) return fail("댓글 없음");
          ownerId = rs.getInt("user_id");
        }
      }

      if (ownerId == null || ownerId.intValue() != userId.intValue()) return fail("권한 없음");

      try (PreparedStatement ps = conn.prepareStatement(updateSql)) {
        ps.setString(1, comment);
        ps.setInt(2, commentId);
        int updated = ps.executeUpdate();
        return ok(Map.of("updated", updated));
      }
    }
  }

  @DeleteMapping("/comments/{commentId}")
  public Map<String, Object> deleteComment(@PathVariable("commentId") int commentId, HttpSession session) throws Exception {
    Integer userId = requireLogin(session);
    if (userId == null) return fail("로그인 필요");

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