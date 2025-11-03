(function (root) {
  const { state, applyDepthColors, incCount } = root.CMT || {};
  const {
    insertByPathToTopList,
    focusByPathOrId,
    htmlToFrag,
    firstShownPath,
    lastShownPath,
    shouldInsertIntoCurrentWindow,
  } = root.CMT || {};
  const { enterWindowMode } = root.CMT || {};
  if (!state) return;

  async function loadComments() {
    if (state.loading || !state.hasMore) return false;
    state.loading = true;
    if (state.sentinel && state.io) state.io.unobserve(state.sentinel);
    if (state.inflight) state.inflight.abort();
    state.inflight = new AbortController();
    try {
      const qs = new URLSearchParams({
        afterPath: state.afterPath,
        limit: 200,
      });
      const url = `${state.listUrl}/${state.postId}?${qs.toString()}`;
      const res = await fetch(url, {
        credentials: "same-origin",
        signal: state.inflight.signal,
      });
      if (!res.ok) throw new Error("HTTP " + res.status);
      const data = await res.json();
      if (data.status !== "success") throw new Error("load fail");

      if (typeof data.html === "string" && data.html.trim()) {
        const tpl = document.createElement("template");
        tpl.innerHTML = data.html.trim();
        const nodes = Array.from(tpl.content.querySelectorAll(".comment-item"));
        const frag = document.createDocumentFragment();
        for (const n of nodes) {
          const idStr = n.id || "";
          if (idStr && document.getElementById(idStr)) continue;
          n.dataset.origin = "server";
          frag.appendChild(n);
        }
        if (frag.childNodes.length) {
          state.list.appendChild(frag);
          applyDepthColors(state.list);
        }
      }

      if (typeof data.hasMore === "boolean") state.hasMore = data.hasMore;
      if (typeof data.nextCursor === "string" && data.nextCursor) {
        state.afterPath = data.nextCursor;
      } else {
        const serverItems = Array.from(
          state.list.querySelectorAll('.comment-item[data-origin="server"]')
        );
        const lastServer = serverItems[serverItems.length - 1];
        const p = lastServer?.dataset.path;
        if (p) state.afterPath = p;
      }

      if (state.hasMore && state.sentinel && state.io)
        state.io.observe(state.sentinel);
      return true;
    } catch (_) {
      if (state.hasMore && state.sentinel && state.io)
        state.io.observe(state.sentinel);
      return false;
    } finally {
      state.loading = false;
      state.inflight = null;
    }
  }

  async function ensureFullPath(centerPath, cid) {
    let path = centerPath;
    if (
      (!path || (path.match(/\//g) || []).length < 2) &&
      cid &&
      state.itemUrlBase
    ) {
      const r = await fetch(`${state.itemUrlBase}/${cid}`, {
        credentials: "same-origin",
      });
      if (r.ok) {
        const j = await r.json();
        path = j.path || path || "";
        if ((!path || (path.match(/\//g) || []).length < 2) && j.html) {
          const t = document.createElement("template");
          t.innerHTML = j.html.trim();
          const n = t.content.firstElementChild;
          path = n?.dataset?.path || path;
        }
      }
    }
    return path;
  }

  async function ensureItemPresent({ path = "", cid = 0 }) {
    const safeSel = (s) =>
      window.CSS && CSS.escape ? CSS.escape(s) : s.replace(/["\\]/g, "\\$&");
    let el = null;
    if (path)
      el = document.querySelector(
        `.comment-item[data-path="${safeSel(path)}"]`
      );
    if (!el && cid) el = document.getElementById("comment-" + cid);
    if (el) return;
    if (!cid || !state.itemUrlBase) return;
    try {
      const r = await fetch(`${state.itemUrlBase}/${cid}`, {
        credentials: "same-origin",
      });
      if (!r.ok) return;
      const j = await r.json();
      if (!(j && j.status === "success" && j.html)) return;
      const t = document.createElement("template");
      t.innerHTML = j.html.trim();
      const node = t.content.firstElementChild;
      if (!node) return;
      const newPath = node.dataset?.path || j.path || path || "";
      if (!root.CMT.shouldInsertIntoCurrentWindow(newPath)) {
        const qs = new URLSearchParams({
          centerPath: newPath,
          before: 100,
          after: 100,
        });
        const r2 = await fetch(`${state.aroundUrlBase}/${state.postId}?${qs}`, {
          credentials: "same-origin",
        });
        if (r2.ok) {
          const a = await r2.json();
          if (a && a.status === "success") {
            root.CMT.enterWindowMode(
              a.html,
              {
                hasPrev: !!a.hasPrev,
                prevCursor: a.prevCursor || "",
                hasNext: !!a.hasNext,
                nextCursor: a.nextCursor || "",
                centerPath: a.centerPath || newPath,
              },
              { behavior: "merge" }
            );
          }
        }
      } else {
        insertByPathToTopList(node);
        applyDepthColors(node.ownerDocument || document);
      }
    } catch (_) {}
  }

  async function ensureThreadVisible(childFullPath) {
    const lastSlash = (childFullPath || "").lastIndexOf("/");
    if (lastSlash <= 0) return;
    const parentPath = childFullPath.slice(0, lastSlash);
    const safe = (s) =>
      window.CSS && CSS.escape ? CSS.escape(s) : s.replace(/["\\]/g, "\\$&");
    const parentEl = document.querySelector(
      `.comment-item[data-path="${safe(parentPath)}"]`
    );
    if (parentEl) return;
    try {
      const qs = new URLSearchParams({
        centerPath: parentPath,
        before: 50,
        after: 50,
      });
      const r = await fetch(`${state.aroundUrlBase}/${state.postId}?${qs}`, {
        credentials: "same-origin",
      });
      if (!r.ok) return;
      const a = await r.json();
      if (a && a.status === "success") {
        root.CMT.enterWindowMode(
          a.html,
          {
            hasPrev: !!a.hasPrev,
            prevCursor: a.prevCursor || "",
            hasNext: !!a.hasNext,
            nextCursor: a.nextCursor || "",
            centerPath: a.centerPath || parentPath,
          },
          { behavior: "merge" }
        );
      }
    } catch (_) {}
  }

  async function showNewCommentToast({
    centerPath = "",
    cid = 0,
    snippet = "",
    meta = "",
  }) {
    window.__toast(`새 댓글이 달렸어요: ${snippet}`, meta, async () => {
      try {
        let path = await ensureFullPath(centerPath, cid);
        if (!root.CMT.shouldInsertIntoCurrentWindow(path)) {
          const qs = new URLSearchParams({
            centerPath: path,
            before: 100,
            after: 100,
          });
          const r2 = await fetch(
            `${state.aroundUrlBase}/${state.postId}?${qs}`,
            { credentials: "same-origin" }
          );
          if (r2.ok) {
            const a = await r2.json();
            if (a && a.status === "success") {
              root.CMT.enterWindowMode(
                a.html,
                {
                  hasPrev: !!a.hasPrev,
                  prevCursor: a.prevCursor || "",
                  hasNext: !!a.hasNext,
                  nextCursor: a.nextCursor || "",
                  centerPath: a.centerPath || path,
                },
                { behavior: "replace" }
              );
            }
          }
        }
        await ensureThreadVisible(path);
        await ensureItemPresent({ path, cid });
        root.CMT.focusByPathOrId(path, cid);
      } catch (_) {}
    });
  }

  root.CMT = Object.assign(root.CMT || {}, {
    loadComments,
    ensureFullPath,
    ensureItemPresent,
    ensureThreadVisible,
    showNewCommentToast,
  });
})(window);
