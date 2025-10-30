<h1>회원가입</h1>

{? isset(flash_success) && flash_success}
  <script>alert({= json_encode(flash_success) });</script>
{/}
{? isset(flash_error) && flash_error}
  <script>alert({= json_encode(flash_error) });</script>
{/}

{? isset(val_errors) && val_errors}
  <div class="auth-error">{= val_errors }</div>
{/}

<form method="post" action="{= site_url('auth/do_register') }">
  <div>
    <label>이름</label>
    <input type="text" name="name" value="{= set_value('name') }" required>
  </div>

  <div>
    <label>아이디</label>
    <input type="text" name="login_id" value="{= set_value('login_id') }" required>
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
</form>

<p><a href="{= site_url('auth/login') }">로그인으로 돌아가기</a></p>
