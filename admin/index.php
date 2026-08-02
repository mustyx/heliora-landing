<?php
/* ════════════════════════════════════════════════════════
   Heliora Consulting — Admin Dashboard
   Access: helioraconsulting.com/admin/
   ════════════════════════════════════════════════════════ */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ── Authentication ────────────────────────────────────────
$isLoggedIn = isset($_SESSION['heliora_admin']) && $_SESSION['heliora_admin'] === true;
$loginError = '';

if (!$isLoggedIn) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $user = trim($_POST['username'] ?? '');
        $pass = trim($_POST['password'] ?? '');

        if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
            session_regenerate_id(true);
            $_SESSION['heliora_admin'] = true;
            $_SESSION['admin_ip']      = $_SERVER['REMOTE_ADDR'];
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $loginError = 'Invalid credentials.';
            sleep(1); // slow brute-force
        }
    }

    // Show login page
    showLogin($loginError);
    exit;
}

// ── Logout ────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportCSV();
    exit;
}

// ── CSRF token (used by the destructive delete action) ────
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ── Delete leads (single or bulk) ─────────────────────────
$deleteNotice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_leads'])) {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $deleteNotice = 'Security check failed. Please try again.';
    } else {
        // Cast every id to int, drop anything invalid
        $ids = array_values(array_filter(
            array_map('intval', (array) ($_POST['ids'] ?? [])),
            fn($i) => $i > 0
        ));

        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            // email_log rows are removed automatically (FK ON DELETE CASCADE)
            $del = getDB()->prepare("DELETE FROM leads WHERE id IN ($ph)");
            $del->execute($ids);
            $n = $del->rowCount();
            $_SESSION['flash'] = $n . ' lead' . ($n === 1 ? '' : 's') . ' deleted.';
        } else {
            $_SESSION['flash'] = 'No leads were selected.';
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')
               . '?status=' . urlencode($_GET['status'] ?? 'all')
               . ($_GET['q'] ?? '' ? '&q=' . urlencode($_GET['q']) : ''));
        exit;
    }
}

// ── Status update ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id     = (int) ($_POST['lead_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $valid  = ['new','contacted','qualified','converted','lost'];
    if ($id && in_array($status, $valid, true)) {
        getDB()->prepare('UPDATE leads SET status=? WHERE id=?')->execute([$status, $id]);
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ── Fetch leads ───────────────────────────────────────────
$pdo    = getDB();
$filter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['p'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = [];
$params = [];

if ($filter !== 'all') {
    $where[]  = 'status = ?';
    $params[] = $filter;
}
if ($search) {
    $where[]  = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like, $like]);
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) $pdo->prepare("SELECT COUNT(*) FROM leads $whereSQL")->execute($params) ? 0 : 0;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM leads $whereSQL");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM leads $whereSQL ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Stats
$stats = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(status='new') as new_count,
        SUM(status='contacted') as contacted,
        SUM(status='qualified') as qualified,
        SUM(status='converted') as converted,
        SUM(DATE(created_at) = CURDATE()) as today,
        SUM(YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1)) as this_week
    FROM leads
")->fetch();

$pages = (int) ceil($total / $limit);

// ── Helpers ───────────────────────────────────────────────
function statusBadge(string $s): string {
    $map = [
        'new'       => 'bg-blue-900/50 text-blue-300 border-blue-700',
        'contacted' => 'bg-yellow-900/50 text-yellow-300 border-yellow-700',
        'qualified' => 'bg-purple-900/50 text-purple-300 border-purple-700',
        'converted' => 'bg-green-900/50 text-green-300 border-green-700',
        'lost'      => 'bg-red-900/50 text-red-400 border-red-800',
    ];
    $cls = $map[$s] ?? 'bg-gray-800 text-gray-400 border-gray-700';
    return "<span class=\"inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border $cls\">" . ucfirst($s) . "</span>";
}

function exportCSV(): void {
    require_once __DIR__ . '/../config/database.php';
    $leads = getDB()->query('SELECT * FROM leads ORDER BY created_at DESC')->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="heliora_leads_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','First Name','Last Name','Email','Phone','Company','Service','Budget','Message','Status','UTM Source','UTM Medium','UTM Campaign','Created At']);
    foreach ($leads as $l) {
        fputcsv($out, [$l['id'],$l['first_name'],$l['last_name'],$l['email'],$l['phone'],$l['company'],$l['service'],$l['project_budget'],$l['message'],$l['status'],$l['utm_source'],$l['utm_medium'],$l['utm_campaign'],$l['created_at']]);
    }
    fclose($out);
}

