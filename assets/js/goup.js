(function () {
  const btn = document.getElementById('btn-back-to-top');
  if (!btn) return;

  const SHOW_AT = 300; // px 기준: 이 이상 스크롤되면 버튼 표시
  let ticking = false;

  // 최초 상태
  btn.hidden = false; // hidden 속성 제거(접근성), 대신 CSS로 제어
  btn.classList.remove('is-visible');

  function onScroll() {
    if (!ticking) {
      window.requestAnimationFrame(() => {
        const shouldShow = window.scrollY > SHOW_AT;
        btn.classList.toggle('is-visible', shouldShow);
        ticking = false;
      });
      ticking = true;
    }
  }

  // 스크롤 이벤트(성능 위해 rAF로 스로틀)
  window.addEventListener('scroll', onScroll, { passive: true });
  // 첫 로드 시 한 번 체크
  onScroll();

  // 클릭 시 최상단으로
  btn.addEventListener('click', () => {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if ('scrollBehavior' in document.documentElement.style && !reduce) {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
      window.scrollTo(0, 0);
    }
  });

  // 키보드 접근성: Enter/Space 대응(버튼이라 기본 동작이지만 안전망)
  btn.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      btn.click();
    }
  });
})();
