<div class="list-header">
  <h3>{= html_escape(title) }</h3>
  <a href="/post/create" class="create-btn">게시글 작성하기</a>
</div>

<form method="get" action="{= site_url('post') }" class="toolbar" id="filterForm">
  <input
    type="text"
    name="q"
    placeholder="제목 검색"
    value="{= html_escape(q) }"
    style="flex:1; min-width:240px;"
  >

  <button type="submit" name="search_btn">검색</button>

  <!-- 카테고리 -->
  <select name="category_id" onchange="document.getElementById('filterForm').submit();">
    <option value="">전체 카테고리</option>
    <!--{@ categories}-->
      <option value="{= .category_id }" {? .selected }selected{/}>
        {= html_escape(.category_name) }
      </option>
    <!--{/}-->
  </select>

  <!-- n개씩 보기 -->
  <select name="per_page" onchange="document.getElementById('filterForm').submit();">
    <option value="10"  {? per_page == 10 }selected{/}>10개씩 보기</option>
    <option value="20"  {? per_page == 20 }selected{/}>20개씩 보기</option>
    <option value="50"  {? per_page == 50 }selected{/}>50개씩 보기</option>
    <option value="100" {? per_page == 100 }selected{/}>100개씩 보기</option>
  </select>
</form>

<table>
  <thead>
    <tr>
      <th style="width:60%;">제목</th>
      <th style="width:20%;">작성자</th>
      <th style="width:20%;">작성일자</th>
    </tr>
  </thead>
  <tbody>
  {? rows && count(rows) > 0 }
    <!--{@ rows}-->
      <tr>
        <td>
          <a href="/post/detail/{= .post_id }">{= html_escape(.title) }</a>
        </td>
        <td>{= html_escape(.author_name) }</td>
        <td class="td-date"><time datetime="{= .created_at }">{= .created_at }</time></td>
      </tr>
    <!--{/}-->
  {:}
      <tr><td colspan="3" style="text-align:center;">게시글이 없습니다.</td></tr>
  {/}
  </tbody>
</table>

{? total_pages > 1 }
  <nav class="pager pager--compact" aria-label="페이지 이동">

    <!-- Prev -->
    {? page > 1 }
      <a class="pager-nav" href="/post?page={= page - 1 }{? qs}&{= qs }{/}">&lt;</a>
    {:}
      <span class="pager-nav disabled">&lt;</span>
    {/}

    <!-- 페이지 시퀀스: num/sep/ellipsis -->
    <!--{@ pages}-->
      {? .type == 'sep' }
        <span class="sep"> | </span>
      {/}

      {? .type == 'ellipsis' }
        <span class="ellipsis">…</span>
      {/}

      {? .type == 'num' }
        {? .current }
          <span class="current">{= .n }</span>
        {:}
          <a href="/post?page={= .n }{? qs}&{= qs }{/}">{= .n }</a>
        {/}
      {/}
    <!--{/}-->

    <!-- Next -->
    {? page < total_pages }
      <a class="pager-nav" href="/post?page={= page + 1 }{? qs}&{= qs }{/}">&gt;</a>
    {:}
      <span class="pager-nav disabled">&gt;</span>
    {/}

  </nav>
{/}
