<?php
/**
 * مجموعه آیکون‌های SVG اختصاصی دانش‌یار
 */

function icon(string $name, array $attrs = []): string {
    $icons = [
        // --- لوگوی رسمی دانش‌یار (درخت دانش + کتاب) ---
        'logo' => '<circle cx="12" cy="12" r="11.5" fill="url(#dyG)"/><g fill="#1a0e05"><path d="M11.7 19.5c-.1-1.5 0-3.5.3-5.5.2-1.5.4-2.5.5-3.2-.1.7-.3 1.6-.5 3.2-.2 2-.4 4-.3 5.5z" stroke="#1a0e05" stroke-width=".4"/><rect x="10.5" y="18.5" width="3" height=".7" rx=".2"/><path d="M10.3 6.5 12 5.8l1.7.7v2l-1.7-.7-1.7.7zM12 5.8v2.7" stroke="#1a0e05" stroke-width=".35" fill="#1a0e05"/><path d="M12 11c-1.5-.5-3-1-4-2.5-.7-1-.5-2 .2-2.3.6-.2 1 .1 1.2.6M12 11c1.5-.5 3-1 4-2.5.7-1 .5-2-.2-2.3-.6-.2-1 .1-1.2.6" stroke="#1a0e05" stroke-width=".6" fill="none" stroke-linecap="round"/><circle cx="8.2" cy="7" r=".5"/><circle cx="15.8" cy="7" r=".5"/><circle cx="7.5" cy="9.5" r=".4"/><circle cx="16.5" cy="9.5" r=".4"/><path d="M12 13c-1.2 0-2.5-.3-3.5-1M12 13c1.2 0 2.5-.3 3.5-1" stroke="#1a0e05" stroke-width=".5" fill="none" stroke-linecap="round"/></g>',

        // --- منو و ناوبری ---
        'home'    => '<path d="M3 11l9-7 9 7v9a2 2 0 0 1-2 2h-4v-6h-6v6H5a2 2 0 0 1-2-2v-9z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
        'chat'    => '<path d="M4 5h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-5 4V7a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="9" cy="11.5" r="1" fill="currentColor"/><circle cx="13" cy="11.5" r="1" fill="currentColor"/><circle cx="17" cy="11.5" r="1" fill="currentColor"/>',
        'user'    => '<circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        'price'   => '<path d="M20 12V7a2 2 0 0 0-2-2h-5L3 15l6 6 10-10z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="15" cy="9" r="1.4" fill="currentColor"/>',
        'logout'  => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 17l-5-5 5-5M5 12h11" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
        'login'   => '<path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4M14 17l5-5-5-5M19 12H8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
        'menu'    => '<path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'close'   => '<path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',

        // --- چت / امتحان ---
        'send'    => '<path d="M3 11l18-8-8 18-2-8-8-2z" fill="currentColor"/>',
        'attach'  => '<path d="M21 12l-8.5 8.5a5 5 0 0 1-7-7L14 5a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
        'image'   => '<rect x="3" y="4" width="18" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="8.5" cy="9.5" r="1.5" fill="currentColor"/><path d="M21 16l-5-5-9 9" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
        'camera'  => '<path d="M4 7h3l2-3h6l2 3h3a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="12" cy="13" r="4" fill="none" stroke="currentColor" stroke-width="1.6"/>',
        'screenshot' => '<rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3 9h18M7 5V3M17 5V3M9 14l2 2 4-4" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        'pdf'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M14 2v6h6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><text x="12" y="17" font-size="5" fill="currentColor" text-anchor="middle" font-weight="700" font-family="Arial">PDF</text>',
        'plus'    => '<path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'check'   => '<path d="M5 12l5 5L20 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>',
        'sparkle' => '<path d="M12 2l2.4 6.6L21 11l-6.6 2.4L12 20l-2.4-6.6L3 11l6.6-2.4L12 2zM19 3l.8 2.2L22 6l-2.2.8L19 9l-.8-2.2L16 6l2.2-.8L19 3zM5 14l.6 1.6L7 16l-1.4.4L5 18l-.6-1.6L3 16l1.4-.4L5 14z" fill="currentColor"/>',
        'warning' => '<path d="M12 3l10 18H2L12 3z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12 10v4M12 17v.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'trash'   => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13M10 11v6M14 11v6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
        'edit'    => '<path d="M3 21l3.5-1 11-11-2.5-2.5-11 11L3 21zM14 6l4 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
        'search'  => '<circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M21 21l-5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
        'book'    => '<path d="M4 5a2 2 0 0 1 2-2h12v17H6a2 2 0 0 0-2 2V5z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M4 20a2 2 0 0 1 2-2h12" fill="none" stroke="currentColor" stroke-width="1.6"/>',
        'crown'   => '<path d="M3 18h18v2H3v-2zM3 7l5 4 4-6 4 6 5-4-2 9H5L3 7z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
        'flash'   => '<path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" fill="currentColor"/>',
        'star'    => '<path d="M12 2l3 7 7 .8-5.4 4.7L18 22l-6-4-6 4 1.4-7.5L2 9.8 9 9l3-7z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
        'shield'  => '<path d="M12 2l9 4v6c0 5-4 9-9 10-5-1-9-5-9-10V6l9-4z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
        'graph'   => '<path d="M3 3v18h18M7 14l4-4 4 4 5-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
        'users'   => '<circle cx="9" cy="8" r="3.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M2 20c0-3.3 3.1-5 7-5s7 1.7 7 5" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="17" cy="7" r="2.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M16 14c3 0 6 1.4 6 4" fill="none" stroke="currentColor" stroke-width="1.6"/>',
        'wallet'  => '<rect x="3" y="6" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3 10h18M17 15h2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        'arrow-left' => '<path d="M19 12H5M12 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        'arrow-right'=> '<path d="M5 12h14M12 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        'arrow-down' => '<path d="M12 5v14M5 12l7 7 7-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        'eye'     => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.6"/>',
        'clock'   => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        'lock'    => '<rect x="5" y="11" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 11V7a4 4 0 0 1 8 0v4" fill="none" stroke="currentColor" stroke-width="1.6"/>',
        'phone'   => '<rect x="6" y="2" width="12" height="20" rx="3" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M10 18h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        'school'  => '<path d="M3 21V9l9-6 9 6v12M9 21v-7h6v7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
        'refresh' => '<path d="M3 12a9 9 0 0 1 15.7-6L21 8M21 3v5h-5M21 12a9 9 0 0 1-15.7 6L3 16M3 21v-5h5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
        'upload'  => '<path d="M12 16V4M6 10l6-6 6 6M4 20h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        'download'=> '<path d="M12 4v12M6 12l6 6 6-6M4 20h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        'new-chat'=> '<path d="M4 20l4-2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v14zM12 9v6M9 12h6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
        'globe'   => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" fill="none" stroke="currentColor" stroke-width="1.4"/>',
        'copy'    => '<rect x="8" y="8" width="13" height="13" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3" fill="none" stroke="currentColor" stroke-width="1.6"/>',

        // --- آیکون امتحان (دست + برگه + مداد) ---
        'exam'    => '<path d="M5 3h12a2 2 0 0 1 2 2v16l-4-2-4 2-4-2-4 2V5a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M8 8h8M8 12h6M8 16h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="18" cy="6" r="2.5" fill="currentColor"/><path d="M17 6l.8.8L19 5.5" stroke="#1a0e05" stroke-width="1.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',

        // --- آیکون "بترکون امتحان" (راکت آتشی) ---
        'rocket'  => '<path d="M12 2c3 2 5 5 5 9v5l-3 3h-4l-3-3v-5c0-4 2-7 5-9z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="12" cy="10" r="1.8" fill="currentColor"/><path d="M9 17l-2 4 3-1M15 17l2 4-3-1" fill="currentColor"/><path d="M9 19c-1 1-1 3 0 4 1-1 1-3 0-4zM15 19c1 1 1 3 0 4-1-1-1-3 0-4z" fill="currentColor" opacity=".6"/>',

        // --- آیکون "ترکوندن / موفقیت" ---
        'trophy'  => '<path d="M7 4h10v4a5 5 0 0 1-10 0V4zM5 4h2v3a2 2 0 0 1-2-2zM17 4h2v1a2 2 0 0 1-2 2zM10 14h4v4h-4zM8 18h8v2H8z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',

        // --- آیکون مغز / هوش ---
        'brain'   => '<path d="M12 3a4 4 0 0 0-4 4v1a3 3 0 0 0-2 5 3 3 0 0 0 2 5v1a4 4 0 0 0 8 0v-1a3 3 0 0 0 2-5 3 3 0 0 0-2-5V7a4 4 0 0 0-4-4z" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M12 3v18M9 8a2 2 0 0 0 0 4M15 8a2 2 0 0 1 0 4M9 13a2 2 0 0 0 0 4M15 13a2 2 0 0 1 0 4" stroke="currentColor" stroke-width="1.2" fill="none" stroke-linecap="round"/>',

        // --- آیکون "20 / نمره" ---
        'grade'   => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.6"/><text x="12" y="16" text-anchor="middle" font-family="Arial" font-size="9" font-weight="900" fill="currentColor">20</text>',
        'pin'     => '<path d="M12 2l2 6 5 1-4 4 1 6-4-3-4 3 1-6-4-4 5-1z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
        'info'    => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M12 8v1M12 11v5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'clipboard' => '<path d="M9 2h6v2H9V2zM6 4h12v18H6V4z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
        'filter'  => '<path d="M4 5h16l-6 7v7l-4-2V12L4 5z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>',
        'globe'   => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" fill="none" stroke="currentColor" stroke-width="1.4"/>',
        'more'    => '<circle cx="5" cy="12" r="1.6" fill="currentColor"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/><circle cx="19" cy="12" r="1.6" fill="currentColor"/>',
    ];

    if (!isset($icons[$name])) {
        $svg = $icons['sparkle'];
    } else {
        $svg = $icons[$name];
    }

    $cls   = $attrs['class'] ?? 'ico';
    $size  = $attrs['size']  ?? null;
    $style = '';
    if ($size) $style = "width:{$size}px;height:{$size}px;";

    $extraDefs = '';
    if ($name === 'logo') {
        $extraDefs = '<defs><radialGradient id="dyG" cx="50%" cy="40%" r="60%"><stop offset="0%" stop-color="#ff9a3d"/><stop offset="100%" stop-color="#eb7c2a"/></radialGradient></defs>';
    }

    return '<svg class="' . htmlspecialchars($cls) . '" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="' . $style . '" aria-hidden="true">' . $extraDefs . $svg . '</svg>';
}
