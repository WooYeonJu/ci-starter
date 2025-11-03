// 파일 시스템 전체에서 DOM(문서 객체 모델)을 다루는 공통 유틸리티 모델
// 즉, 댓글 리스트 조작 및 제어하는 파일
//  위치계산
//  정렬 삽입    - 새 댓글 추가되었을 때 data-path 기준으로 어느 위치에 삽입될지 판단
//  자동 스크롤 및 하이라이트

(function (root) {
  const { state, applyDepthColors } = root.CMT || {};
  if (!state) return;

  // 현재 화면 경계의 path 계산
  // 댓글 리스트(state.list)의 첫 번째 직계 자식(첫 댓글)을 구해 data-path 반환
  // :scope > 써서 중첩 자식이 잡히지 않게 방지
  function firstShownPath() {
    const first =
      state.list &&
      state.list.querySelector(":scope > .comment-item:first-child");
    return first ? first.dataset.path || "" : "";
  }
  // 마지막 직계 자식(마지막 댓글)의 data-path 반환
  function lastShownPath() {
    const last =
      state.list &&
      state.list.querySelector(":scope > .comment-item:last-child");
    return last ? last.dataset.path || "" : "";
  }
  function getLastShownPath() {
    return lastShownPath();
  }

  // 현재 창의 삽입 가능 여부 판정
  function shouldInsertIntoCurrentWindow(newPath) {
    if (!newPath) return true;

    // 윈도우 모드의 경우 서버가 준 winPrevCursor ~ winNextCursor 경계 안에서만 삽입
    if (state.mode === "window") {
      const lo = state.winPrevCursor || firstShownPath();
      const hi = state.winNextCursor || lastShownPath();
      if (!lo || !hi) return true;
      return lo <= newPath && newPath <= hi;
    }

    // 일반(normal) 모드의 경우 현재 보이는 첫~마지막 path 경계 내애서 삽입 허용
    const head = firstShownPath();
    const tail = lastShownPath();
    if (!head || !tail) return true;
    return head <= newPath && newPath <= tail;
  }

  // path 기준 정렬 삽입
  // 이미 있는 댓글들의 data-path와 오름차순 비교하여 삽입
  // 선형 탐색
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

  // 스크롤 + 하이라이트
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

  //
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
