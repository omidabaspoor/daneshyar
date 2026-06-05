<?php
$adminPage = 'users';
$pageTitle  = 'مدیریت کاربران';
include __DIR__ . '/_header.php';

$msg     = '';
$msgType = 'success';

/* ===== اقدامات ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0) {
        switch ($action) {
            case 'ban':
                $reason = trim($_POST['ban_reason'] ?? '');
                ban_user($id, $reason);
                $msg = 'کاربر مسدود شد.';
                break;
            case 'unban':
                unban_user($id);
                $msg = 'کاربر رفع مسدودی شد.';
                break;
            case 'delete':
                try {
                    db()->prepare("DELETE FROM chat_history WHERE user_id=?")->execute([$id]);
                    db()->prepare("DELETE FROM chats WHERE user_id=?")->execute([$id]);
                    db()->prepare("DELETE FROM transactions WHERE user_id=?")->execute([$id]);
                    db()->prepare("DELETE FROM card_receipts WHERE user_id=?")->execute([$id]);
                    db()->prepare("DELETE FROM user_sessions WHERE user_id=?")->execute([$id]);
                    db()->prepare("DELETE FROM users WHERE id=? AND role!='admin'")->execute([$id]);
                    $msg = 'کاربر و تمام داده‌هایش حذف شد.';
                } catch (Throwable $e) {
                    $msg = 'خطا در حذف: ' . $e->getMessage(); $msgType = 'error';
                }
                break;
            case 'activate':
                if (!empty($_POST['plan'])) {
                    activate_subscription($id, $_POST['plan'], null, 'manual', 'admin_grant');
                    $msg = 'اشتراک دستی فعال شد.';
                }
                break;
            case 'cancel_sub':
                db()->prepare("UPDATE users SET subscription_type='none', subscription_start=NULL, subscription_end=NOW() WHERE id=?")
                    ->execute([$id]);
                $msg = 'اشتراک کاربر قطع شد.';
                break;
            case 'reset_sub':
                db()->prepare("UPDATE users SET subscription_type='none', subscription_start=NULL, subscription_end=NULL, messages_used_total=0, messages_used_today=0, free_used_today=0 WHERE id=?")
                    ->execute([$id]);
                $msg = 'اشتراک و مصرف کاربر ریست شد.';
                break;
        }
    }
}

/* ===== جستجو و فیلتر ===== */
$q          = trim($_GET['q'] ?? '');
$filterSub  = $_GET['sub'] ?? '';
$filterBan  = $_GET['ban'] ?? '';
$page       = max(1, (int)($_GET['p'] ?? 1));
$perPage    = 30;
$offset     = ($page - 1) * $perPage;

$wheres = ["u.role != 'admin'"];
$params = [];

if ($q !== '') {
    $wheres[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.mobile LIKE ? OR u.school LIKE ?)";
    $w = "%$q%"; $params = array_merge($params, [$w,$w,$w,$w]);
}
if ($filterSub === 'active') {
    $wheres[] = "u.subscription_end > NOW()";
} elseif ($filterSub === 'none') {
    $wheres[] = "(u.subscription_type='none' OR u.subscription_end IS NULL OR u.subscription_end < NOW())";
}
if ($filterBan === '1') {
    $wheres[] = "u.status='banned'";
} elseif ($filterBan === '0') {
    $wheres[] = "(u.status='active' OR u.status IS NULL)";
}

$whereStr = "WHERE " . implode(' AND ', $wheres);

$cntStmt = db()->prepare("SELECT COUNT(*) FROM users u $whereStr");
$cntStmt->execute($params);
$totalCount = (int)$cntStmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = db()->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM chats c WHERE c.user_id=u.id) AS chats_count,
           (SELECT COUNT(*) FROM chat_history h WHERE h.user_id=u.id) AS msg_count
    FROM users u
    $whereStr
    ORDER BY u.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$stats = [
    'total'   => (int)db()->query("SELECT COUNT(*) FROM users WHERE role!='admin'")->fetchColumn(),
    'active'  => (int)db()->query("SELECT COUNT(*) FROM users WHERE role!='admin' AND subscription_end > NOW()")->fetchColumn(),
    'banned'  => (int)db()->query("SELECT COUNT(*) FROM users WHERE status='banned'")->fetchColumn(),
    'today'   => (int)db()->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
];

$plans = db()->query("SELECT plan_code, title FROM pricing ORDER BY price")->fetchAll();
?>

<div class="admin-page-header">
  <h2><?= icon('users') ?> مدیریت کاربران</h2>
</div>

