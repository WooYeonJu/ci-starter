// #comment-section의 data에서 환경변수(postId, hasMore, listUrl, itemUrlBase, aroundUrlBase, streamUrl, myUserId 등)을 읽어 전역 상태 구축

(function (root) {
  const section = document.getElementById("comment-section");
  if (!section) return; // 댓글 영역이 없는 페이지에서는 종료

  // 1, "true", true 모두 참으로 취급
  const parseBool = (v) => v === "1" || v === "true" || v === true;

  // 전역에서 공유할 객체 생성 -> 다른 모듈들은 이 객체만 참조
  const state = {
    section,
    postId: Number(section.dataset.postId || 0),
    hasMore: parseBool(section.dataset.hasMore),
    listUrl: String(section.dataset.listUrl || "").replace(/\/+$/, ""),
    itemUrlBase: String(section.dataset.itemUrl || "").replace(/\/+$/, ""),
    aroundUrlBase: String(section.dataset.aroundUrl || "").replace(/\/+$/, ""),
    streamUrl: String(section.dataset.streamUrl || "").replace(/\/+$/, ""),
    myUserId: Number(section.dataset.userId || 0),

    // 댓글 ul 컨테이너
    list: document.getElementById("comment-list"),
    sentinel: document.getElementById("cmt-sentinel"), // 일반 - 하단 무한 스크롤 트리거 요소
    newForm: document.getElementById("new-comment"), // 신규 댓글 작성 폼
    extBtn: document.getElementById("btn-new-comment"), // 제출 버튼

    // 목록 로딩 중복을 막는 플래그
    loading: false,
    inflight: null,

    // 클라이언트가 방금 추가한 댓글 id 기록하는 집합
    // 서버에서 sse로 같은 댓글 알림이 들어오므로 토스트 중복 혹은 중복 삽입을 차단
    clientJustAdded: new Set(),

    // 현재 목록 모드
    // normal: 기본(하단 스크롤로 위에서부터 차례대로 출력)
    // window: 윈도우(토스트 등의 이유로 중간 댓글 스킵 후 타겟 댓글과 그 앞 뒤 100개씩만 출력)
    mode: "normal",
    // 윈도우 모드에서 이전/다음 댓글이 더 있는지
    winHasPrev: false,
    winHasNext: false,
    // 윈도우 모드에서 앞뒤 경계(다음에 불러올 기준 댓글)
    winPrevCursor: "",
    winNextCursor: "",
    // 현재 윈도우의 중앙 path
    windowCenterPath: "",

    // normal 모드 하단 무한 스크롤용 인스턴스 및 스크롤 컨테이너
    io: null,
    rootEl: null,
  };

  // 대댓글 들여쓰기 관련 css 적용
  function applyDepthColors(scope = document) {
    scope.querySelectorAll(".comment-item").forEach((el) => {
      const d = Number(el.dataset.depth || 0);
      el.style.setProperty("--depth", d);
    });
  }

  // 주어진 엘리먼트가 스크롤 컨테이너인지 판별
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
