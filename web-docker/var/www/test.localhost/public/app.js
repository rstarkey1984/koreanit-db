// =========================
// 설정
// =========================
const API_BASE = "http://localhost:9091"; // 백엔드 주소로 변경

// 세션 쿠키(JSESSIONID) 유지가 핵심
async function api(path, options = {}) {
  const url = API_BASE + path;
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

  // 서버가 항상 JSON(ok/fail) 반환한다고 가정
  let json = null;
  const text = await res.text();
  try { json = text ? JSON.parse(text) : null; }
  catch { json = { ok: false, message: "JSON 파싱 실패", raw: text }; }

  // HTTP 레벨 에러도 같이 표시
  if (!res.ok) {
    return { ok: false, message: `HTTP ${res.status}`, data: json };
  }

  return json;
}

// =========================
// DOM
// =========================
const $ = (id) => document.getElementById(id);

const logEl = $("log");
function log(...args) {
  const line = args.map(a => {
    if (typeof a === "string") return a;
    try { return JSON.stringify(a, null, 2); } catch { return String(a); }
  }).join(" ");
  logEl.textContent = (line + "\n" + logEl.textContent).slice(0, 6000);
}

function setAuthStatus(text) {
  $("authStatus").textContent = text;
}

// =========================
// Auth
// =========================
let state = {
  me: { logged_in: false, user_id: null },
  posts: {
    page: 1,
    pageSize: 20,
    type: "both",
    keyword: "",
    total: 0,
    items: []
  },
  selectedPostId: null,
  lastViewerKey: null
};

async function refreshMe() {
  const r = await api("/me");
  if (r.ok) {
    state.me = r.data;
    if (state.me.logged_in) {
      setAuthStatus(`로그인됨 (user_id=${state.me.user_id})`);
    } else {
      setAuthStatus("비로그인");
    }
  } else {
    setAuthStatus("로그인 상태 확인 실패");
    log("[/me fail]", r);
  }
}

async function signup() {
  const username = $("suUsername").value.trim();
  const password = $("suPassword").value.trim();
  const nickname = $("suNickname").value.trim();

  const r = await api("/signup", {
    method: "POST",
    body: { username, password, nickname }
  });

  if (r.ok) {
    log("[signup ok]", r.data);
  } else {
    log("[signup fail]", r.message);
  }
}

async function login() {
  const username = $("liUsername").value.trim();
  const password = $("liPassword").value.trim();

  const r = await api("/login", {
    method: "POST",
    body: { username, password }
  });

  if (r.ok) {
    log("[login ok]", r.data);
    await refreshMe();
  } else {
    log("[login fail]", r.message);
  }
}

async function logout() {
  const r = await api("/logout", { method: "POST", body: {} });
  if (r.ok) {
    log("[logout ok]");
    await refreshMe();
  } else {
    log("[logout fail]", r.message);
  }
}

// =========================
// Profile
// =========================
async function loadMyProfile() {
  const r = await api("/me/profile");
  if (!r.ok) {
    log("[myProfile fail]", r.message);
    return;
  }

  const d = r.data;
  $("pfBio").value = d.bio ?? "";
  $("pfPhone").value = d.phone ?? "";
  $("pfBirth").value = d.birth_date ?? ""; // 서버가 Date로 내려주면 문자열화 형태가 다를 수 있음
  $("pfImg").value = d.profile_image_url ?? "";
  log("[myProfile ok]", d);
}

async function saveMyProfile() {
  const bio = $("pfBio").value.trim();
  const phone = $("pfPhone").value.trim();
  const birth_date = $("pfBirth").value.trim();
  const profile_image_url = $("pfImg").value.trim();

  const body = {
    bio: bio === "" ? null : bio,
    phone: phone === "" ? null : phone,
    birth_date: birth_date === "" ? null : birth_date,
    profile_image_url: profile_image_url === "" ? null : profile_image_url,
  };

  const r = await api("/me/profile", { method: "PUT", body });
  if (r.ok) {
    log("[profile upsert ok]", r.data);
    await loadMyProfile();
  } else {
    log("[profile upsert fail]", r.message);
  }
}

// =========================
// Posts
// =========================
function getQueryParamsFromUI() {
  const page = parseInt($("page").value || "1", 10);
  const pageSize = parseInt($("pageSize").value || "20", 10);
  const type = $("searchType").value;
  const keyword = $("searchKeyword").value.trim();

  state.posts.page = isNaN(page) || page < 1 ? 1 : page;
  state.posts.pageSize = isNaN(pageSize) ? 20 : pageSize;
  state.posts.type = type;
  state.posts.keyword = keyword;

  return { page: state.posts.page, pageSize: state.posts.pageSize, type, keyword };
}

function buildPostsUrl() {
  const { page, pageSize, type, keyword } = getQueryParamsFromUI();

  const q = new URLSearchParams();
  q.set("page", String(page));
  q.set("pageSize", String(pageSize));
  if (keyword) {
    q.set("type", type);
    q.set("keyword", keyword);
  }
  return "/posts?" + q.toString();
}

