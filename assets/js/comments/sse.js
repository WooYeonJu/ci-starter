(function (root) {
  // 이전 모듈에서 보낸 CMT에서 state, incCount, showNewCommentToast 읽어오기
  const { state, incCount } = root.CMT || {};
  const { showNewCommentToast } = root.CMT || {};
  // 댓글 없는 페이지, 또는 브라우저가 EventSource 미지원이면 종료
  if (!state || typeof EventSource === "undefined") return;
  // 서버가 내려준 streamUrl(SSE가 없는 경우)가 없어도 종료
  if (!state.streamUrl) return;

  // 현재 화면에 보이는 마지막 댓글 ID를 읽어 초기 커서로 사용
  // 서버에 lastId로 보내 이 ID 이후의 이벤트만 달라는 용도
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
      "lastId=" + // lastId를 쿼리에 붙여 EventSource 생성
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

      // 내가 방금 작성한 댓글이면 무시
      if (state.myUserId && Number(data.user_id) === state.myUserId) return;

      // 폼 제출 직후 클라이언트가 로컬로 삽입한 댓글 SSE id 중복 제거
      if (cid && state.clientJustAdded.has(cid)) {
        state.clientJustAdded.delete(cid);
        return;
      }

      // DOM에 그 댓글 아이디가 있으면 삽입하지 않고 토스트만 띄움
      if (cid && document.getElementById("comment-" + cid)) {
        showNewCommentToast({ cid, snippet: data.snippet || "", meta });
        return;
      }

      // 돔에 아직 없으면
      if (cid) {
        incCount(1); // 댓글 총개수 증가
        // 토스트 띄우기
        showNewCommentToast({ cid, snippet: data.snippet || "", meta });
      }
    } catch (_) {}
  });
})(window);
