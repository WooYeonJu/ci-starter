<!doctype html>
<html lang="ko">

<head>
  <meta charset="utf-8">
  <title><?= isset($title) ? html_escape($title) : '게시판' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= base_url('assets/css/layout.css'); ?>">
</head>

<body>

  <header class="site-header">
    <div class="header-inner">
      <div class="header-left">
        <a href="/post" class="site-title">게시판</a>
      </div>

      <nav class="header-right">
        <span class="welcome">
          {= user_name }님 환영합니다!
        </span>
        <!-- POST 요청으로 전송하기 위해 버튼으로 구현 -->
        <form action="<?= site_url('logout'); ?>" method="post" class="logout-form">
          <button type="submit" class="logout-btn">로그아웃</button>
        </form>

      </nav>
    </div>
  </header>

  <main class="main-content">