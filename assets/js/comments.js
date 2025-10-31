(function () {
  // 뒤로가기 자동 스크롤 방지
  if ("scrollRestoration" in history) {
    try { history.scrollRestoration = "manual"; } catch (_) {}
  }

  const section  = document.getElementById("comment-section");
  if (!section) return;

  // ----- 서버 전달 값 -----
  const postId  = Number(section.dataset.postId || 0);
  const parseBool = (v) => (v === "1" || v === "true" || v === true);
  let hasMore = parseBool(section.dataset.hasMore);

  const listUrl = String(section.dataset.listUrl || "").replace(/\/+$/,"");

  // ----- 엘리먼트 -----
  const list     = document.getElementById("comment-list"); // li.comment-item을 담는 컨테이너
  const sentinel = document.getElementById("cmt-sentinel"); // 추가 로드 트리거(무한 스크롤)
  const newForm  = document.getElementById("new-comment");
  const extBtn   = document.getElementById("btn-new-comment");

  // 마지막 li(.comment-item)의 path 안전 획득
  // 목록의 마지막 항목의 data-path를 afterPath로 기록해서 다음에 불러올 항목 찾기
  const lastItem = () => {
    const items = list ? list.querySelectorAll(".comment-item") : [];
    return items.length ? items[items.length - 1] : null;
  };
  let afterPath = lastItem()?.dataset.path || "";

  let loading  = false;
  let inflight = null;

  // 대댓글 들여쓰기 
  function applyDepthColors(scope = document) {
    scope.querySelectorAll(".comment-item").forEach((el) => {
      const d = Number(el.dataset.depth || 0);
      el.style.setProperty("--depth", d);
    });
  }
  applyDepthColors(list || document);

  // 스크롤 컨테이너(overflow auto/scroll)면 그걸 root로, 아니면 뷰포트
  function isScrollable(el) {
    if (!el) return false;
    const s = getComputedStyle(el);
    return /(auto|scroll)/.test(s.overflow + s.overflowY + s.overflowX);
  }
  const rootEl = isScrollable(section) ? section : null;

  // =========================================
  // 무한 스크롤 구현
  // =========================================
  async function loadComments() {
    if (loading || !hasMore) return false;
    loading = true;

    if (sentinel && io) io.unobserve(sentinel); // 로딩 중 중복 트리거 방지
    if (inflight) inflight.abort();             // 이전 요청 있으면 취소
    inflight = new AbortController();

    try {
      const qs  = new URLSearchParams({ afterPath, limit: 200 });
      const url = `${listUrl}/${postId}?${qs.toString()}`;
      const res = await fetch(url, {
        credentials: "same-origin",
        signal: inflight.signal
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data.status !== "success") throw new Error("load fail");

      // li HTML 조걱을 덧붙임
      if (typeof data.html === "string" && data.html.trim()) {
        list.insertAdjacentHTML("beforeend", data.html);
        applyDepthColors(list);
      }

      // 커서/더보기 갱신
      afterPath = data.nextCursor || afterPath;
      hasMore   = !!data.hasMore;

      // 새로 붙은 마지막 항목 다시 확인
      afterPath = lastItem()?.dataset.path || afterPath;

      if (hasMore && sentinel && io) io.observe(sentinel);
      return true;
    } catch (e) {
      if (e.name !== "AbortError") console.error("[comments] load error:", e);
      if (hasMore && sentinel && io) io.observe(sentinel);
      return false;
    } finally {
      loading = false;
      inflight = null;
    }
  }

  // ----- IO & 초기 보충 로드 -----
  const io = (typeof IntersectionObserver !== "undefined")
    ? new IntersectionObserver(
        ([entry]) => { if (entry.isIntersecting) loadComments(); },
        {
          root: rootEl,               // 뷰포트 또는 스크롤 컨테이너
          rootMargin: "600px 0px",    // 여유를 넉넉히
          threshold: 0
        }
      )
    : null;

  if (io && sentinel && hasMore) io.observe(sentinel);

  // 1) 페이지 높이가 낮으면 즉시 보충 로드
  window.addEventListener("load", () => {
    const doc = document.documentElement;
    if (hasMore && doc.scrollHeight <= window.innerHeight + 1) {
      loadComments();
    }
  });

  // 2) 혹시 IO 트리거가 안 걸린 경우를 대비해 첫 틱에서 한 번 더 시도
  queueMicrotask(() => { if (hasMore) loadComments(); });

  // ----- 답글 폼 show/hide -----
  document.addEventListener("click", (e) => {
    const replyBtn = e.target.closest(".btn-reply");
    if (replyBtn) {
      const form = replyBtn.nextElementSibling;
      if (form) {
        const show = form.style.display !== "block";
        form.style.display = show ? "block" : "none";
        if (show) form.querySelector("textarea")?.focus();
      }
    }
    const cancelBtn = e.target.closest(".btn-cancel-reply");
    if (cancelBtn) {
      const form = cancelBtn.closest("form");
      if (form) { form.reset(); form.style.display = "none"; }
    }
  });

  // ----- 제출 버튼 → form.submit -----
  if (extBtn && newForm) {
    extBtn.addEventListener("click", (ev) => {
      ev.preventDefault();
      newForm.requestSubmit();
    });
  }

  // ----- 제출 처리 (신규/답글 공통) -----
  if (newForm) newForm.addEventListener("submit", handleSubmit);
  document.addEventListener("submit", (e) => {
    if (e.target.matches("form.reply-form")) handleSubmit(e);
  }, true);

  async function handleSubmit(e) {
    const form = e.target;
    e.preventDefault();

    try {
      const res  = await fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        credentials: "same-origin",
        headers: { "X-Requested-With": "XMLHttpRequest" }
      });

      const text = await res.text();
      let data   = null;
      try { data = JSON.parse(text); } catch {}

      if (!data || data.status !== "success") {
        alert("댓글 등록 실패: " + ((data && data.message) || "알 수 없는 오류"));
        return;
      }

      const hasHtml = typeof data.html === "string" && data.html.trim().length > 0;

      if (hasHtml) {
        const tpl = document.createElement("template");
        tpl.innerHTML = data.html.trim();
        const newEl = tpl.content.firstElementChild;

        if (form.classList.contains("reply-form")) {
          const parentLi = form.closest(".comment-item");
          let childrenUl =
            parentLi.querySelector("ul.children") ||
            parentLi.querySelector("ul.reply-children") ||
            parentLi.querySelector("ul");
          if (!childrenUl) {
            childrenUl = document.createElement("ul");
            childrenUl.className = "children";
            childrenUl.style.listStyle = "none";
            childrenUl.style.paddingLeft = "0";
            parentLi.appendChild(childrenUl);
          }
          childrenUl.appendChild(newEl);
        } else {
          list.appendChild(newEl);
        }
        if (form.tagName === "FORM") form.reset();

        // 새 커서/색상 반영
        applyDepthColors(newEl.ownerDocument || document);
        afterPath = lastItem()?.dataset.path || afterPath;
        // 새 댓글 추가 후 즉시 다음 페이지 프리패치 유도
        if (hasMore && io && sentinel) io.observe(sentinel);
        return;
      }

      // html 미포함 → focus 리다이렉트
      const newId = data.comment_id || data.id;
      if (newId) {
        const url = new URL(location.href);
        url.searchParams.set("focus", newId);
        location.href = url.toString();
        return;
      }

      location.reload();
    } catch (err) {
      console.error(err);
      alert("네트워크 오류가 발생했습니다.");
    }
  }


  // =========================================================
  // 여기서부터 댓글 등록 성공 시 자동 스크롤 + 하이라이트 관련 코드
  // =========================================================

  function scrollToWithOffset(el, offset = 100) {
    if (!el) return;
    const rect   = el.getBoundingClientRect();
    const top    = window.pageYOffset + rect.top - offset;
    window.scrollTo({ top, behavior: "smooth" });
  }
  function highlightOnce(el, ms = 2200) {
    if (!el) return;
    el.classList.add("cmt-highlight");
    setTimeout(() => el.classList.remove("cmt-highlight"), ms);
  }

  // focus 대상 ID 파싱
  const urlParams = new URLSearchParams(location.search);
  let focusId = Number(urlParams.get("focus") || 0);

  // ----- ★ 추가: 특정 댓글이 DOM에 나타날 때까지 로드/포커스 -----
  async function ensureFocusVisible(targetId, maxLoads = 10) {
    if (!targetId) return;

    // 1) 이미 DOM에 있으면 바로 처리
    let el = document.getElementById("comment-" + targetId);
    if (el) {
      el.scrollIntoView({ behavior: "instant", block: "center" });
      // 약간 위 여백을 위해 보정 스크롤
      scrollToWithOffset(el, 120);
      highlightOnce(el);
      // URL의 focus 파라미터 제거(새로고침 없이)
      try {
        urlParams.delete("focus");
        const clean = location.pathname + (urlParams.toString() ? "?" + urlParams.toString() : "") + location.hash;
        history.replaceState(null, "", clean);
      } catch (_) {}
      return;
    }

    // 2) 없으면 무한스크롤로 최대 N번까지 더 불러본다
    for (let i = 0; i < maxLoads && hasMore; i++) {
      const ok = await loadComments();   // 기존 함수 재사용
      el = document.getElementById("comment-" + targetId);
      if (el) {
        // 약간의 렌더 타이밍 보정
        await new Promise(r => setTimeout(r, 30));
        scrollToWithOffset(el, 120);
        highlightOnce(el);
        try {
          urlParams.delete("focus");
          const clean = location.pathname + (urlParams.toString() ? "?" + urlParams.toString() : "") + location.hash;
          history.replaceState(null, "", clean);
        } catch (_) {}
        return;
      }
      if (!ok) break; // 로드 실패 시 루프 중단
    }
    // 못 찾았으면 포기(에러는 내지 않음)
  }

  // ... (기존 IO/무한스크롤 설정 코드 유지)

  // ----- 제출 처리 (신규/답글 공통) -----
  async function handleSubmit(e) {
    const form = e.target;
    e.preventDefault();

    try {
      const res  = await fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        credentials: "same-origin",
        headers: { "X-Requested-With": "XMLHttpRequest" }
      });

      const text = await res.text();
      let data   = null;
      try { data = JSON.parse(text); } catch {}

      if (!data || data.status !== "success") {
        alert("댓글 등록 실패: " + ((data && data.message) || "알 수 없는 오류"));
        return;
      }

      if (data.message) {
        alert(data.message);
      }

      const hasHtml = typeof data.html === "string" && data.html.trim().length > 0;

      if (hasHtml) {
        const tpl = document.createElement("template");
        tpl.innerHTML = data.html.trim();
        const newEl = tpl.content.firstElementChild;

        if (form.classList.contains("reply-form")) {
          const parentLi = form.closest(".comment-item");
          let childrenUl =
            parentLi.querySelector("ul.children") ||
            parentLi.querySelector("ul.reply-children") ||
            parentLi.querySelector("ul");
          if (!childrenUl) {
            childrenUl = document.createElement("ul");
            childrenUl.className = "children";
            childrenUl.style.listStyle = "none";
            childrenUl.style.paddingLeft = "0";
            parentLi.appendChild(childrenUl);
          }
          childrenUl.appendChild(newEl);
        } else {
          list.appendChild(newEl);
        }
        if (form.tagName === "FORM") form.reset();

        // 새 커서/색상 반영
        applyDepthColors(newEl.ownerDocument || document);
        afterPath = lastItem()?.dataset.path || afterPath;

        // ----- ★ 추가: 방금 추가된 요소로 스크롤 + 하이라이트
        highlightOnce(newEl);
        // 스크롤 기준 여백(헤더 높이 등) 보정
        scrollToWithOffset(newEl, 120);

        // 새 댓글 추가 후 즉시 다음 페이지 프리패치 유도
        if (hasMore && io && sentinel) io.observe(sentinel);
        return;
      }

      // html 미포함 → focus 리다이렉트
      const newId = data.comment_id || data.id;
      if (newId) {
        const url = new URL(location.href);
        url.searchParams.set("focus", newId);
        location.href = url.toString();
        return;
      }

      location.reload();
    } catch (err) {
      console.error(err);
      alert("네트워크 오류가 발생했습니다.");
    }
  }

  // ----- ★ 추가: 첫 페이지 로드시 focus 대상 보장 처리 -----
  window.addEventListener("load", () => {
    if (focusId > 0) ensureFocusVisible(focusId);
  });

  // // 디버그
  // window.__debug = { postId, hasMore, afterPath, listExists: !!list, rootEl: !!rootEl };
  // console.log("[COMMENTS ready]", window.__debug);
})();
