<?php
$adminPage = 'test';
$pageTitle = 'تست سیستم';
include __DIR__ . '/_header.php';
?>

<style>
.test-container { max-width: 1000px; margin: 0 auto; }
.test-hero { text-align: center; padding: 50px 20px 40px; }
.test-hero h1 { font-size: 32px; font-weight: 900; margin-bottom: 10px; }
.test-hero p { color: var(--text-dim); font-size: 16px; }

.btn-run {
  background: linear-gradient(135deg, #eb7c2a, #d96a1e);
  color: #1a0e05;
  font-weight: 900;
  font-size: 18px;
  padding: 18px 50px;
  border-radius: 18px;
  border: none;
  cursor: pointer;
  box-shadow: 0 10px 35px rgba(235,124,42,.5);
  transition: all .3s ease;
  display: inline-flex;
  align-items: center;
  gap: 12px;
  margin-top: 25px;
}
.btn-run:hover { transform: translateY(-4px); box-shadow: 0 15px 45px rgba(235,124,42,.65); }
.btn-run:disabled { opacity: .6; transform: none; }

.test-results { margin-top: 40px; display: none; }

.test-item {
  background: rgba(255,255,255,.035);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 22px 26px;
  margin-bottom: 14px;
  display: flex;
  align-items: flex-start;
  gap: 20px;
  transition: all .2s ease;
}
.test-item.running { border-color: var(--orange); box-shadow: 0 0 0 4px rgba(235,124,42,.12); }
.test-item.ok { border-color: rgba(56,217,169,.4); }
.test-item.warning { border-color: rgba(255,184,107,.4); }
.test-item.error { border-color: rgba(255,84,112,.4); }

.test-icon {
  width: 48px; height: 48px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  font-size: 22px;
}
.test-icon.ok { background: rgba(56,217,169,.15); color: #38d9a9; }
.test-icon.warning { background: rgba(255,184,107,.15); color: #ffb86b; }
.test-icon.error { background: rgba(255,84,112,.15); color: #ff5470; }
.test-icon.running { background: rgba(235,124,42,.15); color: var(--orange); animation: pulse 1.5s infinite; }

@keyframes pulse {
  0%,100% { transform: scale(1); }
  50% { transform: scale(1.08); }
}

.test-content { flex: 1; min-width: 0; }
.test-title { font-weight: 800; font-size: 17px; margin-bottom: 6px; }
.test-message { font-size: 15px; color: var(--text-dim); line-height: 1.7; }
.test-detail { font-size: 13.5px; color: var(--text-muted); margin-top: 6px; }

.overall-box {
  text-align: center;
  padding: 35px;
  background: rgba(255,255,255,.03);
  border-radius: 22px;
  margin: 30px 0;
  border: 1px solid var(--border);
}
.overall-percent { font-size: 52px; font-weight: 900; line-height: 1; }
.overall-text { font-size: 20px; font-weight: 800; margin-top: 8px; }
</style>

<div class="test-container">
  <div class="test-hero">
    <h1>تست کامل و دقیق سیستم</h1>
    <p>با یک کلیک، تمام بخش‌های حیاتی را به صورت واقعی تست کنید</p>
    
    <button onclick="startRealTest()" class="btn-run" id="runBtn">
      <?= icon('play') ?>
      <span>اجرای تست کامل</span>
    </button>
  </div>

  <div class="test-results" id="resultsBox">
    <div class="overall-box" id="overallBox" style="display:none;"></div>
    <div id="testList"></div>
  </div>
</div>

<script>
const tests = [
  { id: 'db', title: 'اتصال به دیتابیس' },
  { id: 'tables', title: 'جداول اصلی' },
  { id: 'ai', title: 'هوش مصنوعی (تست واقعی اما ارزان)' },
  { id: 'upload', title: 'سیستم آپلود عکس' },
  { id: 'upload_queue', title: 'تست صف آپلود (۱۵ کاربر همزمان)' },
  { id: 'plans', title: 'پلن‌های اشتراک' },
  { id: 'scheduler', title: 'زمان‌بندی اشتراک' },
  { id: 'resources', title: 'منابع سرور' }
];

let results = {};

function updateTest(testId, status, message, detail = '') {
  const list = document.getElementById('testList');
  let el = document.getElementById('test-' + testId);
  
  if (!el) {
    el = document.createElement('div');
    el.id = 'test-' + testId;
    el.className = 'test-item';
    list.appendChild(el);
  }

  let icon = '';
  if (status === 'ok') icon = '✅';
  else if (status === 'warning') icon = '⚠️';
  else if (status === 'error') icon = '❌';
  else icon = '⏳';

  el.innerHTML = `
    <div class="test-icon ${status}">${icon}</div>
    <div class="test-content">
      <div class="test-title">${tests.find(t => t.id === testId).title}</div>
      <div class="test-message">${message}</div>
      ${detail ? `<div class="test-detail">${detail}</div>` : ''}
    </div>
  `;
  
  el.className = `test-item ${status}`;
}

async function startRealTest() {
  const btn = document.getElementById('runBtn');
  const resultsBox = document.getElementById('resultsBox');
  const overallBox = document.getElementById('overallBox');
  
  btn.disabled = true;
  btn.innerHTML = 'در حال اجرای تست‌ها...';
  resultsBox.style.display = 'block';
  overallBox.style.display = 'none';
  document.getElementById('testList').innerHTML = '';
  results = {};

  for (let test of tests) {
    updateTest(test.id, 'running', 'در حال تست...');
    await new Promise(r => setTimeout(r, 250));
    
    try {
      const res = await fetch(`test_api.php?action=${test.id}`);
      const data = await res.json();
      results[test.id] = data;
      updateTest(test.id, data.status, data.message, data.detail);
    } catch (e) {
      updateTest(test.id, 'error', 'خطا در ارتباط', e.message);
    }
  }

  showFinalResult();
  btn.disabled = false;
  btn.innerHTML = 'اجرای مجدد تست کامل';
}

function showFinalResult() {
  const box = document.getElementById('overallBox');
  let ok = 0, total = Object.keys(results).length;
  
  Object.values(results).forEach(r => { if (r.status === 'ok') ok++; });
  
  const percent = total > 0 ? Math.round((ok / total) * 100) : 0;
  
  let text = '', color = '';
  if (percent >= 90) { text = 'عالی! سیستم کاملاً سالم است'; color = '#38d9a9'; }
  else if (percent >= 70) { text = 'خوب — چند بخش نیاز به بررسی دارد'; color = '#ffb86b'; }
  else { text = 'نیاز به بررسی فوری دارد'; color = '#ff5470'; }

  box.innerHTML = `
    <div class="overall-percent" style="color:${color}">${percent}%</div>
    <div class="overall-text" style="color:${color}">${text}</div>
    <div style="margin-top:12px; color:var(--text-dim); font-size:14.5px;">
      ${ok} از ${total} تست موفق
    </div>
  `;
  box.style.display = 'block';
}
</script>

<?php include __DIR__ . '/_footer.php'; ?>