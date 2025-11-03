// Minimal boot probe (keeps behavior, drops noisy logs)
(function () {
  try {
    window.__comments_boot = new Date().toISOString();
    const s = document.getElementById("comment-section");
    if (!s) return; // early exit OK — same semantics
  } catch (_) {}
})();
