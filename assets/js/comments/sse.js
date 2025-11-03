(function (root) {
  const { state, incCount } = root.CMT || {};
  const { showNewCommentToast } = root.CMT || {};
  if (!state || typeof EventSource === "undefined") return;
  if (!state.streamUrl) return;

  const lastKnownId = (function () {
    const last = document.querySelector(
      "#comment-list .comment-item:last-child"
    );
    return last ? Number(last.dataset.id || 0) : 0;
  })();

  let es;
  try {
    const url =
      state.streamUrl +
      (state.streamUrl.includes("?") ? "&" : "?") +
      "lastId=" +
      encodeURIComponent(lastKnownId);
    es = new EventSource(url, { withCredentials: true });
  } catch (_) {
    return;
  }

  es.addEventListener("comment", async (e) => {
    try {
      const data = JSON.parse(e.data || "{}");
      const cid = Number(data.comment_id || 0);
      const meta = `${data.author_name || "익명"} • ${
        data.created_at || ""
      }`.trim();
      if (state.myUserId && Number(data.user_id) === state.myUserId) return;
      if (cid && state.clientJustAdded.has(cid)) {
        state.clientJustAdded.delete(cid);
        return;
      }
      if (cid && document.getElementById("comment-" + cid)) {
        showNewCommentToast({ cid, snippet: data.snippet || "", meta });
        return;
      }
      if (cid) {
        incCount(1);
        showNewCommentToast({ cid, snippet: data.snippet || "", meta });
      }
    } catch (_) {}
  });

  es.addEventListener("error", () => {
    /* browser auto-reconnect */
  });

  /*
  // ===== (kept as block comment) Original alternative SSE flow =====
  // (Fully commented-out logic preserved by request.)
  // (function () { ... })();
  */
})(window);
