<?php
require __DIR__ . '/lib.php';
$admin = require_role('admin');
$db = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    if ($a === 'approve' || $a === 'reject') {
        $db->prepare('UPDATE events SET status = ? WHERE id = ?')->execute([$a === 'approve' ? 'approved' : 'rejected', (int)$_POST['event_id']]);
        flash('Event ' . ($a === 'approve' ? 'approved.' : 'rejected.'));
    } elseif ($a === 'role') {
        $role = $_POST['role'] ?? '';
        $uid = (int)$_POST['user_id'];
        if (in_array($role, ['student', 'restaurant_manager', 'admin'], true) && $uid !== (int)$admin['id']) {
            $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $uid]);
            flash('Role updated.');
        } else flash('You cannot change your own role.', 'err');
    } elseif ($a === 'restaurant_manager') {
        $uid = (int)$_POST['user_id'] ?: null;
        $db->prepare('UPDATE restaurants SET admin_id = ? WHERE id = ?')->execute([$uid, (int)$_POST['restaurant_id']]);
        if ($uid) $db->prepare("UPDATE users SET role = 'restaurant_manager' WHERE id = ? AND role = 'student'")->execute([$uid]);
        flash('Restaurant manager assigned.');
    }
    redirect('admin.php');
}
$pending = $db->query("SELECT e.*, c.name AS restaurant_name FROM events e JOIN restaurants c ON c.id = e.restaurant_id WHERE e.status = 'pending' ORDER BY e.created_at")->fetchAll();
$users = $db->query('SELECT id, name, student_id, email, role FROM users ORDER BY id')->fetchAll();
$restaurants = $db->query('SELECT c.id, c.name, c.admin_id FROM restaurants c ORDER BY c.name')->fetchAll();
$stats = $db->query("SELECT (SELECT COUNT(*) FROM users) u, (SELECT COUNT(*) FROM events WHERE status='approved') e, (SELECT COUNT(*) FROM registrations) r")->fetch();
page_header('Admin'); ?>
<h1>Admin dashboard</h1>
<p class="hint"><?= (int)$stats['u'] ?> users · <?= (int)$stats['e'] ?> live events · <?= (int)$stats['r'] ?> registrations</p>

<section class="panel"><h2>Events waiting for approval</h2>
<?php if (!$pending): ?><p class="empty">Nothing to review right now.</p><?php else: foreach ($pending as $e): ?>
  <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int)$e['id'] ?>">
    <div><strong><a href="event.php?id=<?= (int)$e['id'] ?>"><?= h($e['title']) ?></a></strong><br><span class="hint"><?= h($e['restaurant_name']) ?> · <?= h(fmt_date($e['event_date'])) ?></span></div>
    <div><button class="btn small" name="action" value="approve">Approve</button> <button class="btn small danger" name="action" value="reject">Reject</button></div>
  </form>
<?php endforeach; endif; ?></section>

<section class="panel"><h2>Restaurant managers</h2>
<?php foreach ($restaurants as $c): ?>
  <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="restaurant_manager"><input type="hidden" name="restaurant_id" value="<?= (int)$c['id'] ?>">
    <strong><?= h($c['name']) ?></strong>
    <div><select name="user_id"><option value="0">No admin</option>
      <?php foreach ($users as $x): ?><option value="<?= (int)$x['id'] ?>" <?= (int)$c['admin_id'] === (int)$x['id'] ? 'selected' : '' ?>><?= h($x['name']) ?></option><?php endforeach; ?>
    </select> <button class="btn small">Assign</button></div>
  </form>
<?php endforeach; ?></section>

<section class="panel"><h2>Users</h2><div class="table-wrap"><table>
<tr><th>Name</th><th>Student ID</th><th>Email</th><th>Role</th></tr>
<?php foreach ($users as $x): ?><tr><td><?= h($x['name']) ?></td><td><?= h($x['student_id']) ?></td><td><?= h($x['email']) ?></td><td>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$x['id'] ?>">
  <select name="role"><?php foreach (['student', 'restaurant_manager', 'admin'] as $r): ?><option value="<?= $r ?>" <?= $x['role'] === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select>
  <button class="btn small ghost">Save</button></form></td></tr>
<?php endforeach; ?></table></div></section>
<?php page_footer();
