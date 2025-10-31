// assets/js/comments.js
(function () {
  // 페이지 뒤로가기에 의한 자동 스크롤 복원 방지
  if ("scrollRestoration" in history) {
    try { history.scrollRestoration = "manual"; } catch (_) {}
  }

  const section   = document.getElementById("comment-section");
  if (!section) return;

  // ----- 서버에서 data-attribute로 받은 값들 -----
  const postId    = Number(section.dataset.postId || 0);
  let hasMore     = section.dataset.hasMore === "1" || section.dataset.hasMore === "true";
  const listUrl   = String(section.dataset.listUrl || "").replace(/\/+$/,""); // .../comment/list_json

  // ----- 상태 -----
  const list      = document.getElementById("comment-list");
  const sentinel  = document.getElementById("cmt-sentinel");
  const newForm   = document.getElementById("new-comment");
  const extBtn    = document.getElementById("btn-new-comment");

  let afterPath   = list.querySelector(".comment-item:last-child")?.dataset.path || "";
  let loading     = false;
  let inflight    = null;

  function applyDepthColors(scope = document) {
    scope.querySelectorAll(".comment-item").forEach((el) => {
      const d = Number(el.dataset.depth || 0);
      el.style.setProperty("--depth", d);
    });
  }
  applyDepthColors(list);

  // ----- 무한 스크롤 로드 -----
  async function loadComments() {
    if (loading || !hasMore) return false;
    loading = true;

    io.unobserve(sentinel);
    if (inflight) inflight.abort();
    inflight = new AbortController();

    try {
      const qs  = new URLSearchParams({ afterPath, limit: 200 });
      const res = await fetch(`${listUrl}/${postId}?${qs.toString()}`, {
        credentials: "same-origin",
        signal: inflight.signal
      });
      const data = await res.json();
      if (data.status !== "success") throw new Error("load fail");

      if (typeof data.html === "string" && data.html.trim()) {
        list.insertAdjacentHTML("beforeend", data.html);
        applyDepthColors(list);
      }

      afterPath = data.nextCursor || afterPath;
      hasMore   = !!data.hasMore;

      if (hasMore) io.observe(sentinel);
      return true;
    } catch (e) {
      if (e.name !== "AbortError") console.error(e);
      if (hasMore) io.observe(sentinel);
      return false;
    } finally {
      loading = false;
      inflight = null;
    }
  }

  // ----- IO & 초기 보충 로드 -----
  const io = new IntersectionObserver(([entry]) => {
    if (entry.isIntersecting) loadComments();
  }, { root: null, rootMargin: "200px", threshold: 0 });

  if (hasMore && sentinel) io.observe(sentinel);

  window.addEventListener("load", () => {
    if (hasMore && document.documentElement.scrollHeight <= window.innerHeight + 1) {
      loadComments();
    }
  });

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

      // 서버가 html 조각을 줄 수도 있고(미래 확장), id만 줄 수도 있음
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
        applyDepthColors(newEl.ownerDocument || document);
        return;
      }

      // html이 없으면 focus 파라미터로 리디렉트(서버 응답 키: comment_id)
      const newId = data.comment_id || data.id; // 양쪽 키 허용
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

  // 디버그
  window.__debug = {
    postId, hasMore, afterPath,
    listExists: !!list
  };
  console.log("[COMMENTS ready]", window.__debug);
})();
