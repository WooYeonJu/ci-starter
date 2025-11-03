// 가장 먼저 실행되어 이 페이지에 댓글 영역이 있는지 확인
// 없으면 return
(function () {
  try {
    window.__comments_boot = new Date().toISOString();
    const s = document.getElementById("comment-section");
    if (!s) return;
  } catch (_) {}
})();
