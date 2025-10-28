<link rel="stylesheet" href="<?= base_url('assets/css/post-detail.css'); ?>">

<body>
<div class="container">
    <!-- 게시글 제목 -->
  <h2><?= html_escape($post['title']) ?></h2>

  <!-- 게시글 작성자, 카테고리, 생성일자 -->
  <div class="meta">
    <?= html_escape($post['author_name']) ?> |
    <?= html_escape($post['category_name']) ?> |
    <?= html_escape($post['created_at']) ?>
  </div>

  <!-- 본문 -->
  <div class="content">
    <?= nl2br(html_escape($post['detail'])) ?>
  </div>

  <!-- 이 게시글의 작성자가 본인이면 수정, 삭제 버튼 추가 -->
  <?php if (!empty($is_owner) && $is_owner): ?>
    <div class="owner-actions">
        <a href="<?= site_url('post/edit/'.$post['post_id']) ?>">수정</a>

        <!-- 삭제는 POST로 -->
        <?= form_open('post/delete/'.$post['post_id'], ['style'=>'display:inline']) ?>
        <button type="submit" onclick="return confirm('정말 삭제할까요?')">삭제</button>
        <?= form_close(); ?>
    </div>
    <?php endif; ?>

  <!-- 첨부파일 -->
  <?php if (!empty($files)): ?>
  <h4>첨부파일</h4>
  <ul>
    <?php foreach ($files as $f): ?>
      <li>
        <a href="<?= site_url('post/download/'.$f['file_id']) ?>">
          <?= html_escape($f['original_name']) ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>


  <!-- <h4>댓글</h4> -->
  <?php $this->load->view('comment/list', ['comments' => $comments, 'comment_cnt' => $comment_cnt]); ?>

  <a href="<?= site_url('post') ?>" class="back">← 목록으로</a>
</div>
</body>
</html>