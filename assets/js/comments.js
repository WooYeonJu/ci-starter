(function () {
  // 뒤로가기 자동 스크롤 방지
  if ("scrollRestoration" in history) {
    try { history.scrollRestoration = "manual"; } catch (_) {}
  }

  const section = document.getElementById("comment-section");
  if (!section) return;

  // ----- 서버 전달 값 -----
  const postId = Number(section.dataset.postId || 0);
  const parseBool = (v) => (v === "1" || v === "true" || v === true);
  let hasMore = parseBool(section.dataset.hasMore);
  const listUrl = String(section.dataset.listUrl || "").replace(/\/+$/, "");
  const myUserId = Number(section.dataset.userId || 0); // 작성자 식별용

  // ----- 엘리먼트 -----
  const list = document.getElementById("comment-list");   // li.comment-item 컨테이너
  const sentinel = document.getElementById("cmt-sentinel"); // 무한 스크롤 트리거
  const newForm = document.getElementById("new-comment");
  const extBtn = document.getElementById("btn-new-comment");

  // 마지막 li(.comment-item)의 path 안전 획득
  const lastItem = () => {
    const items = list ? list.querySelectorAll(".comment-item") : [];
    return items.length ? items[items.length - 1] : null;
  };
  let afterPath = lastItem()?.dataset.path || "";

  let loading = false;
  let inflight = null;

  // 클라이언트가 “방금 붙인” 댓글 ID 기록(중복 삽입 방지용)
  const clientJustAdded = new Set();

  // 대댓글 들여쓰기 스타일 변수 적용
  function applyDepthColors(scope = document) {
    scope.querySelectorAll(".comment-item").forEach((el) => {
      const d = Number(el.dataset.depth || 0);
      el.style.setProperty("--depth", d);
    });
  }
  applyDepthColors(list || document);

  // 스크롤 컨테이너(overflow: auto/scroll)면 그걸 root로, 아니면 뷰포트
  function isScrollable(el) {
    if (!el) return false;
    const s = getComputedStyle(el);
    return /(auto|scroll)/.test(s.overflow + s.overflowY + s.overflowX);
  }
  const rootEl = isScrollable(section) ? section : null;

  // =========================================
  // 댓글 개수 갱신 유틸
  // =========================================
  function getCountEl() { return document.getElementById("comment-count"); }
  function setCount(n) {
    const el = getCountEl(); if (!el) return;
    el.dataset.count = String(n);
    el.textContent = String(n);
  }
  function incCount(by = 1) {
    const el = getCountEl(); if (!el) return;
    const cur = parseInt(el.dataset.count || el.textContent || "0", 10) || 0;
    setCount(cur + by);
  }

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
      const qs = new URLSearchParams({ afterPath, limit: 200 });
      const url = `${listUrl}/${postId}?${qs.toString()}`;
      const res = await fetch(url, {
        credentials: "same-origin",
        signal: inflight.signal
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data.status !== "success") throw new Error("load fail");

      // li HTML 조각을 덧붙임 (중복 방지)
      if (typeof data.html === "string" && data.html.trim()) {
        const tpl = document.createElement("template");
        tpl.innerHTML = data.html.trim();
        const nodes = Array.from(tpl.content.querySelectorAll(".comment-item"));
        const frag = document.createDocumentFragment();
        for (const n of nodes) {
          const idStr = n.id || "";
          if (idStr && document.getElementById(idStr)) continue; // 이미 있으면 skip
          n.dataset.origin = "server"; // 커서 계산용 마킹
          frag.appendChild(n);
        }
        if (frag.childNodes.length) {
          list.appendChild(frag);
          applyDepthColors(list);
        }
      }

      // 커서/더보기 갱신 (서버 우선, 없으면 이번에 서버가 준 항목의 마지막 path 사용)
      if (typeof data.hasMore === "boolean") hasMore = data.hasMore;

      if (typeof data.nextCursor === "string" && data.nextCursor) {
        afterPath = data.nextCursor;
      } else {
        const serverItems = Array.from(list.querySelectorAll('.comment-item[data-origin="server"]'));
        const lastServer = serverItems[serverItems.length - 1];
        const p = lastServer?.dataset.path;
        if (p) afterPath = p; // 서버 조각이 없으면 afterPath 유지
      }

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
      { root: rootEl, rootMargin: "600px 0px", threshold: 0 }
    )
    : null;

  if (io && sentinel && hasMore) io.observe(sentinel);

  // 1) 페이지 높이가 낮으면 즉시 보충 로드
  window.addEventListener("load", () => {
    const doc = document.documentElement;
    if (hasMore && doc.scrollHeight <= window.innerHeight + 1) loadComments();
  });

  // 2) 혹시 IO 트리거가 안 걸린 경우를 대비해 첫 틱에서 한 번 더 시도
  queueMicrotask(() => { if (hasMore) loadComments(); });

  // ----- 답글 폼 show/hide -----
  document.addEventListener("click", (e) => {
    const replyBtn = e.target.closest(".btn-reply");
    if (replyBtn) {
          let targetForm = replyBtn.nextElementSibling;
    if (!(targetForm && targetForm.matches("form.reply-form"))) {
      targetForm = replyBtn.parentElement?.querySelector("form.reply-form") || null;
    }

    // 현재 열려있는 다른 모든 답글 폼 닫기
    document.querySelectorAll("form.reply-form").forEach((f) => {
      if (f !== targetForm && f.style.display === "block") {
        try { f.reset(); } catch (_) {}
        f.style.display = "none";
      }
    });
    // 타깃 폼 토글
    if (targetForm) {
      const show = targetForm.style.display !== "block";
      targetForm.style.display = show ? "block" : "none";
      if (show) targetForm.querySelector("textarea")?.focus();
    }
    return; // 이 분기에서 끝
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
      const res = await fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        credentials: "same-origin",
        headers: { "X-Requested-With": "XMLHttpRequest" }
      });

      const text = await res.text();
      let data = null;
      try { data = JSON.parse(text); } catch { }

      if (!data || data.status !== "success") {
        alert("댓글 등록 실패: " + ((data && data.message) || "알 수 없는 오류"));
        return;
      }

      // 서버가 메시지 주면 표시(선택)
      if (data.message) alert(data.message);

      const hasHtml = typeof data.html === "string" && data.html.trim().length > 0;

      if (hasHtml) {
        const tpl = document.createElement("template");
        tpl.innerHTML = data.html.trim();
        const newEl = tpl.content.firstElementChild;

        // 총 댓글 개수 증가 (DOM에 실제로 붙일 때만)
        incCount(1);

        // 새로 붙는 항목은 client 플래그(커서 계산/중복 삽입 방지)
        newEl.dataset.origin = "client";

        // ✅ 항상 최상위 리스트의 '적절한 위치'에 삽입 (부모 내부 X)
  insertByPathToTopList(newEl);

  // 폼 리셋 + reply-form은 닫기
  if (form.tagName === "FORM") form.reset();
  if (form.classList.contains("reply-form")) form.style.display = "none";

  // 단일 오픈(이미 추가하셨다면 유지)
  document.querySelectorAll("form.reply-form").forEach(f => {
    if (f !== form && f.style.display === "block") {
      try { f.reset(); } catch (_){}
      f.style.display = "none";
    }
  });


  //       if (form.classList.contains("reply-form")) {
  //         const parentLi = form.closest(".comment-item");
  //         // let childrenUl =
  //         //   parentLi.querySelector("ul.children") ||
  //         //   parentLi.querySelector("ul.reply-children") ||
  //         //   parentLi.querySelector("ul");

  //         let childrenUl = parentLi.querySelector(':scope > ul.children');

  //         if (!childrenUl) {
  //           childrenUl = document.createElement("ul");
  //           childrenUl.className = "children";
  //           childrenUl.style.listStyle = "none";
  //           childrenUl.style.paddingLeft = "0";
  //           parentLi.appendChild(childrenUl);
  //         }
  //         childrenUl.appendChild(newEl);

  //         // 제출 후 답글 폼 닫기
  //           try { form.reset(); } catch (_){}
  // form.style.display = "none";

  //   // (선택) 혹시 열려있던 다른 reply 폼도 모두 닫기
  // document.querySelectorAll("form.reply-form").forEach(f => {
  //   if (f !== form && f.style.display === "block") {
  //     try { f.reset(); } catch (_){}
  //     f.style.display = "none";
  //   }
  // });

  //       } else {
  //         list.appendChild(newEl);

  //           // (선택) 상단 새댓글 작성 시 열려있던 reply 폼들 정리
  // document.querySelectorAll("form.reply-form").forEach(f => {
  //   if (f.style.display === "block") {
  //     try { f.reset(); } catch (_){}
  //     f.style.display = "none";
  //   }
  // });

  //       }
  //       if (form.tagName === "FORM") form.reset();

        // 방금 추가한 id 기록 → SSE 중복 방지
        
        const idFromEl = Number(newEl.dataset.id || (newEl.id || '').replace('comment-', '')) || 0;
        if (idFromEl) clientJustAdded.add(idFromEl);

        // 스타일/하이라이트/스크롤
        applyDepthColors(newEl.ownerDocument || document);
        highlightOnce(newEl);
        scrollToWithOffset(newEl, 120);

        // 무한스크롤 프리패치
        if (hasMore && io && sentinel) io.observe(sentinel);
        return;
      }

      // html 미포함 → focus 리다이렉트 (fallback)
      const newId = data.comment_id || data.id;
      if (newId) {
        const url = new URL(location.href);
        url.searchParams.set("focus", newId);
        location.href = url.toString();
        return;
      }

      // 최후 fallback
      location.reload();
    } catch (err) {
      console.error(err);
      alert("네트워크 오류가 발생했습니다.");
    }
  }


  // 공통 유틸: path 오름차순 위치에 맞춰 #comment-list에 삽입
function insertByPathToTopList(newEl) {
  const newPath = newEl?.dataset?.path || "";
  const siblings = Array.from(list.querySelectorAll(':scope > .comment-item')); // 직계만
  let placed = false;
  for (const li of siblings) {
    const p = li.dataset.path || "";
    if (p > newPath) {           // path 오름차순
      list.insertBefore(newEl, li);
      placed = true;
      break;
    }
  }
  if (!placed) list.appendChild(newEl);
}


  // =========================================================
  // 댓글 등록 성공 시 자동 스크롤 + 하이라이트
  // =========================================================
  function scrollToWithOffset(el, offset = 100) {
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const top = window.pageYOffset + rect.top - offset;
    window.scrollTo({ top, behavior: "smooth" });
  }
  function highlightOnce(el, ms = 2200) {
    if (!el) return;
    el.classList.add("cmt-highlight");
    setTimeout(() => el.classList.remove("cmt-highlight"), ms);
  }

  // focus 대상 처리 (URL ?focus=ID)
  const urlParams = new URLSearchParams(location.search);
  let focusId = Number(urlParams.get("focus") || 0);

  async function ensureFocusVisible(targetId, maxLoads = 10) {
    if (!targetId) return;

    let el = document.getElementById("comment-" + targetId);
    if (el) {
      el.scrollIntoView({ behavior: "instant", block: "center" });
      scrollToWithOffset(el, 120);
      highlightOnce(el);
      try {
        urlParams.delete("focus");
        const clean = location.pathname + (urlParams.toString() ? "?" + urlParams.toString() : "") + location.hash;
        history.replaceState(null, "", clean);
      } catch (_) { }
      return;
    }

    for (let i = 0; i < maxLoads && hasMore; i++) {
      const ok = await loadComments();
      el = document.getElementById("comment-" + targetId);
      if (el) {
        await new Promise(r => setTimeout(r, 30));
        scrollToWithOffset(el, 120);
        highlightOnce(el);
        try {
          urlParams.delete("focus");
          const clean = location.pathname + (urlParams.toString() ? "?" + urlParams.toString() : "") + location.hash;
          history.replaceState(null, "", clean);
        } catch (_) { }
        return;
      }
      if (!ok) break;
    }
  }

  window.addEventListener("load", () => {
    if (focusId > 0) ensureFocusVisible(focusId);
  });

  // // 디버그
  // window.__debug = { postId, hasMore, afterPath, listExists: !!list, rootEl: !!rootEl, sentinel: !!sentinel };
  // console.log("[COMMENTS ready]", window.__debug);

  // =========================================================
  // 토스트 UI (경량)
  // =========================================================
  (function () {
    if (window.__toast) return;
    const style = document.createElement("style");
    style.textContent = `
    .toast-wrap{position:fixed;left:50%;transform:translateX(-50%);bottom:20px;z-index:9999;display:flex;flex-direction:column;gap:8px}
    .toast{
      min-width: 240px; max-width: 88vw;
      background:#111827; color:#fff; padding:10px 14px; border-radius:10px;
      box-shadow:0 6px 24px rgba(0,0,0,.18); opacity:.95; font-size:.92rem;
      cursor:pointer;
    }
    .toast .meta{opacity:.8;font-size:.85em;margin-top:4px}
    `;
    document.head.appendChild(style);

    const wrap = document.createElement("div");
    wrap.className = "toast-wrap";
    document.body.appendChild(wrap);

    window.__toast = function (msg, metaText, onClick) {
      const el = document.createElement("div");
      el.className = "toast";
      el.innerHTML = `<div>${msg}</div>${metaText ? `<div class="meta">${metaText}</div>` : ''}`;
      wrap.appendChild(el);
      let timer = setTimeout(() => { el.remove(); }, 4500);
      el.addEventListener("click", () => {
        clearTimeout(timer);
        el.remove();
        if (typeof onClick === "function") onClick();
      });
    };
  })();

  // =========================================================
  // SSE 연결
  // =========================================================
  (function () {
    if (!section || typeof EventSource === "undefined") return;

    const streamUrl = String(section.dataset.streamUrl || "").replace(/\/+$/, "");
    const lastKnownId = (function () {
      const last = document.querySelector("#comment-list .comment-item:last-child");
      return last ? Number(last.dataset.id || 0) : 0;
    })();

    let es;
    try {
      const url = streamUrl + (streamUrl.includes('?') ? '&' : '?') + 'lastId=' + encodeURIComponent(lastKnownId);
      es = new EventSource(url, { withCredentials: true });
    } catch (_) { return; }

    es.addEventListener("comment", async (e) => {
      try {
        const data = JSON.parse(e.data || "{}");
        const cid = Number(data.comment_id || 0);
        const meta = `${data.author_name || '익명'} • ${data.created_at || ''}`.trim();

        // 1) 내가 쓴 댓글이면 무시
        if (myUserId && Number(data.user_id) === myUserId) return;

        // 2) 방금 클라이언트가 붙였던 댓글이면 1회 무시(중복 방지)
        if (cid && clientJustAdded.has(cid)) {
          clientJustAdded.delete(cid);
          return;
        }

        // 아직 DOM에 없다고 가정하고 추가분 로드
        hasMore = true;
        await loadComments();

        // 실제 DOM에 들어왔으면 개수 +1
        if (cid && document.getElementById("comment-" + cid)) {
          incCount(1);
        }

        // 토스트 클릭 시 해당 댓글로 스크롤 + 하이라이트 시도
        window.__toast(`새 댓글이 달렸어요: ${data.snippet || ''}`, meta, async () => {
          let el = document.getElementById("comment-" + cid);
          if (!el) { await loadComments(); el = document.getElementById("comment-" + cid); }
          if (el) {
            el.scrollIntoView({ behavior: "smooth", block: "center" });
            highlightOnce(el);
          } else {
            const last = document.querySelector("#comment-list .comment-item:last-child");
            if (last) last.scrollIntoView({ behavior: "smooth", block: "end" });
          }
        });
      } catch (err) {
        console.warn("SSE parse error:", err);
      }
    });

    es.addEventListener("error", () => {
      // 브라우저가 자동 재연결함.
    });
  })();
})();