<!-- ═══ آمار سریع ═══ -->
<div class="stat-grid">
  <div class="stat-card glass">
    <div class="s-icon"><?= icon('users') ?></div>
    <div><div class="l">کل کاربران</div><div class="v"><?= num_fa($stats['total']) ?></div></div>
  </div>
  <div class="stat-card glass">
    <div class="s-icon" style="background:rgba(56,217,169,.12);border-color:rgba(56,217,169,.2);color:var(--success)"><?= icon('crown') ?></div>
    <div><div class="l">اشتراک فعال</div><div class="v text-success"><?= num_fa($stats['active']) ?></div></div>
  </div>
  <div class="stat-card glass">
    <div class="s-icon" style="background:rgba(255,84,112,.12);border-color:rgba(255,84,112,.2);color:var(--danger)"><?= icon('warning') ?></div>
    <div><div class="l">مسدود</div><div class="v text-danger"><?= num_fa($stats['banned']) ?></div></div>
  </div>
  <div class="stat-card glass">
    <div class="s-icon" style="background:rgba(77,171,247,.12);border-color:rgba(77,171,247,.2);color:var(--info)"><?= icon('sparkle') ?></div>
    <div><div class="l">ثبت‌نام امروز</div><div class="v" style="color:var(--info)"><?= num_fa($stats['today']) ?></div></div>
  </div>
</div>

<?php if ($msg): ?>
  <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><?= icon($msgType === 'error' ? 'warning' : 'check') ?> <?= e($msg) ?></div>
<?php endif; ?>

<!-- ═══ جستجو و فیلتر ═══ -->
<div class="admin-filter-bar">
  <form method="get">
    <div class="filter-input">
      <?= icon('search') ?>
      <input class="input" name="q" placeholder="نام، موبایل، مدرسه..." value="<?= e($q) ?>">
    </div>
    <select class="select" name="sub" style="min-width:140px; max-width:200px">
      <option value="" <?= !$filterSub?'selected':'' ?>>همه اشتراک‌ها</option>
      <option value="active" <?= $filterSub==='active'?'selected':'' ?>>اشتراک فعال</option>
      <option value="none" <?= $filterSub==='none'?'selected':'' ?>>بدون اشتراک</option>
    </select>
    <select class="select" name="ban" style="min-width:120px; max-width:160px">
      <option value="" <?= $filterBan===''?'selected':'' ?>>همه وضعیت‌ها</option>
      <option value="0" <?= $filterBan==='0'?'selected':'' ?>>فعال</option>
      <option value="1" <?= $filterBan==='1'?'selected':'' ?>>مسدود</option>
    </select>
    <button class="btn btn-primary" type="submit"><?= icon('search') ?> جستجو</button>
    <?php if ($q || $filterSub || $filterBan): ?>
      <a href="?" class="btn btn-ghost">پاک کردن</a>
    <?php endif; ?>
  </form>
</div>

