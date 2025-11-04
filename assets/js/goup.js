(function () {
  const btn = document.getElementById("btn-back-to-top");
  if (!btn) return;

  const SHOW_AT = 300;
  let ticking = false;

  // ⭐ 버튼 기준으로 "실제 스크롤되는 부모 요소" 찾기
  function getScrollParent(node) {
    let p = node && node.parentElement;
    while (p && p !== document.body) {
      const style = getComputedStyle(p);
      const oy = style.overflowY;
      const oh = style.overflow;
      if (
        oy === "auto" ||
        oy === "scroll" ||
        oh === "auto" ||
        oh === "scroll"
      ) {
        return p; // 이 div가 실제 스크롤 컨테이너
      }
      p = p.parentElement;
    }
    // 못 찾으면 window 스크롤 사용
    return window;
  }

  const scrollTarget = getScrollParent(btn);

  function getScrollTop() {
    if (scrollTarget === window) {
      return window.pageYOffset || document.documentElement.scrollTop || 0;
    }
    return scrollTarget.scrollTop || 0;
  }

  // 버튼 초기 상태
  btn.hidden = false;
  btn.classList.remove("is-visible");

  // 스크롤 정도에 따라 버튼 show/hide
  function onScroll() {
    if (!ticking) {
      ticking = true;
      window.requestAnimationFrame(() => {
        const y = getScrollTop();
        btn.classList.toggle("is-visible", y > SHOW_AT);
        ticking = false;
      });
    }
  }

  const targetForEvent = scrollTarget === window ? window : scrollTarget;
  targetForEvent.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  // ⭐ "진짜 맨 위" URL 계산 함수
  function resolveTopUrl() {
    // 1순위: 버튼에 data-top-url이 명시되어 있으면 그걸 사용
    let topUrl = btn.dataset.topUrl;
    if (topUrl) return topUrl;

    // 2순위: comment-section에 있는 postId로 /post/detail/{postId} 만들기
    const section = document.getElementById("comment-section");
    if (section && section.dataset.postId) {
      const postId = section.dataset.postId;
      const base = (window.CI_SITE_URL_BASE || "/").replace(/\/+$/, "");
      return base + "/post/detail/" + postId;
    }

    // 3순위: 현재 URL에서 쿼리/해시 날린 버전
    try {
      const url = new URL(window.location.href);
      url.search = "";
      url.hash = "";
      return url.toString();
    } catch (_) {
      return window.location.pathname;
    }
  }

  // ⭐ 애니메이션 + 리다이렉트
  function jumpToTopWithMotion(topUrl) {
    const reduce = window.matchMedia(
      "(prefers-reduced-motion: reduce)"
    ).matches;

    const supportsSmooth = "scrollBehavior" in document.documentElement.style;

    // 모션 사용 불가 or 모션 싫어함 → 바로 이동
    if (!supportsSmooth || reduce) {
      window.location.href = topUrl;
      return;
    }

    // 1) 먼저 스크롤 컨테이너를 부드럽게 위로 올림
    if (scrollTarget === window) {
      window.scrollTo({ top: 0, behavior: "smooth" });
    } else if (typeof scrollTarget.scrollTo === "function") {
      scrollTarget.scrollTo({ top: 0, behavior: "smooth" });
    } else {
      scrollTarget.scrollTop = 0;
    }

    // 2) 조금 기다렸다가(애니메이션 연출용) 페이지 리로드
    //    거리 기반으로 계산해도 되는데, 일단 고정값 500ms 정도가 무난
    const distance = getScrollTop(); // 현재 위치 (0 근처면 거의 안 보이긴 함)
    const baseDuration = 400; // ms
    const extra = Math.min(400, distance * 0.1); // 거리 비례 살짝 증가 (옵션)
    const duration = baseDuration + extra; // 최대 800ms 정도

    setTimeout(() => {
      window.location.href = topUrl;
    }, duration);
  }

  // 클릭 시: 모션 + 리다이렉트
  btn.addEventListener("click", () => {
    const topUrl = resolveTopUrl();
    jumpToTopWithMotion(topUrl);
  });

  // 키보드 접근성
  btn.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      const topUrl = resolveTopUrl();
      jumpToTopWithMotion(topUrl);
    }
  });
})();
