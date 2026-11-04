<?php
require __DIR__ . '/lib.php';
$u = current_user();
$id = (int)($_GET['id'] ?? 0);

// Follow / unfollow a restaurant.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = require_login();
    $cid = (int)($_POST['restaurant_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'follow') {
        db()->prepare('INSERT OR IGNORE INTO restaurant_followers(restaurant_id,user_id) VALUES(?,?)')->execute([$cid, $u['id']]);
        flash('You now follow this restaurant.');
    } else {
        db()->prepare('DELETE FROM restaurant_followers WHERE restaurant_id = ? AND user_id = ?')->execute([$cid, $u['id']]);
        flash('You unfollowed this restaurant.');
    }
    redirect("restaurants.php?id=$cid");
}

if ($id) {
    $s = db()->prepare('SELECT c.*, u.name AS admin_name, (SELECT COUNT(*) FROM restaurant_followers m WHERE m.restaurant_id = c.id) AS members FROM restaurants c LEFT JOIN users u ON u.id = c.admin_id WHERE c.id = ?');
    $s->execute([$id]);
    $restaurant = $s->fetch();
    if (!$restaurant) { http_response_code(404); page_header('Not found'); echo '<p class="empty">Restaurant not found.</p>'; page_footer(); exit; }
    $following = false;
    if ($u) { $s = db()->prepare('SELECT 1 FROM restaurant_followers WHERE restaurant_id = ? AND user_id = ?'); $s->execute([$id, $u['id']]); $following = (bool)$s->fetch(); }
    $s = db()->prepare("SELECT e.*, c.name AS restaurant_name, (SELECT COALESCE(SUM(r.guests),0) FROM registrations r WHERE r.event_id = e.id) AS taken
                        FROM events e JOIN restaurants c ON c.id = e.restaurant_id WHERE e.restaurant_id = ? AND e.status = 'approved' AND e.event_date >= ? ORDER BY e.event_date");
    $s->execute([$id, date('Y-m-d H:i')]);
    $events = $s->fetchAll();
    page_header($restaurant['name']); ?>
    <section class="panel">
      <p class="meta"><?= h($restaurant['category']) ?></p>
      <h1><?= h($restaurant['name']) ?></h1>
      <p><?= h($restaurant['description']) ?></p>
      <p class="hint"><?= (int)$restaurant['members'] ?> followers · Run by <?= h($restaurant['admin_name'] ?? 'no admin assigned yet') ?></p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="restaurant_id" value="<?= $id ?>">
        <?php if ($u && $following): ?><button class="btn ghost" name="action" value="unfollow">Unfollow</button>
        <?php elseif ($u): ?><button class="btn" name="action" value="follow">Follow restaurant</button>
        <?php else: ?><a class="btn" href="login.php">Log in to follow</a><?php endif; ?>
      </form>
    </section>
    <h2>Upcoming events</h2>
    <?php if (!$events): ?><p class="empty">No upcoming events from this restaurant yet.</p>
    <?php else: ?><div class="grid"><?php foreach ($events as $e) echo event_card($e); ?></div><?php endif;
    page_footer(); exit;
}

$restaurants = db()->query('SELECT c.*, (SELECT COUNT(*) FROM restaurant_followers m WHERE m.restaurant_id = c.id) AS members FROM restaurants c ORDER BY c.name')->fetchAll();
page_header('Restaurants'); ?>
<section class="hero"><h1>Restaurants and cafes</h1><p>Follow the ones you like to keep track of what they run.</p></section>
<div class="grid">
<?php foreach ($restaurants as $c): ?>
  <a class="card" href="restaurants.php?id=<?= (int)$c['id'] ?>">
    <p class="meta"><?= h($c['category']) ?></p><h3><?= h($c['name']) ?></h3>
    <p><?= h($c['description']) ?></p><span class="tag"><?= (int)$c['members'] ?> followers</span>
  </a>
<?php endforeach; ?>
</div>
<?php page_footer();
