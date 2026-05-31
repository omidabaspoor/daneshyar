<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_login();
$user = current_user();
$page = 'chat';
$pageTitle = 'چت';
$extraCss = ['chat.css'];
$bodyClass = 'has-chat';
$hideSiteChrome = true;
$fullWidthMain = true;

// کتاب‌های قابل نمایش بر اساس پایه و رشته/شاخه کاربر
$allBooks = get_accessible_books_for_user($user);

// همه چت‌های کاربر
$chats = get_user_chats($user['id']);

// چت فعال (از GET یا اولین چت)
$activeChatId = (int)($_GET['c'] ?? 0);
$activeChat = null;
$messages = [];
if ($activeChatId > 0) {
    $activeChat = get_chat($activeChatId, $user['id']);
    if ($activeChat) {
        $messages = chat_messages($activeChatId);
    } else {
        $activeChatId = 0;
    }
}

$activeBookId = '';
if ($activeChat && !empty($activeChat['book_id']) && get_book_for_user((int)$activeChat['book_id'], $user)) {
    $activeBookId = (string)$activeChat['book_id'];
}

$sub = subscription_status($user);
$plan = $sub['plan'] ?? null;
$check = can_send_message($user);
$notices = user_notifications($user);

// تعیین وضعیت نمایش
$subState = 'free'; // free | active | expired | limit_daily | limit_total | not_started
if ($sub['active']) {
    $subState = 'active';
} elseif (!empty($sub['has_plan'])) {
    if (!empty($sub['expired']))                 $subState = 'expired';
    elseif (($sub['limit_hit'] ?? '') === 'daily') $subState = 'limit_daily';
    elseif (($sub['limit_hit'] ?? '') === 'total') $subState = 'limit_total';
    elseif (!empty($sub['starts_in']))           $subState = 'not_started';
}

