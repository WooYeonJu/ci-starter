<link rel="stylesheet" href="<?= base_url('assets/css/post-form.css'); ?>">

<body>
<div class="wrap">
  <h3><?= html_escape($title) ?></h3>

  <?php if (validation_errors()): ?>
    <div class="form-error"><?= validation_errors(); ?></div>
  <?php endif; ?>

  <?= form_open_multipart('post/do_edit/'.$post['post_id']); ?>
    <div class="row">
      <label>카테고리</label>
      <select name="category_id" required>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['category_id'] ?>"
            <?= ((int)$post['category_id']===(int)$c['category_id'])?'selected':'' ?>>
            <?= html_escape($c['category_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="row">
      <label>제목</label>
      <input type="text" name="title" maxlength="200"
             value="<?= set_value('title', $post['title']) ?>" required>
    </div>

    <div class="row">
      <label>내용</label>
      <textarea name="detail" required><?= set_value('detail', $post['detail']) ?></textarea>
    </div>

    <div class="row">
      <label>파일 첨부 (여러 개 가능)
        <span id="fileCount" style="color:#666; font-weight:normal;"></span>
      </label>

      <input type="file" name="files[]" id="files" multiple>

      <ul id="fileList" style="margin-top:8px; list-style:disc; padding-left:18px;"></ul>

      <!-- 기존 파일 삭제요청 hidden input이 여기 추가됩니다 -->
      <div id="removeContainer"></div>

      <button type="button" id="clearAll" style="margin-top:6px; display:none;">모두 제거</button>
    </div>

    <div class="actions">
      <button type="submit">저장</button>
      <a href="<?= site_url('post/detail/'.$post['post_id']) ?>">취소</a>
    </div>
  <?= form_close(); ?>
</div>
</body>
</html>

<script>
(function() {
  var input        = document.getElementById('files');
  var list         = document.getElementById('fileList');
  var count        = document.getElementById('fileCount');
  var clearAllBtn  = document.getElementById('clearAll');
  var removeBox    = document.getElementById('removeContainer');

  // 서버에서 내려온 기존 파일들 (PHP에서 주입)
  var existing = <?= json_encode(array_map(function($f){
    return [
      'id'   => (int)$f['file_id'],
      'name' => (string)$f['original_name'],
      'size' => isset($f['size']) ? (int)$f['size'] : 0
    ];
  }, $files ?? []), JSON_UNESCAPED_UNICODE) ?>;

  // 현재 선택된 "새 파일" 컨테이너
  var dt = new DataTransfer();

  function bytesToKB(b){ return (b/1024).toFixed(1) + ' KB'; }

  function addRemoveHidden(id){
    var h = document.createElement('input');
    h.type = 'hidden';
    h.name = 'remove_files[]';
    h.value = String(id);
    removeBox.appendChild(h);
  }

  function render() {
    list.innerHTML = '';

    // 1) 기존 파일 렌더링 (동일한 모양 + '삭제' 버튼)
    existing.forEach(function(f, idx){
      var li = document.createElement('li');
      li.style.marginBottom = '4px';

      var name = document.createElement('span');
      var sizeText = f.size ? (' — ' + bytesToKB(f.size)) : '';
      name.textContent = f.name + sizeText;

      var del = document.createElement('button');
      del.type = 'button';
      del.textContent = '삭제';
      del.style.marginLeft = '8px';
      del.onclick = function () {
        // 서버에 삭제요청으로 전송될 hidden input 추가
        addRemoveHidden(f.id);
        // 화면 목록에서 즉시 제거
        existing.splice(idx, 1);
        render();
      };

      li.appendChild(name);
      li.appendChild(del);
      list.appendChild(li);
    });

    // 2) 새로 선택된 파일 렌더링 (원본 코드와 동일)
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
        dt.items.remove(idx);         // 목록에서 제거
        input.files = dt.files;       // 실제 input에도 반영
        render();                     // 다시 그리기
      };

      li.appendChild(name);
      li.appendChild(del);
      list.appendChild(li);
    });

    // 카운트 표시는 원본 방식 유지 + (삭제요청 n개) 보조 표기
    var selectedCount = dt.files.length;
    var removedCount  = removeBox.querySelectorAll('input[name="remove_files[]"]').length;
    var label = selectedCount ? ' (선택: ' + selectedCount + '개' : '';
    if (removedCount) {
      label = label ? (label + ', 삭제요청: ' + removedCount + '개)') : (' (삭제요청: ' + removedCount + '개)');
    } else if (label) {
      label += ')';
    }
    count.textContent = label;

    clearAllBtn.style.display = dt.files.length ? 'inline-block' : 'none';
  }

  // 파일 선택 시: 마지막 선택만 유지(원본과 동일)
  input.addEventListener('change', function() {
    dt = new DataTransfer(); // 누적 선택을 원하면 이 줄 제거

    Array.from(input.files).forEach(function(f){
      dt.items.add(f);
    });

    input.files = dt.files;
    render();
  });

  // "모두 제거": 새 파일만 초기화 (기존 파일 삭제요청 상태는 유지)
  clearAllBtn.addEventListener('click', function(){
    dt = new DataTransfer();
    input.value = '';
    input.files = dt.files;
    render();
  });

  // 최초 렌더
  render();
})();
</script>
