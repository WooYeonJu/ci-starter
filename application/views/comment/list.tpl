<!-- TODO: 댓글 등록 시 스크롤 + 하이라이트 -->

<!-- 새 댓글 작성 -->
<div id="comment-form" style="margin-top:16px;">
  <div class="cmt-head">
    <h3>댓글 작성</h3>
    <button type="button" id="btn-new-comment" form="new-comment" class="btn-primary">등록</button>
  </div>

  <form id="new-comment" method="post" action="{= site_url('comment/create') }">
    <input type="hidden" name="post_id" value="{= post_id }">
    <textarea name="comment_detail" rows="3" placeholder="댓글을 입력하세요" style="width:100%;"></textarea>
  </form>
</div>

<h3>댓글 ({= comment_cnt })</h3>

<!-- 댓글 블록 (항상 렌더링) -->
<div
  id="comment-section"
  data-post-id="{= post_id_js }"
  data-has-more="{= has_more_js }"
  data-initial-count="{= initial_count_js }"
  data-total-count="{= comment_cnt }"
  data-list-url="{= site_url('comment/list_json') }"
>
  <ul id="comment-list" class="comment-list" style="list-style:none; padding-left:0;">
    {? isset(comments) && comments } {# comment_items } {/}
  </ul>

  <!-- 스크롤 끝 확인용 -->
  <div id="cmt-sentinel" style="height:1px;"></div>
</div>
