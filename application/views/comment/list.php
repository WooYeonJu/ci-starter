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
        <?php foreach ($comments as $c): ?>
            <!-- data-path: 무한 스크롤시 마지막 li의 경로를 기준으로 데이터 요청하기 위해 -->
            <li class="comment-item"
                data-id="<?= (int)$c['comment_id'] ?>"
                data-depth="<?= (int)$c['depth'] ?>"
                data-path="<?= htmlspecialchars($c['path']) ?>"
                style="margin-left: <?= (int)$c['depth'] * 20 ?>px; margin-bottom:12px;">

                <!-- 메타 정보: 작성자 / 작성일자 -->
                <div class="meta-line">
                    <div class="comment-author">
                        <?= htmlspecialchars($c['author_name']) ?>
                    </div>
                    <div class="comment-date">
                        <?= htmlspecialchars($c['created_at']) ?>
                    </div>
                </div>

                    <!-- 댓글 내용 -->
                <div class="comment-body">
                    <?= nl2br(htmlspecialchars($c['comment_detail'])) ?>
                </div>

                <button class="btn-reply" data-parent="<?= (int)$c['comment_id'] ?>" type="button">답글</button>

                <!-- 답글 버튼 누르면 아래 대댓 입력란 생성 -->
                <form class="reply-form" style="display:none; margin-top:8px;" method="post" action="<?= site_url('comment/create') ?>">
                    <input type="hidden" name="post_id" value="<?= (int)$c['post_id'] ?>">
                    <input type="hidden" name="parent_id" value="<?= (int)$c['comment_id'] ?>">
                    <textarea name="comment_detail" rows="2" placeholder="답글 입력..." style="width:100%;"></textarea>
                    <div style="margin-top:6px;">
                        <button type="submit">등록</button>
                        <button type="button" class="btn-cancel-reply">취소</button>
                    </div>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php else: ?>
<p>아직 댓글이 없습니다.</p>
<?php endif; ?>

<script>
  // 게시글 아이디를 PHP에서 JS로 전달
  const postId = <?= (int)$this->uri->segment(3) ?>;

  // ① 초기 커서 계산 (마지막 댓글의 data-path 사용)
  let afterPath = (function () {
    const last = document.querySelector('#comment-list .comment-item:last-child');
    return last ? last.dataset.path : '';
  })();
  let loading = false;
  let hasMore = true;

  // ② 무한 스크롤용 댓글 로더
  async function loadComments() {
    if (loading || !hasMore) return;
    loading = true;
    try {
      const params = new URLSearchParams({ afterPath, limit: 10 });
      const res = await fetch(`/comment/list_json/${postId}?` + params.toString(), {
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (data.status !== 'success') throw new Error('load fail');

      document.querySelector('#comment-list')
        .insertAdjacentHTML('beforeend', data.html);

      afterPath = data.nextCursor || afterPath;
      hasMore = !!data.hasMore;
    } catch (e) {
      console.error(e);
    } finally {
      loading = false;
    }
  }

  // ③ 스크롤이 바닥에 가까워질 때 다음 댓글 자동 로드
  window.addEventListener('scroll', () => {
    const nearBottom = window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 120;
    if (nearBottom) loadComments();
  });

  // ④ 이벤트 위임으로 동적 요소(답글 버튼/취소 버튼) 처리
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

  // ⑤ 댓글/답글 폼 전송도 위임으로 처리 (새로 추가된 폼 포함)
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
      if (data.status === 'success') {
        location.reload(); // 가장 단순한 방식: 새 댓글 반영 후 새로고침
      } else {
        alert('댓글 등록 실패: ' + (data.message || '알 수 없는 오류'));
      }
    } catch {
      console.error('응답이 JSON이 아님:', text);
      alert('서버 응답이 JSON이 아닙니다.');
    }
  });
</script>


<!-- 
<script>

// 대댓 버튼('답글') 눌렀을 때 입력창 띄우기
document.querySelectorAll('.btn-reply').forEach(btn => {
  btn.addEventListener('click', () => {
    const form = btn.nextElementSibling;
    form.style.display = (form.style.display === 'none' || form.style.display === '') ? 'block' : 'none';
    if (form.style.display === 'block') {
      const ta = form.querySelector('textarea');
      if (ta) ta.focus();
    }
  });
});

// 대댓 입력 취소 눌렀을 때 입력창 지우기
document.querySelectorAll('.btn-cancel-reply').forEach(btn => {
  btn.addEventListener('click', () => {
    const form = btn.closest('form');
    if (form) {
      form.reset();
      form.style.display = 'none';
    }
  });
});

// 댓글 영역 내 폼들만 AJAX로 전송
function wireAjaxSubmit(form) {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(form);

    // fetch로 전송 (세션 쿠키 포함)
    const res = await fetch(form.action, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin' // 동일 오리진(도메인/포트)이라면 이걸로 충분
      // 크로스 도메인/포트라면 'include' 사용
    });

    const text = await res.text();
    try {
      const data = JSON.parse(text);
      if (data.status === 'success') {
        location.reload();
      } else {
        alert('댓글 등록 실패: ' + (data.message || '알 수 없는 오류'));
      }
    } catch (err) {
      // 서버가 JSON 대신 HTML(Profiler/에러 페이지 등)을 반환한 경우 대비
      console.error('응답이 JSON이 아님:', text);
      alert('서버 응답이 JSON이 아닙니다. (개발자도구 Network → Response 확인)');
    }
  });
}

// 댓글 리스트의 답글 폼 + 새 댓글 폼에 바인딩
document.querySelectorAll('#comment-section form.reply-form').forEach(wireAjaxSubmit);
const newCommentForm = document.querySelector('#comment-form #new-comment');
if (newCommentForm) wireAjaxSubmit(newCommentForm);

let afterPath = '';
let loading = false;

function loadComments() {
  if (loading) return;
  loading = true;

  $.get('/comment/list/' + postId, { afterPath, limit: 200 }, function(res) {
    $('#comment-list').append(res.html);
    afterPath = res.nextCursor; // 서버에서 전달받은 다음 커서
    loading = false;
  });
}

// 스크롤이 바닥 근처일 때 다음 댓글 자동 로드
$(window).on('scroll', function() {
  if ($(window).scrollTop() + $(window).height() >= $(document).height() - 100) {
    loadComments();
  }
});
</script> -->
