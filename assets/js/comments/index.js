// 무한 스크롤 로더
// api.js의 loadComments() 호출
(function (root) {
  const { state } = root.CMT || {};
  const { loadComments } = root.CMT || {};
  if (!state) return;

  state.io =
    typeof IntersectionObserver !== "undefined"
      ? new IntersectionObserver(
          ([entry]) => {
            if (entry.isIntersecting) loadComments();
          },
          { root: state.rootEl, rootMargin: "600px 0px", threshold: 0 }
        )
      : null;

  if (state.io && state.sentinel && state.hasMore)
    state.io.observe(state.sentinel);

  window.addEventListener("load", () => {
    const doc = document.documentElement;
    if (state.hasMore && doc.scrollHeight <= window.innerHeight + 1)
      loadComments();
  });

  queueMicrotask(() => {
    if (state.hasMore) loadComments();
  });
})(window);
