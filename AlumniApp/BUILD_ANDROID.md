# Build a shareable Android APK (EAS Build)

This produces an **`.apk` file** anyone can install on Android (no Expo Go required). The app still uses your live API at **https://ccsolfualumni.sbs**.

## One-time setup

### 1. Install dependencies

```bash
cd AlumniApp
npm install
```

### 2. Create a free Expo account

Sign up at https://expo.dev if you do not have one.

### 3. Log in and link the project

```bash
npx eas login
npx eas init
```

`eas init` creates an Expo project ID in `app.json`. Accept the prompts (use the existing slug `olfu-ccs-alumni`).

## Build the APK (cloud — recommended)

```bash
npm run build:apk
```

Or:

```bash
npx eas build -p android --profile preview
```

- Build runs on Expo servers (about 10–20 minutes).
- When finished, open the link in the terminal or at https://expo.dev → your project → **Builds**.
- Download the **`.apk`** file.

## Share with other Android users

1. Send them the `.apk` (Google Drive, email, etc.).
2. On their phone: **Settings → Security → Install unknown apps** (allow Files or Chrome).
3. Open the APK and tap **Install**.

## Install on your own phone (without Expo Go)

Same APK — uninstall Expo Go test session optional; install the standalone **CCS Alumni Portal** app.

## Play Store (later)

For Google Play, use the production profile (AAB, not APK):

```bash
npm run build:play
```

You need a Google Play Developer account ($25 one-time) and a service account JSON for `eas submit` (see `eas.json` submit section).

## Profiles in `eas.json`

| Profile | Output | Use |
|---------|--------|-----|
| `preview` | **APK** | Share with testers, other devs, alumni |
| `production` | **AAB** | Google Play Store |
| `development` | APK + dev client | Advanced debugging only |

## Troubleshooting

| Issue | Fix |
|-------|-----|
| `eas: command not found` | Use `npx eas build ...` |
| Build asks for credentials | Let EAS generate a new Android keystore (yes) |
| App cannot reach API | Phone needs internet; Hostinger `public_html` API must be up |
| Login fails on APK | Use a real **active/approved** alumni account |

## What you do **not** upload to Hostinger

- The `.apk` file — distribute directly or via Play Store.
- `AlumniApp/` folder — stays on your PC for building only.

Keep uploading **`public_html`** PHP files to Hostinger for the database/API.
