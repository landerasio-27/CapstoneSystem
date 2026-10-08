{{--
    The sign-in screens' own stylesheet.

    Every door of this portal is the same card on the same paper: the sign-in
    screen itself, and the three steps the office walks when a password has been
    lost. The rules are written once, here, and each of those pages includes
    them, so the card cannot drift a shade between the screens it stands on.

    They stay inline rather than in the compiled bundle for the reason the
    sign-in screen has always carried them that way: the door has to stand
    dressed on its own, before anything of the signed-in application is loaded.
--}}
    <style>
        :root {
            --isu-green:#087A16;
            --isu-green-dark:#076a12;
            --ink:#172033;
            --muted:#6B7280;
            --line:#E4E9E6;
        }

        html, body { height:100%; }
        body { margin:0; min-height:100%;
               background:#F6F8F7; color:var(--ink);
               font-family:'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
               -webkit-font-smoothing:antialiased; }

        /* ---------- Background: two very soft green washes and one thin curve.
             Decorative only, fixed behind the panel, and quiet enough to ignore. ---------- */
        .bg-accent { position:fixed; inset:0; overflow:hidden; pointer-events:none; z-index:0; }
        .bg-accent .wash { position:absolute; border-radius:50%; filter:blur(70px); opacity:.55; }
        .bg-accent .wash-1 { width:520px; height:520px; top:-220px; left:-160px;
                             background:radial-gradient(circle, #DCEEE3 0%, rgba(220,238,227,0) 70%); }
        .bg-accent .wash-2 { width:620px; height:620px; bottom:-300px; right:-220px;
                             background:radial-gradient(circle, #E6F2EA 0%, rgba(230,242,234,0) 70%); }
        /* One thin curve, hugging the bottom-right corner: a hint of green, not a graphic. */
        .bg-accent .curve { position:absolute; inset:auto -2% -2% auto; width:min(520px, 52vw); height:auto; opacity:.32; }

        /* ---------- Layout ---------- */
        .login-page { position:relative; z-index:1; min-height:100vh; min-height:100svh; display:flex;
                      padding:48px 24px; box-sizing:border-box; }
        /* margin:auto centers on both axes and, unlike flex centering, never clips
           the top of the card when the viewport is shorter than the content. */
        .login-shell { width:100%; max-width:440px; margin:auto; }

        .login-card { box-sizing:border-box; width:100%; padding:36px 34px 26px;
                      background:#FFFFFF; border:1px solid var(--line); border-radius:18px;
                      box-shadow:0 1px 2px rgba(16,24,40,.04), 0 16px 40px -24px rgba(16,24,40,.18); }

        /* Short, professional entrance: the panel settles into place once. */
        @keyframes login-in { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
        .login-card { animation:login-in .38s cubic-bezier(.22,.61,.36,1) both; }
        .page-foot { animation:login-in .38s .06s cubic-bezier(.22,.61,.36,1) both; }
        @media (prefers-reduced-motion: reduce) {
            .login-card, .page-foot { animation:none; }
            * { transition:none !important; }
        }

        /* ---------- Brand block ---------- */
        .brand { text-align:center; }
        .brand-logo { width:76px; height:76px; margin:0 auto 14px; border-radius:24px;
                      display:flex; align-items:center; justify-content:center;
                      background:#F4F9F5; border:1px solid #E4EFE8; }
        /* The seal fills its container: only the logo grows here, the rounded
           tile around it keeps its size at every breakpoint. */
        .brand-logo img { display:block; width:72px; height:72px; object-fit:contain; }
        .brand-name { margin:0; font-size:12px; font-weight:700; letter-spacing:1.4px;
                      text-transform:uppercase; color:#243043; }
        .brand-campus { margin:3px 0 0; font-size:12px; color:var(--muted); }

        .title { margin:22px 0 0; text-align:center; font-size:24px; font-weight:700; letter-spacing:-.2px; color:var(--ink); }
        .subtitle { margin:6px 0 0; text-align:center; font-size:13.5px; color:var(--muted); }

        /* ---------- Messages ---------- */
        .login-error { display:flex; align-items:flex-start; gap:8px; margin-top:18px; padding:10px 12px;
                       border-radius:10px; background:#FEF2F2; border:1px solid #FECACA;
                       color:#B91C1C; font-size:13px; line-height:1.45; }
        .login-error svg { flex:none; margin-top:1px; }
        /* The green of the same banner: what the screen before this one ended
           with, rather than what went wrong on it. */
        .login-ok { display:flex; align-items:flex-start; gap:8px; margin-top:18px; padding:10px 12px;
                    border-radius:10px; background:#F1F9F3; border:1px solid #C4DECE;
                    color:#087A16; font-size:13px; line-height:1.45; }
        .login-ok svg { flex:none; margin-top:1px; }
        .field-err { margin:6px 0 0 2px; font-size:12.5px; color:#DC2626; }

        /* ---------- Form ---------- */
        .login-form { margin-top:22px; }
        .field-label { display:block; margin:0 0 7px 2px; font-size:13px; font-weight:500; color:#374151; }
        .field { position:relative; }
        .field + .field-label, .field-err + .field-label { margin-top:16px; }
        .field .ficon { position:absolute; left:15px; top:50%; transform:translateY(-50%);
                        color:#9CA3AF; pointer-events:none; }
        .field input { width:100%; height:50px; box-sizing:border-box; border-radius:12px;
                       border:1px solid #D5DCD8; background:#FFFFFF; padding:0 46px;
                       font-size:14.5px; color:var(--ink); font-family:inherit;
                       transition:border-color .16s ease, box-shadow .16s ease, background .16s ease; }
        .field input::placeholder { color:#9CA3AF; }
        .field input:hover { border-color:#C6D0CA; }
        .field input:focus { outline:none; border-color:var(--isu-green);
                             box-shadow:0 0 0 3px rgba(8,122,22,.13); }
        .field input[aria-invalid='true'] { border-color:#DC2626; }
        .field input[aria-invalid='true']:focus { box-shadow:0 0 0 3px rgba(220,38,38,.12); }
        .field .eye { position:absolute; right:8px; top:50%; transform:translateY(-50%);
                      display:flex; align-items:center; justify-content:center; width:36px; height:36px;
                      border:0; border-radius:9px; background:transparent; color:#9CA3AF;
                      cursor:pointer; transition:color .16s ease, background .16s ease; }
        .field .eye:hover { color:#4B5563; background:#F3F5F4; }
        .field .eye:focus-visible, .btn-login:focus-visible, .forgot-toggle:focus-visible,
        .remember input:focus-visible, .skip-link:focus-visible {
            outline:2px solid var(--isu-green); outline-offset:2px;
        }

        /* ---------- Remember me / Forgot password, one row ---------- */
        .form-row { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:18px; }
        .remember { display:flex; align-items:center; gap:8px; font-size:13px; color:#4B5563;
                    user-select:none; cursor:pointer; }
        .remember input { width:15px; height:15px; accent-color:var(--isu-green); cursor:pointer; }
        .forgot-toggle { padding:0; border:0; background:none; cursor:pointer;
                         font-family:inherit; font-size:13px; color:var(--isu-green);
                         text-decoration:none; border-radius:6px; }
        .forgot-toggle:hover { text-decoration:underline; }
        .forgot-note { margin:10px 0 0; padding:9px 11px; border-radius:10px;
                       background:#F4F7F5; border:1px solid #E7EDE9;
                       font-size:12.5px; line-height:1.5; color:#4B5563; }
        .forgot-note a { color:var(--isu-green); }
        /* The line under the note that offers the way back — another code, a
           different address, the sign-in screen. */
        .note-actions { display:flex; flex-wrap:wrap; align-items:center; justify-content:center;
                        gap:8px; margin-top:7px; }
        .note-actions form { display:inline; margin:0; }

        /* The one-time code is counted off the mail a digit at a time, so it is
           typed into six boxes rather than one field: each digit gets a box of
           its own, which keeps the eye's place as it walks the mail. The six
           boxes fill the card's width between them (grid, so they meet whatever
           width they are given), and what is written in them is gathered into
           the one field the form actually sends (see verify-code). */
        .code-row { display:grid; grid-template-columns:repeat(6, 1fr); gap:9px; }
        .code-box { width:100%; height:56px; box-sizing:border-box; padding:0;
                    text-align:center; border-radius:12px; border:1px solid #D5DCD8;
                    background:#FFFFFF; font-family:inherit; font-size:21px;
                    font-weight:600; color:var(--ink); caret-color:var(--isu-green);
                    transition:border-color .16s ease, box-shadow .16s ease, background .16s ease; }
        .code-box:hover { border-color:#C6D0CA; }
        /* A digit that is already in means the box is spent, so it is drawn a
           shade deeper than the empty ones waiting their turn. */
        .code-box[data-filled] { border-color:#AFC0B6; }
        .code-box:focus { outline:none; border-color:var(--isu-green);
                          box-shadow:0 0 0 3px rgba(8,122,22,.13); }
        .code-row[aria-invalid='true'] .code-box { border-color:#DC2626; }
        .code-row[aria-invalid='true'] .code-box[data-filled] { border-color:#DC2626; }
        .code-row[aria-invalid='true'] .code-box:focus { box-shadow:0 0 0 3px rgba(220,38,38,.12); }

        /* A word that acts like a link without wearing a link's underline until
           it is pointed at: asking for the code again. */
        .link-btn { padding:0; border:0; background:none; cursor:pointer; font-family:inherit;
                    font-size:12.5px; color:var(--isu-green); border-radius:6px; }
        .link-btn:hover { text-decoration:underline; }
        .link-btn:focus-visible { outline:2px solid var(--isu-green); outline-offset:2px; }

        /* ---------- Submit ---------- */
        .btn-login { display:flex; align-items:center; justify-content:center; gap:8px;
                     width:100%; height:51px; margin-top:20px; border:0; border-radius:12px;
                     background:var(--isu-green); color:#FFFFFF; font-family:inherit;
                     font-size:15px; font-weight:600; cursor:pointer;
                     transition:background .16s ease, box-shadow .16s ease; }
        .btn-login:hover { background:var(--isu-green-dark); }
        .btn-login:active { background:#06590f; }
        .btn-login:disabled { opacity:.8; cursor:not-allowed; }

        .secure-note { margin:14px 0 0; text-align:center; font-size:12px; color:#9CA3AF; }

        /* ---------- Footer ---------- */
        .page-foot { margin:20px 0 0; text-align:center; font-size:12.5px; line-height:1.6; color:#9CA3AF; }
        .page-foot p { margin:0; }

        /* Keyboard users land on the form first. */
        .skip-link { position:absolute; left:-9999px; top:0; padding:8px 12px; background:#FFFFFF;
                     border:1px solid var(--line); border-radius:8px; font-size:13px; color:var(--ink); }
        .skip-link:focus { left:8px; top:8px; z-index:5; }

        /* ---------- Dark mode: same layout on quiet surfaces ----------
           Mirrors app.css's html[data-theme='dark'] convention and out-specifies
           its global form-control safety net (this <style> loads after the bundle). */
        html[data-theme='dark'] body { background:#0e1411; color:#E5E7EB; }
        html[data-theme='dark'] .bg-accent .wash { opacity:.35; }
        html[data-theme='dark'] .bg-accent .wash-1 { background:radial-gradient(circle, #16301f 0%, rgba(22,48,31,0) 70%); }
        html[data-theme='dark'] .bg-accent .wash-2 { background:radial-gradient(circle, #14251d 0%, rgba(20,37,29,0) 70%); }
        html[data-theme='dark'] .bg-accent .curve { opacity:.1; }
        html[data-theme='dark'] .login-card { background:#141b18; border-color:#26302b; box-shadow:none; }
        html[data-theme='dark'] .brand-logo { background:#18211d; border-color:#2a352f; }
        html[data-theme='dark'] .brand-name { color:#E5E7EB; }
        html[data-theme='dark'] .brand-campus, html[data-theme='dark'] .subtitle,
        html[data-theme='dark'] .page-foot, html[data-theme='dark'] .secure-note { color:#9CA3AF; }
        html[data-theme='dark'] .title { color:#F3F4F6; }
        html[data-theme='dark'] .field-label { color:#D1D5DB; }
        html[data-theme='dark'] .login-card .field input:not([type='checkbox']):not([type='radio']) {
            background:#161d1a; color:#E5E7EB; border-color:#33403a; }
        html[data-theme='dark'] .field input::placeholder { color:#6B7A72; }
        html[data-theme='dark'] .field input:hover { border-color:#3d4b44; }
        html[data-theme='dark'] .field input:focus { border-color:#2FA36B; box-shadow:0 0 0 3px rgba(47,163,107,.18); }
        html[data-theme='dark'] .field .ficon, html[data-theme='dark'] .field .eye { color:#6B7A72; }
        html[data-theme='dark'] .field .eye:hover { color:#D1D5DB; background:#1d2621; }
        html[data-theme='dark'] .code-box { background:#161d1a; color:#E5E7EB; border-color:#33403a; }
        html[data-theme='dark'] .code-box:hover { border-color:#3d4b44; }
        html[data-theme='dark'] .code-box[data-filled] { border-color:#4d5f55; }
        html[data-theme='dark'] .code-box:focus { border-color:#2FA36B; box-shadow:0 0 0 3px rgba(47,163,107,.18); }
        html[data-theme='dark'] .remember { color:#D1D5DB; }
        html[data-theme='dark'] .forgot-toggle { color:#4ADE80; }
        html[data-theme='dark'] .forgot-note { background:#18211d; border-color:#2a352f; color:#9CA3AF; }
        html[data-theme='dark'] .forgot-note a, html[data-theme='dark'] .link-btn { color:#4ADE80; }
        html[data-theme='dark'] .login-error { background:rgba(248,113,113,.12); border-color:rgba(248,113,113,.35); color:#F87171; }
        html[data-theme='dark'] .login-ok { background:rgba(74,222,128,.12); border-color:rgba(74,222,128,.38); color:#4ADE80; }

        /* ---------- Mobile ---------- */
        @media (max-width: 480px) {
            .login-page { padding:28px 20px; }
            .login-card { padding:28px 22px 22px; border-radius:16px; }
            .brand-logo { width:68px; height:68px; border-radius:22px; margin-bottom:12px; }
            .brand-logo img { width:64px; height:64px; }
            .title { margin-top:18px; font-size:22px; }
            .login-form { margin-top:18px; }
            .field input { height:48px; }
            .code-row { gap:7px; }
            .code-box { height:52px; font-size:19px; }
            .btn-login { height:49px; margin-top:18px; }
        }

        /* ---------- Short viewports (small laptops, split windows, landscape phones):
             tighten the page so the whole login — button included — stays on screen
            without scrolling. Width rules above are untouched. ---------- */
        @media (max-height: 760px) {
            .login-page { padding:24px; }
            .login-card { padding:26px 32px 18px; }
            .brand-logo { width:64px; height:64px; border-radius:20px; margin-bottom:10px; }
            .brand-logo img { width:60px; height:60px; }
            .title { margin-top:14px; font-size:22px; }
            .login-form { margin-top:16px; }
            .field + .field-label, .field-err + .field-label { margin-top:12px; }
            .field input { height:46px; }
            .code-box { height:48px; font-size:19px; }
            .form-row { margin-top:14px; }
            .btn-login { height:47px; margin-top:14px; }
            .secure-note { margin-top:10px; }
            .page-foot { margin-top:12px; }
        }
        @media (max-height: 620px) {
            .login-page { padding:16px; }
            .login-card { padding:20px 28px 14px; border-radius:14px; }
            .brand-logo { width:54px; height:54px; border-radius:18px; margin-bottom:8px; }
            .brand-logo img { width:50px; height:50px; }
            .title { margin-top:10px; font-size:20px; }
            .subtitle { margin-top:4px; }
            .field input { height:44px; }
            .btn-login { height:45px; }
            /* The footer repeats the brand block above, so it can go on very short screens. */
            .page-foot { display:none; }
        }
    </style>
