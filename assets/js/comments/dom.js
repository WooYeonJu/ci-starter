(function (root) {
  const { state, applyDepthColors } = root.CMT || {};
  if (!state) return;

  function firstShownPath() {
    const first =
      state.list &&
      state.list.querySelector(":scope > .comment-item:first-child");
    return first ? first.dataset.path || "" : "";
  }
  function lastShownPath() {
    const last =
      state.list &&
      state.list.querySelector(":scope > .comment-item:last-child");
    return last ? last.dataset.path || "" : "";
  }
  function getLastShownPath() {
    return lastShownPath();
  }

  function shouldInsertIntoCurrentWindow(newPath) {
    if (!newPath) return true;
    if (state.mode === "window") {
      const lo = state.winPrevCursor || firstShownPath();
      const hi = state.winNextCursor || lastShownPath();
      if (!lo || !hi) return true;
      return lo <= newPath && newPath <= hi;
    }
    const head = firstShownPath();
    const tail = lastShownPath();
    if (!head || !tail) return true;
    return head <= newPath && newPath <= tail;
  }

  function insertByPathToTopList(newEl) {
    const newPath = newEl?.dataset?.path || "";
    const siblings = Array.from(
      state.list.querySelectorAll(":scope > .comment-item")
    );
    let placed = false;
    for (const li of siblings) {
      const p = li.dataset.path || "";
      if (p > newPath) {
        state.list.insertBefore(newEl, li);
        placed = true;
        break;
      }
    }
    if (!placed) state.list.appendChild(newEl);
  }

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

  function htmlToFrag(html) {
    const t = document.createElement("template");
    t.innerHTML = (html || "").trim();
    return t.content;
  }
  function firstVisible() {
    const items = Array.from(state.list.querySelectorAll(".comment-item"));
    const vh = window.innerHeight;
    for (const it of items) {
      const r = it.getBoundingClientRect();
      if (r.bottom >= 0 && r.top <= vh) return it;
    }
    return items[0] || null;
  }
  function trimListFromTopIfTooMany(max = 400) {
    const items = state.list.querySelectorAll(".comment-item");
    if (items.length <= max) return;
    const remove = items.length - max;
    for (let i = 0; i < remove; i++) items[i].remove();
  }
  function trimListFromBottomIfTooMany(max = 400) {
    const items = state.list.querySelectorAll(".comment-item");
    if (items.length <= max) return;
    const remove = items.length - max;
    for (let i = items.length - 1; i >= 0 && i >= items.length - remove; i--)
      items[i].remove();
  }

  function safeSel(s) {
    return window.CSS && CSS.escape
      ? CSS.escape(s)
      : s.replace(/["\\]/g, "\\$&");
  }

  function focusByPathOrId(path, cid) {
    let target = path
      ? document.querySelector(`.comment-item[data-path="${safeSel(path)}"]`)
      : null;
    if (!target && cid) target = document.getElementById("comment-" + cid);
    if (target) {
      target.scrollIntoView({ behavior: "instant", block: "center" });
      highlightOnce(target);
    }
  }

  root.CMT = Object.assign(root.CMT || {}, {
    firstShownPath,
    lastShownPath,
    getLastShownPath,
    shouldInsertIntoCurrentWindow,
    insertByPathToTopList,
    scrollToWithOffset,
    highlightOnce,
    htmlToFrag,
    firstVisible,
    trimListFromTopIfTooMany,
    trimListFromBottomIfTooMany,
    focusByPathOrId,
  });
})(window);
