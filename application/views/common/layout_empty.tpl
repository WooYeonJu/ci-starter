<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">

  <!-- 페이지 타이틀: 컨트롤러에서 'title' 지정 시 사용 -->
  <title>{? isset(title) && title}{= title }{:}사이트 제목{/}</title>

  {? isset(css_optimizer) && css_optimizer }{= css_optimizer }{/}

</head>
<body>

  <div id="wrap" style="display:flex; gap:0; min-height:100vh;">

    <!-- 본문 영역 -->
    <main id="content" role="main" style="flex:1;">
      <!-- 각 페이지 컨텐츠 슬롯 -->
      {? this->viewDefined('layout_common')}
        {# layout_common }
      {/}
    </main>
  </div>

  <!-- 페이지 개별 JS: 컨트롤러에서 page_js = ['.../post-list.js', ...] 형태로 assign -->
  {? isset(js_optimizer) && js_optimizer }{= js_optimizer }{/}

</body>
</html>
