<?php
require __DIR__ . '/lib.php';
if (current_user()) redirect('index.php');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $s = db()->prepare('SELECT * FROM users WHERE email = ?');
    $s->execute([strtolower(trim($_POST['email'] ?? ''))]);
    $u = $s->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);   // prevents session fixation
        $_SESSION['uid'] = (int)$u['id'];
        flash('Logged in as ' . $u['name'] . '.');
        redirect('index.php');
    }
    $err = 'Email or password is incorrect.';
}
page_header('Log in'); ?>
<form class="panel narrow" method="post">
  <h1>Log in</h1>
  <?php if ($err): ?><div class="flash err"><?= h($err) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>Email <input type="email" name="email" required autofocus></label>
  <label>Password <input type="password" name="password" required></label>
  <button class="btn" type="submit">Log in</button>
  <p class="hint">New here? <a href="register.php">Create an account</a></p>
</form>
<?php page_footer();
