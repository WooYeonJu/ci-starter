(function (root) {
  const { state, applyDepthColors, incCount } = root.CMT || {};
  const {
    insertByPathToTopList,
    highlightOnce,
    scrollToWithOffset,
    shouldInsertIntoCurrentWindow,
  } = root.CMT || {};
  const { ensureItemPresent, showNewCommentToast } = root.CMT || {};
  if (!state) return;

  // back/forward auto scroll prevention
  if ("scrollRestoration" in history) {
    try {
      history.scrollRestoration = "manual";
    } catch (_) {}
  }

  // reply form show/hide
  document.addEventListener("click", (e) => {
    const replyBtn = e.target.closest(".btn-reply");
    if (replyBtn) {
      let targetForm = replyBtn.nextElementSibling;
      if (!(targetForm && targetForm.matches("form.reply-form"))) {
        targetForm =
          replyBtn.parentElement?.querySelector("form.reply-form") || null;
      }
      document.querySelectorAll("form.reply-form").forEach((f) => {
        if (f !== targetForm && f.style.display === "block") {
          try {
            f.reset();
          } catch (_) {}
          f.style.display = "none";
        }
      });
      if (targetForm) {
        const show = targetForm.style.display !== "block";
        targetForm.style.display = show ? "block" : "none";
        if (show) targetForm.querySelector("textarea")?.focus();
      }
      return;
    }
    const cancelBtn = e.target.closest(".btn-cancel-reply");
    if (cancelBtn) {
      const form = cancelBtn.closest("form");
      if (form) {
        try {
          form.reset();
        } catch (_) {}
        form.style.display = "none";
      }
    }
  });

  if (state.extBtn && state.newForm) {
    state.extBtn.addEventListener("click", (ev) => {
      ev.preventDefault();
      state.newForm.requestSubmit();
    });
  }

  if (state.newForm) state.newForm.addEventListener("submit", handleSubmit);
  document.addEventListener(
    "submit",
    (e) => {
      if (e.target.matches("form.reply-form")) handleSubmit(e);
    },
    true
  );

  async function handleSubmit(e) {
    const form = e.target;
    e.preventDefault();
    try {
      const res = await fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        credentials: "same-origin",
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      const status = res.status;
      const ct = res.headers.get("content-type") || "";
      const raw = await res.text();
      if (!res.ok) {
        let msg = `HTTP ${status}`;
        try {
          const j = JSON.parse(raw);
          if (j && j.message) msg = j.message;
        } catch {}
        alert("댓글 등록 실패: " + msg);
        return;
      }
      let data = null;
      try {
        data = JSON.parse(raw);
      } catch (_) {
        alert("서버 응답 형식이 올바르지 않습니다.");
        return;
      }
      const newId = data.comment_id || data.id;
      if (!data || data.status !== "success") {
        alert(
          "댓글 등록 실패: " + ((data && data.message) || "알 수 없는 오류")
        );
        return;
      }
      if (data.message) alert(data.message);

      const hasHtml =
        typeof data.html === "string" && data.html.trim().length > 0;
      if (hasHtml) {
        const tpl = document.createElement("template");
        tpl.innerHTML = data.html.trim();
        const newEl = tpl.content.firstElementChild;
        if (newEl) {
          incCount(1);
          newEl.dataset.origin = "client";
          const newPath = newEl.dataset?.path || "";
          if (
            typeof shouldInsertIntoCurrentWindow === "function" &&
            !shouldInsertIntoCurrentWindow(newPath)
          ) {
            const nid =
              Number(
                newEl.dataset.id || (newEl.id || "").replace("comment-", "")
              ) || 0;
            if (nid) state.clientJustAdded.add(nid);
            showNewCommentToast({
              centerPath: newPath,
              cid: nid,
              snippet: data.snippet || "",
              meta: "",
            });
            try {
              form.reset();
            } catch {}
            if (form.classList.contains("reply-form"))
              form.style.display = "none";
            return;
          }
          insertByPathToTopList(newEl);
          try {
            form.reset();
          } catch {}
          if (form.classList.contains("reply-form"))
            form.style.display = "none";
          const idFromEl =
            Number(
              newEl.dataset.id || (newEl.id || "").replace("comment-", "")
            ) || 0;
          if (idFromEl) state.clientJustAdded.add(idFromEl);
          applyDepthColors(newEl.ownerDocument || document);
          highlightOnce(newEl);
          scrollToWithOffset(newEl, 120);
          if (state.hasMore && state.io && state.sentinel)
            state.io.observe(state.sentinel);
          return;
        }
      }

      if (!hasHtml && newId && state.itemUrlBase) {
        try {
          const r = await fetch(`${state.itemUrlBase}/${newId}`, {
            credentials: "same-origin",
          });
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
                if (!shouldInsertIntoCurrentWindow(newPath)) {
                  showNewCommentToast({
                    centerPath: newPath,
                    cid: newId,
                    snippet: data.snippet || "",
                    meta: "",
                  });
                  return;
                }
                insertByPathToTopList(node);
                applyDepthColors(node.ownerDocument || document);
                highlightOnce(node);
                scrollToWithOffset(node, 120);
                try {
                  e.target.reset();
                } catch {}
                if (e.target.classList?.contains("reply-form"))
                  e.target.style.display = "none";
                if (state.hasMore && state.io && state.sentinel)
                  state.io.observe(state.sentinel);
                return;
              }
            }
          }
        } catch (_) {}
      }

      if (newId) {
        const url = new URL(location.href);
        url.searchParams.set("focus", newId);
        location.href = url.toString();
        return;
      }
      location.reload();
    } catch (_) {
      alert("네트워크 오류가 발생했습니다.");
    }
  }

  // focus via ?focus=ID
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
        const clean =
          location.pathname +
          (urlParams.toString() ? "?" + urlParams.toString() : "") +
          location.hash;
        history.replaceState(null, "", clean);
      } catch (_) {}
      return;
    }
    for (let i = 0; i < maxLoads && state.hasMore; i++) {
      const ok = await root.CMT.loadComments();
      el = document.getElementById("comment-" + targetId);
      if (el) {
        await new Promise((r) => setTimeout(r, 30));
        scrollToWithOffset(el, 120);
        highlightOnce(el);
        try {
          urlParams.delete("focus");
          const clean =
            location.pathname +
            (urlParams.toString() ? "?" + urlParams.toString() : "") +
            location.hash;
          history.replaceState(null, "", clean);
        } catch (_) {}
        return;
      }
      if (!ok) break;
    }
  }
  window.addEventListener("load", () => {
    if (focusId > 0) ensureFocusVisible(focusId);
  });

  root.CMT = Object.assign(root.CMT || {}, { handleSubmit });
})(window);
