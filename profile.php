<?php
require __DIR__ . '/lib.php';
$u = require_login();
$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? ''); $bio = trim($_POST['bio'] ?? '');
    $new = $_POST['new_password'] ?? '';
    if (strlen($name) < 2 || strlen($name) > 80) $err[] = 'Enter your full name (2 to 80 characters).';
    if (strlen($bio) > 300) $err[] = 'Bio can be at most 300 characters.';
    if ($new !== '') {
        if (!password_verify($_POST['old_password'] ?? '', $u['password_hash'])) $err[] = 'Current password is incorrect.';
        if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) $err[] = 'New password needs 8+ characters with a letter and a number.';
    }
    if (!$err) {
        db()->prepare('UPDATE users SET name = ?, bio = ? WHERE id = ?')->execute([$name, $bio, $u['id']]);
        if ($new !== '') db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        flash('Profile saved.'); redirect('profile.php');
    }
}
$s = db()->prepare("SELECT e.id, e.title, e.event_date, e.venue, c.name AS restaurant_name FROM registrations r
                    JOIN events e ON e.id = r.event_id JOIN restaurants c ON c.id = e.restaurant_id WHERE r.user_id = ? ORDER BY e.event_date");
$s->execute([$u['id']]); $mine = $s->fetchAll();
$s = db()->prepare('SELECT c.id, c.name FROM restaurant_followers m JOIN restaurants c ON c.id = m.restaurant_id WHERE m.user_id = ?');
$s->execute([$u['id']]); $following = $s->fetchAll();
page_header('Profile'); ?>
<div class="two-col">
<form class="panel" method="post">
  <h1>Your profile</h1>
  <p class="hint"><?= h($u['email']) ?> · ID <?= h($u['student_id']) ?> · <?= h(str_replace('_', ' ', $u['role'])) ?></p>
  <?php foreach ($err as $m): ?><div class="flash err"><?= h($m) ?></div><?php endforeach; ?>
  <?= csrf_field() ?>
  <label>Full name <input name="name" value="<?= h($u['name']) ?>" required></label>
  <label>Bio <textarea name="bio" rows="3" maxlength="300"><?= h($u['bio']) ?></textarea></label>
  <label>Current password <input type="password" name="old_password" autocomplete="current-password"></label>
  <label>New password (leave blank to keep) <input type="password" name="new_password" autocomplete="new-password"></label>
  <button class="btn" type="submit">Save changes</button>
</form>
<section class="panel">
  <h2>My registered events</h2>
  <?php if (!$mine): ?><p class="empty">You have not joined any events yet. <a href="index.php">Browse events</a>.</p><?php else: ?>
  <ul class="list"><?php foreach ($mine as $m): ?>
    <li><a href="event.php?id=<?= (int)$m['id'] ?>"><?= h($m['title']) ?></a><br><span class="hint"><?= h($m['restaurant_name']) ?> · <?= h(fmt_date($m['event_date'])) ?></span></li>
  <?php endforeach; ?></ul><?php endif; ?>
  <h2>Restaurants I follow</h2>
  <?php if (!$following): ?><p class="empty">None yet. <a href="restaurants.php">Explore restaurants</a>.</p><?php else: ?>
  <ul class="list"><?php foreach ($following as $c): ?><li><a href="restaurants.php?id=<?= (int)$c['id'] ?>"><?= h($c['name']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
</section>
</div>
<?php page_footer();
