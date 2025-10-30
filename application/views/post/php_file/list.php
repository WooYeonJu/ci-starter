<link rel="stylesheet" href="<?= base_url('assets/css/post.css'); ?>">

<div class="list-header">
  <h3><?= html_escape($title) ?></h3>
  <a href="/post/create" class="create-btn">게시글 작성하기</a>
</div>


<!-- 필터 폼 (GET) -->
<form method="get" action="<?= site_url('post') ?>" class="toolbar" id="filterForm">
  
  <!-- 제목 검색어 -->
  <input type="text" name="q" placeholder="제목 검색"
         value="<?= html_escape(isset($q) ? $q : $this->input->get('q', TRUE)) ?>"
         style="flex:1; min-width:240px;">

  <!-- 검색 버튼 -->
  <button type="submit" name="search_btn">검색</button>

  <!-- 카테고리 -->
  <select name="category_id" onchange="document.getElementById('filterForm').submit();">
    <option value="">전체 카테고리</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= (int)$c['category_id'] ?>"
        <?= ($category_id === (int)$c['category_id']) ? 'selected' : '' ?>>
        <?= html_escape($c['category_name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <!-- n개씩 보기 -->
  <select name="per_page" onchange="document.getElementById('filterForm').submit();">
    <?php foreach ([10,20,50,100] as $n): ?>
      <option value="<?= $n ?>" <?= ($per_page===$n)?'selected':'' ?>><?= $n ?>개씩 보기</option>
    <?php endforeach; ?>
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
  <?php if (!empty($rows)): ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <!-- 제목 클릭하면 상세페이지로 이동할 수 있도록 -->
        <td>
            <a href="<?= site_url('post/detail/' . $r['post_id']) ?>">
                <?= html_escape($r['title']) ?>
            </a>
        </td>
        <td><?= html_escape($r['author_name']) ?></td>
        <td><?= html_escape($r['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  <?php else: ?>
      <tr><td colspan="3" style="text-align:center;">게시글이 없습니다.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php if ($total_pages > 1): ?>
  <?php
    // 보여줄 페이지 집합 만들기: [1, (현재-1), 현재, (현재+1), 마지막]
    $pages = [1, max(1, $page-1), $page, min($total_pages, $page+1), $total_pages];
    $pages = array_values(array_unique(array_filter($pages, function($p) use ($total_pages){ return $p >= 1 && $p <= $total_pages; })));
    sort($pages);

    // 링크 생성 헬퍼
    $qsStr = $qs ? '&'.$qs : '';
    $makeUrl = function($p) use ($qsStr) {
      return site_url('post?page='.$p.$qsStr);
    };
  ?>

  <nav class="pager pager--compact" aria-label="페이지 이동">
    <!-- Prev -->
    <?php if ($page > 1): ?>
      <a class="pager-nav" href="<?= $makeUrl($page-1) ?>" aria-label="이전 페이지">&lt;</a>
      <span class="sep"> | </span>
    <?php else: ?>
      <span class="pager-nav disabled">&lt;</span>
      <span class="sep"> | </span>
    <?php endif; ?>

    <!-- Page numbers with ellipsis -->
    <?php
      $prev = null;
      foreach ($pages as $idx => $p):
        if ($prev !== null && $p - $prev > 1) {
          echo '<span class="ellipsis">…</span><span class="sep"> | </span>';
        }
    ?>
        <?php if ($p == $page): ?>
          <span class="current"><?= $p ?></span>
        <?php else: ?>
          <a href="<?= $makeUrl($p) ?>"><?= $p ?></a>
        <?php endif; ?>
        <?php if ($idx < count($pages)-1): ?><span class="sep"> | </span><?php endif; ?>
    <?php
        $prev = $p;
      endforeach;
    ?>

    <span class="sep"> | </span>
    <!-- Next -->
    <?php if ($page < $total_pages): ?>
      <a class="pager-nav" href="<?= $makeUrl($page+1) ?>" aria-label="다음 페이지">&gt;</a>
    <?php else: ?>
      <span class="pager-nav disabled">&gt;</span>
    <?php endif; ?>
  </nav>
<?php endif; ?>


</body>
</html>
