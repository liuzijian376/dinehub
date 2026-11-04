<?php
require __DIR__ . '/lib.php';
// Browse upcoming approved events, with optional keyword and restaurant filters.
$q = trim($_GET['q'] ?? '');
$restaurant = (int)($_GET['restaurant'] ?? 0);
$sql = "SELECT e.*, c.name AS restaurant_name,
          (SELECT COALESCE(SUM(r.guests),0) FROM registrations r WHERE r.event_id = e.id) AS taken
        FROM events e JOIN restaurants c ON c.id = e.restaurant_id
        WHERE e.status = 'approved' AND e.event_date >= :now";
$p = [':now' => date('Y-m-d H:i')];
if ($q !== '') { $sql .= ' AND (e.title LIKE :q OR e.description LIKE :q OR e.venue LIKE :q)'; $p[':q'] = "%$q%"; }
if ($restaurant) { $sql .= ' AND e.restaurant_id = :restaurant'; $p[':restaurant'] = $restaurant; }
$sql .= ' ORDER BY e.event_date';
$st = db()->prepare($sql);
$st->execute($p);
$events = $st->fetchAll();
$restaurants = db()->query('SELECT id, name FROM restaurants ORDER BY name')->fetchAll();

page_header('Events'); ?>
<section class="hero">
  <h1>What's cooking at Taylor's dining outlets</h1>
  <p>Book a seat at a tasting night, workshop or themed buffet.</p>
</section>
<form class="filters" method="get">
  <input type="search" name="q" placeholder="Search events, dishes or venues" value="<?= h($q) ?>">
  <select name="restaurant">
    <option value="0">All restaurants</option>
    <?php foreach ($restaurants as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= $restaurant === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Search</button>
</form>
<?php if (!$events): ?>
  <p class="empty">No events match your search. Clear the filters or check back soon.</p>
<?php else: ?>
  <div class="grid"><?php foreach ($events as $e) echo event_card($e); ?></div>
<?php endif;
page_footer();
