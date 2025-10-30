<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <title>로그인</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= base_url('assets/css/auth.css'); ?>">
</head>
<body>
  <h1>로그인</h1>

<?php
  $success = $this->session->flashdata('success');
  $error   = $this->session->flashdata('error');

  if ($success) {
      echo "<script>alert('{$success}');</script>";
  }

  if ($error) {
      echo "<script>alert('{$error}');</script>";
  }
?>

  <form method="post" action="<?= site_url('auth/do_login'); ?>">
  <div>
    <label>아이디</label>
    <input type="text" name="login_id" value="<?= set_value('login_id'); ?>" required>
  </div>
  <div>
    <label>비밀번호</label>
    <input type="password" name="password" required>
  </div>
  <button type="submit">로그인</button>
  </form>
  <a href="../auth/register">회원가입</a>
</body>
</html>
