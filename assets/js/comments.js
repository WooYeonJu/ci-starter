// ===== BOOT PROBE (put this at THE VERY TOP of comments.js) =====
(function () {
  try {
    console.info("[comments] BOOT");
    window.__comments_boot = (new Date).toISOString();

    const s = document.getElementById("comment-section");
    if (!s) {
      console.warn("[comments] #comment-section NOT FOUND -> script will exit early");
    } else {
      console.info("[comments] #comment-section FOUND", {
        postId: s.dataset.postId, listUrl: s.dataset.listUrl,
        itemUrl: s.dataset.itemUrl, aroundUrl: s.dataset.aroundUrl
      });
    }
  } catch (e) {
    // 만약 여기서 에러 나면 파일은 로드됨 + 실행 중 죽음
    console.error("[comments] BOOT ERROR", e);
  }
})();

// fetch 로거 (최상단 어딘가에 1회)
(function(){
  if (window.__fetch_spy) return;
  const _fetch = window.fetch;
  window.fetch = function(input, init) {
    const url = (typeof input === "string") ? input : (input && input.url);
    console.debug("[fetch->]", url, init && init.method);
    return _fetch.apply(this, arguments).then(res => {
      console.debug("[fetch<-]", url, res.status);
      return res;
    });
  };
  window.__fetch_spy = true;
})();



