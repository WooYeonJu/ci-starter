(function (root) {
  const { state, applyDepthColors, incCount } = root.CMT || {};
  const {
    insertByPathToTopList,
    highlightOnce,
    scrollToWithOffset,
    shouldInsertIntoCurrentWindow,
  } = root.CMT || {};
  const { ensureItemPresent, showNewCommentToast, jumpToComment } =
    root.CMT || {};
  if (!state) return;

  // 뒤로가기 클릭시 자동 스크롤 복원 끄는 코드
  if ("scrollRestoration" in history) {
    try {
      history.scrollRestoration = "manual";
    } catch (_) {}
  }

  // config: base url
  const STATUS_BASE = (window.CI_SITE_URL_BASE || "/") + "comment/status";

  // 10초 캐시(선택): 같은 댓글 연속 조회 방지
  const statusCache = new Map();
  function setCache(cid, data) {
    statusCache.set(cid, { data, at: Date.now() });
  }
  function getCache(cid) {
    const e = statusCache.get(cid);
    if (!e) return null;
    if (Date.now() - e.at > 10_000) {
      statusCache.delete(cid);
      return null;
    }
    return e.data;
  }

  /**
   * 삭제된 댓글인지 확인 -> 답글 버튼 눌렀을 때 처리용
   * @param {*} cid
   * @returns
   */
  async function checkDeleted(cid) {
    const cached = getCache(cid);
    if (cached) return cached;
    try {
      const r = await fetch(`${STATUS_BASE}/${cid}`, {
        credentials: "same-origin",
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      const j = await r.json().catch(() => null);
      // 404도 deleted 취급
      const ans =
        !r.ok || !j || j.is_deleted || j.exists === false
          ? { exists: !!(j && j.exists), is_deleted: true }
          : { exists: true, is_deleted: !!j.is_deleted };
      setCache(cid, ans);
      return ans;
    } catch {
      // 네트워크 오류시 보수적으로 차단
      return { exists: false, is_deleted: true };
    }
  }

  // reply form show/hide
  document.addEventListener("click", async (e) => {
    const replyBtn = e.target.closest(".btn-reply");
    if (!replyBtn) return;

    const li = replyBtn.closest(".comment-item");
    const cid = Number(li?.dataset.id || replyBtn.dataset.id || 0);
    if (!cid) return;

    // 1) DOM 플래그로 즉시 차단
    if (li?.dataset.deleted === "1") {
      alert("이미 삭제된 댓글입니다.");
      return;
    }

    // 2) 서버로 최종 확인 (SSE 안 씀)
    const st = await checkDeleted(cid);
    if (st.is_deleted) {
      // DOM도 즉시 반영(선택)
      if (li) {
        const ok = await refreshCommentNodeById(cid);
        if (!ok) markCommentDeleted(li);
      }
      alert("이미 삭제된 댓글입니다.");
      return;
    }

    // 3) 여기서부터 폼 토글 (기존 로직)
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

  // 댓글 DOM을 '삭제됨'으로 표시 (로컬 치환용)
  function markCommentDeleted(li) {
    if (!li) return;
    li.classList.add("is-deleted");
    li.dataset.deleted = "1";
    const bodyEl = li.querySelector(".comment-body");
    if (bodyEl) bodyEl.textContent = "삭제된 댓글입니다";
    // 답글/삭제 버튼 및 열려있는 폼 제거
    li.querySelectorAll(".btn-reply, .btn-delete, .reply-form").forEach((el) =>
      el.remove()
    );
  }

  // 서버에서 최신 HTML을 받아 해당 댓글 노드를 통째로 교체(가능하면 이 경로 사용)
  async function refreshCommentNodeById(cid) {
    try {
      if (!state.itemUrlBase) return false;
      const r = await fetch(`${state.itemUrlBase}/${cid}`, {
        credentials: "same-origin",
      });
      if (!r.ok) return false;
      const j = await r.json();
      if (!j || j.status !== "success" || !j.html) return false;

      const t = document.createElement("template");
      t.innerHTML = j.html.trim();
      const fresh = t.content.firstElementChild;
      const old = document.querySelector(`.comment-item[data-id="${cid}"]`);
      if (fresh && old) {
        old.replaceWith(fresh);
        // 색상/하이라이트 등 후처리
        applyDepthColors(fresh.ownerDocument || document);
        // 선택: 새 상태를 살짝 하이라이트
        if (typeof highlightOnce === "function") highlightOnce(fresh);
        return true;
      }
    } catch (_) {}
    return false;
  }

  // 열려있는 답글 폼 닫고 초기화
  function closeReplyForm(form) {
    if (!form) return;
    try {
      form.reset();
    } catch (_) {}
    if (form.classList?.contains("reply-form")) form.style.display = "none";
  }

  async function handleSubmit(e) {
    const form = e.target;
    e.preventDefault();

    // 1) 먼저 parent_id를 읽고
    const parentId = Number(
      form.querySelector('input[name="parent_id"]')?.value || 0
    );
    // 2) parent_id가 있으면 해당 li를 DOM에서 찾아서 삭제 여부 확인
    if (parentId > 0) {
      const parentLi = document.querySelector(
        `.comment-item[data-id="${parentId}"]`
      );
      if (parentLi?.dataset.deleted === "1") {
        alert("이미 삭제된 댓글입니다.");
        try {
          form.reset();
        } catch {}
        if (form.classList.contains("reply-form")) form.style.display = "none";
        return;
      }
    } else {
      // 대댓글이 아니면 기존 로직(폼이 li 안에 있을 수 있으니 보조 체크)
      const hostItem = form.closest(".comment-item");
      if (hostItem && hostItem.dataset.deleted === "1") {
        alert("이미 삭제된 댓글입니다.");
        try {
          form.reset();
        } catch {}
        if (form.classList.contains("reply-form")) form.style.display = "none";
        return;
      }
    }

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
        if (res.status === 409 || res.status === 410) {
          alert("이미 삭제된 댓글에 답글을 달 수 없습니다.");

          // 폼 닫기
          closeReplyForm(form);

          // 부모가 있다면 부모 댓글을 최신 상태로 갱신
          const parentId = Number(
            form.querySelector('input[name="parent_id"]')?.value || 0
          );
          if (parentId > 0) {
            const parentLi = document.querySelector(
              `.comment-item[data-id="${parentId}"]`
            );
            // 1순위: 서버 렌더 HTML 교체
            const ok = await refreshCommentNodeById(parentId);
            // 2순위: 실패하면 로컬 치환
            if (!ok && parentLi) markCommentDeleted(parentLi);
          }

          return;
        }

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

      // const hasHtml =
      //   typeof data.html === "string" && data.html.trim().length > 0;
      // if (hasHtml) {
      //   const tpl = document.createElement("template");
      //   tpl.innerHTML = data.html.trim();
      //   const newEl = tpl.content.firstElementChild;
      //   if (newEl) {
      //     incCount(1);
      //     newEl.dataset.origin = "client";
      //     const newPath = newEl.dataset?.path || "";
      //     if (
      //       typeof shouldInsertIntoCurrentWindow === "function" &&
      //       !shouldInsertIntoCurrentWindow(newPath)
      //     ) {
      //       const nid =
      //         Number(
      //           newEl.dataset.id || (newEl.id || "").replace("comment-", "")
      //         ) || 0;
      //       if (nid) state.clientJustAdded.add(nid);
      //       showNewCommentToast({
      //         centerPath: newPath,
      //         cid: nid,
      //         snippet: data.snippet || "",
      //         meta: "",
      //       });
      //       try {
      //         form.reset();
      //       } catch {}
      //       if (form.classList.contains("reply-form"))
      //         form.style.display = "none";
      //       return;
      //     }
      //     insertByPathToTopList(newEl);

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
          const nid =
            Number(
              newEl.dataset.id || (newEl.id || "").replace("comment-", "")
            ) ||
            newId ||
            0;
          if (nid) state.clientJustAdded.add(nid);

          // 폼은 일단 정리
          try {
            form.reset();
          } catch {}
          if (form.classList.contains("reply-form"))
            form.style.display = "none";

          // ★ 윈도우 밖이면: 토스트 X, 바로 jumpToComment로 이동
          if (
            typeof shouldInsertIntoCurrentWindow === "function" &&
            !shouldInsertIntoCurrentWindow(newPath)
          ) {
            await jumpToComment({
              centerPath: newPath,
              cid: nid,
              behavior: "replace",
            });
            return;
          }

          // ★ 윈도우 안이면: 지금 리스트에 끼워넣고 스크롤/하이라이트
          insertByPathToTopList(newEl);
          applyDepthColors(newEl.ownerDocument || document);
          highlightOnce(newEl);
          scrollToWithOffset(newEl, 120);

          if (state.hasMore && state.io && state.sentinel)
            state.io.observe(state.sentinel);

          return;
        }
      }

      //     try {
      //       form.reset();
      //     } catch {}
      //     if (form.classList.contains("reply-form"))
      //       form.style.display = "none";
      //     const idFromEl =
      //       Number(
      //         newEl.dataset.id || (newEl.id || "").replace("comment-", "")
      //       ) || 0;
      //     if (idFromEl) state.clientJustAdded.add(idFromEl);
      //     applyDepthColors(newEl.ownerDocument || document);
      //     highlightOnce(newEl);
      //     scrollToWithOffset(newEl, 120);
      //     if (state.hasMore && state.io && state.sentinel)
      //       state.io.observe(state.sentinel);
      //     return;
      //   }
      // }

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
                const nid = newId;

                // 폼 정리
                try {
                  e.target.reset();
                } catch {}
                if (e.target.classList?.contains("reply-form"))
                  e.target.style.display = "none";

                // ★ 윈도우 밖이면: 토스트 대신 자동 점프
                if (
                  typeof shouldInsertIntoCurrentWindow === "function" &&
                  !shouldInsertIntoCurrentWindow(newPath)
                ) {
                  await jumpToComment({
                    centerPath: newPath,
                    cid: nid,
                    behavior: "replace",
                  });
                  return;
                }

                // 윈도우 안이면: 그냥 현재 리스트에 삽입
                insertByPathToTopList(node);
                applyDepthColors(node.ownerDocument || document);
                highlightOnce(node);
                scrollToWithOffset(node, 120);

                if (state.hasMore && state.io && state.sentinel)
                  state.io.observe(state.sentinel);

                return;
              }
            }
          }
        } catch (_) {}
      }

      // if (!hasHtml && newId && state.itemUrlBase) {
      //   try {
      //     const r = await fetch(`${state.itemUrlBase}/${newId}`, {
      //       credentials: "same-origin",
      //     });
      //     if (r.ok) {
      //       const j = await r.json();
      //       if (j && j.status === "success" && j.html) {
      //         const t = document.createElement("template");
      //         t.innerHTML = j.html.trim();
      //         const node = t.content.firstElementChild;
      //         if (node) {
      //           incCount(1);
      //           node.dataset.origin = "client";
      //           const newPath = node.dataset?.path || "";
      //           if (!shouldInsertIntoCurrentWindow(newPath)) {
      //             showNewCommentToast({
      //               centerPath: newPath,
      //               cid: newId,
      //               snippet: data.snippet || "",
      //               meta: "",
      //             });
      //             return;
      //           }
      //           insertByPathToTopList(node);
      //           applyDepthColors(node.ownerDocument || document);
      //           highlightOnce(node);
      //           scrollToWithOffset(node, 120);
      //           try {
      //             e.target.reset();
      //           } catch {}
      //           if (e.target.classList?.contains("reply-form"))
      //             e.target.style.display = "none";
      //           if (state.hasMore && state.io && state.sentinel)
      //             state.io.observe(state.sentinel);
      //           return;
      //         }
      //       }
      //     }
      //   } catch (_) {}
      // }

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
