// assets/js/comment-delete.js
(function () {
  const section = document.getElementById("comment-section");
  if (!section) return;

  // 옵션: 템플릿에서 내려주면 사용, 없으면 기본 경로
  // <div id="comment-section" data-delete-base="{= site_url('comment/delete') }">
  const deleteBase =
    section.dataset.deleteBase ||
    (window.CI_SITE_URL_BASE
      ? `${window.CI_SITE_URL_BASE}comment/delete`
      : "/comment/delete");

  // (선택) CSRF 토큰을 data-*나 meta에서 내려줄 경우 주입
  const csrfName = section.dataset.csrfName || null;
  const csrfHash = section.dataset.csrfHash || null;

  // 섹션 내부 위임 (섹션 밖 클릭은 무시)
  section.addEventListener(
    "click",
    async (e) => {
      const btn = e.target.closest(".btn-delete");
      if (!btn || !section.contains(btn)) return;

      // 이미 처리 중이면 무시
      if (btn.disabled) return;

      const li = btn.closest(".comment-item");
      const cid = Number(btn.dataset.id || li?.dataset.id || 0);
      if (!cid) return;

      if (!window.confirm("정말 이 댓글을 삭제하시겠습니까?")) return;

      // UI 잠금
      btn.disabled = true;
      const prevText = btn.textContent;
      btn.textContent = "삭제 중…";

      try {
        // POST 본문 (빈 본문 + 선택적 CSRF)
        let body = null;
        let headers = { "X-Requested-With": "XMLHttpRequest" };

        if (csrfName && csrfHash) {
          body = new URLSearchParams();
          body.set(csrfName, csrfHash);
          headers["Content-Type"] =
            "application/x-www-form-urlencoded; charset=utf-8";
        }

        const res = await fetch(`${deleteBase}/${cid}`, {
          method: "POST",
          credentials: "same-origin",
          headers,
          body,
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (data.status !== "success") {
          throw new Error(data.message || "삭제 실패");
        }

        // DOM 업데이트: 본문 치환, 클래스 부여, 답글/삭제 버튼 제거
        if (li) {
          li.classList.add("is-deleted");
          li.dataset.deleted = "1";
          const bodyEl = li.querySelector(".comment-body");
          if (bodyEl) bodyEl.textContent = "삭제된 댓글입니다";
          li.querySelectorAll(".btn-reply, .reply-form, .btn-delete").forEach(
            (el) => el.remove()
          );
        }

        console.info("[comment] deleted", cid);
      } catch (err) {
        console.error(err);
        alert("삭제 처리 중 오류가 발생했습니다.");
        // 복구
        btn.disabled = false;
        btn.textContent = prevText;
      }
    },
    false
  );
})();
