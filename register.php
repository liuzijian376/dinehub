<?php
require __DIR__ . '/lib.php';
if (current_user()) redirect('index.php');
$err = []; $in = ['name' => '', 'student_id' => '', 'email' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $in = ['name' => trim($_POST['name'] ?? ''), 'student_id' => trim($_POST['student_id'] ?? ''), 'email' => strtolower(trim($_POST['email'] ?? ''))];
    $pw = $_POST['password'] ?? ''; $pw2 = $_POST['password2'] ?? '';
    if (strlen($in['name']) < 2 || strlen($in['name']) > 80) $err[] = 'Enter your full name (2 to 80 characters).';
    if (!preg_match('/^\d{7}$/', $in['student_id'])) $err[] = 'Student ID must be 7 digits.';
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid email address.';
    if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) $err[] = 'Password needs 8+ characters with at least one letter and one number.';
    if ($pw !== $pw2) $err[] = 'Passwords do not match.';
    if (!$err) {
        $s = db()->prepare('SELECT 1 FROM users WHERE email = ? OR student_id = ?');
        $s->execute([$in['email'], $in['student_id']]);
        if ($s->fetch()) $err[] = 'An account with that email or student ID already exists.';
    }
    if (!$err) {
        db()->prepare('INSERT INTO users(name,student_id,email,password_hash) VALUES(?,?,?,?)')
            ->execute([$in['name'], $in['student_id'], $in['email'], password_hash($pw, PASSWORD_DEFAULT)]);
        $_SESSION['uid'] = (int)db()->lastInsertId();
        session_regenerate_id(true);
        flash('Welcome to DineHub! Your account is ready.');
        redirect('index.php');
    }
}
page_header('Sign up'); ?>
<form class="panel narrow" method="post" id="signup" novalidate>
  <h1>Create your account</h1>
  <?php foreach ($err as $e): ?><div class="flash err"><?= h($e) ?></div><?php endforeach; ?>
  <?= csrf_field() ?>
  <label>Full name <input name="name" value="<?= h($in['name']) ?>" required></label>
  <label>Student ID <input name="student_id" value="<?= h($in['student_id']) ?>" inputmode="numeric" maxlength="7" required></label>
  <label>Email <input type="email" name="email" value="<?= h($in['email']) ?>" required></label>
  <label>Password <input type="password" name="password" minlength="8" required></label>
  <label>Confirm password <input type="password" name="password2" required></label>
  <button class="btn" type="submit">Create account</button>
  <p class="hint">Already registered? <a href="login.php">Log in</a></p>
</form>
<?php page_footer();
