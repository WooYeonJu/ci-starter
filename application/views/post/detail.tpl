<div class="container">
  <!-- 제목 -->
  <h2>{= htmlspecialchars(post.title, ENT_QUOTES, 'UTF-8') }</h2>

  <!-- 메타 -->
  <div class="meta">
    {= htmlspecialchars(post.author_name,   ENT_QUOTES, 'UTF-8') } |
    {= htmlspecialchars(post.category_name, ENT_QUOTES, 'UTF-8') } |
    {= htmlspecialchars(post.created_at,    ENT_QUOTES, 'UTF-8') }
  </div>

  <!-- 본문 -->
  <div class="content">
    {= nl2br(htmlspecialchars(post.detail, ENT_QUOTES, 'UTF-8')) }
  </div>

  <!-- 작성자 전용 액션 -->
  {? is_owner }
    <div class="owner-actions">
      <a href="{= site_url('post/edit/' ~ post.post_id) }">수정</a>

      <form action="{= site_url('post/delete/' ~ post.post_id) }" method="post" style="display:inline">
        <button type="submit" onclick="return confirm('정말 삭제할까요?')">삭제</button>
      </form>
    </div>
  {/}

  <!-- 첨부파일 -->
  {? isset(files) && files }
    <h4>첨부파일</h4>
    <ul>
      <!--{@ files}-->
        <li>
          <a href="{= site_url('post/download/' ~ .file_id) }">
            {= htmlspecialchars(.original_name, ENT_QUOTES, 'UTF-8') }
          </a>
        </li>
      <!--{/}-->
    </ul>
  {/}

  <!-- 댓글 블록 -->
  {? this->viewDefined('comments') } {# comments } {/}

  <a href="{= site_url('post') }" class="back">← 목록으로</a>
</div>
