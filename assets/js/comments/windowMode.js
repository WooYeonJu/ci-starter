(function (root) {
  const { state, applyDepthColors } = root.CMT || {};
  const {
    insertByPathToTopList,
    htmlToFrag,
    firstVisible,
    trimListFromTopIfTooMany,
    trimListFromBottomIfTooMany,
  } = root.CMT || {};
  if (!state) return;

  function updateWindowCursors({
    hasPrev,
    prevCursor,
    hasNext,
    nextCursor,
    centerPath,
  }) {
    const minPath = (a, b) => (!a ? b : !b ? a : a <= b ? a : b);
    const maxPath = (a, b) => (!a ? b : !b ? a : a >= b ? a : b);
    state.winHasPrev = !!(state.winHasPrev || hasPrev);
    state.winHasNext = !!(state.winHasNext || hasNext);
    state.winPrevCursor = minPath(state.winPrevCursor, prevCursor || "");
    state.winNextCursor = maxPath(state.winNextCursor, nextCursor || "");
    if (centerPath) state.windowCenterPath = centerPath;
  }

  function enterWindowMode(html, cursors, { behavior = "replace" } = {}) {
    behavior = behavior === "merge" ? "merge" : "replace";
    state.mode = "window";

    const tpl = document.createElement("template");
    tpl.innerHTML = (html || "").trim();

    if (behavior === "replace") {
      state.list.innerHTML = "";
      state.list.appendChild(tpl.content);
    } else {
      const nodes = Array.from(tpl.content.querySelectorAll(".comment-item"));
      for (const n of nodes) {
        if (!n.id || document.getElementById(n.id)) continue;
        insertByPathToTopList(n);
      }
    }

    applyDepthColors(state.list);
    updateWindowCursors(cursors || {});

    if (state.io && state.sentinel) state.io.unobserve(state.sentinel);
    if (!root.CMT.topIO) initTopIO();
    if (!root.CMT.botIO) initBotIO();

    queueMicrotask(() => {
      if (state.winHasNext) loadDownInWindow();
    });
  }

  let topSentinel = document.getElementById("cmt-sentinel-top");
  if (!topSentinel) {
    topSentinel = document.createElement("div");
    topSentinel.id = "cmt-sentinel-top";
    topSentinel.style.height = "1px";
    state.list.parentElement.insertBefore(topSentinel, state.list);
  }
  let botSentinel = document.getElementById("cmt-sentinel-bot");
  if (!botSentinel) {
    botSentinel = document.createElement("div");
    botSentinel.id = "cmt-sentinel-bot";
    botSentinel.style.height = "1px";
    state.list.parentElement.appendChild(botSentinel);
  }

  function initTopIO() {
    root.CMT.topIO = new IntersectionObserver(
      ([e]) => {
        if (e.isIntersecting && state.mode === "window") loadUpInWindow();
      },
      { root: state.rootEl, rootMargin: "400px 0px", threshold: 0 }
    );
    root.CMT.topIO.observe(topSentinel);
  }
  function initBotIO() {
    root.CMT.botIO = new IntersectionObserver(
      ([e]) => {
        if (e.isIntersecting && state.mode === "window") loadDownInWindow();
      },
      { root: state.rootEl, rootMargin: "400px 0px", threshold: 0 }
    );
    root.CMT.botIO.observe(botSentinel);
  }

  let winLoadingUp = false,
    winLoadingDown = false;

  async function loadUpInWindow() {
    if (winLoadingUp || !state.winHasPrev || !state.winPrevCursor) return;
    winLoadingUp = true;
    const anchor = firstVisible();
    const anchorTop = anchor?.getBoundingClientRect().top || 0;
    try {
      const qs = new URLSearchParams({
        centerPath: state.winPrevCursor,
        before: 200,
        after: 0,
      });
      const url = `${state.listUrl.replace(/list_json.*/, "")}around_json/${
        state.postId
      }?${qs}`;
      const r = await fetch(url, { credentials: "same-origin" });
      if (!r.ok) throw 0;
      const j = await r.json();
      if (j.status !== "success") throw 0;
      const frag = htmlToFrag(j.html || "");
      const nodes = Array.from(frag.querySelectorAll(".comment-item"));
      for (const n of nodes) state.list.insertBefore(n, state.list.firstChild);
      applyDepthColors(state.list);
      state.winHasPrev = !!j.hasPrev;
      state.winPrevCursor = j.prevCursor || "";
      if (anchor) {
        const diff = (anchor.getBoundingClientRect().top || 0) - anchorTop;
        window.scrollBy(0, diff);
      }
      trimListFromBottomIfTooMany();
    } finally {
      winLoadingUp = false;
    }
  }

  async function loadDownInWindow() {
    if (winLoadingDown || !state.winHasNext || !state.winNextCursor) return;
    winLoadingDown = true;
    try {
      const qs = new URLSearchParams({
        centerPath: state.winNextCursor,
        before: 0,
        after: 200,
      });
      const url = `${state.listUrl.replace(/list_json.*/, "")}around_json/${
        state.postId
      }?${qs}`;
      const r = await fetch(url, { credentials: "same-origin" });
      if (!r.ok) throw 0;
      const j = await r.json();
      if (j.status !== "success") throw 0;
      const frag = htmlToFrag(j.html || "");
      const nodes = Array.from(frag.querySelectorAll(".comment-item"));
      for (const n of nodes) state.list.appendChild(n);
      applyDepthColors(state.list);
      state.winHasNext = !!j.hasNext;
      state.winNextCursor = j.nextCursor || "";
      trimListFromTopIfTooMany();
    } finally {
      winLoadingDown = false;
    }
  }

  root.CMT = Object.assign(root.CMT || {}, {
    updateWindowCursors,
    enterWindowMode,
    loadUpInWindow,
    loadDownInWindow,
  });
})(window);
