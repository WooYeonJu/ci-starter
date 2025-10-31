<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">

  <title>{? isset(title) && title}{= title }{:}사이트 제목{/}</title>

  {? isset(css_optimizer) && css_optimizer }{= css_optimizer }{/}

</head>
<body>

  <div id="wrap">
    <!-- 전역 헤더: 래퍼 내부로 이동 -->
  {? this->viewDefined('layout_header')}
    {# layout_header }
  {/}

    <!-- 본문 -->
    <main id="content" role="main">
      <div class="main-content">
        {? this->viewDefined('layout_common')}
          {# layout_common }
        {/}
      </div>
    </main>
  </div>

  <button id="btn-back-to-top" aria-label="맨 위로" title="맨 위로" hidden> ↑ </button>


  {? isset(js_optimizer) && js_optimizer }{= js_optimizer }{/}
</body>
</html>
