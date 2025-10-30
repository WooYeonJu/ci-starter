<link rel="stylesheet" href="<?= base_url('assets/css/comments.css'); ?>">

<!-- 새 댓글 작성 -->
<div id="comment-form" style="margin-top:16px;">
  <div class="cmt-head">
    <h3>댓글 작성</h3>
    <button type="submit" form="new-comment" class="btn-primary">등록</button>
  </div>

  <form id="new-comment" method="post" action="<?= site_url('comment/create') ?>">
    <input type="hidden" name="post_id" value="<?= (int)$this->uri->segment(3) ?>">
    <textarea name="comment_detail" rows="3" placeholder="댓글을 입력하세요" style="width:100%;"></textarea>
  </form>
</div>

<h3>댓글 (<?= $comment_cnt ?>)</h3>

<!-- 댓글 목록 -->
<?php if (!empty($comments)): ?>
  <div id="comment-section">
    <ul id="comment-list" class="comment-list" style="list-style:none; padding-left:0;">
      <?php $this->load->view('comment/_items', ['comments' => $comments]); ?>
    </ul>
    <!-- 스크롤 끝 확인용 -->
    <div id="cmt-sentinel" style="height:1px;"></div>
  </div>
<?php else: ?>
  <p>아직 댓글이 없습니다.</p>
<?php endif; ?>

<script>
  const postId = <?= (int)$this->uri->segment(3) ?>;
  let afterPath = (document.querySelector('#comment-list .comment-item:last-child')?.dataset.path) || '';
  let loading = false;
  // 초기 서버렌더링에서 0개라면 더 불러올 데이터도 없음(커서 기반 페이징 가정)
  let hasMore = <?= !empty($comments) ? 'true' : 'false' ?>;

  // AbortController: 이전 요청이 남아 있으면 취소
  let inflight = null;

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

    // 관찰을 잠시 중단(중복 트리거 방지)
    io.unobserve(sentinel);

    // 이전 요청 취소
    if (inflight) inflight.abort();
    inflight = new AbortController();

    try {
      const params = new URLSearchParams({
        afterPath,
        limit: 10
      });
      const res = await fetch(`/comment/list_json/${postId}?` + params.toString(), {
        credentials: 'same-origin',
        signal: inflight.signal
      });
      const data = await res.json();
      if (data.status !== 'success') throw new Error('load fail');

      const list = document.querySelector('#comment-list');
      list.insertAdjacentHTML('beforeend', data.html);
      applyDepthColors(list);

      afterPath = data.nextCursor || afterPath;
      hasMore = !!data.hasMore;

      // 더 가져올 게 있으면 다시 관찰(없으면 그대로 중단)
      if (hasMore) io.observe(sentinel);
    } catch (e) {
      if (e.name !== 'AbortError') console.error(e);
      // 에러 시에도 센티널 복구(원한다면 재시도 정책을 두세요)
      if (hasMore) io.observe(sentinel);
    } finally {
      loading = false;
      inflight = null;
    }
  }

  // IO 설정: rootMargin으로 미리 로드
  const sentinel = document.getElementById('cmt-sentinel');
  const io = new IntersectionObserver(([entry]) => {
    if (!entry.isIntersecting) return;
    loadComments();
  }, {
    root: null,
    rootMargin: '200px',
    threshold: 0
  });

  // 초기 댓글이 0개면 관찰 자체를 안 붙임(= 무한 호출 방지)
  if (hasMore) io.observe(sentinel);

  // 선택: 페이지가 너무 짧아 스크롤이 없는 경우 1회만 프리로드
  window.addEventListener('load', () => {
    if (hasMore && document.documentElement.scrollHeight <= window.innerHeight + 1) {
      loadComments(); // 1회만
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
      if (form) {
        form.reset();
        form.style.display = 'none';
      }
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
</script>