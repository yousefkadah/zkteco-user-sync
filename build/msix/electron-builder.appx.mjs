// Microsoft Store (MSIX) build config.
//
// Derived from NativePHP's bundled config at
//   vendor/nativephp/desktop/resources/electron/electron-builder.mjs
// with the Windows target switched from NSIS to `appx` (a full-trust MSIX:
// electron-builder fixes EntryPoint="Windows.FullTrustApplication" and always
// injects the runFullTrust capability, so the bundled PHP binary + child
// processes + UDP 4370 keep working).
//
// The GitHub Actions "Build MSIX" workflow copies this over the vendored file
// before `php artisan native:build win`. Re-sync with the vendored file if you
// upgrade nativephp/desktop.

import { exec } from 'child_process';
import { join } from 'path';

const appUrl = process.env.APP_URL;
const appId = process.env.NATIVEPHP_APP_ID;
const appName = process.env.NATIVEPHP_APP_NAME;
const appAuthor = process.env.NATIVEPHP_APP_AUTHOR;
const fileName = process.env.NATIVEPHP_APP_FILENAME;
const appVersion = process.env.NATIVEPHP_APP_VERSION;
const appCopyright = process.env.NATIVEPHP_APP_COPYRIGHT;
const deepLinkProtocol = process.env.NATIVEPHP_DEEPLINK_SCHEME;
const updaterEnabled = process.env.NATIVEPHP_UPDATER_ENABLED === 'true';
const deleteAppDataOnUninstall = process.env.NATIVEPHP_NSIS_DELETE_APP_DATA === 'true';

const isWindows = process.argv.includes('--win');
const isLinux = process.argv.includes('--linux');
const isDarwin = process.argv.includes('--mac');

let targetOs;
if (isWindows) targetOs = 'win';
if (isLinux) targetOs = 'linux';
if (isDarwin) targetOs = 'mac';

let updaterConfig = {};
try {
    updaterConfig = JSON.parse(process.env.NATIVEPHP_UPDATER_CONFIG);
} catch {
    updaterConfig = {};
}

export default {
    appId: appId,
    productName: appName,
    copyright: appCopyright,
    directories: {
        buildResources: 'build',
        output: join(process.env.APP_PATH, 'nativephp', 'electron', 'dist'),
    },
    files: [
        '!**/.vscode/*',
        '!src/*',
        '!dist/*',
        '!electron.vite.config.{js,ts,mjs,cjs}',
        '!{.eslintignore,.eslintrc.cjs,.prettierignore,.prettierrc.yaml,dev-app-update.yml,CHANGELOG.md,README.md}',
        '!{.env,.env.*,.npmrc,pnpm-lock.yaml}',
    ],
    beforePack: async (context) => {
        const arch = { 1: 'x64', 3: 'arm64' }[context.arch];

        if (arch === undefined) {
            console.error('Cannot build PHP for unsupported architecture');
            process.exit(1);
        }

        console.log(`  • building php binary - exec php.js --${targetOs} --${arch}`);
        exec(`node php.js --${targetOs} --${arch}`);
    },
    afterSign: 'build/notarize.js',
    win: {
        executableName: fileName,
        target: ['appx'],
    },
    // Microsoft Store identity — set these from Partner Center → Product identity.
    // For a Store submission you upload UNSIGNED (the Store re-signs); the values
    // below must still match your reserved product identity.
    appx: {
        identityName: process.env.MSIX_IDENTITY_NAME || appId,
        publisher: process.env.MSIX_PUBLISHER, // e.g. "CN=1234ABCD-....." from Partner Center
        publisherDisplayName: process.env.MSIX_PUBLISHER_DISPLAY_NAME || appAuthor,
        applicationId: 'App',
        // Store-compliant name: Microsoft rejects "ZKTeco" (another company's
        // trademark) in the product name. The direct .exe/.dmg/.AppImage keep
        // "ZKTeco User Sync"; only the Store listing + this MSIX use this name.
        displayName: 'Fullness Device Sync',
        backgroundColor: '#0b1220',
        showNameOnTiles: true,
        languages: ['en-US'],
    },
    nsis: {
        artifactName: appName + '-${version}-setup.${ext}',
        shortcutName: '${productName}',
        uninstallDisplayName: '${productName}',
        createDesktopShortcut: 'always',
        deleteAppDataOnUninstall: deleteAppDataOnUninstall,
    },
    // Only register a URL-protocol handler when a deep-link scheme is configured.
    // An empty <uap:Protocol Name=""> makes MakeAppx reject the MSIX manifest
    // (0x80080204 "the package manifest is not valid"); NSIS/dmg tolerate it, but
    // the Store build must omit it entirely when unused.
    ...(deepLinkProtocol
        ? {
            protocols: {
                name: deepLinkProtocol,
                schemes: [deepLinkProtocol],
            },
        }
        : {}),
    mac: {
        entitlementsInherit: 'build/entitlements.mac.plist',
        artifactName: appName + '-${version}-${arch}.${ext}',
    },
    dmg: {
        artifactName: appName + '-${version}-${arch}.${ext}',
    },
    linux: {
        target: ['AppImage', 'deb'],
        maintainer: appUrl,
        category: 'Utility',
    },
    appImage: {
        artifactName: appName + '-${version}.${ext}',
    },
    npmRebuild: false,
    extraMetadata: {
        name: fileName,
        homepage: appUrl,
        version: appVersion,
        author: appAuthor,
    },
    extraResources: [
        {
            from: process.env.NATIVEPHP_BUILD_PATH,
            to: 'build',
            filter: ['**/*', '!{.git}'],
        },
    ],
    extraFiles: [
        {
            from: join(process.env.APP_PATH, 'extras'),
            to: 'extras',
            filter: ['**/*'],
        },
    ],
    ...(updaterEnabled ? { publish: updaterConfig } : {}),
};
