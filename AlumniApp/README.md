# OLFU CCS Alumni Portal — Expo (React Native)

Mobile app scaffold matching `OLFUAlumniApp.jsx` (forest green `#0d2e18`, gold `#b8922a`).

## Flow

`Splash` → `Login` → `RegType` → `Register` (new | legacy, 5 steps) → `Success` → `Login` → main tabs (Home, Directory, Events, Messages, Profile)

- **New alumni:** Student ID front/back upload; Alumni ID assigned after approval.
- **Legacy alumni:** Alumni Card upload + 8–16 digit Alumni ID number.

## API

Base URL: `https://ccsolfualumni.sbs` (live Hostinger API under `/api/mobile/`)

| Module | Endpoints |
|--------|-----------|
| `src/api/auth.js` | `POST /api/mobile/login.php`, `POST /api/mobile/registration.php` |
| `src/api/alumni.js` | `GET /api/mobile/dashboard.php`, `directory.php`, `events.php`, `conversations.php` |

Demo login (offline fallback): `demo@olfu.edu.ph` / `password123`

## Run (development)

```bash
cd AlumniApp
npm install
npx expo start
```

## Build installable APK (other Android phones)

See **[BUILD_ANDROID.md](./BUILD_ANDROID.md)** — uses Expo EAS to produce a shareable `.apk` (no Expo Go).

Add placeholder assets under `assets/` (`icon.png`, `splash.png`, `adaptive-icon.png`) or remove those paths from `app.json` for a quick start.

## Structure

```
AlumniApp/
  App.js
  app.json
  package.json
  src/
    api/
    components/
    constants/
    navigation/
    screens/
```
