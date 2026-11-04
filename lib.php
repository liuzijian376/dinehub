<?php
/* Shared helpers: database connection, sessions, CSRF, flash messages, auth. */
session_start();
const DB_FILE = __DIR__ . '/data/dinehub.sqlite';

/** Returns one shared PDO connection. Builds and seeds the database on first run. */
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!is_dir(dirname(DB_FILE))) mkdir(dirname(DB_FILE), 0775, true);
    $fresh = !file_exists(DB_FILE);
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    if ($fresh) {
        $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
        seed_data($pdo);
    }
    return $pdo;
}

/** Demo data so the prototype is not empty. */
function seed_data(PDO $db): void {
    $pw = fn($p) => password_hash($p, PASSWORD_DEFAULT);
    $u = $db->prepare('INSERT INTO users(name,student_id,email,password_hash,role,bio) VALUES(?,?,?,?,?,?)');
    $u->execute(['System Admin', '0000001', 'admin@taylors.edu.my', $pw('Admin123'), 'admin', 'Platform administrator']);
    $u->execute(['Aisha Rahman', '0312345', 'aisha@sd.taylors.edu.my', $pw('Chef1234'), 'restaurant_manager', 'Manager, Lakeside Cafe']);
    $u->execute(['Daniel Lim', '0323456', 'daniel@sd.taylors.edu.my', $pw('Chef1234'), 'restaurant_manager', 'Manager, Garden Bistro']);
    $u->execute(['Mei Ling Tan', '0334567', 'meiling@sd.taylors.edu.my', $pw('Student123'), 'student', 'Year 2 Computer Science']);

    // Sample outlets (fictional names for demo purposes).
    $c = $db->prepare('INSERT INTO restaurants(name,category,description,admin_id) VALUES(?,?,?,?)');
    $c->execute(['Lakeside Cafe', 'Cafe', 'Coffee, pastries and live music evenings by the lake.', 2]);
    $c->execute(['Garden Bistro', 'Western', 'Western set menus and chef tasting nights in a garden setting.', 3]);
    $c->execute(['The Student Canteen', 'Local', 'Affordable local favourites and cooking workshops.', null]);
    $c->execute(['Sky Dining Hall', 'Buffet', 'Themed buffets on festive and international nights.', null]);

    $e = $db->prepare('INSERT INTO events(restaurant_id,title,description,venue,event_date,capacity,status,created_by) VALUES(?,?,?,?,?,?,?,?)');
    $d = fn($days, $t) => date('Y-m-d', strtotime("+$days days")) . ' ' . $t;
    $e->execute([1, 'Acoustic Latte Night', 'Live acoustic music with a free signature latte for every guest.', 'Lakeside Cafe, Level 1', $d(3, '19:30'), 40, 'approved', 2]);
    $e->execute([2, 'Chef Tasting Menu: Five Courses', 'A five-course tasting menu paired with mocktails. Vegetarian option available.', 'Garden Bistro', $d(7, '19:00'), 24, 'approved', 3]);
    $e->execute([3, 'Nasi Lemak Cooking Workshop', 'Learn to cook nasi lemak from scratch, then eat what you make.', 'Student Canteen Kitchen', $d(5, '14:00'), 16, 'approved', 1]);
    $e->execute([4, 'International Buffet Night', 'Dishes from twelve countries cooked by our chefs and student cooks.', 'Sky Dining Hall', $d(10, '18:30'), 120, 'approved', 1]);
    $e->execute([4, 'Festive Open House Lunch', 'Open-house lunch with traditional festive dishes and desserts.', 'Sky Dining Hall', $d(14, '12:00'), 100, 'approved', 1]);
    $e->execute([2, 'Midnight Supper Pop-up', 'Waiting for admin approval.', 'Garden Bistro', $d(18, '22:00'), 30, 'pending', 3]);
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header("Location: $url"); exit; }

function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'][] = [$msg, $type]; }
function take_flash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function csrf_field(): string {
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
}
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid form token. Go back, reload the page and try again.');
    }
}

function current_user(): ?array {
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $s = db()->prepare('SELECT * FROM users WHERE id = ?');
            $s->execute([$_SESSION['uid']]);
            $user = $s->fetch() ?: null;
        }
    }
    return $user;
}
function require_login(): array {
    $u = current_user();
    if (!$u) { flash('Log in to continue.', 'err'); redirect('login.php'); }
    return $u;
}
function require_role(string ...$roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) { http_response_code(403); exit('You do not have access to this page.'); }
    return $u;
}
/** Can this user edit/delete/see attendees for the given event row (needs restaurant manager_id joined in)? */
function can_manage(?array $u, array $event): bool {
    return $u && ($u['role'] === 'admin' || (int)$u['id'] === (int)($event['admin_id'] ?? 0));
}

function fmt_date(string $d): string { return date('D, j M Y \a\t g:i A', strtotime($d)); }

function event_card(array $e): string {
    $left = $e['capacity'] - $e['taken'];
    $seats = $left <= 0 ? '<span class="tag full">Full</span>' : '<span class="tag">' . $left . ' seats left</span>';
    return '<a class="card" href="event.php?id=' . (int)$e['id'] . '">'
        . '<p class="meta">' . h($e['restaurant_name']) . '</p>'
        . '<h3>' . h($e['title']) . '</h3>'
        . '<p>' . h(fmt_date($e['event_date'])) . '<br>' . h($e['venue']) . '</p>'
        . $seats . '</a>';
}

function page_header(string $title): void {
    $u = current_user(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> | DineHub</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="top">
  <a class="brand" href="index.php">DineHub</a>
  <nav>
    <a href="index.php">Events</a>
    <a href="restaurants.php">Restaurants</a>
    <?php if ($u): ?>
      <?php if (in_array($u['role'], ['restaurant_manager', 'admin'], true)): ?><a href="event_form.php">New event</a><?php endif; ?>
      <?php if ($u['role'] === 'admin'): ?><a href="admin.php">Admin</a><?php endif; ?>
      <a href="profile.php"><?= h($u['name']) ?></a>
      <a href="logout.php">Log out</a>
    <?php else: ?>
      <a href="login.php">Log in</a>
      <a class="btn small" href="register.php">Sign up</a>
    <?php endif; ?>
  </nav>
</header>
<main>
<?php foreach (take_flash() as [$m, $t]): ?><div class="flash <?= h($t) ?>"><?= h($m) ?></div><?php endforeach;
}

function page_footer(): void { ?>
</main>
<footer>DineHub · WAP project prototype · Taylor's University</footer>
<script src="assets/app.js"></script>
</body>
</html>
<?php }