include __DIR__ . '/includes/header.php';
?>
<div class="chat-shell">

  <!-- ============ Sidebar ============ -->
  <aside class="chat-sidebar" id="chatSidebar">
    <div class="sidebar-head">
      <a href="<?= BASE_URL ?>/chat.php" class="sidebar-brand">
        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="" width="34" height="34">
        <span>دانش‌یار</span>
      </a>
      <button class="icon-btn-mini" id="sidebarClose" aria-label="بستن"><?= icon('close') ?></button>
    </div>

    <!-- Subscription card -->
    <div class="sub-card" data-state="<?= e($subState) ?>">
      <div class="sub-card-head">
        <?php
          $iconName = 'sparkle';
          if ($subState === 'active') $iconName = 'crown';
          elseif (in_array($subState, ['expired','limit_daily','limit_total'], true)) $iconName = 'warning';
          elseif ($subState === 'not_started') $iconName = 'clock';
        ?>
        <div class="sub-icon"><?= icon($iconName) ?></div>
        <div class="sub-info">
          <?php if ($subState === 'active'): ?>
            <div class="sub-title"><?= e($plan['title']) ?></div>
            <div class="sub-sub"><?= time_left($user['subscription_end']) ?></div>
          <?php elseif ($subState === 'expired'): ?>
            <div class="sub-title">اشتراک منقضی شد</div>
            <div class="sub-sub"><?= e($plan['title'] ?? '') ?> – لطفاً تمدید کن</div>
          <?php elseif ($subState === 'limit_daily'): ?>
            <div class="sub-title">سقف روزانه پر شد</div>
            <div class="sub-sub">فردا ساعت ۰۰:۰۰ ریست می‌شود</div>
          <?php elseif ($subState === 'limit_total'): ?>
            <div class="sub-title">سقف کل پلن پر شد</div>
            <div class="sub-sub"><?= e($plan['title'] ?? '') ?> – خرید پلن جدید</div>
          <?php elseif ($subState === 'not_started'): ?>
            <div class="sub-title">اشتراک هنوز شروع نشده</div>
            <div class="sub-sub"><?= e($plan['title'] ?? '') ?></div>
          <?php else: ?>
            <div class="sub-title">پلن رایگان</div>
            <div class="sub-sub">فعلاً رایگان</div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($subState === 'active' && (int)($plan['daily_limit'] ?? 0) > 0):
        $used = (int)$user['messages_used_today']; $lim = (int)$plan['daily_limit'];
        $pct  = $lim > 0 ? min(100, $used / $lim * 100) : 0; ?>
        <div class="sub-progress"><div style="width:<?= $pct ?>%"></div></div>
        <div class="sub-count">امروز: <b><?= num_fa($used) ?>/<?= num_fa($lim) ?></b></div>

      <?php elseif ($subState === 'active' && (int)($plan['total_limit'] ?? 0) > 0):
        $used = (int)$user['messages_used_total']; $lim = (int)$plan['total_limit'];
        $pct  = $lim > 0 ? min(100, $used / $lim * 100) : 0; ?>
        <div class="sub-progress"><div style="width:<?= $pct ?>%"></div></div>
        <div class="sub-count">پیام کل: <b><?= num_fa($used) ?>/<?= num_fa($lim) ?></b></div>

      <?php elseif (in_array($subState, ['expired','limit_daily','limit_total'], true)): ?>
        <div class="sub-progress"><div style="width:100%; background:#ef4444"></div></div>
        <div class="sub-count sub-count-warn">
          <?php if ($subState === 'limit_daily'): ?>
            <b><?= num_fa((int)$plan['daily_limit']) ?>/<?= num_fa((int)$plan['daily_limit']) ?></b> پیام امروز
          <?php elseif ($subState === 'limit_total'): ?>
            <b><?= num_fa((int)$plan['total_limit']) ?>/<?= num_fa((int)$plan['total_limit']) ?></b> پیام کل
          <?php else: ?>
            <b>منقضی شده</b>
          <?php endif; ?>
        </div>

      <?php else:
        $used = (int)$user['free_used_today']; $lim = FREE_DAILY_LIMIT;
        $left = max(0, $lim - $used);
        $pct  = $lim > 0 ? min(100, $used / $lim * 100) : 0; ?>
        <div class="sub-progress"><div style="width:<?= $pct ?>%"></div></div>
        <div class="sub-count">
          <?php if ($left > 0): ?>
            <b><?= num_fa($left) ?> سوال رایگان</b> امروز
          <?php else: ?>
            <b>سوال رایگان امروز تمام شد</b>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($subState !== 'active'): ?>
        <a href="<?= BASE_URL ?>/pricing.php" class="btn-upgrade">
          <?= icon('rocket') ?>
          <span><?= $subState === 'expired' ? 'تمدید اشتراک' : ($subState === 'limit_total' ? 'خرید پلن جدید' : 'دسترسی نامحدود') ?></span>
        </a>
      <?php endif; ?>
    </div>

    <?php if (!empty($notices)): ?>
      <div class="sidebar-notices">
        <?php foreach (array_slice($notices, 0, 2) as $n): ?>
          <div class="dy-notice notice-<?= e($n['type']) ?>">
            <div class="dy-notice-ico"><?= icon($n['icon']) ?></div>
            <div class="dy-notice-body">
              <b><?= e($n['title']) ?></b>
              <small><?= e($n['text']) ?></small>
              <?php if (!empty($n['action'])): ?><a href="<?= e($n['action']) ?>"><?= e($n['action_text']) ?></a><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- New chat -->
    <button class="btn-new-chat" type="button" id="btnNewChat">
      <?= icon('new-chat') ?><span>گفت‌وگوی جدید</span>
    </button>

    
    

