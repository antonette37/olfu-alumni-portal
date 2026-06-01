import { API_MOBILE } from "../constants/colors";
import { authHeader, getStoredToken, normalizeUser, clearSession } from "./auth";

function resolveToken(tokenHint, userId) {
  if (tokenHint && tokenHint !== "admin_token") return tokenHint;
  if (userId) return `token_${userId}`;
  return null;
}

function buildApiUrl(path, token) {
  const clean = String(path).replace(/^\//, "");
  let url = `${API_MOBILE}/${clean}`;
  if (token && token !== "admin_token") {
    const t = String(token).startsWith("token_") ? String(token) : `token_${token}`;
    const sep = url.includes("?") ? "&" : "?";
    url += `${sep}access_token=${encodeURIComponent(t)}`;
  }
  return url;
}

function parseApiError(res, url, text, data) {
  const snippet = (text || "").slice(0, 120).toLowerCase();
  if (data?.message) return data.message;
  if (snippet.includes("unauthorized") || snippet.includes("log in")) {
    return "Please log in again.";
  }
  if (res.status === 401) return "Please log in again.";
  if (res.status === 404) {
    if (snippet.includes("<!doctype") || snippet.includes("<html")) {
      return `Server error page (404) — check dashboard.php on Hostinger. URL: ${url}`;
    }
    return `Network or server issue (404). Pull to refresh after logging in. URL: ${url}`;
  }
  return `Request failed (${res.status})`;
}

async function apiGet(path, tokenHint = null, userId = null) {
  let token = resolveToken(tokenHint, userId);
  if (!token) {
    token = await getStoredToken();
  }
  const url = buildApiUrl(path, token);
  const res = await fetch(url, {
    method: "GET",
    headers: authHeader(token),
  });
  const text = await res.text().catch(() => "");
  let data = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = null;
  }

  let status = res.status;
  if (!res.ok && data?.message && String(data.message).toLowerCase().includes("unauthorized")) {
    status = 401;
  }

  if (!res.ok) {
    const msg = parseApiError({ status }, url, text, data);
    const err = new Error(msg);
    err.status = status;
    err.url = url;
    if (status === 401) {
      await clearSession();
    }
    throw err;
  }
  if (data?.success === false) {
    const err = new Error(data.message || "Request failed");
    err.status = 401;
    err.url = url;
    await clearSession();
    throw err;
  }
  return data;
}

async function apiPost(path, body) {
  const token = await getStoredToken();
  const url = buildApiUrl(path, token);
  const res = await fetch(url, {
    method: "POST",
    headers: {
      ...authHeader(token),
      "Content-Type": "application/json",
    },
    body: JSON.stringify(body),
  });
  const text = await res.text().catch(() => "");
  let data = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = null;
  }
  if (!res.ok || data?.success === false) {
    const msg = parseApiError({ status: res.status }, url, text, data);
    const err = new Error(msg);
    err.status = res.status;
    if (res.status === 401) await clearSession();
    throw err;
  }
  return data;
}

/** Map live event row (api/mobile/events.php) to UI shape */
export function mapEvent(row) {
  const type = row.normalized_event_type || row.type || row.event_type || "General";
  const location = row.resolved_location || row.location || row.venue || "";
  const registered = Number(row.registered_count) || 0;
  const max = Number(row.max_attendees ?? row.capacity ?? row.max_participants) || 0;
  return {
    id: row.id,
    title: row.title || "",
    event_date: (row.event_date || "").substring(0, 10),
    event_time: row.event_time || "",
    location,
    type,
    registered_count: registered,
    spots_left: max > 0 ? Math.max(0, max - registered) : null,
    is_registered: Number(row.is_registered) > 0 ? 1 : 0,
    description: row.description || "",
  };
}

function mapAnnouncement(a) {
  const img = a.image_url || a.image;
  return {
    id: a.id,
    title: a.title,
    content: a.content || a.description || "",
    created_at: a.created_at,
    image_url: img && String(img).startsWith("http") ? img : null,
    has_image: !!(img && String(img).trim() !== ""),
  };
}

