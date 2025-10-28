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
        <?= isset($user_name) ? html_escape($user_name) : '' ?>님 환영합니다!
      </span>
      <a href="/logout" class="logout-btn">로그아웃</a>
    </nav>
  </div>
</header>

<main class="main-content">
