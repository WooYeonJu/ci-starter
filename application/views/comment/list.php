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
  </div>
<?php else: ?>
  <p>아직 댓글이 없습니다.</p>
<?php endif; ?>

<script>
  const postId = <?= (int)$this->uri->segment(3) ?>;

  // 커서
  let afterPath = (function() {
    const last = document.querySelector('#comment-list .comment-item:last-child');
    return last ? last.dataset.path : '';
  })();
  let loading = false;
  let hasMore = true;

  // 루트ID => HSL hue 캐시
  const hueByRoot = new Map();

  function hue(id) {
    if (hueByRoot.has(id)) return hueByRoot.get(id);
    let h = 0;
    String(id).split('').forEach(ch => h = (h * 31 + ch.charCodeAt(0)) % 360);
    hueByRoot.set(id, h);
    return h;
  }

  // depth/hue 적용 (선 관련 변수 제거)
  function applyDepthColors(scope = document) {
    scope.querySelectorAll('.comment-item').forEach(el => {
      const d = Number(el.dataset.depth || 0);
      el.style.setProperty('--depth', d);
    });
  }

  // 초기 1회 적용
  applyDepthColors();

  // 무한 스크롤 로더
  async function loadComments() {
    if (loading || !hasMore) return;
    loading = true;
    try {
      const params = new URLSearchParams({
        afterPath,
        limit: 10
      });
      const res = await fetch(`/comment/list_json/${postId}?` + params.toString(), {
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (data.status !== 'success') throw new Error('load fail');

      const list = document.querySelector('#comment-list');
      list.insertAdjacentHTML('beforeend', data.html);

      // 방금 붙인 조각에만 적용
      applyDepthColors(list);

      afterPath = data.nextCursor || afterPath;
      hasMore = !!data.hasMore;
    } catch (e) {
      console.error(e);
    } finally {
      loading = false;
    }
  }

  // 스크롤이 바닥 근처면 더 불러오기
  window.addEventListener('scroll', () => {
    const nearBottom = window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 120;
    if (nearBottom) loadComments();
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