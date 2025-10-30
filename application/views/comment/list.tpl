<!-- 새 댓글 작성 -->
<div id="comment-form" style="margin-top:16px;">
  <div class="cmt-head">
    <h3>댓글 작성</h3>
    <button type="submit" form="new-comment" class="btn-primary">등록</button>
  </div>

  <form id="new-comment" method="post" action="{= site_url('comment/create') }">
    <input type="hidden" name="post_id" value="{= post_id }">
    <textarea name="comment_detail" rows="3" placeholder="댓글을 입력하세요" style="width:100%;"></textarea>
  </form>
</div>

<h3>댓글 ({= comment_cnt })</h3>

<!-- 댓글 목록 -->
{? isset(comments) && comments }
  <div id="comment-section">
    <ul id="comment-list" class="comment-list" style="list-style:none; padding-left:0;">
      {# comment_items }  <!-- partial include -->
    </ul>
    <!-- 스크롤 끝 확인용 -->
    <div id="cmt-sentinel" style="height:1px;"></div>
  </div>
{:}
  <p>아직 댓글이 없습니다.</p>
{/}

<script>
  (function(){
    // ✅ 항상 유효한 숫자 리터럴로 출력 (예: 123 또는 0)
    const postId     = Number({= json_encode(post_id_js) });

    // 초기 목록의 마지막 path 찾기 (없으면 빈 문자열)
    let afterPath  = (document.querySelector('#comment-list .comment-item:last-child')?.dataset.path) || '';

    // ✅ 항상 유효한 불리언 리터럴로 출력 (true/false)
    let hasMore    = {= json_encode(has_more_js) };

    let loading    = false;
    let inflight   = null;

    function applyDepthColors(scope = document) {
      scope.querySelectorAll('.comment-item').forEach(el => {
        const d = Number(el.dataset.depth || 0);
        el.style.setProperty('--depth', d);
      });
    }
    applyDepthColors();

    async function loadComments() {
      if (loading || !hasMore) return;
      loading = true;

      io.unobserve(sentinel);
      if (inflight) inflight.abort();
      inflight = new AbortController();

      try {
        const params = new URLSearchParams({ afterPath, limit: 10 });
        const res = await fetch("{= site_url('comment/list_json') }/" + postId + "?" + params.toString(), {
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
      } catch (e) {
        if (e.name !== 'AbortError') console.error(e);
        if (hasMore) io.observe(sentinel);
      } finally {
        loading = false;
        inflight = null;
      }
    }

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

    // 답글 폼 show/hide
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

    // 댓글/답글 등록
    document.addEventListener('submit', async (e) => {
      const form = e.target.closest('#comment-section form.reply-form, #comment-form #new-comment');
      if (!form) return;
      e.preventDefault();
      const res = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin'
      });
      const text = await res.text();
      try {
        const data = JSON.parse(text);
        if (data.status === 'success') location.reload();
        else alert('댓글 등록 실패: ' + (data.message || '알 수 없는 오류'));
      } catch {
        console.error('응답이 JSON이 아님:', text);
        alert('서버 응답이 JSON이 아닙니다.');
      }
    });
  })();
</script>
