(function (root) {
  // CMT: 댓글 관련 모든 상태와 함수들을 모아둔 하나의 전역 저장소
  // window.CMT: 브라우저 최상위 전역 객체
  // root.CMT: window를 참조하기 위한 지역 객체
  const { state, applyDepthColors } = root.CMT || {};
  const {
    insertByPathToTopList, // path 순서에 맞게 댓글 하나를 리스트에 끼워넣는 함수
    htmlToFrag, // HTML 문자열을 DocumentFragment()로 바꿔주는 함수
    firstVisible, // 화면에 실제로 보이는 첫 번째 댓글 DOM을 리턴하는 함수
    trimListFromTopIfTooMany, // 댓글이 너무 많으면 위, 아래 스킵하는 함수
    trimListFromBottomIfTooMany,
  } = root.CMT || {};
  if (!state) return; // state(댓글 모듈이 아직 준비 안 됐으면) 파일 전체 로직 종료

  /**
   * 윈도우 모드에서 사용하는 커서 정보 업데이트용 함수
   * - 윈도우 모드에서 앞, 뒤로 더 가져올 내용이 있는지
   * - 이전/다음 커서 path가 뭔지
   */
  function updateWindowCursors({
    hasPrev,
    prevCursor,
    hasNext,
    nextCursor,
    centerPath,
  }) {
    // 문자열 path 2개 중 더 작은/큰 것을 리턴하는 함수
    const minPath = (a, b) => (!a ? b : !b ? a : a <= b ? a : b);
    const maxPath = (a, b) => (!a ? b : !b ? a : a >= b ? a : b);
    // 이전/이후에 값을 갖고있는지 여부가 기존 값과 동일한지 여부 판단
    state.winHasPrev = !!(state.winHasPrev || hasPrev);
    state.winHasNext = !!(state.winHasNext || hasNext);
    // 이전/이후 커서값을 가장 작은/가장 큰 path 기준으로 갱신
    state.winPrevCursor = minPath(state.winPrevCursor, prevCursor || "");
    state.winNextCursor = maxPath(state.winNextCursor, nextCursor || "");
    // 중심 댓글 path가 넘어오면 windowCenterPath에 저장
    if (centerPath) state.windowCenterPath = centerPath;
  }

  /**
   * 윈도우 모드 진입 함수
   * - html: 서버에서 받아온 주변 댓글들의 html
   * - cursors: updateWindowCursors에 넘길 커서 정보 객체
   * - behavior
   *      - replace: 지금 보이는 댓글 목록 전체 갈아끼우기
   *      - merge: 기존 목록에 새로 들어온 댓글만 병합해서 끼우기
   */
  function enterWindowMode(html, cursors, { behavior = "replace" } = {}) {
    behavior = behavior === "merge" ? "merge" : "replace";
    state.mode = "window"; // 현재 댓글 모드를 window로 표시(센터 기준 앞뒤 100개 출력)

    // <template> 엘리먼트 생성 -> 문자열 HTML을 DOM으로 안전하게 변환 후 사용
    const tpl = document.createElement("template");
    // html이 없으면 빈 문자열, 있으면 앞 뒤 공백 제거 후 template 내용으로 세팅
    tpl.innerHTML = (html || "").trim();

    // replace 모드
    if (behavior === "replace") {
      state.list.innerHTML = ""; // 현재 댓글 리스트 비우기
      state.list.appendChild(tpl.content); // template 안에 있는 DOM 조각 전체를 리스트 안에 붙임
    }
    // merge 모드
    else {
      // 기존 리스트에 겹치지 않는 댓글만 삽입
      const nodes = Array.from(tpl.content.querySelectorAll(".comment-item"));
      for (const n of nodes) {
        if (!n.id || document.getElementById(n.id)) continue;
        insertByPathToTopList(n);
      }
    }

    // depth 기반 들여쓰기 적용
    applyDepthColors(state.list);
    // 방금 가져온 윈도우 영역의 커서 정보를 state에 반영
    updateWindowCursors(cursors || {});

    // 기존
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

  /**
   * 스크롤이 위로 가까워졌을 때 위쪽 댓글 더 가져오기 자동호출
   */
  function initTopIO() {
    root.CMT.topIO = new IntersectionObserver(
      ([e]) => {
        // 댓글 스크롤을 위로 올렸을 때 위쪽 sentinel이 댓글 영역 화면에 닿는 순간 + window 모드일 때
        // 위쪽 댓글 200개 더 가져오라(loadUpInWindow)는 의미
        if (e.isIntersecting && state.mode === "window") loadUpInWindow();
      },
      { root: state.rootEl, rootMargin: "400px 0px", threshold: 0 }
    );
    // 아까 만든 topSentinel을 감시 대상에 등록
    root.CMT.topIO.observe(topSentinel);
  }
  /**
   * 스크롤이 아래에 가까워졌을 때 아래쪽 댓글 더 가져오기 자동 호출(위와 같은 로직)
   */
  function initBotIO() {
    root.CMT.botIO = new IntersectionObserver(
      ([e]) => {
        if (e.isIntersecting && state.mode === "window") loadDownInWindow();
      },
      { root: state.rootEl, rootMargin: "400px 0px", threshold: 0 }
    );
    root.CMT.botIO.observe(botSentinel);
  }

  // 윈도우 모드 로딩 플래그
  let winLoadingUp = false, // 위쪽으로 불러오는 AJAX가 진행 중인지 여부
    winLoadingDown = false; // 아래쪽으로 불러오는 AJAX가 진행 중인지 여부

  /**
   * 위쪽 댓글 로딩 함수
   */
  async function loadUpInWindow() {
    // 이미 위쪽 로딩 중이거나, 더 이상 위쪽에 댓글이 없거나, 어디까지 가져왔는지 위쪽 기준 커서가 없는 경우는 실행X
    if (winLoadingUp || !state.winHasPrev || !state.winPrevCursor) return;

    winLoadingUp = true; // 위쪽 로딩 시작 플래그 변수 선언
    const anchor = firstVisible(); // 현재 화면에 보이는 첫 번째 댓글 DOM을 기준으로 삼음
    const anchorTop = anchor?.getBoundingClientRect().top || 0; // 현재 스크롤 상태에서 위의 anchor가 얼마나 떨어져 있는지 기록
    try {
      const qs = new URLSearchParams({
        centerPath: state.winPrevCursor, // 이번에 가져올 기준 path
        before: 200, // 기준 path 앞의 200개 가져와라
        after: 0,
      });
      // url 생성
      const url = `${state.listUrl.replace(/list_json.*/, "")}around_json/${
        state.postId
      }?${qs}`;
      // 쿠키를 넣어서 AJAX 요청 전송
      const r = await fetch(url, { credentials: "same-origin" });
      // HTTP 상태코드가 실패면 에러 처리
      if (!r.ok) throw 0;
      // JSON 파싱
      const j = await r.json();
      // 서버에서 보낸 데이터에 status: "success"가 아니면 실패
      if (j.status !== "success") throw 0;

      // 서버가 보내준 HTML 문자열을 DocumentFragment로 변환
      const frag = htmlToFrag(j.html || "");
      const nodes = Array.from(frag.querySelectorAll(".comment-item"));
      // 각 댓글 DOM을 기존 리스트의 맨 앞에 차례로 끼워넣기
      for (const n of nodes) state.list.insertBefore(n, state.list.firstChild);
      // depth 기반 들여쓰기 다시 적용
      applyDepthColors(state.list);
      // hasPrev 업데이트
      state.winHasPrev = !!j.hasPrev;
      // PrevCursor 업데이트
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

  /**
   * 아래쪽 로딩 함수
   * - loadUpInWindow()와 로직 동일(위쪽 아래쪽 방향만 반대)
   */
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

  // 기존 CMT 객체에 이 파일에서 만든 함수 합쳐넣음으로써
  // 다른 JS 파일에서 이 기능들 활용 가능
  root.CMT = Object.assign(root.CMT || {}, {
    updateWindowCursors,
    enterWindowMode,
    loadUpInWindow,
    loadDownInWindow,
  });
})(window);
