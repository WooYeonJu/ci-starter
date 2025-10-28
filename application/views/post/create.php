<link rel="stylesheet" href="<?= base_url('assets/css/post-form.css'); ?>">

<body>
<div class="wrap">
  <h3><?= html_escape($title) ?></h3>

  <?php
    $success = $this->session->flashdata('success');
    $error   = $this->session->flashdata('error');
    if ($success) echo "<script>alert('{$success}');</script>";
    if ($error)   echo "<script>alert('{$error}');</script>";
  ?>

  <?php if (validation_errors()): ?>
    <div class="form-error"><?= validation_errors(); ?></div>
  <?php endif; ?>


  <?= form_open_multipart('post/do_create'); ?>
    <div class="row">
      <label>카테고리</label>
      <select name="category_id" required>
        <option value="">선택하세요</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['category_id'] ?>"
            <?= set_select('category_id', (int)$c['category_id']) ?>>
            <?= html_escape($c['category_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="row">
      <label>제목</label>
      <input type="text" name="title" maxlength="200"
             value="<?= set_value('title'); ?>" required>
    </div>

    <div class="row">
      <label>내용</label>
      <textarea name="detail" required><?= set_value('detail'); ?></textarea>
    </div>

    <div class="row">
        <label>파일 첨부 (여러 개 가능)
        <span id="fileCount" style="color:#666; font-weight:normal;"></span>
        </label>
        <input type="file" name="files[]" id="files" multiple>

        <ul id="fileList" style="margin-top:8px; list-style:disc; padding-left:18px;"></ul>

        <button type="button" id="clearAll" style="margin-top:6px; display:none;">모두 제거</button>
    </div>

    <div class="actions">
        <button type="submit">등록</button>
        <a href="<?= site_url('post') ?>">목록</a>
    </div>
  <?= form_close(); ?>
</div>
</body>
</html>

<script>
(function() {
  var input = document.getElementById('files');
  var list  = document.getElementById('fileList');
  var count = document.getElementById('fileCount');
  var clearAllBtn = document.getElementById('clearAll');

  // 현재 선택된 파일들을 보관할 컨테이너
  var dt = new DataTransfer();

  function bytesToKB(b){ return (b/1024).toFixed(1) + ' KB'; }

  function render() {
    list.innerHTML = '';
    count.textContent = dt.files.length ? ' (선택: ' + dt.files.length + '개)' : '';
    clearAllBtn.style.display = dt.files.length ? 'inline-block' : 'none';

    Array.from(dt.files).forEach(function(f, idx){
      var li = document.createElement('li');
      li.style.marginBottom = '4px';

      var name = document.createElement('span');
      name.textContent = f.name + ' — ' + bytesToKB(f.size);

      var del = document.createElement('button');
      del.type = 'button';
      del.textContent = '삭제';
      del.style.marginLeft = '8px';
      del.onclick = function() {
        dt.items.remove(idx);            // 목록에서 제거
        input.files = dt.files;          // 실제 input에도 반영
        render();                        // 다시 그리기
      };

      li.appendChild(name);
      li.appendChild(del);
      list.appendChild(li);
    });
  }

  // 파일 선택 시 dt에 반영 (기존 선택 + 새 선택을 합치고 싶으면 아래 주석 해제)
  input.addEventListener('change', function(e) {
    // 기본 동작: 마지막 선택만 유지하고 싶다면 dt 초기화
    dt = new DataTransfer();

    // 여러 번 선택을 누적하고 싶다면 ↑ 이 줄을 제거하고 사용:
    // Array.from(dt.files).forEach(f => dt.items.add(f));

    Array.from(input.files).forEach(function(f){
      dt.items.add(f);
    });

    input.files = dt.files;
    render();
  });

  clearAllBtn.addEventListener('click', function(){
    dt = new DataTransfer();
    input.value = '';       // 파일 선택창 초기화
    input.files = dt.files;
    render();
  });
})();
</script>

