import AsyncStorage from "@react-native-async-storage/async-storage";
import { API_MOBILE } from "../constants/colors";
import { fetchFullProfile } from "./alumni";

const TOKEN_KEY = "alumni_token";
const USER_KEY = "alumni_user";

export async function getStoredToken() {
  return AsyncStorage.getItem(TOKEN_KEY);
}

export async function getStoredUser() {
  const raw = await AsyncStorage.getItem(USER_KEY);
  return raw ? JSON.parse(raw) : null;
}

export async function clearSession() {
  await AsyncStorage.multiRemove([TOKEN_KEY, USER_KEY]);
}

async function saveSession(token, user) {
  await AsyncStorage.setItem(TOKEN_KEY, token);
  await AsyncStorage.setItem(USER_KEY, JSON.stringify(user));
}

/** Normalize API user shape to app fields */
export function normalizeUser(apiUser) {
  if (!apiUser) return null;
  return {
    id: Number(apiUser.id) || apiUser.id,
    firstname: apiUser.firstname ?? "",
    lastname: apiUser.lastname ?? "",
    email: apiUser.email ?? "",
    program: apiUser.course ?? apiUser.program ?? "",
    year_graduated: apiUser.batch ?? apiUser.year_graduated ?? "",
    personal_contact: apiUser.phone ?? apiUser.personal_contact ?? "",
    address: apiUser.address ?? "",
    position: apiUser.position ?? "",
    company: apiUser.company ?? "",
    employment_status: apiUser.employment_status ?? apiUser.currentlyEmployed ?? "",
    profile_completion: apiUser.profile_completion ?? 50,
    skills: apiUser.skills ?? [],
    profile_image: apiUser.profile_image ?? null,
    profile_image_data: apiUser.profile_image_data ?? null,
    photo: apiUser.photo ?? null,
    status: apiUser.status,
  };
}

export function authHeader(token) {
  if (!token || token === "admin_token") return { Accept: "application/json" };
  const t = token.startsWith("token_") ? token : `token_${token}`;
  return {
    Authorization: `Bearer ${t}`,
    "X-Auth-Token": t,
    Accept: "application/json",
  };
}

/**
 * POST /api/mobile/login.php — JSON { email, password }
 */
export async function login(email, password) {
  const res = await fetch(`${API_MOBILE}/login.php`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ email: email.trim(), password }),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok || !data.success) {
    throw new Error(data.message || "Invalid email or password");
  }
  const token = data.token || `token_${data.user?.id}`;
  let user = normalizeUser(data.user);
  await saveSession(token, user);
  try {
    user = await fetchFullProfile();
    await saveSession(token, user);
  } catch {
    /* keep basic user from login response */
  }
  return { token, user };
}

/**
 * POST /api/mobile/registration.php — multipart (field names match web/mobile PHP)
 */
export async function register(formData) {
  const res = await fetch(`${API_MOBILE}/registration.php`, {
    method: "POST",
    body: formData,
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  });
  const text = await res.text();
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    if (res.ok) return { success: true, message: "Registration submitted" };
    throw new Error(text?.slice(0, 200) || "Registration failed. Please try again.");
  }
  if (!data.success) {
    throw new Error(data.message || "Registration failed");
  }
  return data;
}

export async function persistUser(user) {
  const token = await getStoredToken();
  if (token && user) await saveSession(token, user);
}
