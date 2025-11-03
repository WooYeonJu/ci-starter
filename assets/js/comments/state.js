(function (root) {
  const section = document.getElementById("comment-section");
  if (!section) return; // allow pages without comments

  const parseBool = (v) => v === "1" || v === "true" || v === true;

  const state = {
    section,
    postId: Number(section.dataset.postId || 0),
    hasMore: parseBool(section.dataset.hasMore),
    listUrl: String(section.dataset.listUrl || "").replace(/\/+$/, ""),
    itemUrlBase: String(section.dataset.itemUrl || "").replace(/\/+$/, ""),
    aroundUrlBase: String(section.dataset.aroundUrl || "").replace(/\/+$/, ""),
    streamUrl: String(section.dataset.streamUrl || "").replace(/\/+$/, ""),
    myUserId: Number(section.dataset.userId || 0),

    // elements
    list: document.getElementById("comment-list"),
    sentinel: document.getElementById("cmt-sentinel"),
    newForm: document.getElementById("new-comment"),
    extBtn: document.getElementById("btn-new-comment"),

    // loading flags / aborts
    loading: false,
    inflight: null,

    // client-side recently added ids to suppress duplicates
    clientJustAdded: new Set(),

    // window-mode state
    mode: "normal", // normal | window
    winHasPrev: false,
    winHasNext: false,
    winPrevCursor: "",
    winNextCursor: "",
    windowCenterPath: "",

    // observers for normal infinite scroll
    io: null,
    rootEl: null,
  };

  // depth CSS var helper
  function applyDepthColors(scope = document) {
    scope.querySelectorAll(".comment-item").forEach((el) => {
      const d = Number(el.dataset.depth || 0);
      el.style.setProperty("--depth", d);
    });
  }

  function isScrollable(el) {
    if (!el) return false;
    const s = getComputedStyle(el);
    return /(auto|scroll)/.test(s.overflow + s.overflowY + s.overflowX);
  }

  state.rootEl = isScrollable(section) ? section : null;
  applyDepthColors(state.list || document);

  // count helpers
  function getCountEl() {
    return document.getElementById("comment-count");
  }
  function setCount(n) {
    const el = getCountEl();
    if (!el) return;
    el.dataset.count = String(n);
    el.textContent = String(n);
  }
  function incCount(by = 1) {
    const el = getCountEl();
    if (!el) return;
    const cur = parseInt(el.dataset.count || el.textContent || "0", 10) || 0;
    setCount(cur + by);
  }

  // last item path helper
  const lastItem = () => {
    const items = state.list
      ? state.list.querySelectorAll(".comment-item")
      : [];
    return items.length ? items[items.length - 1] : null;
  };
  state.afterPath = lastItem()?.dataset.path || "";

  // expose
  root.CMT = Object.assign(root.CMT || {}, {
    state,
    applyDepthColors,
    setCount,
    incCount,
  });
})(window);
