<?php foreach ($comments as $c): ?>
    <li class="comment-item"
        data-id="<?= (int)$c['comment_id'] ?>"
        data-root="<?= (int)$c['root_id'] ?>"
        data-depth="<?= (int)$c['depth'] ?>"
        data-path="<?= htmlspecialchars($c['path']) ?>">
        <!-- 댓글 작성자 -->
        <div class="meta-line">
            <div class="comment-author">
                <?= htmlspecialchars($c['author_name']) ?>
            </div>
            <!-- 댓글 작성 일시 -->
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