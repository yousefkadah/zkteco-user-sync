# Publishing — macOS, Windows, Linux

This is a NativePHP (Electron) app, packaged by **electron-builder**. You build
**one platform at a time** on (or matching) that platform:

```bash
php artisan native:build mac      # → .dmg  (+ .zip for auto-update)
php artisan native:build win      # → NSIS  setup .exe
php artisan native:build linux    # → .AppImage  and  .deb
```

> Cross-compiling isn't supported (except Windows-from-Linux via wine). Build
> macOS on a Mac, Windows on Windows (or CI), Linux on Linux.

There are **two distribution models**. NativePHP officially supports **direct
distribution** (signed/notarized installers you host yourself, with the built-in
auto-updater). The **app stores** are *possible* but are a **manual
electron-builder override that NativePHP does not wire up or support** — see the
per-store sections.

---

## 1. Direct distribution (recommended default)

This is the path NativePHP is built for and the fastest to ship on all three OSes.

### macOS — notarized `.dmg`
Requires an **Apple Developer Program** membership ($99/yr) and a *Developer ID
Application* certificate. Set these in `.env` (they're stripped from the build):

```
NATIVEPHP_APPLE_ID="you@example.com"
NATIVEPHP_APPLE_ID_PASS="app-specific-password"   # appleid.apple.com → App-Specific Passwords
NATIVEPHP_APPLE_TEAM_ID="XXXXXXXXXX"
```

`native:build mac` signs with the hardened runtime and notarizes via Apple's
`notarytool` automatically. Without notarization the app shows *"is damaged and
can't be opened"* on other Macs.

> **Testing an unsigned build locally:** with no cert, the built `.app` gets an
> inconsistent ad-hoc signature and crashes at launch (`dyld: … Electron Framework
> … different Team IDs`). To run it on the build machine only, re-sign the whole
> bundle consistently:
> `codesign --remove-signature "…app" && codesign --force --deep --sign - "…app"`.
> This is a local-testing workaround — real distribution still needs the Developer
> ID signing + notarization above.

### Windows — signed `.exe`
Recommended: **Azure Trusted Signing** (cloud, no local cert). In `.env`:

```
AZURE_TENANT_ID=...
AZURE_CLIENT_ID=...
AZURE_CLIENT_SECRET=...
NATIVEPHP_AZURE_PUBLISHER_NAME="Your Name / Company"
NATIVEPHP_AZURE_ENDPOINT="https://wus2.codesigning.azure.net/"
NATIVEPHP_AZURE_CERTIFICATE_PROFILE_NAME=...
NATIVEPHP_AZURE_CODE_SIGNING_ACCOUNT_NAME=...
```

(Or a traditional code-signing certificate via `signtool`.) Unsigned installers
trigger SmartScreen warnings.

### Linux — `.AppImage` + `.deb`
No signing required. Ship the AppImage (runs anywhere) and/or the `.deb`.

---

## 2. Microsoft Store — **wired up** (MSIX)

The Store target is **implemented** in this repo:

- **`build/msix/electron-builder.appx.mjs`** — a full-trust MSIX build config
  (derived from NativePHP's config with `win.target: ['appx']`). MSIX runs
  full-trust — electron-builder fixes `EntryPoint="Windows.FullTrustApplication"`
  and injects `runFullTrust` — so the **bundled PHP binary + child processes +
  UDP 4370 keep working**.
- **`.github/workflows/build-msix.yml`** — a Windows CI job that produces the
  `.msix` for you (MSIX packaging is Windows-only, so you don't need a Windows PC).

### Steps

1. **Open a Partner Center account** — *Individual* is **free** (2025; ID + selfie)
   or *Company* (~$99 one-time). Create the app, reserve the name, open
   **Product → Product identity**, and copy three values:
   - Package/Identity **Name** → `MSIX_IDENTITY_NAME`
   - **Publisher** (`CN=…`) → `MSIX_PUBLISHER`
   - **Publisher display name** → `MSIX_PUBLISHER_DISPLAY_NAME`
2. In GitHub → **Settings → Secrets and variables → Actions → Variables**, add
   those three as repository **Variables**.
3. Run **Actions → “Build MSIX (Microsoft Store)” → Run workflow** (or push a
   `v*` tag). Download the **`windows-msix`** artifact — that's your unsigned
   `.msix` (the Store re-signs it).
4. In Partner Center, create a submission, **upload the `.msix`**, and on the
   *Submission options* page justify **`runFullTrust`** as an Electron/Desktop-Bridge
   app and describe the **LAN UDP (port 4370)** device networking.
5. Submit for certification. On approval the Store signs & distributes with
   automatic updates. (Budget extra review time for the restricted capability.)

> Notes: CI pins **PHP 8.3** (php-bin ships a win-x64 static binary for it; all
> app deps support `^8.3`), sets `CSC_IDENTITY_AUTO_DISCOVERY=false` for an
> unsigned package, and adds the Windows SDK to PATH so `makeappx.exe` is found.
> If you upgrade `nativephp/desktop`, re-sync `build/msix/electron-builder.appx.mjs`
> with the vendored config. If the first CI run surfaces a path/manifest issue,
> send the log and I'll adjust.

---

## 3. Mac App Store — **hard, but possible** (not recommended first)

Verdict: **possible but a lot of manual, unsupported work.** The two things people
fear are actually fine:

- The **App Sandbox does NOT block spawning the bundled PHP binary** — non-`.app`
  child processes automatically inherit the parent's sandbox (confirmed by Apple
  DTS). NativePHP's spawn-PHP model is mechanically allowed.
- A **bundled interpreter running bundled code is not a §2.5.2 violation** (the
  rule targets code *downloaded at runtime*; iSH/Pyto/BeeWare ship interpreters).

What makes it hard (all **manual overrides NativePHP doesn't provide**):

- Add `com.apple.security.app-sandbox`, `network.client`, `network.server` to a
  new `build/entitlements.mas.plist`.
- Add `build/entitlements.mas.inherit.plist` containing **exactly**
  `app-sandbox` + `inherit` (any extra key aborts the PHP child).
- Add a login-helper entitlements file; **code-sign every** bundled binary and
  PHP extension `.so/.dylib` with the hardened runtime.
- **Relocate all writable paths** (SQLite DB, storage, cache, logs, sessions)
  into the sandbox container — the bundle is read-only.
- **Remove the NativePHP auto-updater** (downloading build artifacts violates
  §2.5.2 / MAS update policy — updates come only through the Store).
- Provision *Mac App Distribution* + *Mac Installer Distribution* certs, build the
  `pkg` via the `mas` target (not `dmg`), and submit.

**Recommendation:** ship the **notarized `.dmg`** (section 1) for Mac unless the
App Store is a hard requirement. It's the supported path and avoids all of the
above.

---

## 4. What's yours vs. mine

I can set up build config, entitlements, and CI. I **cannot** (these are yours):

- Create the **Apple Developer** / **Microsoft Partner Center** accounts or pay
  memberships.
- Hold or use your **signing certificates / Apple ID credentials**.
- **Submit** builds to either store.

Tell me which target you want to pursue first and I'll wire up the concrete build
configuration and a CI workflow for it.