function renderPosts() {
  const ul = $("postsList");
  ul.innerHTML = "";

  const { page, pageSize, total, items } = state.posts;
  const totalPages = Math.max(1, Math.ceil(total / pageSize));

  $("postsMeta").textContent = `total=${total}, items=${items.length}`;
  $("pageTotal").textContent = `/ ${totalPages}`;

  for (const p of items) {
    const li = document.createElement("li");
    li.className = "listItem";

    const created = p.created_at ? String(p.created_at) : "";
    li.innerHTML = `
      <div class="listItem__title">
        <button class="linkBtn" data-post-id="${p.id}">
          #${p.id} ${escapeHtml(p.title)}
        </button>
        <span class="badge">v:${p.view_count} c:${p.comments_cnt}</span>
      </div>
      <div class="listItem__meta">
        <span>user_id=${p.user_id}</span>
        <span>${escapeHtml(created)}</span>
      </div>
    `;

    li.querySelector("button").addEventListener("click", () => {
      openPostDetail(p.id);
    });

    ul.appendChild(li);
  }
}

async function loadPosts() {
  const url = buildPostsUrl();
  const r = await api(url);
  if (!r.ok) {
    log("[posts fail]", r.message);
    return;
  }

  state.posts.total = r.data.total;
  state.posts.items = r.data.items || [];

  renderPosts();
}

async function openPostDetail(postId) {
  state.selectedPostId = postId;
  $("commentPostId").value = String(postId);

  // 조회수 중복 방지 테스트를 위해 viewer_key를 서버가 내려주는 값을 저장
  const r = await api(`/posts/${postId}`);
  if (!r.ok) {
    $("postDetail").textContent = "게시글 상세 조회 실패";
    log("[post detail fail]", r.message);
    return;
  }

  const p = r.data;
  state.lastViewerKey = p.viewer_key || null;

  renderPostDetail(p);
  fillPostEditor(p);

  await loadComments(postId);
}

function renderPostDetail(p) {
  const el = $("postDetail");
  el.innerHTML = `
    <div class="kv">
      <div><b>id</b>: ${p.id}</div>
      <div><b>user_id</b>: ${p.user_id}</div>
      <div><b>title</b>: ${escapeHtml(p.title)}</div>
      <div><b>content</b>: <div class="small">${escapeHtml(p.content)}</div></div>
      <div><b>view_count</b>: ${p.view_count}</div>
      <div><b>comments_cnt</b>: ${p.comments_cnt}</div>
      <div><b>created_at</b>: <span class="small">${escapeHtml(String(p.created_at ?? ""))}</span></div>
      <div><b>viewer_key</b>: <span class="small">${escapeHtml(String(p.viewer_key ?? ""))}</span></div>
    </div>
  `;
}

function fillPostEditor(p) {
  $("editPostId").value = String(p.id);
  $("editTitle").value = p.title ?? "";
  $("editContent").value = p.content ?? "";
}

function clearPostEditor() {
  $("editPostId").value = "";
  $("editTitle").value = "";
  $("editContent").value = "";
}

function openNewPost() {
  state.selectedPostId = null;
  $("postDetail").textContent = "새 글 작성 모드";
  $("commentPostId").value = "";
  $("commentsList").innerHTML = "";
  clearPostEditor();
}

async function savePost() {
  const id = $("editPostId").value.trim();
  const title = $("editTitle").value.trim();
  const content = $("editContent").value.trim();

  if (!title || !content) {
    log("[save post]", "입력값 오류");
    return;
  }

  if (!id) {
    // create
    const r = await api("/posts", { method: "POST", body: { title, content } });
    if (!r.ok) {
      log("[create post fail]", r.message);
      return;
    }
    log("[create post ok]", r.data);
    await loadPosts();
    await openPostDetail(r.data.post_id);
  } else {
    // update
    const r = await api(`/posts/${id}`, { method: "PUT", body: { title, content } });
    if (!r.ok) {
      log("[update post fail]", r.message);
      return;
    }
    log("[update post ok]", r.data);
    await loadPosts();
    await openPostDetail(parseInt(id, 10));
  }
}

async function deletePost() {
  const id = $("editPostId").value.trim();
  if (!id) {
    log("[delete post]", "삭제할 글이 선택되지 않았습니다");
    return;
  }

  const r = await api(`/posts/${id}`, { method: "DELETE" });
  if (!r.ok) {
    log("[delete post fail]", r.message);
    return;
  }

  log("[delete post ok]", r.data);
  openNewPost();
  await loadPosts();
}

// =========================
// Comments
// =========================
async function loadComments(postId) {
  const r = await api(`/posts/${postId}/comments`);
  if (!r.ok) {
    log("[comments fail]", r.message);
    $("commentsList").innerHTML = "";
    return;
  }

  renderComments(r.data.items || []);
}

