(function (root) {
  if (root.__toast) return;

  // 토스트 스타일 생성 및 주입
  const style = document.createElement("style");
  style.textContent = `
  .toast-wrap{position:fixed;left:50%;transform:translateX(-50%);bottom:20px;z-index:2147483647;display:flex;flex-direction:column;gap:8px;pointer-events:none}
  .toast{min-width:240px;max-width:88vw;background:#111827;color:#fff;padding:10px 14px;border-radius:10px;box-shadow:0 6px 24px rgba(0,0,0,.18);opacity:.95;font-size:.92rem;cursor:pointer;pointer-events:auto}
  .toast .meta{opacity:.8;font-size:.85em;margin-top:4px}
  `;
  document.head.appendChild(style);

  // 토스트 담을 컨테이너를 body에 추가
  const wrap = document.createElement("div");
  wrap.className = "toast-wrap";
  document.body.appendChild(wrap);

  // 전역에 window.__toast() 함수 등록
  root.__toast = function (msg, metaText, onClick) {
    const el = document.createElement("div");
    el.className = "toast";
    el.innerHTML = `<div>${msg}</div>${
      metaText ? `<div class="meta">${metaText}</div>` : ""
    }`;
    wrap.appendChild(el);
    let timer = setTimeout(() => {
      el.remove();
    }, 4500);
    el.addEventListener("click", () => {
      clearTimeout(timer);
      el.remove();
      if (typeof onClick === "function") onClick();
    });
  };
})(window);
