{@ comments}
  <li id="comment-{= .comment_id }"
      class="comment-item{? .comment_detail == '삭제된 댓글입니다'} is-deleted{/}"
      data-id="{= .comment_id }"
      data-user-id="{= .user_id }"
      data-root="{= .root_id }"
      data-depth="{= .depth }"
      data-path="{= htmlspecialchars(.path, ENT_QUOTES, 'UTF-8') }"
      {? .comment_detail == '삭제된 댓글입니다'} data-deleted="1"{/}>

    <div class="meta-line" style="display:flex; gap:8px; align-items:center;">
      <div class="comment-author">
        {= htmlspecialchars(.author_name, ENT_QUOTES, 'UTF-8') }
      </div>
      <div class="comment-date">
        {= htmlspecialchars(.created_at, ENT_QUOTES, 'UTF-8') }
      </div>

      {* 현재 로그인 사용자 == 작성자 일 때만 삭제 버튼 *}
      {? isset(user_id) && intval(user_id) == intval(.user_id) && .comment_detail != '삭제된 댓글입니다' }
        <button class="btn-delete" data-id="{= .comment_id }" type="button" style="margin-left:auto;">삭제</button>
      {/}
    </div>

    <div class="comment-body">
      {= nl2br(htmlspecialchars(.comment_detail, ENT_QUOTES, 'UTF-8')) }
    </div>

    {? .comment_detail != '삭제된 댓글입니다'}
      <button class="btn-reply" data-parent="{= .comment_id }" type="button">답글</button>

      <form class="reply-form" style="display:none; margin-top:8px;"
            method="post" action="{= site_url('comment/create') }">
        <input type="hidden" name="post_id"   value="{= .post_id }">
        <input type="hidden" name="parent_id" value="{= .comment_id }">
        <textarea name="comment_detail" rows="2" placeholder="답글 입력..." style="width:100%;"></textarea>
        <div style="margin-top:6px;">
          <button type="submit">등록</button>
          <button type="button" class="btn-cancel-reply">취소</button>
        </div>
      </form>
    {/}
  </li>
{/}
