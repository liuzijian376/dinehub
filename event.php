<?php
require __DIR__ . '/lib.php';
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT e.*, c.name AS restaurant_name, c.admin_id FROM events e JOIN restaurants c ON c.id = e.restaurant_id WHERE e.id = ?');
$st->execute([$id]);
$e = $st->fetch();
$u = current_user();
if (!$e || ($e['status'] !== 'approved' && !can_manage($u, $e) && (int)($u['id'] ?? 0) !== (int)$e['created_by'])) {
    http_response_code(404);
    page_header('Not found'); echo '<p class="empty">That event does not exist or is not public yet.</p>'; page_footer(); exit;
}

// Handle join / leave / delete (all POST + CSRF protected).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = require_login();
    $action = $_POST['action'] ?? '';
    $db = db();
    if ($action === 'join') {
        if ($e['status'] !== 'approved' || $e['event_date'] < date('Y-m-d H:i')) {
            flash('This event is not open for registration.', 'err');
        } else {
            $guests = max(1, min(4, (int)($_POST['guests'] ?? 1)));
            $diet = in_array($_POST['dietary'] ?? '', ['None', 'Vegetarian', 'Vegan', 'Halal', 'Nut allergy'], true) ? $_POST['dietary'] : 'None';
            $db->beginTransaction();   // keeps the seat count correct if two people book at once
            $taken = (int)$db->query('SELECT COALESCE(SUM(guests),0) FROM registrations WHERE event_id = ' . (int)$id)->fetchColumn();
            if ($taken + $guests > $e['capacity']) {
                $db->rollBack(); flash('Not enough seats left for a group of ' . $guests . '.', 'err');
            } else {
                $db->prepare('INSERT OR IGNORE INTO registrations(event_id,user_id,guests,dietary) VALUES(?,?,?,?)')->execute([$id, $u['id'], $guests, $diet]);
                $db->commit(); flash('Seat booked for ' . $guests . '. See you there!');
            }
        }
    } elseif ($action === 'leave') {
        $db->prepare('DELETE FROM registrations WHERE event_id = ? AND user_id = ?')->execute([$id, $u['id']]);
        flash('Your registration was cancelled.');
    } elseif ($action === 'delete' && can_manage($u, $e)) {
        $db->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
        flash('Event deleted.'); redirect('index.php');
    }
    redirect("event.php?id=$id");
}

$taken = (int)db()->query('SELECT COALESCE(SUM(guests),0) FROM registrations WHERE event_id = ' . $id)->fetchColumn();
$joined = false;
if ($u) {
    $s = db()->prepare('SELECT 1 FROM registrations WHERE event_id = ? AND user_id = ?');
    $s->execute([$id, $u['id']]);
    $joined = (bool)$s->fetch();
}
$manage = can_manage($u, $e);
$past = $e['event_date'] < date('Y-m-d H:i');
$attendees = [];
if ($manage) {
    $s = db()->prepare('SELECT u.name, u.student_id, u.email, r.guests, r.dietary, r.created_at FROM registrations r JOIN users u ON u.id = r.user_id WHERE r.event_id = ? ORDER BY r.created_at');
    $s->execute([$id]);
    $attendees = $s->fetchAll();
}
page_header($e['title']); ?>
<article class="panel">
  <p class="meta"><a href="restaurants.php?id=<?= (int)$e['restaurant_id'] ?>"><?= h($e['restaurant_name']) ?></a>
    <?php if ($e['status'] !== 'approved'): ?><span class="tag full"><?= h(ucfirst($e['status'])) ?></span><?php endif; ?></p>
  <h1><?= h($e['title']) ?></h1>
  <dl class="facts">
    <dt>When</dt><dd><?= h(fmt_date($e['event_date'])) ?></dd>
    <dt>Where</dt><dd><?= h($e['venue']) ?></dd>
    <dt>Seats</dt><dd><?= $taken ?> of <?= (int)$e['capacity'] ?> taken</dd>
  </dl>
  <p><?= nl2br(h($e['description'])) ?></p>

  <form method="post" class="actions">
    <?= csrf_field() ?>
    <?php if ($past): ?>
      <span class="tag full">This event has ended</span>
    <?php elseif (!$u): ?>
      <a class="btn" href="login.php">Log in to book</a>
    <?php elseif ($joined): ?>
      <button class="btn ghost" name="action" value="leave" data-confirm="Cancel your registration?">Cancel booking</button>
    <?php elseif ($taken >= $e['capacity']): ?>
      <span class="tag full">Event is full</span>
    <?php elseif ($e['status'] === 'approved'): ?>
      <label class="inl">Seats <select name="guests"><?php for ($i = 1; $i <= min(4, $e['capacity'] - $taken); $i++): ?><option><?= $i ?></option><?php endfor; ?></select></label>
      <label class="inl">Dietary <select name="dietary"><?php foreach (['None', 'Vegetarian', 'Vegan', 'Halal', 'Nut allergy'] as $dv): ?><option><?= $dv ?></option><?php endforeach; ?></select></label>
      <button class="btn" name="action" value="join">Book seats</button>
    <?php endif; ?>
    <?php if ($manage): ?>
      <a class="btn ghost" href="event_form.php?id=<?= $id ?>">Edit</a>
      <button class="btn danger" name="action" value="delete" data-confirm="Delete this event and all registrations?">Delete</button>
    <?php endif; ?>
  </form>
</article>

<?php if ($manage): ?>
<section class="panel">
  <h2>Bookings (<?= count($attendees) ?> students, <?= $taken ?> seats)</h2>
  <?php $sum = []; foreach ($attendees as $a) { $sum[$a['dietary']] = ($sum[$a['dietary']] ?? 0) + $a['guests']; } ?>
  <?php if ($sum): ?><p class="hint">Kitchen summary: <?= h(implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($sum), $sum))) ?></p><?php endif; ?>
  <?php if (!$attendees): ?><p class="empty">No one has joined yet.</p><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Name</th><th>Student ID</th><th>Email</th><th>Seats</th><th>Dietary</th><th>Booked</th></tr>
    <?php foreach ($attendees as $a): ?>
      <tr><td><?= h($a['name']) ?></td><td><?= h($a['student_id']) ?></td><td><?= h($a['email']) ?></td><td><?= (int)$a['guests'] ?></td><td><?= h($a['dietary']) ?></td><td><?= h($a['created_at']) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>
<?php endif;
page_footer();
