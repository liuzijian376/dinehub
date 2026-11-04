<?php
require __DIR__ . '/lib.php';
// Create or edit an event. Restaurant managers manage their own restaurant; admins manage any restaurant.
$u = require_role('restaurant_manager', 'admin');
$id = (int)($_GET['id'] ?? 0);
$ev = ['restaurant_id' => 0, 'title' => '', 'description' => '', 'venue' => '', 'event_date' => '', 'capacity' => 30];

if ($u['role'] === 'admin') {
    $restaurants = db()->query('SELECT id, name FROM restaurants ORDER BY name')->fetchAll();
} else {
    $s = db()->prepare('SELECT id, name FROM restaurants WHERE admin_id = ?'); $s->execute([$u['id']]); $restaurants = $s->fetchAll();
}
if (!$restaurants) { flash('You are not assigned to a restaurant yet. Ask an admin to assign you.', 'err'); redirect('index.php'); }

if ($id) {
    $s = db()->prepare('SELECT e.*, c.admin_id FROM events e JOIN restaurants c ON c.id = e.restaurant_id WHERE e.id = ?');
    $s->execute([$id]); $existing = $s->fetch();
    if (!$existing || !can_manage($u, $existing)) { http_response_code(403); exit('You cannot edit this event.'); }
    $ev = $existing;
}
$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ev = ['restaurant_id' => (int)($_POST['restaurant_id'] ?? 0), 'title' => trim($_POST['title'] ?? ''), 'description' => trim($_POST['description'] ?? ''),
           'venue' => trim($_POST['venue'] ?? ''), 'event_date' => str_replace('T', ' ', trim($_POST['event_date'] ?? '')), 'capacity' => (int)($_POST['capacity'] ?? 0)] + $ev;
    if (!in_array($ev['restaurant_id'], array_column($restaurants, 'id'), false)) $err[] = 'Pick one of your restaurants.';
    if (strlen($ev['title']) < 3 || strlen($ev['title']) > 100) $err[] = 'Title must be 3 to 100 characters.';
    if (strlen($ev['description']) < 10) $err[] = 'Add a description of at least 10 characters.';
    if ($ev['venue'] === '') $err[] = 'Venue is required.';
    $ts = strtotime($ev['event_date']);
    if (!$ts) $err[] = 'Choose a valid date and time.';
    elseif ($ts < time()) $err[] = 'The event must be in the future.';
    if ($ev['capacity'] < 1 || $ev['capacity'] > 1000) $err[] = 'Capacity must be between 1 and 1000.';
    if (!$err) {
        $date = date('Y-m-d H:i', $ts);
        if ($id) {
            db()->prepare('UPDATE events SET restaurant_id=?, title=?, description=?, venue=?, event_date=?, capacity=? WHERE id=?')
                ->execute([$ev['restaurant_id'], $ev['title'], $ev['description'], $ev['venue'], $date, $ev['capacity'], $id]);
            flash('Event updated.');
        } else {
            // Admin events go live immediately; restaurant manager events wait for approval.
            $status = $u['role'] === 'admin' ? 'approved' : 'pending';
            db()->prepare('INSERT INTO events(restaurant_id,title,description,venue,event_date,capacity,status,created_by) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$ev['restaurant_id'], $ev['title'], $ev['description'], $ev['venue'], $date, $ev['capacity'], $status, $u['id']]);
            $id = (int)db()->lastInsertId();
            flash($status === 'approved' ? 'Event published.' : 'Event submitted. It will appear once an admin approves it.');
        }
        redirect("event.php?id=$id");
    }
}
$dtval = $ev['event_date'] ? date('Y-m-d\TH:i', strtotime($ev['event_date'])) : '';
page_header($id ? 'Edit event' : 'New event'); ?>
<form class="panel narrow" method="post">
  <h1><?= $id ? 'Edit event' : 'Create an event' ?></h1>
  <?php foreach ($err as $m): ?><div class="flash err"><?= h($m) ?></div><?php endforeach; ?>
  <?= csrf_field() ?>
  <label>Restaurant <select name="restaurant_id"><?php foreach ($restaurants as $c): ?>
    <option value="<?= (int)$c['id'] ?>" <?= (int)$ev['restaurant_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
  <label>Title <input name="title" maxlength="100" value="<?= h($ev['title']) ?>" required></label>
  <label>Description <textarea name="description" rows="5" required><?= h($ev['description']) ?></textarea></label>
  <label>Venue <input name="venue" value="<?= h($ev['venue']) ?>" required></label>
  <label>Date and time <input type="datetime-local" name="event_date" value="<?= h($dtval) ?>" required></label>
  <label>Capacity <input type="number" name="capacity" min="1" max="1000" value="<?= (int)$ev['capacity'] ?>" required></label>
  <button class="btn" type="submit"><?= $id ? 'Save changes' : 'Submit event' ?></button>
</form>
<?php page_footer();