function showLogin(string $error): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Admin — Heliora Consulting</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-950 flex items-center justify-center p-4">
  <div class="w-full max-w-sm">
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-yellow-500 text-gray-900 font-bold text-2xl font-serif mb-4">H</div>
      <h1 class="text-white text-2xl font-bold">Admin Panel</h1>
      <p class="text-gray-500 text-sm mt-1">Heliora Consulting</p>
    </div>
    <form method="POST" class="bg-gray-900 border border-gray-800 rounded-2xl p-8 space-y-5">
      <?php if ($error): ?><div class="p-3 bg-red-900/40 border border-red-800 rounded-lg text-red-400 text-sm"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <div>
        <label class="block text-gray-400 text-sm mb-2">Username</label>
        <input type="text" name="username" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-white text-sm outline-none focus:border-yellow-500" autocomplete="username"/>
      </div>
      <div>
        <label class="block text-gray-400 text-sm mb-2">Password</label>
        <input type="password" name="password" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-white text-sm outline-none focus:border-yellow-500" autocomplete="current-password"/>
      </div>
      <button type="submit" name="login" class="w-full py-3 bg-yellow-500 hover:bg-yellow-400 text-gray-900 font-bold rounded-lg transition-colors">Sign In</button>
    </form>
  </div>
</body>
</html>
<?php }

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Admin Dashboard — Heliora Consulting</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .scrollbar-thin::-webkit-scrollbar { height: 4px; }
    .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
    .scrollbar-thin::-webkit-scrollbar-thumb { background: #374151; border-radius: 2px; }

    /* ── Light mode ───────────────────────────────────────────────
       Remaps the dark Tailwind utilities this page uses onto the
       Heliora light palette. Applied via .light on <body>.       */
    body.light,
    html.pre-light body                 { background: #f4f7fb !important; color: #0b2140 !important; }
    body.light .bg-gray-950             { background: #f4f7fb !important; }
    body.light .bg-gray-900             { background: #ffffff !important; }
    body.light .bg-gray-800             { background: #eef1f5 !important; }
    body.light .bg-black                { background: #ffffff !important; }
    body.light .text-white              { color: #0b2140 !important; }
    body.light .text-gray-300           { color: #44576f !important; }
    body.light .text-gray-400           { color: #5f7188 !important; }
    body.light .text-gray-500           { color: #5f7188 !important; }
    body.light .text-gray-600           { color: #8595a8 !important; }
    body.light .border-gray-800         { border-color: #e6ecf3 !important; }
    body.light .border-gray-700         { border-color: #d5dde7 !important; }
    body.light .divide-gray-800 > * + * { border-color: #e6ecf3 !important; }
    body.light .hover\:text-white:hover { color: #0a7c7e !important; }
    body.light input::placeholder       { color: #8595a8 !important; }
    body.light .scrollbar-thin::-webkit-scrollbar-thumb { background: #d5dde7; }
    /* Cards and rows get a touch of depth on white */
    body.light .bg-gray-900.border       { box-shadow: 0 1px 3px rgba(7,33,68,.06); }
    /* Keep the amber accent readable on a light ground */
    body.light .text-yellow-400          { color: #8a5a07 !important; }
    body.light .bg-yellow-500\/10        { background: rgba(208,135,13,.10) !important; }
    body.light .border-yellow-500\/30    { border-color: rgba(208,135,13,.35) !important; }

    /* Status badges: readable pastels instead of dark translucent fills */
    body.light .bg-blue-900\/50   { background: #e8f0fe !important; }
    body.light .text-blue-300     { color: #1a4fa0 !important; }
    body.light .border-blue-700   { border-color: #b6cef5 !important; }
    body.light .bg-yellow-900\/50 { background: #fdf4e3 !important; }
    body.light .text-yellow-300   { color: #8a5a07 !important; }
    body.light .border-yellow-700 { border-color: #f5e3be !important; }
    body.light .bg-purple-900\/50 { background: #f3ecfd !important; }
    body.light .text-purple-300   { color: #6b2fb5 !important; }
    body.light .border-purple-700 { border-color: #ddc9f7 !important; }
    body.light .bg-green-900\/50  { background: #e6f5ec !important; }
    body.light .text-green-300    { color: #1c6b40 !important; }
    body.light .border-green-700  { border-color: #b9e0c9 !important; }
    body.light .bg-red-900\/50    { background: #fdecec !important; }
    body.light .text-red-400      { color: #b42318 !important; }
    body.light .border-red-800    { border-color: #f6c9c6 !important; }
    /* Stat card figures */
    body.light .text-blue-400   { color: #1a4fa0 !important; }
    body.light .text-purple-400 { color: #6b2fb5 !important; }
    body.light .text-green-400  { color: #1c6b40 !important; }

    /* Bulk-select controls in light mode */
    body.light .bg-gray-800.accent-yellow-500,
    body.light input[type=checkbox] { background: #ffffff !important; border-color: #d5dde7 !important; }
    body.light #bulk-bar { background: #ffffff !important; border-color: #d5dde7 !important; }

    #theme-toggle svg { display: block; }
    body.light #theme-toggle .icon-sun   { display: none; }
    body:not(.light) #theme-toggle .icon-moon { display: none; }
  </style>
  <script>
    // Apply the saved theme before paint, so the page never flashes dark.
    (function () {
      try {
        if (localStorage.getItem('heliora_admin_theme') === 'light') {
          document.documentElement.classList.add('pre-light');
        }
      } catch (e) {}
    })();
  </script>
</head>
<body class="bg-gray-950 text-white min-h-screen">

  <!-- Navbar -->
  <header class="bg-gray-900 border-b border-gray-800 sticky top-0 z-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-16">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-yellow-500 flex items-center justify-center text-gray-900 font-bold text-sm font-serif">H</div>
        <span class="font-semibold">Heliora Admin</span>
        <span class="text-gray-600 text-sm hidden sm:inline">/ Leads Dashboard</span>
      </div>
      <div class="flex items-center gap-4">
        <a href="?export=csv" class="hidden sm:inline-flex items-center gap-2 px-4 py-2 bg-yellow-500/10 border border-yellow-500/30 text-yellow-400 rounded-lg text-sm hover:bg-yellow-500/20 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          Export CSV
        </a>
        <button id="theme-toggle" type="button" aria-label="Toggle light or dark theme" title="Toggle light / dark"
                class="text-gray-400 hover:text-white transition-colors p-1.5 rounded-lg">
          <svg class="icon-sun w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="4"/>
            <path stroke-linecap="round" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
          </svg>
          <svg class="icon-moon w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z"/>
          </svg>
        </button>
        <a href="/?logout=1" onclick="return confirm('Sign out?')" class="text-gray-400 hover:text-white text-sm transition-colors">Sign out</a>
        <a href="?logout=1" onclick="return confirm('Sign out?')" class="text-gray-500 hover:text-white transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        </a>
      </div>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8">

    <!-- Stats row -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
      <?php
      $statCards = [
        ['label'=>'Total Leads',   'value'=>$stats['total'],     'color'=>'text-white'],
        ['label'=>'New',           'value'=>$stats['new_count'], 'color'=>'text-blue-400'],
        ['label'=>'Contacted',     'value'=>$stats['contacted'], 'color'=>'text-yellow-400'],
        ['label'=>'Qualified',     'value'=>$stats['qualified'], 'color'=>'text-purple-400'],
        ['label'=>'Converted',     'value'=>$stats['converted'], 'color'=>'text-green-400'],
        ['label'=>'Today',         'value'=>$stats['today'],     'color'=>'text-yellow-300'],
      ];
      foreach ($statCards as $sc): ?>
      <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
        <div class="<?= $sc['color'] ?> text-2xl font-bold"><?= $sc['value'] ?></div>
        <div class="text-gray-500 text-xs mt-1"><?= $sc['label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Filters & search -->
    <div class="flex flex-col sm:flex-row gap-4 mb-6">
      <form method="GET" class="flex gap-2 flex-1">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, email, company..."
          class="flex-1 bg-gray-900 border border-gray-700 rounded-lg px-4 py-2.5 text-white text-sm outline-none focus:border-yellow-500 min-w-0"/>
        <?php if ($filter !== 'all'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>"/><?php endif; ?>
        <button type="submit" class="px-4 py-2.5 bg-yellow-500 text-gray-900 font-semibold text-sm rounded-lg hover:bg-yellow-400 transition-colors whitespace-nowrap">Search</button>
      </form>

      <div class="flex gap-2 flex-wrap">
        <?php
        $statuses = ['all'=>'All','new'=>'New','contacted'=>'Contacted','qualified'=>'Qualified','converted'=>'Converted','lost'=>'Lost'];
        foreach ($statuses as $s => $l):
          $active = $filter === $s;
          $cls    = $active ? 'bg-yellow-500 text-gray-900 border-yellow-500' : 'bg-gray-900 text-gray-400 border-gray-700 hover:border-gray-500';
        ?>
        <a href="?status=<?= $s ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-3 py-2 text-xs font-medium border rounded-lg transition-colors <?= $cls ?>"><?= $l ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Flash message -->
    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="mb-4 px-4 py-3 rounded-lg bg-yellow-500/10 border border-yellow-500/30 text-yellow-400 text-sm">
        <?= htmlspecialchars($_SESSION['flash']) ?>
      </div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
    <?php if ($deleteNotice): ?>
      <div class="mb-4 px-4 py-3 rounded-lg bg-red-900/40 border border-red-800 text-red-400 text-sm">
        <?= htmlspecialchars($deleteNotice) ?>
      </div>
    <?php endif; ?>

    <!-- Table -->
    <form method="POST" id="bulk-form">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"/>

    <!-- Bulk action bar (appears once rows are selected) -->
    <div id="bulk-bar" class="hidden mb-4 px-4 py-3 rounded-lg bg-gray-900 border border-gray-700 flex items-center justify-between gap-4">
      <span class="text-sm text-gray-400"><span id="bulk-count">0</span> selected</span>
      <div class="flex items-center gap-2">
        <button type="button" id="bulk-clear" class="px-3 py-2 text-xs font-medium rounded-lg border border-gray-700 text-gray-400 hover:border-gray-500 transition-colors">Clear</button>
        <button type="submit" name="delete_leads" value="1" id="bulk-delete"
                class="px-4 py-2 text-xs font-semibold rounded-lg bg-red-600 hover:bg-red-500 text-white transition-colors">
          Delete selected
        </button>
      </div>
    </div>

    <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden">
      <div class="overflow-x-auto scrollbar-thin">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-800 text-left">
              <th class="px-4 py-3 w-10">
                <input type="checkbox" id="check-all" aria-label="Select all leads on this page"
                       class="w-4 h-4 rounded border-gray-600 bg-gray-800 accent-yellow-500 cursor-pointer"/>
              </th>
              <th class="px-4 py-3 text-gray-500 text-xs font-medium uppercase tracking-wider">#</th>
              <th class="px-4 py-3 text-gray-500 text-xs font-medium uppercase tracking-wider">Lead</th>
              <th class="px-4 py-3 text-gray-500 text-xs font-medium uppercase tracking-wider hidden md:table-cell">Service</th>
              <th class="px-4 py-3 text-gray-500 text-xs font-medium uppercase tracking-wider hidden lg:table-cell">Budget</th>
              <th class="px-4 py-3 text-gray-500 text-xs font-medium uppercase tracking-wider hidden xl:table-cell">Source</th>
              <th class="px-4 py-3 text-gray-500 text-xs font-medium uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-gray-500 text-xs font-medium uppercase tracking-wider hidden sm:table-cell">Date</th>
              <th class="px-4 py-3 text-gray-500 text-xs font-medium uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-800">
            <?php if (empty($leads)): ?>
            <tr><td colspan="9" class="px-6 py-12 text-center text-gray-500">No leads found.</td></tr>
            <?php else: foreach ($leads as $l): ?>
            <tr class="hover:bg-gray-800/50 transition-colors" id="row-<?= $l['id'] ?>">
              <td class="px-4 py-4">
                <input type="checkbox" name="ids[]" value="<?= $l['id'] ?>" form="bulk-form"
                       aria-label="Select lead <?= $l['id'] ?>"
                       class="row-check w-4 h-4 rounded border-gray-600 bg-gray-800 accent-yellow-500 cursor-pointer"/>
              </td>
              <td class="px-4 py-4 text-gray-500 text-sm"><?= $l['id'] ?></td>
              <td class="px-4 py-4">
                <div class="font-medium text-sm"><?= htmlspecialchars($l['first_name'] . ' ' . $l['last_name']) ?></div>
                <a href="mailto:<?= htmlspecialchars($l['email']) ?>" class="text-yellow-400 text-xs hover:text-yellow-300"><?= htmlspecialchars($l['email']) ?></a>
                <?php if ($l['company']): ?><div class="text-gray-500 text-xs mt-0.5"><?= htmlspecialchars($l['company']) ?></div><?php endif; ?>
              </td>
              <td class="px-4 py-4 hidden md:table-cell">
                <span class="text-xs text-gray-300"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($l['service']))) ?></span>
              </td>
              <td class="px-4 py-4 hidden lg:table-cell text-gray-400 text-xs"><?= htmlspecialchars($l['project_budget'] ?: '—') ?></td>
              <td class="px-4 py-4 hidden xl:table-cell text-gray-500 text-xs"><?= htmlspecialchars($l['utm_source'] ?: 'Direct') ?></td>
              <td class="px-4 py-4"><?= statusBadge($l['status']) ?></td>
              <td class="px-4 py-4 hidden sm:table-cell text-gray-500 text-xs whitespace-nowrap"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
              <td class="px-4 py-4">
                <button onclick="openModal(<?= htmlspecialchars(json_encode($l)) ?>)" class="text-gray-400 hover:text-white transition-colors p-1" title="View">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
                <button type="button" class="row-delete text-gray-400 hover:text-red-400 transition-colors p-1"
                        data-id="<?= $l['id'] ?>"
                        data-name="<?= htmlspecialchars($l['first_name'] . ' ' . $l['last_name'], ENT_QUOTES) ?>"
                        title="Delete">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
      <div class="px-4 py-4 border-t border-gray-800 flex items-center justify-between">
        <span class="text-gray-500 text-sm"><?= $total ?> leads total</span>
        <div class="flex gap-2">
          <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a href="?p=<?= $i ?>&status=<?= urlencode($filter) ?>&q=<?= urlencode($search) ?>"
             class="w-8 h-8 flex items-center justify-center rounded text-sm transition-colors <?= $i === $page ? 'bg-yellow-500 text-gray-900 font-bold' : 'text-gray-400 hover:bg-gray-800' ?>">
            <?= $i ?>
          </a>
          <?php endfor; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    </form>

  </main>

  <!-- Lead detail modal -->
  <div id="modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-gray-900 border border-gray-700 rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between p-6 border-b border-gray-800">
        <h3 class="font-semibold text-lg" id="modal-title">Lead Details</h3>
        <button onclick="closeModal()" class="text-gray-500 hover:text-white transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div id="modal-body" class="p-6"></div>
    </div>
  </div>

  <script>
  function openModal(lead) {
    const statusOptions = ['new','contacted','qualified','converted','lost'];
    const statusColors = {new:'text-blue-400',contacted:'text-yellow-400',qualified:'text-purple-400',converted:'text-green-400',lost:'text-red-400'};
    document.getElementById('modal-title').textContent = lead.first_name + ' ' + lead.last_name;
    document.getElementById('modal-body').innerHTML = `
      <div class="grid sm:grid-cols-2 gap-4 mb-6">
        <div><div class="text-gray-500 text-xs mb-1">Email</div><a href="mailto:${lead.email}" class="text-yellow-400 text-sm">${lead.email}</a></div>
        <div><div class="text-gray-500 text-xs mb-1">Phone</div><div class="text-sm">${lead.phone || '—'}</div></div>
        <div><div class="text-gray-500 text-xs mb-1">Company</div><div class="text-sm">${lead.company || '—'}</div></div>
        <div><div class="text-gray-500 text-xs mb-1">Service</div><div class="text-sm text-yellow-300">${lead.service.replace(/_/g,' ')}</div></div>
        <div><div class="text-gray-500 text-xs mb-1">Budget</div><div class="text-sm">${lead.project_budget || '—'}</div></div>
        <div><div class="text-gray-500 text-xs mb-1">Submitted</div><div class="text-sm">${lead.created_at}</div></div>
        <div><div class="text-gray-500 text-xs mb-1">Source</div><div class="text-sm">${[lead.utm_source,lead.utm_medium,lead.utm_campaign].filter(Boolean).join(' / ') || 'Direct'}</div></div>
        <div><div class="text-gray-500 text-xs mb-1">Zoho ID</div><div class="text-sm">${lead.zoho_lead_id || 'Not synced'}</div></div>
      </div>
      <div class="mb-6">
        <div class="text-gray-500 text-xs mb-2">Message</div>
        <div class="bg-gray-800 rounded-xl p-4 text-sm text-gray-300 leading-relaxed whitespace-pre-wrap">${lead.message}</div>
      </div>
      <form method="POST" class="flex items-center gap-3">
        <input type="hidden" name="lead_id" value="${lead.id}"/>
        <select name="status" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white outline-none focus:border-yellow-500">
          ${statusOptions.map(s => `<option value="${s}" ${s===lead.status?'selected':''}>${s.charAt(0).toUpperCase()+s.slice(1)}</option>`).join('')}
        </select>
        <button type="submit" name="update_status" class="px-5 py-2.5 bg-yellow-500 text-gray-900 font-semibold text-sm rounded-lg hover:bg-yellow-400 transition-colors">Update Status</button>
        <a href="mailto:${lead.email}" class="px-5 py-2.5 bg-gray-800 border border-gray-700 text-white font-semibold text-sm rounded-lg hover:bg-gray-700 transition-colors">Reply</a>
      </form>`;
    document.getElementById('modal').classList.replace('hidden','flex');
  }
  function closeModal() {
    document.getElementById('modal').classList.replace('flex','hidden');
  }
  document.getElementById('modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
  });
  </script>
<script>
/* ── Light / dark theme toggle ─────────────────────────────── */
(function () {
  var KEY  = 'heliora_admin_theme';
  var body = document.body;
  var btn  = document.getElementById('theme-toggle');

  function apply(theme) {
    body.classList.toggle('light', theme === 'light');
    document.documentElement.classList.remove('pre-light');
  }

  var saved = 'dark';
  try { saved = localStorage.getItem(KEY) || 'dark'; } catch (e) {}
  apply(saved);

  if (btn) {
    btn.addEventListener('click', function () {
      var next = body.classList.contains('light') ? 'dark' : 'light';
      apply(next);
      try { localStorage.setItem(KEY, next); } catch (e) {}
    });
  }
}());
</script>

<script>
/* ── Lead selection + delete ───────────────────────────────── */
(function () {
  var form     = document.getElementById('bulk-form');
  if (!form) return;
  var checkAll = document.getElementById('check-all');
  var bar      = document.getElementById('bulk-bar');
  var countEl  = document.getElementById('bulk-count');
  var clearBtn = document.getElementById('bulk-clear');
  var delBtn   = document.getElementById('bulk-delete');

  function rows() { return Array.prototype.slice.call(document.querySelectorAll('.row-check')); }
  function selected() { return rows().filter(function (c) { return c.checked; }); }

  function sync() {
    var n = selected().length;
    countEl.textContent = n;
    bar.classList.toggle('hidden', n === 0);
    bar.classList.toggle('flex', n > 0);
    if (checkAll) {
      checkAll.checked = n > 0 && n === rows().length;
      checkAll.indeterminate = n > 0 && n < rows().length;
    }
  }

  if (checkAll) {
    checkAll.addEventListener('change', function () {
      rows().forEach(function (c) { c.checked = checkAll.checked; });
      sync();
    });
  }
  rows().forEach(function (c) { c.addEventListener('change', sync); });

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      rows().forEach(function (c) { c.checked = false; });
      if (checkAll) checkAll.indeterminate = false;
      sync();
    });
  }

  if (delBtn) {
    delBtn.addEventListener('click', function (e) {
      var n = selected().length;
      if (!n) { e.preventDefault(); return; }
      var msg = n === 1
        ? 'Delete this lead permanently? This cannot be undone.'
        : 'Delete these ' + n + ' leads permanently? This cannot be undone.';
      if (!confirm(msg)) e.preventDefault();
    });
  }

  // Single-row delete: tick just that row and submit
  document.querySelectorAll('.row-delete').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id   = btn.getAttribute('data-id');
      var name = btn.getAttribute('data-name') || 'this lead';
      if (!confirm('Delete ' + name + ' permanently? This cannot be undone.')) return;
      rows().forEach(function (c) { c.checked = (c.value === id); });
      var flag = document.createElement('input');
      flag.type = 'hidden'; flag.name = 'delete_leads'; flag.value = '1';
      form.appendChild(flag);
      form.submit();
    });
  });

  sync();
}());
</script>
</body>
</html>