function mapJob(j) {
  return {
    id: j.id,
    title: j.title,
    company: j.company,
    location: j.location || "",
    job_type: j.job_type || j.type || j.employment_type || "",
    salary_range: j.salary_range || "",
    description: j.description || "",
    requirements: j.requirements || "",
    posted_date: j.posted_date || j.created_at || "",
    has_applied: Number(j.has_applied) > 0 ? 1 : 0,
    tags: j.tags ? (Array.isArray(j.tags) ? j.tags : String(j.tags).split(",")) : [],
    type: j.type || j.employment_type || "Full-time",
  };
}

function mapDirectoryUser(row) {
  const initials = `${(row.firstname || "?")[0]}${(row.lastname || "?")[0]}`.toUpperCase();
  const colors = [
    ["#1b5e35", "#2d7a4f"],
    ["#b8922a", "#e0b84a"],
    ["#2d7a4f", "#3d9966"],
    ["#0d2e18", "#2d7a4f"],
  ];
  return {
    id: row.id,
    firstname: row.firstname,
    lastname: row.lastname,
    program: row.course ?? row.program ?? "",
    year_graduated: row.batch ?? row.year_graduated ?? "",
    position: row.position ?? "Alumni",
    company: row.company ?? "",
    initials,
    color: colors[row.id % colors.length],
    profile_image: row.profile_image ?? null,
    photo: row.photo ?? null,
  };
}

function formatMessageTime(iso) {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  const diff = Date.now() - d.getTime();
  if (diff < 60000) return "Just now";
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
  return d.toLocaleDateString("en-PH", { month: "short", day: "numeric" });
}

function mapConversation(c) {
  const ou = c.other_user || {};
  const initials = `${(ou.firstname || "?")[0]}${(ou.lastname || "?")[0]}`.toUpperCase();
  const colors = [
    ["#1b5e35", "#2d7a4f"],
    ["#b8922a", "#e0b84a"],
    ["#2d7a4f", "#3d9966"],
  ];
  return {
    id: c.id,
    name: `${ou.firstname || ""} ${ou.lastname || ""}`.trim() || "Alumni",
    initials,
    color: colors[c.id % colors.length],
    preview: c.last_message || "",
    time: formatMessageTime(c.last_message_time),
    unread: c.unread_count || 0,
    online: false,
    other_user_id: ou.id,
    profile_image: ou.profile_image ?? null,
    profile_image_data: ou.profile_image_data ?? null,
    photo: ou.photo ?? null,
  };
}

/** GET /api/mobile/dashboard.php — mirrors al_dashboard.php */
export async function fetchDashboard(userId = null, tokenHint = null) {
  const endpoints = ["dashboard.php", "alumni_dashboard.php"];
  let lastErr = null;
  let data = null;

  for (const endpoint of endpoints) {
    try {
      data = await apiGet(endpoint, tokenHint, userId);
      break;
    } catch (e) {
      lastErr = e;
      if (e.status !== 404) throw e;
    }
  }

  if (!data) {
    throw lastErr || new Error("Dashboard unavailable");
  }
  if (data?.success === false) throw new Error(data.message || "Dashboard unavailable");

  const eventsRaw = data.upcoming_events || data.events || [];
  const userRaw = data.user ? { ...data.user } : null;
  if (userRaw) {
    delete userRaw.profile_image_data;
  }
  const user = userRaw ? normalizeUser(userRaw) : null;
  if (user && data.user?.profile_completion != null) {
    user.profile_completion = data.user.profile_completion;
  }

  return {
    user,
    announcements: (data.announcements || []).map(mapAnnouncement),
    events: eventsRaw.map(mapEvent),
    jobs: (data.jobs || []).map(mapJob),
    skills: data.skills || [],
    recent_activities: data.recent_activities || [],
    notifications: data.notifications || [],
    unread_notifications: data.unread_notifications ?? 0,
    job_applications_count: data.job_applications_count ?? 0,
    upcoming_events_count: data.upcoming_events_count ?? eventsRaw.length,
  };
}

/** GET /api/mobile/directory.php — returns JSON array */
export async function fetchDirectory(search = "") {
  const q = search ? `?search=${encodeURIComponent(search)}` : "";
  const data = await apiGet(`directory.php${q}`);
  if (data?.success === false) throw new Error(data.message || "Directory unavailable");
  const list = Array.isArray(data) ? data : data.users || [];
  return list.map(mapDirectoryUser);
}

