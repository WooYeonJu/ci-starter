<!--{@ comments}-->
  <li class="comment-item"
      data-id="{= .comment_id }"
      data-root="{= .root_id }"
      data-depth="{= .depth }"
      data-path="{= htmlspecialchars(.path, ENT_QUOTES, 'UTF-8') }">

    <div class="meta-line">
      <div class="comment-author">
        {= htmlspecialchars(.author_name, ENT_QUOTES, 'UTF-8') }
      </div>
      <div class="comment-date">
        {= htmlspecialchars(.created_at,  ENT_QUOTES, 'UTF-8') }
      </div>
    </div>

    <div class="comment-body">
      {= nl2br(htmlspecialchars(.comment_detail, ENT_QUOTES, 'UTF-8')) }
    </div>

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
  </li>
<!--{/}-->
