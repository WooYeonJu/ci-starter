<!-- TODO: 업로드 가능한 파일 확장자명 같이 띄워주기(jpg|jpeg|png|gif|pdf|txt|zip|doc|docx|ppt|pptx|xls|xlsx) -->

<div class="wrap">
  <h3>{= htmlspecialchars(title, ENT_QUOTES, 'UTF-8') }</h3>

  {? isset(flash_success) && flash_success }
    <script>alert({= json_encode(flash_success) });</script>
  {/}
  {? isset(flash_error) && flash_error }
    <script>alert({= json_encode(flash_error) });</script>
  {/}

  {? isset(validation_errors_html) && validation_errors_html }
    <div class="form-error">{= validation_errors_html }</div>
  {/}

  <form method="post" action="{= site_url('post/do_create') }" enctype="multipart/form-data">
    <div class="row">
      <label>카테고리</label>
      <select name="category_id" required>
        <option value="">선택하세요</option>
        <!--{@ categories}-->
          <option value="{= .category_id }" {= set_select('category_id', .category_id) }>
            {= htmlspecialchars(.category_name, ENT_QUOTES, 'UTF-8') }
          </option>
        <!--{/}-->
      </select>
    </div>

    <div class="row">
      <label>제목</label>
      <input type="text" name="title" maxlength="200"
             value="{= set_value('title') }" required>
    </div>

    <div class="row">
      <label>내용</label>
      <textarea name="detail" required>{= set_value('detail') }</textarea>
    </div>

    <div class="row">
      <label>
        파일 첨부 (여러 개 가능)
        <span id="fileCount" style="color:#666; font-weight:normal;"></span>
      </label>
      <input type="file" name="files[]" id="files" multiple>

      <ul id="fileList" style="margin-top:8px; list-style:disc; padding-left:18px;"></ul>

      <button type="button" id="clearAll" style="margin-top:6px; display:none;">모두 제거</button>
    </div>

    <div class="actions">
      <button type="submit">등록</button>
      <a href="{= site_url('post') }">목록</a>
    </div>
  </form>
</div>

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

  // 파일 선택 시 dt에 반영 (기존 선택만 유지; 누적 원하면 아래 주석 해제)
  input.addEventListener('change', function() {
    // 누적하려면 다음 줄을 지우고, 아래 주석 처리된 forEach를 활성화하세요.
    dt = new DataTransfer();

    // Array.from(dt.files).forEach(function(f){ dt.items.add(f); });

    Array.from(input.files).forEach(function(f){
      dt.items.add(f);
    });

    input.files = dt.files;
    render();
  });

  clearAllBtn.addEventListener('click', function(){
    dt = new DataTransfer();
    input.value = '';
    input.files = dt.files;
    render();
  });
})();
</script>
