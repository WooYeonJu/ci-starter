if ('scrollRestoration' in history) {
  try { history.scrollRestoration = 'manual'; } catch (_) {}
}

(function () {

  /* ===== 상태 ===== */
const postId = Number(root?.dataset.postId || 0);
  let afterPath  = document.querySelector('#comment-list .comment-item:last-child')?.dataset.path || '';
let hasMore  = root?.dataset.hasMore === '1';
  let loading    = false;
  let inflight   = null;

  function applyDepthColors(scope = document) {
    scope.querySelectorAll('.comment-item').forEach((el) => {
      const d = Number(el.dataset.depth || 0);
      el.style.setProperty('--depth', d);
    });
  }
  applyDepthColors();

  /* ===== 무한 스크롤 로드 ===== */
  async function loadComments() {
    if (loading || !hasMore) return false;
    loading = true;

    io.unobserve(sentinel);
    if (inflight) inflight.abort();
    inflight = new AbortController();

    try {
      const qs  = new URLSearchParams({ afterPath, limit: 200 });
      const res = await fetch("{= site_url('comment/list_json') }/" + postId + "?" + qs.toString(), {
        credentials: 'same-origin',
        signal: inflight.signal
      });
      const data = await res.json();
      if (data.status !== 'success') throw new Error('load fail');

      const list = document.querySelector('#comment-list');
      list.insertAdjacentHTML('beforeend', data.html);
      applyDepthColors(list);

      afterPath = data.nextCursor || afterPath;
      hasMore   = !!data.hasMore;

      if (hasMore) io.observe(sentinel);
      return true;
    } catch (e) {
      if (e.name !== 'AbortError') console.error(e);
      if (hasMore) io.observe(sentinel);
      return false;
    } finally {
      loading = false;
      inflight = null;
    }
  }

  /* ===== IO & 초기 보충 로드 ===== */
  const sentinel = document.getElementById('cmt-sentinel');
  const io = new IntersectionObserver(([entry]) => {
    if (entry.isIntersecting) loadComments();
  }, { root: null, rootMargin: '200px', threshold: 0 });
  if (hasMore) io.observe(sentinel);

  window.addEventListener('load', () => {
    if (hasMore && document.documentElement.scrollHeight <= window.innerHeight + 1) {
      loadComments();
    }
  });

  /* ===== 답글 폼 show/hide ===== */
  document.addEventListener('click', (e) => {
    const replyBtn = e.target.closest('.btn-reply');
    if (replyBtn) {
      const form = replyBtn.nextElementSibling;
      if (form) {
        const show = form.style.display !== 'block';
        form.style.display = show ? 'block' : 'none';
        if (show) form.querySelector('textarea')?.focus();
      }
    }
    const cancelBtn = e.target.closest('.btn-cancel-reply');
    if (cancelBtn) {
      const form = cancelBtn.closest('form');
      if (form) { form.reset(); form.style.display = 'none'; }
    }
  });

  /* ===== 제출 처리 ===== */
  const extBtn  = document.getElementById('btn-new-comment');
  const newForm = document.getElementById('new-comment');
  if (extBtn && newForm) {
    extBtn.addEventListener('click', (ev) => {
      ev.preventDefault();
      newForm.requestSubmit();
    });
  }

  if (newForm) newForm.addEventListener('submit', handleSubmit);

  document.addEventListener('submit', (e) => {
    if (e.target.matches('form.reply-form')) handleSubmit(e);
  }, true);

  async function handleSubmit(e) {
    const form = e.target;
    e.preventDefault();

    try {
      const res  = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const text = await res.text();
      let data   = null;
      try { data = JSON.parse(text); } catch {}

      if (!data || data.status !== 'success') {
        alert('댓글 등록 실패: ' + ((data && data.message) || '알 수 없는 오류'));
        return;
      }

      const hasHtml = typeof data.html === 'string' && data.html.trim().length > 0;

      if (hasHtml) {
        const tpl = document.createElement('template');
        tpl.innerHTML = data.html.trim();
        const newEl = tpl.content.firstElementChild;

        if (form.classList.contains('reply-form')) {
          const parentLi = form.closest('.comment-item');
          let childrenUl =
            parentLi.querySelector('ul.children') ||
            parentLi.querySelector('ul.reply-children') ||
            parentLi.querySelector('ul');
          if (!childrenUl) {
            childrenUl = document.createElement('ul');
            childrenUl.className = 'children';
            childrenUl.style.listStyle = 'none';
            childrenUl.style.paddingLeft = '0';
            parentLi.appendChild(childrenUl);
          }
          childrenUl.appendChild(newEl);
        } else {
          document.getElementById('comment-list').appendChild(newEl);
        }

        if (form.tagName === 'FORM') form.reset();
        return;
      }

      if (data.id) {
        const url = new URL(location.href);
        url.searchParams.set('focus', data.id);
        location.href = url.toString();
        return;
      }

      location.reload();

    } catch (err) {
      console.error(err);
      alert('네트워크 오류가 발생했습니다.');
    }
  }

  window.__debug = { hasMore, afterPath, postId, listExists: !!document.getElementById('comment-list') };
  console.log('[DEBUG ready]', window.__debug);
})();