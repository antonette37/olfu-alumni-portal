import { BASE_URL } from "../constants/colors";

/**
 * Profile images for mobile.
 * Prefer profile_photo.php?user_id= (server resolves profile + ID card fallbacks).
 */
export function resolveProfileImageUrl(photo, userId = null, profileImageData = null) {
  if (
    profileImageData &&
    typeof profileImageData === "string" &&
    profileImageData.startsWith("data:image/")
  ) {
    return profileImageData;
  }

  const uid = parseInt(String(userId ?? "").replace(/^token_/i, ""), 10);

  const fromApi = normalizePhotoField(photo);
  if (fromApi) {
    if (fromApi.includes("profile_photo.php") || /^https?:\/\//i.test(fromApi)) {
      return fromApi;
    }
  }

  if (profileImageData && typeof profileImageData === "string") {
    return profileImageData;
  }

  if (uid > 0) {
    return `${BASE_URL}/api/mobile/profile_photo.php?user_id=${uid}`;
  }

  return fromApi;
}

function normalizePhotoField(photo) {
  if (!photo || typeof photo !== "string") return null;
  const p = photo.trim();
  if (!p || p.toLowerCase() === "default-avatar.png") return null;
  if (p.startsWith("data:image/")) return p;
  if (/^https?:\/\//i.test(p)) return p;
  if (p.startsWith("//")) return `https:${p}`;

  if (p.includes("serve_profile_image.php") || p.includes("profile_photo.php")) {
    return p.startsWith("http") ? p : `${BASE_URL}/${p.replace(/^\//, "")}`;
  }

  const base = p
    .replace(/^uploads\//i, "")
    .replace(/^photos\//i, "")
    .replace(/^\//, "");
  const name = base.includes("/") ? base.split("/").pop() : base;
  if (!name) return null;

  return `${BASE_URL}/serve_profile_image.php?img=${encodeURIComponent(name)}`;
}
