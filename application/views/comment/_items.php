<link rel="stylesheet" href="<?= base_url('assets/css/comments.css'); ?>">

<?php foreach ($comments as $c): ?>
<li class="comment-item"
    data-id="<?= (int)$c['comment_id'] ?>"
    data-path="<?= htmlspecialchars($c['path']) ?>"
    style="margin-left: <?= (int)$c['depth'] * 20 ?>px; margin-bottom:12px;">
    <!-- 댓글 작성자 -->
    <div class="comment-author" style="font-weight:bold;">
        <?= htmlspecialchars($c['author_name']) ?>
    </div>
    <!-- 댓글 작성 일시 -->
    <div class="comment-date" style="color:#777; font-size:12px;">
        <?= htmlspecialchars($c['created_at']) ?>
    </div>
    <!-- 댓글 내용 -->
    <div class="comment-body" style="margin:6px 0;">
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
