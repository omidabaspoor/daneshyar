/* ============================================================
   دانش‌یار – منطق چت
   ============================================================ */
(function(){
  'use strict';

  document.body.classList.add('has-chat');

  /* ===== shortcuts ===== */
  const $  = (id) => document.getElementById(id);
  const input          = $('messageInput');
  const sendBtn        = $('sendBtn');
  const fileInputCamera  = $('fileInputCamera');
  const fileInputGallery = $('fileInputGallery');
  const fileInputFiles   = $('fileInputFiles');
  const btnAttach      = $('btnAttach');
  const msgsBox        = $('chatMessages');
  const bookSelect     = $('bookSelect');
  const attachBox      = $('attachmentBox');
  const composerRow    = document.querySelector('.composer-row');
  const sidebar        = $('chatSidebar');
  const sidebarOpen    = $('sidebarOpen');
  const sidebarClose   = $('sidebarClose');
  const sidebarOverlay = $('sidebarOverlay');
  const btnNewChat     = $('btnNewChat');
  const btnNewMobile   = $('btnNewChatMobile');
  const dropZone       = $('dropZone');
  const bookPickerBtn  = $('bookPickerBtn');
  const bookPickerText = $('bookPickerText');
  const bookModal      = $('bookModal');
  const bookList       = $('bookList');
  const bookSearchInput= $('bookSearchInput');
  const chatActionsModal = $('chatActionsModal');
  const actionModalTitle = $('actionModalTitle');
  const actPin    = $('actPin');
  const actPinText= $('actPinText');
  const actRename = $('actRename');
  const actDelete = $('actDelete');
  const renameModal = $('renameModal');
  const renameInput = $('renameInput');
  const renameSave  = $('renameSave');
  const attachModal = $('attachModal');

  let pendingFile = null;
  let isSending   = false;
  let currentChatActionId = null;

  /* ====================================================
     SVG helpers
     ==================================================== */
  function svgCopy()  { return '<svg class="ico" viewBox="0 0 24 24" fill="none"><rect x="8" y="8" width="13" height="13" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3" stroke="currentColor" stroke-width="1.6"/></svg>'; }
  function svgCheck() { return '<svg class="ico" viewBox="0 0 24 24" fill="none"><path d="M5 12l5 5L20 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>'; }
  function svgClose() { return '<svg class="ico" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'; }
  function svgPdf()   { return '<svg class="ico" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" stroke="currentColor" stroke-width="1.6"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="1.6"/></svg>'; }

  /* ====================================================
     markdown + KaTeX
     ==================================================== */
  /**
   * پاکسازی پاسخ AI قبل از تبدیل به HTML.
   * گاهی مدل با وجود پرامپت قوی، فرمول رو با markdown italic یا
   * unicode combining mark تولید می‌کنه (مثل ρA*ρA*​). اینجا اصلاحش می‌کنیم.
   */
  function cleanAiOutput(raw) {
    if (!raw) return '';
    var t = raw;

    // 1) حذف کاراکترهای combining mark/zero-width که مدل گاهی به‌اشتباه می‌سازه
    //    (U+200B ZWSP, U+200C ZWNJ, U+200D ZWJ, U+FEFF BOM, combining marks خاص)
    t = t.replace(/[\u200B\u200D\uFEFF\u2060\u180E]/g, '');
    // U+200C (ZWNJ - نیم‌فاصله فارسی) نباید پاک شود

    // 2) الگوهای علامت تکراری بد مدل: حذف "*X*\u200b*X*" که در فرمول‌های خراب می‌بینیم
    //    مثل: ρA*ρA*​>*ρB*​>*ρC*​
    //    این یعنی مدل دوبار همان متغیر رو با italic markdown نوشته. نسخه دوم رو حذف کن.
    t = t.replace(/([^\s\*])\*([^\s\*][^\*\n]*)\*\u200B*\*\2\*/g, function(_, prev, mid) {
      return prev + '*' + mid + '*';
    });

    // 3) اگه مدل برای فرمول از HTML entity مثل &gt; &lt; استفاده کرده، تبدیل کن
    //    (فقط در خطوطی که علامت‌های math داشته باشن)
    t = t.replace(/(.*?(?:&gt;|&lt;|&amp;).*)/g, function(line) {
      if (/[\$ρθαβ∑∫π√]/.test(line) || /[A-Za-z]_?\w*\s*&(gt|lt);\s*[A-Za-z]/.test(line)) {
        return line.replace(/&gt;/g, '>').replace(/&lt;/g, '<').replace(/&amp;/g, '&');
      }
      return line;
    });

    // 4) اگه دیدیم متغیرهای یونان مثل ρA یا α_1 خام در متن آمدن (بدون $) ولی همراه > < =،
    //    اونها رو خودکار در $...$ قرار بدیم. (آخرین چاره)
    //    این الگو: کلمه/سمبل با subscript/superscript + علامت مقایسه + همان
    t = t.replace(/(?<![\$\\])([ρθαβγδλμπσφψω][A-Za-z0-9_]*\s*[<>=≤≥≠≈]\s*[ρθαβγδλμπσφψω][A-Za-z0-9_]*(?:\s*[<>=≤≥≠≈]\s*[ρθαβγδλμπσφψω][A-Za-z0-9_]*)*)(?![\$\\])/g, function(m) {
      // تبدیل ρA به \rho_A
      var converted = m
        .replace(/ρ/g, '\\rho ').replace(/θ/g, '\\theta ')
        .replace(/α/g, '\\alpha ').replace(/β/g, '\\beta ')
        .replace(/γ/g, '\\gamma ').replace(/δ/g, '\\delta ')
        .replace(/λ/g, '\\lambda ').replace(/μ/g, '\\mu ')
        .replace(/π/g, '\\pi ').replace(/σ/g, '\\sigma ')
        .replace(/φ/g, '\\phi ').replace(/ψ/g, '\\psi ')
        .replace(/ω/g, '\\omega ')
        .replace(/(\\(?:rho|theta|alpha|beta|gamma|delta|lambda|mu|pi|sigma|phi|psi|omega))\s+([A-Za-z0-9])/g, '$1_{$2}');
      return '$' + converted.trim() + '$';
    });

    // 5) فرمول‌های ساده‌ای مثل x^2، y_1، v_0 که بدون $ آمدن:
    //    احتیاط: فقط در صورتی که هیچ $ در همان خط نباشه و کاراکترهای متن انگلیسی نباشه
    //    این رو فعلاً انجام نمی‌دیم چون ممکنه false positive داشته باشه.

    return t;
  }

  function mdToHtml(raw) {
    if (!raw) return '';
    raw = cleanAiOutput(raw);
    var latexBlocks   = [];
    var latexInlines  = [];
    var text = raw.replace(/\$\$([\s\S]+?)\$\$/g, function(_, math) {
      latexBlocks.push(math);
      return '%%LATEX_BLOCK_' + (latexBlocks.length - 1) + '%%';
    });
    text = text.replace(/\\\[([\s\S]+?)\\\]/g, function(_, math) {
      latexBlocks.push(math);
      return '%%LATEX_BLOCK_' + (latexBlocks.length - 1) + '%%';
    });
    text = text.replace(/\\\(([\s\S]+?)\\\)/g, function(_, math) {
      latexInlines.push(math);
      return '%%LATEX_INLINE_' + (latexInlines.length - 1) + '%%';
    });
    text = text.replace(/(?<!\$)\$(?!\$)([^\$\n]+?)\$(?!\$)/g, function(_, math) {
      latexInlines.push(math);
      return '%%LATEX_INLINE_' + (latexInlines.length - 1) + '%%';
    });
    let html = '';
    if (window.marked) {
      try {
        html = window.marked.parse(text, { breaks: true, gfm: true, mangle: false, headerIds: false });
      } catch(e) {
        html = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
      }
    } else {
      html = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    }
    html = html.replace(/%%LATEX_BLOCK_(\d+)%%/g, function(_, i) { return '$$' + latexBlocks[+i] + '$$'; });
    html = html.replace(/%%LATEX_INLINE_(\d+)%%/g, function(_, i) { return '$' + latexInlines[+i] + '$'; });
    html = html.replace(/<table/g, '<div class="table-wrap"><table').replace(/<\/table>/g, '</table></div>');
    return html;
  }

  function applyKaTeX(el) {
    if (window.renderMathInElement) {
      try {
        renderMathInElement(el, {
          delimiters: [
            { left: '$$', right: '$$', display: true  },
            { left: '\\[', right: '\\]', display: true  },
            { left: '\\(', right: '\\)', display: false },
            { left: '$',  right: '$',  display: false }
          ],
          throwOnError: false, strict: false
        });
      } catch(e) {}
    }
    el.querySelectorAll('.katex-display').forEach(function(kd) {
      if (kd.parentElement && kd.parentElement.classList.contains('kd-wrap')) return;
      var w = document.createElement('div');
      w.className = 'kd-wrap';
      kd.parentNode.insertBefore(w, kd);
      w.appendChild(kd);
    });
  }

  // جواب نهایی رو با رنگ سبز مشخص کن
  function highlightAnswers(el) {
    if (!el) return;
    el.querySelectorAll('blockquote').forEach(function(bq) {
      var text = bq.textContent || '';
      if (text.indexOf('✅') !== -1 || text.indexOf('جواب') !== -1) {
        bq.classList.add('answer-box');
      }
    });
  }

  function renderExistingMessages() {
    document.querySelectorAll('.chat-messages .message').forEach(msg => {
      const bubble = msg.querySelector('.bubble');
      if (!bubble) return;
      const raw = bubble.getAttribute('data-raw');
      if (!raw) return;
      const isAI = msg.classList.contains('assistant');
      if (isAI) {
        const img = bubble.querySelector('img.attached-img');
        bubble.innerHTML = mdToHtml(raw);
        if (img) bubble.insertBefore(img, bubble.firstChild);
        applyKaTeX(bubble);
        highlightAnswers(bubble);
      } else {
        const img = bubble.querySelector('img.attached-img');
        bubble.textContent = raw;
        if (img) {
          bubble.insertBefore(img, bubble.firstChild);
          img.addEventListener('click', () => openLightbox(img.src));
        }
      }
    });
  }

  function waitForLibsAndRender() {
    if (window.marked) renderExistingMessages();
    else {
      let tries = 0;
      const iv = setInterval(() => {
        tries++;
        if (window.marked) { clearInterval(iv); renderExistingMessages(); }
        else if (tries > 60) { clearInterval(iv); renderExistingMessages(); }
      }, 50);
    }
  }
  waitForLibsAndRender();

  /* Sidebar */
  const openSidebar  = () => { sidebar.classList.add('open');    sidebarOverlay.classList.add('show');    };
  const closeSidebar = () => { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('show'); };
  sidebarOpen    && sidebarOpen.addEventListener('click', openSidebar);
  sidebarClose   && sidebarClose.addEventListener('click', closeSidebar);
  sidebarOverlay && sidebarOverlay.addEventListener('click', closeSidebar);

  /* Composer */
  function autosize() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 140) + 'px';
    updateSendBtn();
  }
  function updateSendBtn() {
    sendBtn.disabled = (!input.value.trim() && !pendingFile) || isSending;
  }
  input.addEventListener('input', autosize);
  input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey && window.innerWidth >= 900) {
      e.preventDefault(); sendMessage();
    }
  });
  sendBtn.addEventListener('click', sendMessage);

  /* File upload - Attach Modal (دوربین / گالری / فایل‌ها) */
  // دکمه سنجاق: مودال انتخاب رو باز کن
  btnAttach.addEventListener('click', () => {
    if (attachModal) attachModal.classList.add('show');
  });

  // هندل کردن انتخاب‌های مودال
  document.querySelector('#attachModal .modal-body')?.addEventListener('click', (e) => {
    const option = e.target.closest('.attach-option');
    if (!option) return;
    const action = option.dataset.action;
    if (!action) return;

    // بستن مودال
    attachModal?.classList.remove('show');

    // انتخاب فایل اینپوت مناسب
    if (action === 'camera') {
      fileInputCamera?.click();
    } else if (action === 'gallery') {
      fileInputGallery?.click();
    } else if (action === 'files') {
      fileInputFiles?.click();
    }
  });

  // هندل کردن change برای هر سه تا فایل اینپوت
  function handleFileInputChange(inputEl) {
    if (!inputEl) return;
    if (inputEl.files?.[0]) handleFile(inputEl.files[0]);
    inputEl.value = ''; // ریست برای انتخاب مجدد
  }

  fileInputCamera?.addEventListener('change', () => handleFileInputChange(fileInputCamera));
  fileInputGallery?.addEventListener('change', () => handleFileInputChange(fileInputGallery));
  fileInputFiles?.addEventListener('change', () => handleFileInputChange(fileInputFiles));

  // فشرده‌سازی عکس در مرورگر قبل از آپلود (سرعت بالاتر)
  function compressImage(file, maxDim, quality) {
    return new Promise(function(resolve) {
      // PDF رو فشرده نکن
      if (!file.type || !file.type.startsWith('image/') || file.type === 'image/heic' || file.type === 'image/heif') {
        resolve(file); return;
      }
      // اگه فایل کوچکه نیازی نیست
      if (file.size < 2 * 1024 * 1024) { resolve(file); return; }
      var img = new Image();
      img.onload = function() {
        var w = img.width, h = img.height;
        if (w <= maxDim && h <= maxDim && file.size < 3 * 1024 * 1024) { resolve(file); return; }
        var ratio = Math.min(maxDim / w, maxDim / h, 1);
        var nw = Math.round(w * ratio), nh = Math.round(h * ratio);
        var canvas = document.createElement('canvas');
        canvas.width = nw; canvas.height = nh;
        canvas.getContext('2d').drawImage(img, 0, 0, nw, nh);
        canvas.toBlob(function(blob) {
          if (blob && blob.size < file.size) {
            resolve(new File([blob], file.name.replace(/\.[^.]+$/, '.jpg'), { type: 'image/jpeg' }));
          } else { resolve(file); }
        }, 'image/jpeg', quality || 0.8);
      };
      img.onerror = function() { resolve(file); };
      img.src = URL.createObjectURL(file);
    });
  }

  function handleFile(f) {
    const nameLower = (f.name || '').toLowerCase();
    const isHeic    = nameLower.endsWith('.heic') || nameLower.endsWith('.heif') || f.type === 'image/heic' || f.type === 'image/heif';
    const isImage   = (f.type && f.type.startsWith('image/')) || isHeic
                      || nameLower.endsWith('.jpg') || nameLower.endsWith('.jpeg')
                      || nameLower.endsWith('.png') || nameLower.endsWith('.webp')
                      || nameLower.endsWith('.gif');
    const isPdf     = f.type === 'application/pdf' || nameLower.endsWith('.pdf');
    if (!isImage && !isPdf) {
      alert('فقط عکس (JPG/PNG/WEBP/GIF/HEIC) یا PDF قابل ارسال است.');
      resetAllFileInputs(); return;
    }
    if (f.size > 15 * 1024 * 1024) {
      alert('حجم فایل نباید بیش از ۱۵ مگابایت باشد.');
      resetAllFileInputs(); return;
    }
    if (isHeic) {
      showToast('در حال تبدیل عکس HEIC… لطفاً کمی صبر کن');
    }
    if (isImage && !isHeic && f.size > 2 * 1024 * 1024) {
      // هماهنگ با سرور: ۱۸۰۰px برای خواندن متن ریز کافی‌تر از ۱۲۰۰ و بسیار سریع‌تر از عکس خام دوربین است.
      compressImage(f, 1800, 0.86).then(function(compressed) {
        uploadFile(compressed, { isHeic: false, isImage: true, isPdf: false });
      });
    } else {
      uploadFile(f, { isHeic, isImage, isPdf });
    }
  }

  function uploadFile(f, meta) {
    meta = meta || {};
    const fd = new FormData();
    fd.append('file', f);
    fd.append('csrf', window.DANESHYAR.csrf);
    const url = window.DANESHYAR.apiUrl.replace('chat.php', 'upload.php');
    renderAttachmentUploading();
    const xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.timeout = 120000; // 2 دقیقه برای آپلود روی نت ضعیف
    xhr.ontimeout = () => {
      alert('آپلود طولانی شد. لطفاً اینترنت رو چک کن و دوباره تلاش کن.');
      pendingFile = null; renderAttachment();
    };
    xhr.onload = () => {
      let data; try { data = JSON.parse(xhr.responseText); } catch(e) { data = {}; }

      // Smart retry for 429 (server busy) - حالت امتحانی: صف کوتاه و تلاش مجدد سریع
      if (xhr.status === 429 && data.error) {
        const retryCount = (meta.retryCount || 0) + 1;
        if (retryCount <= 10) {
          // ۲.۵s، ۳.۵s، ۴.۵s ... حداکثر ۸s؛ برای عکس‌های سبک معطل نکن
          const waitTime = Math.min(1500 + (retryCount * 1000), 8000);
          showToast('سرور شلوغ است؛ ' + Math.round(waitTime/1000) + ' ثانیه دیگر خودکار تلاش می‌کنیم...');
          setTimeout(() => {
            uploadFile(f, { ...meta, retryCount: retryCount });
          }, waitTime);
          return;
        } else {
          alert('آپلود پس از چند تلاش ناموفق بود. لطفاً دوباره تلاش کنید.');
          pendingFile = null;
          renderAttachment();
          return;
        }
      }

      if (xhr.status === 200 && data.ok) {
        // type رو از سرور بگیر (ممکنه HEIC → JPEG شده باشه)
        const finalType = data.mime || f.type || '';
        const finalName = (data.path || '').split('/').pop() || f.name;
        pendingFile = { name: finalName, type: finalType, path: data.path, preview: null };
        // برای HEIC، preview محلی کار نمی‌کنه. آیکن نشون می‌دیم.
        const canPreviewLocal = (f.type && f.type.startsWith('image/')
                                 && f.type !== 'image/heic' && f.type !== 'image/heif')
                                && !meta.isHeic;
        if (canPreviewLocal) {
          const rd = new FileReader();
          rd.onload = ev => { pendingFile.preview = ev.target.result; renderAttachment(); };
          rd.onerror = () => renderAttachment();
          rd.readAsDataURL(f);
        } else if (meta.isHeic) {
          // عکس تبدیل‌شده رو از سرور بگیر برای preview
          pendingFile.preview = (window.DANESHYAR.baseUrl || '') + '/' + data.path + '?t=' + Date.now();
          pendingFile.type    = 'image/jpeg';
          renderAttachment();
        } else {
          renderAttachment();
        }
        showToast('✓ فایل آپلود شد');
      } else {
        const errMsg = (data && data.error) ? data.error : ('کد ' + xhr.status);
        alert('خطا در آپلود:\n' + errMsg);
        pendingFile = null; renderAttachment();
      }
    };
    xhr.onerror = () => {
      alert('خطای شبکه در آپلود. اینترنت رو چک کن.');
      pendingFile = null; renderAttachment();
    };
    xhr.send(fd);
  }

  function renderAttachmentUploading() {
    attachBox.classList.add('active');
    attachBox.innerHTML = `<div class="attachment-container"><div class="attachment-preview att-uploading"><div class="att-loader"><div class="att-loader-circle"></div></div></div></div>`;
  }

  function renderAttachment() {
    if (!pendingFile) { attachBox.innerHTML = ''; attachBox.classList.remove('active'); return; }
    attachBox.classList.add('active');
    const isImg = pendingFile.type && pendingFile.type.startsWith('image/') || (pendingFile.name && (pendingFile.name.toLowerCase().endsWith('.heic') || pendingFile.name.toLowerCase().endsWith('.heif')));
    const thumb = (isImg && pendingFile.preview) ? `<img src="${pendingFile.preview}" alt="">` : svgPdf();
    attachBox.innerHTML = `
      <div class="attachment-container">
        <div class="attachment-preview">
          <div class="att-thumb">${thumb}</div>
          <button class="att-remove" type="button" aria-label="حذف">${svgClose()}</button>
        </div>
      </div>`;
    attachBox.querySelector('.att-remove').onclick = () => {
      pendingFile = null; resetAllFileInputs(); renderAttachment(); updateSendBtn();
    };
  }

  /* paste image */
  document.addEventListener('paste', e => {
    if (!e.clipboardData) return;
    for (const item of e.clipboardData.items) {
      if (item.type.startsWith('image/')) {
        e.preventDefault();
        const blob = item.getAsFile();
        const ts   = Date.now();
        handleFile(new File([blob], `screenshot_${ts}.png`, { type: blob.type }));
        showToast('✓ اسکرین‌شات اضافه شد!');
        break;
      }
    }
  });

  /* drag & drop */
  let dragCount = 0;
  document.addEventListener('dragenter', e => { e.preventDefault(); dragCount++; if (e.dataTransfer?.types.includes('Files')) dropZone.classList.add('active'); });
  document.addEventListener('dragleave', e => { e.preventDefault(); if (--dragCount <= 0) { dragCount = 0; dropZone.classList.remove('active'); } });
  document.addEventListener('dragover',  e => e.preventDefault());
  document.addEventListener('drop', e => {
    e.preventDefault(); dragCount = 0; dropZone.classList.remove('active');
    if (e.dataTransfer.files?.[0]) { handleFile(e.dataTransfer.files[0]); showToast('✓ فایل اضافه شد!'); }
  });

  /* append message */
  function appendMessage(role, content, imgDataUrl) {
    msgsBox.querySelector('.welcome-screen')?.remove();
    const wrap = document.createElement('div');
    wrap.className = 'message ' + role;
    const avatar = role === 'assistant'
      ? `<img src="${window.DANESHYAR.logoUrl}" alt="" width="34" height="34">`
      : `<span>${escH(window.DANESHYAR.userFirstLetter || 'م')}</span>`;
    wrap.innerHTML =
      `<div class="avatar">${avatar}</div>` +
      `<div class="message-wrap">` +
        `<div class="bubble"></div>` +
        `<div class="msg-actions"><button class="msg-action-btn" data-action="copy" title="کپی">${svgCopy()}</button></div>` +
      `</div>`;
    const bubble = wrap.querySelector('.bubble');
    if (imgDataUrl) {
      const img = document.createElement('img');
      img.src = imgDataUrl; img.className = 'attached-img'; img.alt = 'پیوست';
      img.addEventListener('click', () => openLightbox(imgDataUrl));
      bubble.appendChild(img);
    }
    if (content) {
      const tw = document.createElement('div');
      if (role === 'assistant') tw.innerHTML = mdToHtml(content);
      else tw.textContent = content;
      bubble.appendChild(tw);
    }
    bubble.setAttribute('data-raw', content || '');
    msgsBox.appendChild(wrap);
    if (role === 'assistant') requestAnimationFrame(() => applyKaTeX(bubble));
    msgsBox.scrollTop = msgsBox.scrollHeight;
    return bubble;
  }

  function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  /* typing indicator */
  function svgBrain() {
    return '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.5 2a3.5 3.5 0 0 0-3.44 4.15A3.5 3.5 0 0 0 4 9.5c0 .98.4 1.87 1.06 2.5A3.5 3.5 0 0 0 6 15.5a3.5 3.5 0 0 0 3 3.46V21h2v-2.04A3.5 3.5 0 0 0 14 21v-2.04A3.5 3.5 0 0 0 18 15.5a3.5 3.5 0 0 0 .94-3.5A3.5 3.5 0 0 0 20 9.5a3.5 3.5 0 0 0-2.06-3.35A3.5 3.5 0 0 0 14.5 2c-.98 0-1.87.4-2.5 1.06A3.49 3.49 0 0 0 9.5 2z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 3v18M9 7.5a2 2 0 0 0 3 0M15 7.5a2 2 0 0 1-3 0M9 13a2 2 0 0 0 3 0M15 13a2 2 0 0 1-3 0" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>';
  }

  function appendTyping(hasImg, hasPdf) {
    const cfg = hasImg ? {
      title:  'در حال بررسی تصویر',
      steps:  ['دریافت تصویر','تحلیل محتوا','نوشتن پاسخ'],
      subs:   ['تصویر دریافت شد...','محتوا در حال تحلیل...','آماده‌سازی پاسخ...'],
    } : hasPdf ? {
      title:  'در حال پردازش فایل',
      steps:  ['خواندن PDF','استخراج متن','نوشتن پاسخ'],
      subs:   ['فایل PDF دریافت شد...','متن در حال استخراج...','آماده‌سازی پاسخ...'],
    } : {
      title:  'در حال فکر کردن',
      steps:  ['دریافت سوال','تحلیل و بررسی','نوشتن پاسخ'],
      subs:   ['پیام دریافت شد...','در حال فکر کردن...','آماده‌سازی پاسخ...'],
    };
    const stepsHtml = cfg.steps.map((s,i) => `<div class="t-step${i===0?' active':''}">` + `<span class="t-step-dot"></span>` + `<span>${s}</span>` + `</div>`).join('');
    const wrap = document.createElement('div');
    wrap.className = 'message assistant typing-msg';
    wrap.innerHTML = `<div class="avatar"><img src="${window.DANESHYAR.logoUrl}" alt="" width="34" height="34"></div>` + `<div class="message-wrap">` + `<div class="thinking-bubble">` + `<div class="thinking-header">` + `<div class="thinking-brain">${svgBrain()}</div>` + `<div class="thinking-status">` + `<div class="thinking-status-title">${cfg.title}</div>` + `<div class="thinking-status-sub">${cfg.subs[0]}</div>` + `</div>` + `<div class="thinking-dots"><span></span><span></span><span></span></div>` + `</div>` + `<div class="thinking-steps">${stepsHtml}</div>` + `</div>` + `</div>`;
    msgsBox.appendChild(wrap);
    msgsBox.scrollTop = msgsBox.scrollHeight;
    const subEl    = wrap.querySelector('.thinking-status-sub');
    const stepEls  = [...wrap.querySelectorAll('.t-step')];
    let step = 0;
    const iv = setInterval(() => {
      step++; if (step >= cfg.steps.length) { clearInterval(iv); return; }
      stepEls.forEach((el, i) => { el.classList.toggle('done', i < step); el.classList.toggle('active', i === step); });
      if (subEl) subEl.textContent = cfg.subs[step] || '';
    }, 1800);
    wrap._iv = iv;
    return wrap;
  }

  /* SEND MESSAGE */
  async function sendMessage() {
    if (isSending) return;
    const text = input.value.trim();
    if (!text && !pendingFile) return;
    isSending = true;
    composerRow?.classList.add('sending');
    updateSendBtn();
    const imgPreview = (pendingFile?.type?.startsWith('image/') || (pendingFile?.name && (pendingFile.name.toLowerCase().endsWith('.heic') || pendingFile.name.toLowerCase().endsWith('.heif')))) ? pendingFile.preview : null;
    appendMessage('user', text, imgPreview);
    input.value = ''; autosize();
    const hasImg = !!imgPreview;
    const hasPdf = pendingFile?.type === 'application/pdf' || (pendingFile?.name && pendingFile.name.toLowerCase().endsWith('.pdf'));
    const typing  = appendTyping(hasImg, hasPdf);

    try {
      const fd = new FormData();
      fd.append('message', text);
      fd.append('book_id', bookSelect?.value || '0');
      fd.append('chat_id', window.DANESHYAR.activeChatId || '');
      fd.append('csrf',    window.DANESHYAR.csrf || '');
      if (pendingFile?.path) fd.append('attachment_path', pendingFile.path);

      const response = await fetch(window.DANESHYAR.apiUrl, { method: 'POST', body: fd });

      const wrap = document.createElement('div');
      wrap.className = 'message assistant';
      wrap.style.display = 'none';
      wrap.innerHTML = `<div class="avatar"><img src="${window.DANESHYAR.logoUrl}" alt="" width="34" height="34"></div>` + `<div class="message-wrap">` + `<div class="bubble streaming-bubble">` + `<div class="stream-content"></div>` + `<span class="stream-cursor"></span>` + `</div>` + `<div class="msg-actions"><button class="msg-action-btn" data-action="copy" title="کپی">${svgCopy()}</button></div>` + `</div>`;
      msgsBox.appendChild(wrap);
      const bubble     = wrap.querySelector('.bubble');
      const contentDiv = wrap.querySelector('.stream-content');
      const cursor     = wrap.querySelector('.stream-cursor');
      
      let rawText = '';
      let chunkCounter = 0;
      let firstChunk = false;
      function showWrap() { if (firstChunk) return; firstChunk = true; if (typing && typing.parentNode) typing.remove(); wrap.style.display = ''; }

      if (!response.ok) {
        showWrap();
        let errorMsg = '⚠ خطا در برقراری ارتباط با سرور (' + response.status + ')';
        try { const errData = await response.json(); if (errData && errData.error) errorMsg = '⚠ خطا: ' + errData.error; } catch(e) {}
        rawText = errorMsg;
        contentDiv.innerHTML = rawText;
        isSending = false;
        cursor?.remove();
        return;
      }

      const reader  = response.body.getReader();
      const decoder = new TextDecoder();
      let sseBuffer = '';

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        sseBuffer += decoder.decode(value, { stream: true });
        let lines = sseBuffer.split('\n\n');
        sseBuffer = lines.pop();

        for (let line of lines) {
          line = line.trim();
          if (!line || line.startsWith(':')) continue;
          if (line.startsWith('data: ')) {
            let payload = line.slice(6);
            if (payload === '[DONE]') continue;
            try {
              let obj = JSON.parse(payload);
              if (obj.error) {
                showWrap(); rawText = '⚠ خطای هوش مصنوعی: ' + obj.error;
                contentDiv.innerHTML = rawText; break;
              }
              if (obj.chunk) {
                showWrap(); rawText += obj.chunk; chunkCounter++;
                // بهینه: هر 3 chunk یکبار render (سرعت بالاتر روی گوشی ضعیف)
                if (chunkCounter % 3 === 0 || obj.chunk.includes('\n')) {
                  contentDiv.innerHTML = mdToHtml(rawText);
                  msgsBox.scrollTop = msgsBox.scrollHeight;
                }
              } else if (obj.ok) {
                if (obj.chat_id && !window.DANESHYAR.activeChatId) {
                  window.DANESHYAR.activeChatId = obj.chat_id;
                  history.replaceState(null, '', '?c=' + obj.chat_id);
                }
                // لیمیت لایو: آپدیت sidebar بدون رفرش
                updateQuotaLive(obj.mode);
              }
            } catch(e) {}
          }
        }
      }
      showWrap();
      if (!rawText) {
          rawText = '⚠ متاسفانه پاسخی از هوش مصنوعی دریافت نشد. این مشکل ممکن است به دلیل ترافیک بالا یا نامعتبر بودن کلید API باشد.';
      }
      contentDiv.innerHTML = mdToHtml(rawText);
      applyKaTeX(bubble);
      highlightAnswers(bubble);
      cursor?.remove();
      bubble.classList.remove('streaming-bubble');
      bubble.setAttribute('data-raw', rawText);
      msgsBox.scrollTop = msgsBox.scrollHeight;
    } catch (err) {
      if (typing && typing.parentNode) typing.remove();
      appendMessage('assistant', '⚠ خطای غیرمنتظره: ' + err.message);
    } finally {
      pendingFile = null; resetAllFileInputs(); renderAttachment();
      isSending = false; composerRow?.classList.remove('sending');
      updateSendBtn(); input.focus();
    }
  }

  function resetAllFileInputs() {
    if (fileInputCamera) fileInputCamera.value = '';
    if (fileInputGallery) fileInputGallery.value = '';
    if (fileInputFiles) fileInputFiles.value = '';
  }

  function openLightbox(src) {
    let lb = document.querySelector('.lightbox');
    if (!lb) {
      lb = document.createElement('div'); lb.className = 'lightbox';
      lb.innerHTML = `<button class="lightbox-close">${svgClose()}</button><img>`;
      document.body.appendChild(lb);
      lb.addEventListener('click', () => lb.classList.remove('show'));
    }
    lb.querySelector('img').src = src; lb.classList.add('show');
  }
  window.openLightboxFromHistory = openLightbox;

  let toastTimer;
  function showToast(msg) {
    let el = document.querySelector('.toast');
    if (!el) {
      el = document.createElement('div'); el.className = 'toast';
      Object.assign(el.style, { position:'fixed', bottom:'100px', left:'50%', transform:'translateX(-50%)', background:'rgba(15,15,24,.96)', color:'#fff', padding:'10px 18px', borderRadius:'12px', border:'1px solid rgba(235,124,42,.35)', fontSize:'13px', zIndex:'10000', backdropFilter:'blur(10px)', opacity:'0', transition:'opacity .25s', pointerEvents:'none', whiteSpace:'nowrap' });
      document.body.appendChild(el);
    }
    el.textContent = msg; el.style.opacity = '1';
    clearTimeout(toastTimer); toastTimer = setTimeout(() => { el.style.opacity = '0'; }, 2200);
  }

  document.addEventListener('click', async e => {
    const btn = e.target.closest('.msg-action-btn[data-action="copy"]');
    if (!btn) return;
    const bubble = btn.closest('.message-wrap').querySelector('.bubble');
    const raw = bubble.getAttribute('data-raw') || bubble.innerText;
    try { await navigator.clipboard.writeText(raw); btn.innerHTML = svgCheck(); showToast('✓ کپی شد!'); setTimeout(() => { btn.innerHTML = svgCopy(); }, 1500); } catch { showToast('کپی ناموفق'); }
  });

  function newChat() { if (window.innerWidth < 900) closeSidebar(); location.href = window.location.pathname; }
  btnNewChat   && btnNewChat.addEventListener('click', newChat);
  btnNewMobile && btnNewMobile.addEventListener('click', newChat);

  function openModal(m)  { m?.classList.add('show'); }
  function closeModal(m) { m?.classList.remove('show'); }
  document.querySelectorAll('[data-close-modal]').forEach(b => b.addEventListener('click', () => b.closest('.modal-overlay')?.classList.remove('show')));
  document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if (e.target === o) o.classList.remove('show'); }));

  bookPickerBtn?.addEventListener('click', () => openModal(bookModal));
  bookList?.addEventListener('click', e => {
    const item = e.target.closest('.book-item'); if (!item) return;
    bookList.querySelectorAll('.book-item').forEach(i => i.classList.remove('active'));
    item.classList.add('active'); bookSelect.value = item.dataset.bookId;
    bookPickerText.textContent = item.dataset.bookName; closeModal(bookModal); showToast('✓ ' + item.dataset.bookName);
  });
  bookSearchInput?.addEventListener('input', () => {
    const q = bookSearchInput.value.trim().toLowerCase();
    bookList.querySelectorAll('.book-item').forEach(item => { if (item.classList.contains('book-item-general')) return; item.classList.toggle('hidden', q !== '' && !(item.dataset.search || '').toLowerCase().includes(q)); });
    bookList.querySelectorAll('.book-group-header').forEach(h => { let next = h.nextElementSibling, vis = false; while (next?.classList.contains('book-item')) { if (!next.classList.contains('hidden')) vis = true; next = next.nextElementSibling; } h.style.display = (q === '' || vis) ? '' : 'none'; });
  });
  if (window.DANESHYAR.activeBookId) {
    const ai = bookList?.querySelector(`[data-book-id="${window.DANESHYAR.activeBookId}"]`);
    if (ai) { bookList.querySelectorAll('.book-item').forEach(i => i.classList.remove('active')); ai.classList.add('active'); bookPickerText.textContent = ai.dataset.bookName; bookSelect.value = window.DANESHYAR.activeBookId; }
  }
  document.addEventListener('click', e => {
    const btn = e.target.closest('.chat-item-menu'); if (!btn) return;
    e.preventDefault(); e.stopPropagation(); currentChatActionId = btn.dataset.id;
    actionModalTitle.textContent = btn.dataset.title || 'عملیات';
    actPinText.textContent = btn.dataset.pinned === '1' ? 'برداشتن سنجاق' : 'سنجاق کردن';
    openModal(chatActionsModal);
  });
  async function chatsApi(body) {
    const fd = new FormData(); Object.entries(body).forEach(([k,v]) => fd.append(k, v)); fd.append('csrf', window.DANESHYAR.csrf);
    const r = await fetch(window.DANESHYAR.chatsApi, { method: 'POST', body: fd }); return r.json();
  }
  actPin?.addEventListener('click', async () => { if (!currentChatActionId) return; closeModal(chatActionsModal); const d = await chatsApi({ action: 'toggle_pin', chat_id: currentChatActionId }); if (d.ok) { showToast(d.pinned ? '📌 سنجاق شد' : '✓ سنجاق برداخه شد'); setTimeout(() => location.reload(), 400); } else showToast('خطا: ' + (d.error || 'ناموفق')); });
  actRename?.addEventListener('click', () => { if (!currentChatActionId) return; closeModal(chatActionsModal); renameInput.value = actionModalTitle.textContent; openModal(renameModal); setTimeout(() => renameInput.focus(), 100); });
  renameSave?.addEventListener('click', async () => { const title = renameInput.value.trim(); if (!title || !currentChatActionId) return; const d = await chatsApi({ action: 'rename', chat_id: currentChatActionId, title }); if (d.ok) { closeModal(renameModal); showToast('✓ نام تغییر کرد'); setTimeout(() => location.reload(), 400); } else showToast('خطا: ' + (d.error || 'ناموفق')); });
  renameInput?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); renameSave.click(); } });
  actDelete?.addEventListener('click', async () => { if (!currentChatActionId) return; if (!confirm('این چت برای همیشه حذف می‌شه. مطمئنی؟')) return; closeModal(chatActionsModal); const d = await chatsApi({ action: 'delete', chat_id: currentChatActionId }); if (d.ok) { showToast('🗑 چت حذف شد'); setTimeout(() => { if (parseInt(currentChatActionId) === window.DANESHYAR.activeChatId) location.href = window.location.pathname; else location.reload(); }, 400); } else showToast('خطا: ' + (d.error || 'ناموفق')); });
  // ====== لیمیت لایو ======
  function updateQuotaLive(mode) {
    // آپدیت progress bar و شمارنده در sidebar بدون رفرش
    var subCard = document.querySelector('.sub-card');
    if (!subCard) return;
    var countEl = subCard.querySelector('.sub-count b');
    var progressEl = subCard.querySelector('.sub-progress > div');
    if (!countEl) return;

    // اعداد فارسی → انگلیسی برای محاسبه
    function faToEn(s) { return s.replace(/[۰-۹]/g, function(c) { return String.fromCharCode(c.charCodeAt(0) - 1728); }); }
    function enToFa(n) { return String(n).replace(/[0-9]/g, function(c) { return String.fromCharCode(parseInt(c) + 1776); }); }

    var text = countEl.textContent || '';
    var match = faToEn(text).match(/(\d+)\s*\/\s*(\d+)/);
    if (match) {
      var used = parseInt(match[1]) + 1;
      var limit = parseInt(match[2]);
      countEl.textContent = text.replace(/[۰-۹]+\s*\//, enToFa(used) + '/');
      if (progressEl) {
        var pct = limit > 0 ? Math.min(100, (used / limit) * 100) : 0;
        progressEl.style.width = pct + '%';
        if (pct >= 100) progressEl.style.background = '#ef4444';
      }
    } else if (mode === 'free') {
      // پلن رایگان: "X سوال رایگان"
      var freeMatch = faToEn(text).match(/(\d+)/);
      if (freeMatch) {
        var left = Math.max(0, parseInt(freeMatch[1]) - 1);
        countEl.textContent = enToFa(left) + ' سوال رایگان';
        if (progressEl) {
          progressEl.style.width = (left <= 0 ? 100 : ((3 - left) / 3) * 100) + '%';
        }
        if (left <= 0 && countEl.parentElement) {
          countEl.innerHTML = '<b>سوال رایگان امروز تمام شد</b>';
        }
      }
    }
  }

  updateSendBtn(); msgsBox.scrollTop = msgsBox.scrollHeight; if (window.innerWidth >= 900) input.focus();
})();
