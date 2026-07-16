{{--
    Boot splash. Rendered twice, identically, so the hand-off is seamless:

      1. by the standalone /splash page — the window's FIRST paint. NativePHP only
         reveals the window on Electron's `did-finish-load`, i.e. after every
         subresource (the whole React bundle) has loaded. Pointing the window at a
         bundle-free page makes that fire almost immediately, so the user sees
         branding + progress instead of no window at all.
      2. by app.blade.php while the bundle downloads and React mounts (app.tsx
         fades it out).

    Inline SVG + inline CSS only — it must not depend on anything the bundle ships,
    or it cannot paint before the bundle loads.
--}}
<div id="app-splash">
    <div class="app-splash__inner">
        <svg class="app-splash__mark" viewBox="0 0 43 31" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Fullness">
            <path d="M42.84,5.76L40.03,0l-23.3,21.24c-0.83,0.71-0.93,1.96-0.22,2.79s1.96,0.93,2.79,0.22l1.74-1.49l2.84,2.33L42.84,5.76z" fill="#00B8E4"/>
            <path d="M17.88,20.19L2.67,7.67L0,13.17l18.2,17.67l5.69-5.76l-2.85-2.32l-1.74,1.49c-0.83,0.71-2.08,0.61-2.79-0.22c-0.71-0.83-0.61-2.08,0.22-2.79L17.88,20.19z" fill="#61FFF8"/>
        </svg>
        <div class="app-splash__word">Fullness</div>
        <div class="app-splash__sub">{{ config('app.name', 'ZKTeco User Sync') }}</div>
        <div class="app-splash__bar" role="progressbar" aria-label="Loading"><span></span></div>
    </div>
</div>
<style>
    #app-splash { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center;
        background: #0b0d10; -webkit-app-region: drag; transition: opacity .35s ease; }
    #app-splash .app-splash__inner { display: flex; flex-direction: column; align-items: center; gap: 12px; }
    #app-splash .app-splash__mark { width: 78px; height: 57px; animation: appSplashPulse 1.6s ease-in-out infinite; }
    #app-splash .app-splash__word { font: 700 22px/1 -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; letter-spacing: -.02em; color: #e6e9ee; }
    #app-splash .app-splash__sub { font: 500 12px/1 -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; color: #8a919d; }
    /* Indeterminate track: the app can't report real progress, so a looping sweep
       signals "working" without implying a percentage. */
    #app-splash .app-splash__bar { margin-top: 6px; width: 132px; height: 2px; border-radius: 2px; background: #1c212b; overflow: hidden; }
    #app-splash .app-splash__bar span { display: block; width: 40%; height: 100%; border-radius: 2px;
        background: linear-gradient(90deg, #00B8E4, #61FFF8); animation: appSplashSweep 1.15s ease-in-out infinite; }
    @keyframes appSplashPulse { 0%, 100% { opacity: .78; } 50% { opacity: 1; } }
    @keyframes appSplashSweep { 0% { transform: translateX(-115%); } 100% { transform: translateX(365%); } }
    @media (prefers-reduced-motion: reduce) {
        #app-splash .app-splash__mark { animation: none; }
        #app-splash .app-splash__bar span { animation: none; width: 100%; }
    }
</style>