<!-- ═══ جدول کاربران ═══ -->
<div class="admin-card glass">
  <div class="admin-card-body" style="padding:0">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>#</th>
            <th>کاربر</th>
            <th>موبایل</th>
            <th>پایه / رشته</th>
            <th>اشتراک</th>
            <th>فعالیت</th>
            <th>وضعیت</th>
            <th>عملیات</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="8" class="admin-empty">کاربری یافت نشد.</td></tr>
        <?php endif; ?>
        <?php foreach ($users as $u):
          $isBanned = ($u['status'] ?? 'active') === 'banned';
          $sub = subscription_status($u);
        ?>
          <tr style="<?= $isBanned ? 'opacity:.65' : '' ?>">

            <td style="color:var(--text-muted); font-size:12px"><?= num_fa($u['id']) ?></td>

            <td>
              <a href="<?= BASE_URL ?>/admin/user.php?id=<?= $u['id'] ?>" style="font-weight:700; color:var(--orange); text-decoration:none">
                <?= e($u['first_name'] . ' ' . $u['last_name']) ?>
              </a>
              <?php if ($u['school']): ?>
                <br><small class="text-dim"><?= e($u['school']) ?></small>
              <?php endif; ?>
            </td>

            <td dir="ltr" class="font-mono text-sm"><?= e($u['mobile']) ?></td>

            <td>
              <span class="text-sm">پایه <?= num_fa($u['grade']) ?></span>
              <br><small class="text-dim"><?= e(major_label($u['major'] ?? 'math')) ?></small>
            </td>

            <td>
              <?php if ($sub['active']): ?>
                <span class="status-badge status-active">✅ <?= e($sub['plan']['title'] ?? '') ?></span>
                <br><small class="text-dim"><?= time_left($u['subscription_end']) ?></small>
              <?php else: ?>
                <span class="text-muted text-sm">بدون اشتراک</span>
              <?php endif; ?>
            </td>

            <td class="text-sm">
              <div><?= icon('chat') ?> <?= num_fa($u['chats_count'] ?? 0) ?> چت</div>
              <div class="text-dim"><?= icon('send') ?> <?= num_fa($u['msg_count'] ?? 0) ?> پیام</div>
            </td>

            <td>
              <?php if ($isBanned): ?>
                <span class="status-badge status-banned">🚫 مسدود</span>
              <?php else: ?>
                <span class="status-badge status-active">✓ فعال</span>
              <?php endif; ?>
            </td>

            <td>
              <div class="admin-actions">
                <a href="<?= BASE_URL ?>/admin/user.php?id=<?= $u['id'] ?>" class="btn btn-ghost btn-sm" title="جزئیات"><?= icon('eye') ?></a>

                <?php if ($isBanned): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="action" value="unban">
                    <button class="btn btn-sm" style="background:rgba(56,217,169,.12); color:#38d9a9; border:1px solid rgba(56,217,169,.25)" title="رفع مسدودی" onclick="return confirm('رفع مسدودی شود؟')"><?= icon('check') ?></button>
                  </form>
                <?php else: ?>
                  <button class="btn btn-danger btn-sm" title="مسدود کردن" onclick="showBanForm(<?= $u['id'] ?>)"><?= icon('lock') ?></button>
                <?php endif; ?>

                <button type="button" class="btn btn-ghost btn-sm" title="فعال‌سازی اشتراک" onclick="showActivateForm(<?= $u['id'] ?>)"><?= icon('crown') ?></button>

                <form method="post" style="display:inline">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <button class="btn btn-sm" style="background:rgba(255,84,112,.08); color:var(--danger); border:1px solid rgba(255,84,112,.2)" title="حذف" onclick="return confirm('کاربر و تمام داده‌هایش حذف شود؟ این عمل برگشت‌پذیر نیست!')"><?= icon('trash') ?></button>
                </form>
              </div>

              <!-- فرم مسدود کردن -->
              <div id="banForm<?= $u['id'] ?>" style="display:none; margin-top:8px; padding:10px; background:rgba(255,84,112,.04); border:1px solid rgba(255,84,112,.15); border-radius:10px">
                <form method="post" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <input type="hidden" name="action" value="ban">
                  <input class="input" name="ban_reason" placeholder="دلیل مسدودی (اختیاری)" style="font-size:12px; padding:8px 10px; flex:1; min-width:150px">
                  <button class="btn btn-danger btn-sm" onclick="return confirm('مسدود شود؟')">تایید مسدودی</button>
                </form>
              </div>

              <!-- فرم مدیریت اشتراک -->
              <div id="activateForm<?= $u['id'] ?>" style="display:none; margin-top:8px; padding:12px; background:rgba(255,255,255,.03); border:1px solid var(--border); border-radius:10px">
                <div class="text-sm text-dim" style="margin-bottom:8px; font-weight:700">مدیریت اشتراک</div>
                <form method="post" style="display:flex; gap:8px; margin-bottom:8px; flex-wrap:wrap; align-items:flex-end">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <input type="hidden" name="action" value="activate">
                  <select name="plan" class="select" style="font-size:12px; padding:7px 10px; flex:1; min-width:100px">
                    <?php foreach ($plans as $pl): ?>
                      <option value="<?= e($pl['plan_code']) ?>"><?= e($pl['title']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-primary btn-sm" onclick="return confirm('اشتراک فعال شود؟')"><?= icon('check') ?> فعال</button>
                </form>
                <?php if ($sub['active']): ?>
                <div style="display:flex; gap:6px; flex-wrap:wrap">
                  <form method="post" style="display:inline">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="action" value="cancel_sub">
                    <button class="btn btn-sm" style="background:rgba(255,84,112,.08); color:#ff5470; border:1px solid rgba(255,84,112,.2); font-size:11px" onclick="return confirm('اشتراک قطع شود؟')"><?= icon('close') ?> قطع اشتراک</button>
                  </form>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="action" value="reset_sub">
                    <button class="btn btn-sm" style="background:rgba(255,184,107,.08); color:#ffb86b; border:1px solid rgba(255,184,107,.2); font-size:11px" onclick="return confirm('اشتراک و مصرف ریست شود؟')"><?= icon('refresh') ?> ریست کامل</button>
                  </form>
                </div>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══ صفحه‌بندی ═══ -->
<?php if ($totalPages > 1): ?>
<div class="admin-pagination">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="?q=<?= e($q) ?>&sub=<?= e($filterSub) ?>&ban=<?= e($filterBan) ?>&p=<?= $p ?>"
       class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>"><?= num_fa($p) ?></a>
  <?php endfor; ?>
  <div class="page-info"><?= num_fa($totalCount) ?> کاربر · صفحه <?= num_fa($page) ?> از <?= num_fa($totalPages) ?></div>
</div>
<?php endif; ?>

<script>
function showBanForm(id) {
  var el = document.getElementById('banForm' + id);
  if (el) { el.style.display = el.style.display === 'none' ? 'block' : 'none'; }
  document.querySelectorAll('[id^="activateForm"]').forEach(function(e){ e.style.display='none'; });
}
function showActivateForm(id) {
  var el = document.getElementById('activateForm' + id);
  if (el) { el.style.display = el.style.display === 'none' ? 'block' : 'none'; }
  document.querySelectorAll('[id^="banForm"]').forEach(function(e){ e.style.display='none'; });
}
</script>

<?php include __DIR__ . '/_footer.php'; ?>