<!-- Chats list -->
    <div class="sidebar-section">
      <div class="sidebar-label">
        <?= icon('chat') ?>
        <span>چت‌های من</span>
        <?php if (count($chats) > 0): ?>
          <span class="chat-count"><?= num_fa(count($chats)) ?></span>
        <?php endif; ?>
      </div>
      <div class="chats-list" id="chatsList">
        <?php if (empty($chats)): ?>
          <div class="chats-empty">
            <?= icon('chat') ?>
            <p>هنوز چتی نداری!<br>یه گفت‌وگوی جدید شروع کن.</p>
          </div>
        <?php else:
          foreach ($chats as $c):
            $isActive = $activeChatId === (int)$c['id'];
            $title = $c['title'] ?: ($c['first_msg'] ? smart_chat_title($c['first_msg']) : 'گفت‌وگوی جدید');
          ?>
          <div class="chat-item <?= $isActive?'active':'' ?> <?= $c['is_pinned']?'pinned':'' ?>" data-id="<?= $c['id'] ?>">
            <a href="<?= BASE_URL ?>/chat.php?c=<?= $c['id'] ?>" class="chat-item-link">
              <?php if ($c['is_pinned']): ?>
                <span class="chat-pin-indicator" title="سنجاق‌شده"><?= icon('pin') ?></span>
              <?php else: ?>
                <span class="chat-bullet"></span>
              <?php endif; ?>
              <span class="chat-item-title"><?= e($title) ?></span>
            </a>
            <button class="chat-item-menu" type="button" aria-label="منو" data-id="<?= $c['id'] ?>" data-pinned="<?= $c['is_pinned']?'1':'0' ?>" data-title="<?= e($title) ?>">
              <?= icon('more') ?>
            </button>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Footer -->
    <div class="sidebar-footer">
    

      <a href="<?= BASE_URL ?>/profile.php" class="sidebar-link">
        <div class="user-avatar"><?= e(mb_substr($user['first_name'],0,1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= e($user['first_name']) ?></div>
          <div class="user-grade">پایه <?= num_fa($user['grade']) ?> · <?= e(major_label($user['major'] ?? 'math')) ?></div>
        </div>
      </a>
      <a href="<?= BASE_URL ?>/logout.php" class="icon-btn-mini" title="خروج"><?= icon('logout') ?></a>
    </div>
  </aside>

  <!-- ============ Main Chat ============ -->
  <section class="chat-main">
    <!-- Top bar -->
    <header class="chat-top">
      <button class="icon-btn-mini" id="sidebarOpen" aria-label="منو"><?= icon('menu') ?></button>

      <!-- Custom book picker (دکمه که سفارشی) -->
      <button class="book-picker-btn" type="button" id="bookPickerBtn" title="انتخاب کتاب درسی مرجع">
        <span class="book-picker-ico"><?= icon('book') ?></span>
        <span class="book-picker-meta">
          <small>انتخاب کتاب درسی</small>
          <b class="book-picker-text" id="bookPickerText"><?= $activeBookId !== '' ? 'در حال بارگذاری...' : 'حالت عمومی' ?></b>
        </span>
        <span class="book-picker-arr"><?= icon('arrow-down') ?></span>
      </button>
      <input type="hidden" id="bookSelect" value="<?= e($activeBookId) ?>">

      <a href="<?= BASE_URL ?>/contact.php" class="contact-btn-header" title="ارتباط با ما">
        <span class="contact-btn-meta">
          <small>درخواست کتاب اختصاصی</small>
          <b>ارتباط با ما</b>
        </span>
        <?= icon('mail') ?>
      </a>

      
      <button class="icon-btn-mini btn-new-mobile" id="btnNewChatMobile" title="جدید"><?= icon('plus') ?></button>
    </header>

    <!-- Drop zone -->
    <div class="drop-zone" id="dropZone">
      <div class="drop-content">
        <?= icon('upload', ['class'=>'ico-drop']) ?>
        <h3>عکس رو اینجا رها کن</h3>
        <p>اسکرین‌شات یا تصویر سوال</p>
      </div>
    </div>

    <!-- Messages -->
    <div class="chat-messages" id="chatMessages" data-chat-id="<?= $activeChatId ?>">
      <?php if (empty($messages)): ?>
        <div class="welcome-screen">
          <div class="welcome-logo">
            <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="" width="80" height="80">
          </div>
          <h2>سلام <span class="grad"><?= e($user['first_name']) ?></span> جان! 👋</h2>
          <p class="welcome-sub">چی یاد بگیریم امروز؟ اول اگر خواستی کتاب درسی‌ات را از بالای صفحه انتخاب کن، بعد سوالتو بنویس یا عکس بفرست.</p>

          <div class="chat-quick-guide">
            <div><b>1</b><span>کتاب مرجع را انتخاب کن</span></div>
            <div><b>2</b><span>سوال یا عکس را بفرست</span></div>
            <div><b>3</b><span>جواب آموزشی بگیر</span></div>
          </div>

          <div class="welcome-mini-hint">
            از کادر پایین سوالت را بنویس یا با دکمه پیوست، عکس تمرینت را ارسال کن.
          </div>
        </div>
      <?php else:
        foreach ($messages as $m):
          $hasImg = $m['role']==='user' && $m['attachment'] && preg_match('/^uploads\//', $m['attachment']);
        ?>
          <div class="message <?= $m['role'] ?>" data-msg-id="<?= $m['id'] ?>">
            <?php if ($m['role']==='assistant'): ?>
              <div class="avatar">
                <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="" width="36" height="36">
              </div>
            <?php else: ?>
              <div class="avatar"><span><?= e(mb_substr($user['first_name'],0,1)) ?></span></div>
            <?php endif; ?>
            <div class="message-wrap">
              <div class="bubble" data-raw="<?= e($m['content']) ?>">
                <?php if ($hasImg): ?>
                  <img src="<?= BASE_URL . '/' . e($m['attachment']) ?>" class="attached-img" alt="پیوست" onclick="window.openLightboxFromHistory && window.openLightboxFromHistory(this.src)">
                <?php endif; ?>
              </div>
              <div class="msg-actions">
                <button class="msg-action-btn" data-action="copy" title="کپی پیام"><?= icon('copy') ?></button>
              </div>
            </div>
          </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Composer -->
    <footer class="chat-composer">
      <div id="attachmentBox" class="attachment-container"></div>

      <div class="composer-row">
        <button class="comp-btn comp-btn-attach" type="button" title="پیوست" id="btnAttach">
          <?= icon('attach') ?>
        </button>
        <input type="file" id="fileInput" accept="image/*,image/heic,image/heif,application/pdf" hidden>

        <textarea
          class="composer-input"
          id="messageInput"
          rows="1"
          placeholder="سوالتو بنویس یا عکس بفرست..."
          autocomplete="off"
          autocapitalize="off"
          spellcheck="false"
        ></textarea>

        <button class="comp-btn send-btn" id="sendBtn" type="button" title="ارسال" disabled>
          <?= icon('send') ?>
        </button>
      </div>

      <div class="composer-hint">PDF فقط متنی (قابل کپی) باشد</div>
    </footer>
  </section>

  <!-- Overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
</div>

<!-- ====== Book Picker Modal ====== -->
<div class="modal-overlay" id="bookModal">
  <div class="modal-box book-modal">
    <div class="modal-head">
      <h3><?= icon('book') ?> انتخاب کتاب</h3>
      <button class="icon-btn-mini" data-close-modal><?= icon('close') ?></button>
    </div>
    <div class="modal-search">
      <?= icon('search') ?>
      <input type="text" id="bookSearchInput" placeholder="جستجوی کتاب یا درس...">
    </div>
    <div class="modal-body book-list" id="bookList">
      <button class="book-item book-item-general active" data-book-id="" data-book-name="حالت عمومی">
        <div class="book-item-ico"><?= icon('sparkle') ?></div>
        <div class="book-item-info">
          <b>حالت عمومی</b>
          <small>بدون محدودیت به کتاب خاص</small>
        </div>
        <div class="book-item-check"><?= icon('check') ?></div>
      </button>

      <?php
      $byGrade = [];
      foreach ($allBooks as $b) $byGrade[$b['grade']][] = $b;
      foreach ($byGrade as $grade => $books): ?>
        <div class="book-group-header">پایه <?= num_fa($grade) ?></div>
        <?php foreach ($books as $b): ?>
          <button class="book-item" data-book-id="<?= $b['id'] ?>" data-book-name="<?= e($b['title']) ?> – <?= e($b['subject']) ?>" data-search="<?= e($b['title'] . ' ' . $b['subject'] . ' ' . major_label($b['major'] ?? 'all')) ?>">
            <div class="book-item-ico"><?= icon('book') ?></div>
            <div class="book-item-info">
              <b><?= e($b['title']) ?></b>
              <small><?= e($b['subject']) ?> · <?= e(major_label($b['major'] ?? 'all')) ?></small>
            </div>
            <div class="book-item-check"><?= icon('check') ?></div>
          </button>
        <?php endforeach;
      endforeach;
      if (empty($allBooks)): ?>
        <div class="book-list-empty">
          <?= icon('book') ?>
          <p>برای پایه و رشته شما هنوز کتابی اضافه نشده</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ====== Chat Actions Modal ====== -->
<div class="modal-overlay" id="chatActionsModal">
  <div class="modal-box action-modal">
    <div class="modal-head">
      <h3 id="actionModalTitle">عملیات</h3>
      <button class="icon-btn-mini" data-close-modal><?= icon('close') ?></button>
    </div>
    <div class="modal-body">
      <button class="action-btn" id="actPin">
        <?= icon('pin') ?>
        <span id="actPinText">سنجاق کردن</span>
      </button>
      <button class="action-btn" id="actRename">
        <?= icon('edit') ?>
        <span>تغییر نام</span>
      </button>
      <button class="action-btn action-danger" id="actDelete">
        <?= icon('trash') ?>
        <span>حذف چت</span>
      </button>
    </div>
  </div>
</div>

<!-- ====== Rename Modal ====== -->
<div class="modal-overlay" id="renameModal">
  <div class="modal-box rename-modal">
    <div class="modal-head">
      <h3><?= icon('edit') ?> تغییر نام چت</h3>
      <button class="icon-btn-mini" data-close-modal><?= icon('close') ?></button>
    </div>
    <div class="modal-body">
      <input type="text" id="renameInput" class="input" placeholder="نام جدید" maxlength="100">
      <div style="display:flex; gap:8px; margin-top:14px">
        <button class="btn btn-primary btn-block" id="renameSave"><?= icon('check') ?> ذخیره</button>
        <button class="btn btn-ghost" data-close-modal>انصراف</button>
      </div>
    </div>
  </div>
</div>

<!-- KaTeX & Marked -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/katex/katex.min.css">
<script defer src="<?= BASE_URL ?>/assets/vendor/katex/katex.min.js"></script>
<script defer src="<?= BASE_URL ?>/assets/vendor/katex/auto-render.min.js"></script>
<script defer src="<?= BASE_URL ?>/assets/vendor/marked.min.js"></script>

<script>
  window.DANESHYAR = {
    baseUrl: '<?= BASE_URL ?>',
    apiUrl: '<?= BASE_URL ?>/api/chat.php',
    chatsApi: '<?= BASE_URL ?>/api/chats.php',
    logoUrl: '<?= BASE_URL ?>/assets/img/logo.png',
    userFirstLetter: '<?= e(mb_substr($user['first_name'],0,1)) ?>',
    activeChatId: <?= $activeChatId ?: 'null' ?>,
    csrf: '<?= csrf_token() ?>',
    activeBookId: '<?= e($activeBookId) ?>'
  };
</script>
<script defer src="<?= BASE_URL ?>/assets/js/chat.js?v=21"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