/** GET /api/mobile/events.php — returns JSON array */
export async function fetchEvents() {
  const data = await apiGet("events.php");
  if (data?.success === false) throw new Error(data.message || "Events unavailable");
  const list = Array.isArray(data) ? data : data.events || [];
  return list.map(mapEvent);
}

/** POST /api/mobile/send_message.php */
export async function sendMessage(receiverId, message) {
  return apiPost("send_message.php", {
    receiver_id: Number(receiverId),
    message: String(message).trim(),
  });
}

/** GET /api/mobile/conversations.php — returns JSON array */
export async function fetchConversations() {
  const data = await apiGet("conversations.php");
  if (data?.success === false) throw new Error(data.message || "Messages unavailable");
  const list = Array.isArray(data) ? data : data.conversations || [];
  return list.map(mapConversation);
}

/** GET /api/mobile/profile.php — single user object */
export async function fetchProfile() {
  const data = await apiGet("profile.php");
  if (data?.success === false) throw new Error(data.message || "Profile unavailable");
  return normalizeUser(data);
}

/** GET /api/mobile/profile.php?id= — another alumnus (directory detail) */
export async function fetchAlumniProfile(alumniId) {
  const data = await apiGet(`profile.php?id=${encodeURIComponent(alumniId)}`);
  if (data?.success === false) throw new Error(data.message || "Profile unavailable");
  return normalizeUser(data);
}

/** GET /api/mobile/jobs.php — career center job board */
export async function fetchJobs() {
  const data = await apiGet("jobs.php");
  if (data?.success === false) throw new Error(data.message || "Jobs unavailable");
  return (data.jobs || []).map(mapJob);
}

/** POST /api/mobile/apply_job.php */
export async function applyToJob(jobId, message = "") {
  return apiPost("apply_job.php", {
    job_id: Number(jobId),
    message: String(message || "").trim(),
  });
}

/** POST /api/mobile/register_event.php */
export async function registerForEvent(eventId) {
  return apiPost("register_event.php", { event_id: Number(eventId) });
}

/** GET /api/mobile/career.php */
export async function fetchCareer() {
  const data = await apiGet("career.php");
  if (data?.success === false) throw new Error(data.message || "Career unavailable");
  const latest = (data.career_history || [])[0];
  const skills = (data.skills || []).map((s) => s.skill_name || s.name || s).filter(Boolean);
  return {
    position: latest?.position || "",
    company: latest?.company || "",
    skills,
    career_history: data.career_history || [],
    achievements: data.achievements || [],
  };
}

/** Full profile like web: profile + career merged */
export async function fetchFullProfile() {
  const profile = await fetchProfile();
  let career = { position: "", company: "", skills: [] };
  try {
    career = await fetchCareer();
  } catch {
    /* career.php optional — profile still loads */
  }
  return {
    ...profile,
    position: career.position || profile.position,
    company: career.company || profile.company,
    skills: career.skills?.length ? career.skills : profile.skills,
    profile_completion: career.skills?.length
      ? Math.min(95, 50 + career.skills.length * 5)
      : profile.profile_completion,
  };
}

/**
 * POST /api/mobile/profile_update.php — multipart fields + optional photo
 * @param {object} fields — text fields to update
 * @param {{ uri: string, name?: string, type?: string } | null} photoAsset
 */
export async function updateProfile(fields, photoAsset = null) {
  const token = await getStoredToken();
  const form = new FormData();

  const textKeys = [
    "firstname",
    "lastname",
    "personal_contact",
    "address",
    "year_graduated",
    "program",
    "employment_status",
    "company",
    "position",
  ];
  for (const key of textKeys) {
    if (fields[key] !== undefined && fields[key] !== null) {
      form.append(key, String(fields[key]).trim());
    }
  }

  if (photoAsset?.uri) {
    const name = photoAsset.name || `profile_${Date.now()}.jpg`;
    const type = photoAsset.type || (name.toLowerCase().endsWith(".png") ? "image/png" : "image/jpeg");
    form.append("photo", { uri: photoAsset.uri, name, type });
  }

  const res = await fetch(`${API_MOBILE}/profile_update.php`, {
    method: "POST",
    headers: authHeader(token),
    body: form,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok || !data.success) {
    throw new Error(data.message || `Update failed (${res.status})`);
  }
  return normalizeUser(data.user);
}