(function () {
  // TODO: comments.js 기능별 파일 분리
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

      const status = res.status;
      const ct = res.headers.get("content-type") || "";
      const raw = await res.text();

      // 디버그 로그 (필요하면 주석)
      console.debug("[create.res]", { status, ct, raw });

      if (!res.ok) {
        // 서버가 4xx/5xx면 바로 에러 토스트/알럿
        let msg = `HTTP ${status}`;
        try {
          const j = JSON.parse(raw);
          if (j && j.message) msg = j.message;
        } catch {}
        alert("댓글 등록 실패: " + msg);
        return; // ❗ catch로 안 보내고 정상 종료
      }

      // const text = await res.text();
      let data = null;
      try { data = JSON.parse(raw); } catch (err) {
        console.error("[create] JSON parse failed. raw:", raw);
        alert("서버 응답 형식이 올바르지 않습니다.");
        return;
      }

      const newId = data.comment_id || data.id;


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

        if (!newEl) {
          console.warn("[create] html ok but no .comment-item root");
        } else {
          incCount(1);
          newEl.dataset.origin = "client";

          // ✅ 창 범위 밖이면 삽입하지 말고 토스트로 윈도우 모드 유도 (너가 쓰던 패턴)
          const newPath = newEl.dataset?.path || "";

          console.debug("[submit] newPath=", newPath,
              "mode=", mode,
              "head=", firstShownPath(),
              "tail=", lastShownPath(),
              "winPrev=", winPrevCursor, "winNext=", winNextCursor,
              "=> insert?", shouldInsertIntoCurrentWindow(newPath));

          // if (typeof shouldInsertIntoCurrentWindow === "function" && !shouldInsertIntoCurrentWindow(newPath)) {
          //   const newId = Number(newEl.dataset.id || (newEl.id || "").replace("comment-","")) || 0;
          //   if (newId) clientJustAdded.add(newId);
            
          //   showNewCommentToast({ centerPath: newPath, cid: Number(newEl.dataset.id || (newEl.id || "").replace("comment-","")) || 0, snippet: data.snippet || "" });

          //   // 폼 정리 후 종료
          //   try { form.reset(); } catch {}
          //   if (form.classList.contains("reply-form")) form.style.display = "none";
          //   return;
          // }

          if (typeof shouldInsertIntoCurrentWindow === "function" && !shouldInsertIntoCurrentWindow(newPath)) {
            const newId = Number(newEl.dataset.id || (newEl.id || "").replace("comment-","")) || 0;
            if (newId) clientJustAdded.add(newId);
            showNewCommentToast({ centerPath: newPath, cid: newId, snippet: data.snippet || "", meta: "" });
            try { form.reset(); } catch {}
            if (form.classList.contains("reply-form")) form.style.display = "none";
            return;
          }

          insertByPathToTopList(newEl);
          try { form.reset(); } catch {}
          if (form.classList.contains("reply-form")) form.style.display = "none";

          const idFromEl = Number(newEl.dataset.id || (newEl.id || "").replace("comment-","")) || 0;
          if (idFromEl) clientJustAdded.add(idFromEl);

          applyDepthColors(newEl.ownerDocument || document);
          highlightOnce(newEl);
          scrollToWithOffset(newEl, 120);
          if (hasMore && io && sentinel) io.observe(sentinel);
          return;
        }
      }

      if (!hasHtml && newId && itemUrlBase) {
        try {
          const r = await fetch(`${itemUrlBase}/${newId}`, { credentials: "same-origin" });
          if (r.ok) {
            const j = await r.json();
            if (j && j.status === "success" && j.html) {
              const t = document.createElement("template");
              t.innerHTML = j.html.trim();
              const node = t.content.firstElementChild;
              if (node) {
                incCount(1);
                node.dataset.origin = "client";
                const newPath = node.dataset?.path || "";
                // 윈도우 경계 미세팅 시에도 삽입 허용됨(패치 A 덕분)
                if (!shouldInsertIntoCurrentWindow(newPath)) {
                  // 그래도 범위 밖이면 토스트만 띄우고 종료
                  showNewCommentToast({ centerPath: newPath, cid: newId, snippet: data.snippet || "", meta: "" });
                  return;
                }
                insertByPathToTopList(node);
                applyDepthColors(node.ownerDocument || document);
                highlightOnce(node);
                scrollToWithOffset(node, 120);
                try { e.target.reset(); } catch {}
                if (e.target.classList?.contains("reply-form")) e.target.style.display = "none";
                if (hasMore && io && sentinel) io.observe(sentinel);
                return;
              }
            }
          }
          console.debug("[create.fallback] item fetch failed or empty html", { newId });
        } catch (err) {
          console.warn("[create.fallback] error", err);
        }
      }

      // html 미포함 → focus 리다이렉트 (fallback)
      // const newId = data.comment_id || data.id;
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
      .toast-wrap{
        position:fixed;left:50%;transform:translateX(-50%);bottom:20px;
        z-index:2147483647;display:flex;flex-direction:column;gap:8px;
        pointer-events:none; /* 래퍼는 클릭 안 받음 */
      }
      .toast{
        min-width:240px;max-width:88vw;
        background:#111827;color:#fff;padding:10px 14px;border-radius:10px;
        box-shadow:0 6px 24px rgba(0,0,0,.18);opacity:.95;font-size:.92rem;
        cursor:pointer;pointer-events:auto; /* 토스트만 클릭 받음 */
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
        console.debug("[toast] click fired");
        clearTimeout(timer);
        el.remove();
        if (typeof onClick === "function") onClick();
      });
    };
  })();

  // =========================================================
  // SSE 연결
  // =========================================================

  // === 전역 base URL 정의 ===
  const itemUrlBase = String(section.dataset.itemUrl || "").replace(/\/+$/, "");
  // 윈도우 모드 들어갔을 때 현재 창 경계 체크 후 범위 밖인 경우 중간에 로드할 대댓 점프
  const aroundUrlBase = String(section.dataset.aroundUrl || "").replace(/\/+$/, "");
  // 토스트 클릭 시 윈도우 모드 돌입 관련 변수
  let mode = "normal"; // "normal" | "window"
  let winHasPrev = false, winHasNext = false;
  let winPrevCursor = "", winNextCursor = "";
  let windowCenterPath = ""; // 현재 윈도 중앙 기준 path

  function firstShownPath() {
    const first = list && list.querySelector(':scope > .comment-item:first-child');
    return first ? (first.dataset.path || "") : "";
  }
  function lastShownPath() {
    const last = list && list.querySelector(':scope > .comment-item:last-child');
    return last ? (last.dataset.path || "") : "";
  }
  function getLastShownPath() {
    // 현재 보이는 리스트의 마지막 path(= normal 모드에서는 afterPath와 동일 의미)
    const last = list && list.querySelector(':scope > .comment-item:last-child');
    return last ? (last.dataset.path || "") : "";
  }
  function shouldInsertIntoCurrentWindow(newPath){
    if (!newPath) return true; // 정보 없으면 일단 붙임

    // 윈도우 모드 판정
    if (mode === "window") {
      const lo = winPrevCursor || firstShownPath();
      const hi = winNextCursor || lastShownPath();
      if (!lo || !hi) return true;// 커서 없으면 강제로 around_json 유도
      return (lo <= newPath && newPath <= hi);
    }

    // 노멀 모드 판정
    const head = firstShownPath();
    const tail = lastShownPath();
    if (!head || !tail) return true; // 리스트가 비어있거나 경계 모르면 삽입
    return (head <= newPath && newPath <= tail);
  }

  // =========================================================
  // 공통: "새 댓글 토스트" + 클릭 시 점프/윈도우모드 진입 함수
  // =========================================================
  async function showNewCommentToast({ centerPath = "", cid = 0, snippet = "", meta = "" }) {
    window.__toast(`새 댓글이 달렸어요: ${snippet}`, meta, async () => {
      try {
        // 1) path 보정 (item로 보강)
        let path = await ensureFullPath(centerPath, cid);

        // 2) 창 범위 밖이면 최초 1회 window replace 진입
        if (!shouldInsertIntoCurrentWindow(path)) {
          const qs  = new URLSearchParams({ centerPath: path, before: 100, after: 100 });
          const r2  = await fetch(`${aroundUrlBase}/${postId}?${qs}`, { credentials: "same-origin" });
          if (r2.ok) {
            const a = await r2.json();
            if (a && a.status === "success") {
              enterWindowMode(a.html, {
                hasPrev: !!a.hasPrev, prevCursor: a.prevCursor || "",
                hasNext: !!a.hasNext, nextCursor: a.nextCursor || "",
                centerPath: a.centerPath || path
              }, { behavior: "replace" });
            }
          }
        }

        // 3) 부모가 없으면 부모 블록 보충
        await ensureThreadVisible(path);

        // 4) ★ 여기가 포인트: 대상 아이템이 DOM에 없으면 직접 fetch해서 꽂기
        await ensureItemPresent({ path, cid });

        // 5) 스크롤 & 하이라이트
        focusByPathOrId(path, cid);
      } catch (e) {
        console.warn("[showNewCommentToast] failed:", e);
      }
    });
  }

  // 리스트에 target 아이템이 없으면 item 엔드포인트에서 받아서 path 오름차순으로 삽입
  async function ensureItemPresent({ path = "", cid = 0 }) {
    const safeSel = (s) => (window.CSS && CSS.escape) ? CSS.escape(s) : s.replace(/["\\]/g, "\\$&");
    let el = null;

    // 1) path로 먼저 찾고, 없으면 id로 찾기
    if (path) el = document.querySelector(`.comment-item[data-path="${safeSel(path)}"]`);
    if (!el && cid) el = document.getElementById("comment-" + cid);
    if (el) return; // 이미 있으면 끝

    // 2) 없으면 서버에서 단일 아이템 받아서 삽입
    if (!cid || !itemUrlBase) return;
    try {
      const r = await fetch(`${itemUrlBase}/${cid}`, { credentials: "same-origin" });
      if (!r.ok) return;
      const j = await r.json();
      if (!(j && j.status === "success" && j.html)) return;

      const t = document.createElement("template");
      t.innerHTML = j.html.trim();
      const node = t.content.firstElementChild;
      if (!node) return;

      // 경계 체크: 현재 창 범위 밖인데도 여기 들어오면 안전하게 윈도우 모드로 전환해서 표시
      const newPath = node.dataset?.path || j.path || path || "";
      if (!shouldInsertIntoCurrentWindow(newPath)) {
        // 창 바깥이면 around로 창을 맞춰놓고 다시 삽입 시도
        const qs  = new URLSearchParams({ centerPath: newPath, before: 100, after: 100 });
        const r2  = await fetch(`${aroundUrlBase}/${postId}?${qs}`, { credentials: "same-origin" });
        if (r2.ok) {
          const a = await r2.json();
          if (a && a.status === "success") {
            enterWindowMode(a.html, {
              hasPrev: !!a.hasPrev, prevCursor: a.prevCursor || "",
              hasNext: !!a.hasNext, nextCursor: a.nextCursor || "",
              centerPath: a.centerPath || newPath
            }, { behavior: "merge" });
          }
        }
      } else {
        // 창 범위 안이면 그냥 path 정렬 삽입
        insertByPathToTopList(node);
        applyDepthColors(node.ownerDocument || document);
      }
    } catch (_) {}
  }



  async function ensureFullPath(centerPath, cid){
    let path = centerPath;
    if ((!path || (path.match(/\//g)||[]).length < 2) && cid && itemUrlBase) {
      const r = await fetch(`${itemUrlBase}/${cid}`, { credentials: "same-origin" });
      if (r.ok) {
        const j = await r.json();
        path = j.path || path || "";
        if ((!path || (path.match(/\//g)||[]).length < 2) && j.html) {
          const t = document.createElement("template"); t.innerHTML = j.html.trim();
          const n = t.content.firstElementChild;
          path = n?.dataset?.path || path;
        }
      }
    }
    return path;
  }

  function focusByPathOrId(path, cid){
    const safe = s => (window.CSS && CSS.escape) ? CSS.escape(s) : s.replace(/["\\]/g, "\\$&");
    let target = document.querySelector(`.comment-item[data-path="${safe(path)}"]`);
    if (!target && cid) target = document.getElementById("comment-" + cid);
    if (target) { target.scrollIntoView({ behavior: "instant", block: "center" }); highlightOnce(target); }
  }


  /**
   * 자식 path를 기준으로, 화면에 부모가 없으면 부모 경로(centerPath의 상위)를 기준으로
   * around_json을 한 번 더 불러 부모 블록을 채우는 보조 함수.
   */
  async function ensureThreadVisible(childFullPath) {
    // childFullPath 예: "/009946/016624"
    // 이미 부모가 있으면 스킵
    const lastSlash = (childFullPath || "").lastIndexOf("/");
    if (lastSlash <= 0) return;

    const parentPath = childFullPath.slice(0, lastSlash);  // "/009946"
    const safe = (s) => (window.CSS && CSS.escape) ? CSS.escape(s) : s.replace(/["\\]/g, "\\$&");
    const parentEl = document.querySelector(`.comment-item[data-path="${safe(parentPath)}"]`);
    if (parentEl) return;

    try {
      // 부모를 중앙으로 잡고 before/after 적당히 가져와 위쪽을 채움
      const qs = new URLSearchParams({ centerPath: parentPath, before: 50, after: 50 });
      const r = await fetch(`${aroundUrlBase}/${postId}?${qs}`, { credentials: "same-origin" });
      if (!r.ok) return;
      const a = await r.json();
      if (a && a.status === "success") {
        // 현재 모드는 유지하되 목록을 "보충" 삽입 (enterWindowMode를 그대로 써도 OK)
        enterWindowMode(a.html, {
          hasPrev: !!a.hasPrev, prevCursor: a.prevCursor || "",
          hasNext: !!a.hasNext, nextCursor: a.nextCursor || "",
          centerPath: a.centerPath || parentPath
        }, { behavior: "merge"});
      }
    } catch (_) {}
  }


  (function () {
    if (!section || typeof EventSource === "undefined") return;

    const streamUrl = String(section.dataset.streamUrl || "").replace(/\/+$/, "");
    const itemUrlBase = String(section.dataset.itemUrl || "").replace(/\/+$/, ""); // ★ 단일 아이템 엔드포인트

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

        // 2) 직전에 내가 클라에서 붙였던 거면 1회 무시
        if (cid && clientJustAdded.has(cid)) { clientJustAdded.delete(cid); return; }

        // 3) 이미 DOM에 있으면(다른 경로로 로드됨) 토스트만 띄우고 끝
        if (cid && document.getElementById("comment-" + cid)) {

          // 토스트 클릭 시 윈도우 모드 진입
          // 토스트 클릭 콜백 내부 (기존 코드에 이어서)
          showNewCommentToast({ cid, snippet: data.snippet || "", meta });

          return;
        }

        // 4) ★ 핵심: “그 댓글 1개”만 서버에서 받아와 정렬 위치에 삽입
        if (cid && itemUrlBase) {
          const res = await fetch(`${itemUrlBase}/${cid}`, { credentials: "same-origin" });
          if (res.ok) {
            const j = await res.json();
            if (j && j.status === "success" && typeof j.html === "string" && j.html.trim()) {
              const tpl = document.createElement("template");
              tpl.innerHTML = j.html.trim();
              const node = tpl.content.firstElementChild;
              if (node) {
                // node.dataset.origin = "sse";
                // insertByPathToTopList(node);          // ← path 오름차순 자리 꽂기
                // applyDepthColors(node.ownerDocument || document);
                incCount(1); // 총 개수 +1 (중복삽입 방지 로직이 있어 안전)

                // ✅ newPath 쓰지 말고, 이처럼 로컬에서 centerPath 계산
                const centerPath = j.path || node.dataset.path || "";

                console.debug("[sse] centerPath=", centerPath,
                  "mode=", mode,
                  "head=", firstShownPath(),
                  "tail=", lastShownPath(),
                  "winPrev=", winPrevCursor, "winNext=", winNextCursor,
                  "=> insert?", shouldInsertIntoCurrentWindow(centerPath));


                // ✅ 공통 함수 한 방으로 토스트 + 클릭 이동 처리
                showNewCommentToast({ centerPath, cid, snippet: data.snippet || "", meta });
                return;
              }
            }
          }
        }

        // 5) (폴백) 그래도 못 붙였으면 마지막 페이지 보충 로드 한번 시도
        // hasMore = true;
        // await loadComments();
        // if (cid && document.getElementById("comment-" + cid)) {
        //   incCount(1);
        //   showNewCommentToast({ cid, snippet: data.snippet || "", meta });
        // }
        incCount(1);
        showNewCommentToast({ cid, snippet: data.snippet || "", meta });
      } catch (err) {
        console.warn("SSE parse error:", err);
      }
    });

    es.addEventListener("error", () => {
      // 브라우저가 자동 재연결
    });
  })();

  // =========================================================
  // 토스트 클릭 시 윈도우 기반 모드 돌입 관련 함수
  // =========================================================
  // === window 모드 커서 업데이트(기존 범위를 '확장' 방향으로만 갱신)
  function updateWindowCursors({hasPrev, prevCursor, hasNext, nextCursor, centerPath}) {
    const minPath = (a,b) => (!a ? b : !b ? a : (a <= b ? a : b));
    const maxPath = (a,b) => (!a ? b : !b ? a : (a >= b ? a : b));

    winHasPrev    = !!(winHasPrev || hasPrev);
    winHasNext    = !!(winHasNext || hasNext);
    winPrevCursor = minPath(winPrevCursor, prevCursor || "");
    winNextCursor = maxPath(winNextCursor, nextCursor || "");
    if (centerPath) windowCenterPath = centerPath;
  }

  // === 단일 진입점: replace / merge 모두 처리
  function enterWindowMode(html, cursors, { behavior = "replace" } = {}) {
    behavior = (behavior === "merge") ? "merge" : "replace";
    mode = "window"; 

    const tpl = document.createElement("template");
    tpl.innerHTML = (html || "").trim();

    if (behavior === "replace") {
      list.innerHTML = "";
      list.appendChild(tpl.content);
    } else {
      // MERGE: 이미 있는 li는 건들지 않고 없는 것만 path 순서로 끼워넣기
      const nodes = Array.from(tpl.content.querySelectorAll(".comment-item"));
      for (const n of nodes) {
        if (!n.id || document.getElementById(n.id)) continue;
        insertByPathToTopList(n);
      }
    }

    applyDepthColors(list);
    updateWindowCursors(cursors);

    // 양방향 IO 보장 (normal 하단 IO는 끔)
    if (io && sentinel) io.unobserve(sentinel);
    if (!topIO) initTopIO();
    if (!botIO) initBotIO();

    // 화면 낮을 때 아래 보충
    queueMicrotask(() => { if (winHasNext) loadDownInWindow(); });
  }


  let topSentinel = document.getElementById("cmt-sentinel-top");
  if (!topSentinel) {
    topSentinel = document.createElement("div");
    topSentinel.id = "cmt-sentinel-top";
    topSentinel.style.height = "1px";
    list.parentElement.insertBefore(topSentinel, list); // 리스트 위
  }
  let botSentinel = document.getElementById("cmt-sentinel-bot");
  if (!botSentinel) {
    botSentinel = document.createElement("div");
    botSentinel.id = "cmt-sentinel-bot";
    botSentinel.style.height = "1px";
    list.parentElement.appendChild(botSentinel); // 리스트 아래
  }

  let topIO = null, botIO = null;
  function initTopIO(){
    topIO = new IntersectionObserver(([e])=>{
      if (e.isIntersecting && mode==="window") loadUpInWindow();
    }, {root: rootEl, rootMargin: "400px 0px", threshold: 0});
    topIO.observe(topSentinel);
  }
  function initBotIO(){
    botIO = new IntersectionObserver(([e])=>{
      if (e.isIntersecting && mode==="window") loadDownInWindow();
    }, {root: rootEl, rootMargin: "400px 0px", threshold: 0});
    botIO.observe(botSentinel);
  }

  let winLoadingUp = false, winLoadingDown = false;

  async function loadUpInWindow(){
    if (winLoadingUp || !winHasPrev || !winPrevCursor) return;
    winLoadingUp = true;
    const anchor = firstVisible(); // 스크롤 앵커
    const anchorTop = anchor?.getBoundingClientRect().top || 0;

    try {
      // prev 로더: prevCursor보다 작은 쪽으로 before 200(?) 등
      const qs = new URLSearchParams({
        centerPath: winPrevCursor, before: 200, after: 0
      });
      const url = `${listUrl.replace(/list_json.*/,'')}around_json/${postId}?${qs}`;
      const r = await fetch(url, { credentials: "same-origin" });
      if (!r.ok) throw 0;
      const j = await r.json();
      if (j.status!=="success") throw 0;

      // 새 조각에서 .comment-item만 추출하여 "앞"에 삽입
      const frag = htmlToFrag(j.html||"");
      const nodes = Array.from(frag.querySelectorAll(".comment-item"));
      // around_json(before-only)이므로 전체가 이전 파트
      for (const n of nodes) list.insertBefore(n, list.firstChild);
      applyDepthColors(list);

      // 커서/상태 갱신
      winHasPrev = !!j.hasPrev;
      winPrevCursor = j.prevCursor || "";

      // 스크롤 보정
      if (anchor) {
        const diff = (anchor.getBoundingClientRect().top || 0) - anchorTop;
        window.scrollBy(0, diff);
      }

      trimListFromBottomIfTooMany();
    } finally {
      winLoadingUp = false;
    }
  }

  async function loadDownInWindow(){
    if (winLoadingDown || !winHasNext || !winNextCursor) return;
    winLoadingDown = true;
    const beforeHeight = document.documentElement.scrollHeight;

    try {
      const qs = new URLSearchParams({
        centerPath: winNextCursor, before: 0, after: 200
      });
      const url = `${listUrl.replace(/list_json.*/,'')}around_json/${postId}?${qs}`;
      const r = await fetch(url, { credentials: "same-origin" });
      if (!r.ok) throw 0;
      const j = await r.json();
      if (j.status!=="success") throw 0;

      const frag = htmlToFrag(j.html||"");
      const nodes = Array.from(frag.querySelectorAll(".comment-item"));
      for (const n of nodes) list.appendChild(n);
      applyDepthColors(list);

      winHasNext = !!j.hasNext;
      winNextCursor = j.nextCursor || "";

      trimListFromTopIfTooMany();
    } finally {
      winLoadingDown = false;
    }
  }

  // helpers
  function htmlToFrag(html){
    const t=document.createElement("template"); t.innerHTML=(html||"").trim();
    return t.content;
  }
  function firstVisible(){
    const items = Array.from(list.querySelectorAll(".comment-item"));
    const vh = window.innerHeight;
    for (const it of items) {
      const r = it.getBoundingClientRect();
      if (r.bottom >= 0 && r.top <= vh) return it;
    }
    return items[0] || null;
  }
  function trimListFromTopIfTooMany(max=400){
    const items = list.querySelectorAll(".comment-item");
    if (items.length <= max) return;
    const removeCount = items.length - max;
    for (let i=0;i<removeCount;i++) items[i].remove();
  }
  function trimListFromBottomIfTooMany(max=400){
    const items = list.querySelectorAll(".comment-item");
    if (items.length <= max) return;
    const removeCount = items.length - max;
    for (let i=items.length-1; i>=0 && i>=items.length-removeCount; i--) {
      items[i].remove();
    }
  }

  // // =========================================================
  // // SSE 연결
  // // =========================================================
  // (function () {
  //   if (!section || typeof EventSource === "undefined") return;
  //
  //   const streamUrl = String(section.dataset.streamUrl || "").replace(/\/+$/, "");
  //   const lastKnownId = (function () {
  //     const last = document.querySelector("#comment-list .comment-item:last-child");
  //     return last ? Number(last.dataset.id || 0) : 0;
  //   })();
  //
  //   let es;
  //   try {
  //     const url = streamUrl + (streamUrl.includes('?') ? '&' : '?') + 'lastId=' + encodeURIComponent(lastKnownId);
  //     es = new EventSource(url, { withCredentials: true });
  //   } catch (_) { return; }
  //
  //   es.addEventListener("comment", async (e) => {
  //     try {
  //       const data = JSON.parse(e.data || "{}");
  //       const cid = Number(data.comment_id || 0);
  //       const meta = `${data.author_name || '익명'} • ${data.created_at || ''}`.trim();
  //
  //       // 1) 내가 쓴 댓글이면 무시
  //       if (myUserId && Number(data.user_id) === myUserId) return;
  //
  //       // 2) 방금 클라이언트가 붙였던 댓글이면 1회 무시(중복 방지)
  //       if (cid && clientJustAdded.has(cid)) {
  //         clientJustAdded.delete(cid);
  //         return;
  //       }
  //
  //       // 아직 DOM에 없다고 가정하고 추가분 로드
  //       hasMore = true;
  //       await loadComments();
  //
  //       // 실제 DOM에 들어왔으면 개수 +1
  //       if (cid && document.getElementById("comment-" + cid)) {
  //         incCount(1);
  //       }
  //
  //       // 토스트 클릭 시 해당 댓글로 스크롤 + 하이라이트 시도
  //       window.__toast(`새 댓글이 달렸어요: ${data.snippet || ''}`, meta, async () => {
  //         let el = document.getElementById("comment-" + cid);
  //         if (!el) { await loadComments(); el = document.getElementById("comment-" + cid); }
  //         if (el) {
  //           el.scrollIntoView({ behavior: "smooth", block: "center" });
  //           highlightOnce(el);
  //         } else {
  //           const last = document.querySelector("#comment-list .comment-item:last-child");
  //           if (last) last.scrollIntoView({ behavior: "smooth", block: "end" });
  //         }
  //       });
  //     } catch (err) {
  //       console.warn("SSE parse error:", err);
  //     }
  //   });
  //
  //   es.addEventListener("error", () => {
  //     // 브라우저가 자동 재연결함.
  //   });
  // })();
})();
