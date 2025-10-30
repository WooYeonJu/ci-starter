<h1>로그인</h1>

{? isset(flash_success) && flash_success}
  <script>alert({= json_encode(flash_success) });</script>
{/}
{? isset(flash_error) && flash_error}
  <script>alert({= json_encode(flash_error) });</script>
{/}

<form method="post" action="{= site_url('auth/do_login') }">
  <div>
    <label>아이디</label>
    <input type="text" name="login_id" value="{= set_value('login_id') }" required>
  </div>

  <div>
    <label>비밀번호</label>
    <input type="password" name="password" required>
  </div>

  <button type="submit">로그인</button>
</form>

<a href="{= site_url('auth/register') }">회원가입</a>
