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