<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <title>회원가입</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= base_url('assets/css/auth.css'); ?>">
</head>
<body>

<h1>회원가입</h1>

<?php
  $success = $this->session->flashdata('success');
  $error   = $this->session->flashdata('error');
  if ($success) echo "<script>alert('{$success}');</script>";
  if ($error)   echo "<script>alert('{$error}');</script>";
?>

<?php if (validation_errors()): ?>
  <div class="auth-error"><?= validation_errors(); ?></div>
<?php endif; ?>


<?= form_open('auth/do_register'); ?>
  <div>
    <label>이름</label>
    <input type="text" name="name" value="<?= set_value('name'); ?>" required>
  </div>

  <div>
    <label>아이디</label>
    <input type="text" name="login_id" value="<?= set_value('login_id'); ?>" required>
  </div>

  <div>
    <label>비밀번호</label>
    <input type="password" name="password" required>
  </div>

  <div>
    <label>비밀번호 확인</label>
    <input type="password" name="password_confirm" required>
  </div>

  <button type="submit">가입하기</button>
<?= form_close(); ?>

<p><a href="<?= site_url('auth/login'); ?>">로그인으로 돌아가기</a></p>

</body>
</html>
