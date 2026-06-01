/** Design tokens — OLFU CCS Alumni Portal */
export const T = {
  forest: "#0d2e18",
  pine: "#133d23",
  leaf: "#1b5e35",
  moss: "#2d7a4f",
  fern: "#3d9966",
  mist: "#e8f2ec",
  snow: "#f5f9f6",
  cream: "#faf8f3",
  white: "#ffffff",
  gold: "#b8922a",
  goldLt: "#e0b84a",
  goldPale: "#fdf5e0",
  ink: "#0c1a10",
  charcoal: "#2a3d30",
  slate: "#4a6355",
  silver: "#8aab96",
  fog: "#c8ddd2",
  danger: "#e24b4a",
  dangerPale: "#fef2f2",
  blue: "#2563eb",
  bluePale: "#e8f0fb",
  amber: "#d97706",
  amberPale: "#fffbeb",
};

export const shadow = {
  shadowColor: T.forest,
  shadowOffset: { width: 0, height: 4 },
  shadowOpacity: 0.08,
  shadowRadius: 8,
  elevation: 3,
};

/** Hostinger web root is public_html — do not put "public_html" in the URL */
export const BASE_URL = "https://ccsolfualumni.sbs";
export const API_MOBILE = `${BASE_URL}/api/mobile`;