function renderComments(items) {
  const ul = $("commentsList");
  ul.innerHTML = "";

  for (const c of items) {
    const li = document.createElement("li");
    li.className = "listItem";

    li.innerHTML = `
      <div class="listItem__title">
        <span>#${c.id} (user_id=${c.user_id})</span>
        <span class="small">${escapeHtml(String(c.created_at ?? ""))}</span>
      </div>
      <div class="small" style="margin-top:8px;">${escapeHtml(c.comment)}</div>
      <div class="row" style="margin-top:10px;">
        <button class="btn btn-ghost" data-act="edit">수정</button>
        <button class="btn btn-danger" data-act="del">삭제</button>
      </div>
    `;

    li.querySelector('[data-act="edit"]').addEventListener("click", () => {
      $("editCommentId").value = String(c.id);
      $("commentText").value = c.comment ?? "";
      log("[comment edit mode]", { commentId: c.id });
    });

    li.querySelector('[data-act="del"]').addEventListener("click", async () => {
      await deleteComment(c.id);
    });

    ul.appendChild(li);
  }
}

async function addComment() {
  const postId = $("commentPostId").value.trim();
  if (!postId) {
    log("[add comment]", "게시글을 먼저 선택하세요");
    return;
  }

  const comment = $("commentText").value.trim();
  if (!comment) {
    log("[add comment]", "입력값 오류");
    return;
  }

  const r = await api(`/posts/${postId}/comments`, { method: "POST", body: { comment } });
  if (!r.ok) {
    log("[add comment fail]", r.message);
    return;
  }

  log("[add comment ok]", r.data);
  $("commentText").value = "";
  $("editCommentId").value = "";

  // 목록/상세 갱신(댓글 카운트 반영)
  await loadComments(parseInt(postId, 10));
  await openPostDetail(parseInt(postId, 10));
}

async function updateComment() {
  const commentId = $("editCommentId").value.trim();
  const postId = $("commentPostId").value.trim();

  if (!commentId) {
    log("[update comment]", "수정할 댓글을 선택하세요");
    return;
  }

  const comment = $("commentText").value.trim();
  if (!comment) {
    log("[update comment]", "입력값 오류");
    return;
  }

  const r = await api(`/comments/${commentId}`, { method: "PUT", body: { comment } });
  if (!r.ok) {
    log("[update comment fail]", r.message);
    return;
  }

  log("[update comment ok]", r.data);
  $("commentText").value = "";
  $("editCommentId").value = "";

  await loadComments(parseInt(postId, 10));
}

async function deleteComment(commentId) {
  const postId = $("commentPostId").value.trim();

  const r = await api(`/comments/${commentId}`, { method: "DELETE" });
  if (!r.ok) {
    log("[delete comment fail]", r.message);
    return;
  }

  log("[delete comment ok]", r.data);

  // 목록/상세 갱신(댓글 카운트 반영)
  await loadComments(parseInt(postId, 10));
  await openPostDetail(parseInt(postId, 10));
}

function cancelCommentEdit() {
  $("editCommentId").value = "";
  $("commentText").value = "";
}

// =========================
// Utils
// =========================
function escapeHtml(s) {
  return String(s ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

// =========================
// 이벤트 바인딩
// =========================
$("btnRefreshMe").addEventListener("click", refreshMe);

$("btnSignup").addEventListener("click", signup);
$("btnLogin").addEventListener("click", login);
$("btnLogout").addEventListener("click", logout);

$("btnLoadMyProfile").addEventListener("click", loadMyProfile);
$("btnSaveMyProfile").addEventListener("click", saveMyProfile);

$("btnReloadPosts").addEventListener("click", loadPosts);
$("btnOpenNewPost").addEventListener("click", openNewPost);
$("btnSavePost").addEventListener("click", savePost);
$("btnCancelEdit").addEventListener("click", () => {
  if (state.selectedPostId) openPostDetail(state.selectedPostId);
  else openNewPost();
});
$("btnDeletePost").addEventListener("click", deletePost);

$("btnSearch").addEventListener("click", () => {
  $("page").value = "1";
  loadPosts();
});
$("pageSize").addEventListener("change", () => {
  $("page").value = "1";
  loadPosts();
});

$("btnPrev").addEventListener("click", () => {
  const p = Math.max(1, parseInt($("page").value || "1", 10) - 1);
  $("page").value = String(p);
  loadPosts();
});
$("btnNext").addEventListener("click", () => {
  const p = parseInt($("page").value || "1", 10) + 1;
  $("page").value = String(p);
  loadPosts();
});
$("page").addEventListener("change", () => {
  const p = Math.max(1, parseInt($("page").value || "1", 10));
  $("page").value = String(p);
  loadPosts();
});

// comments
$("btnAddComment").addEventListener("click", addComment);
$("btnUpdateComment").addEventListener("click", updateComment);
$("btnCancelCommentEdit").addEventListener("click", cancelCommentEdit);

// =========================
// 초기 로드
// =========================
(async function init() {
  await refreshMe();
  await loadPosts();
})();
