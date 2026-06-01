import { useCallback, useState } from "react";
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
} from "react-native";
import { Image } from "expo-image";
import { useFocusEffect, useNavigation } from "@react-navigation/native";
import { T, shadow } from "../constants/colors";
import Badge from "../components/Badge";
import Avatar from "../components/Avatar";
import AppIcon from "../components/AppIcon";
import { ICONS } from "../constants/icons";
import { fetchDashboard } from "../api/alumni";
import { useAuth } from "../context/AuthContext";

function SectionLabel({ children }) {
  return (
    <View style={styles.sectionLabel}>
      <View style={styles.sectionBar} />
      <Text style={styles.sectionText}>{children}</Text>
    </View>
  );
}

function greeting() {
  const h = new Date().getHours();
  if (h < 12) return "Good morning";
  if (h < 18) return "Good afternoon";
  return "Good evening";
}

function formatActivityWhen(iso) {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  const diff = Math.floor((Date.now() - d.getTime()) / 86400000);
  if (diff === 0) return "Today";
  if (diff === 1) return "Yesterday";
  if (diff < 7) return `${diff} days ago`;
  return d.toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
}

function navigateRoot(navigation, screen, params) {
  let nav = navigation;
  while (nav?.getParent?.()) {
    nav = nav.getParent();
  }
  nav?.navigate(screen, params);
}

export default function DashboardScreen() {
  const navigation = useNavigation();
  const { user, token, setUser } = useAuth();
  const [data, setData] = useState({
    announcements: [],
    events: [],
    jobs: [],
    skills: [],
    recent_activities: [],
    job_applications_count: 0,
    upcoming_events_count: 0,
    unread_notifications: 0,
  });
  const [error, setError] = useState("");
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    setError("");
    try {
      const dash = await fetchDashboard(user?.id, token);
      if (dash.user) setUser(dash.user);
      setData(dash);
    } catch (e) {
      const msg = e.message || "Could not load dashboard";
      setError(
        msg.includes("404") || msg.toLowerCase().includes("session") || msg.toLowerCase().includes("log in")
          ? `${msg} — try logging out and back in.`
          : msg
      );
    }
  }, [setUser, user?.id, token]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load])
  );

  const onRefresh = async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  };

  if (!user) {
    return (
      <View style={styles.center}>
        <Text style={styles.muted}>Loading profile…</Text>
      </View>
    );
  }

  const programShort = (user.program || "").split(" ").pop() || user.program || "—";
  const initials = `${(user.firstname || "?")[0]}${(user.lastname || "?")[0]}`.toUpperCase();
  const upcoming = data.events || [];
  const profilePct = user.profile_completion ?? 0;

  return (
    <ScrollView
      style={styles.flex}
      contentContainerStyle={styles.padBottom}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={T.forest} />
      }
    >
      {error ? (
        <View style={styles.errBanner}>
          <Text style={styles.errText}>⚠ {error}</Text>
        </View>
      ) : null}

      <View style={styles.hero}>
        <View style={styles.heroGlow} />
        <Avatar
          initials={initials}
          size={52}
          uri={user.profile_image}
          photo={user.photo}
          userId={user.id}
          style={styles.heroAvatar}
        />
        <Text style={styles.greet}>{greeting()},</Text>
        <Text style={styles.welcome}>
          Welcome back, <Text style={styles.nameEm}>{user.firstname}!</Text>
        </Text>
        <Text style={styles.heroSub}>Here's what's happening in your alumni community today.</Text>
        <View style={styles.chips}>
          <View style={styles.chip}>
            <Text style={styles.chipText}>🎓 Class of {user.year_graduated || "—"}</Text>
          </View>
          <View style={styles.chip}>
            <Text style={styles.chipText}>🏛 {programShort}</Text>
          </View>
          {user.company ? (
            <View style={styles.chip}>
              <Text style={styles.chipText}>🏢 {user.company}</Text>
            </View>
          ) : null}
        </View>
      </View>

      <View style={styles.stats}>
        {[
          {
            icon: ICONS.upcomingEvents,
            num: data.upcoming_events_count ?? upcoming.length,
            label: "Upcoming Events",
            bg: T.mist,
          },
          {
            icon: ICONS.jobApplications,
            num: data.job_applications_count ?? 0,
            label: "Job Applications",
            bg: T.bluePale,
          },
          {
            icon: ICONS.notifications,
            num: data.unread_notifications ?? 0,
            label: "Notifications",
            bg: T.goldPale,
          },
        ].map((s) => (
          <View key={s.label} style={[styles.statCard, shadow]}>
            <View style={[styles.statIcon, { backgroundColor: s.bg }]}>
              <AppIcon name={s.icon} size={18} color={T.forest} />
            </View>
            <Text style={styles.statNum}>{s.num}</Text>
            <Text style={styles.statLbl}>{s.label}</Text>
          </View>
        ))}
      </View>

      <View style={styles.section}>
        <SectionLabel>Latest Announcements</SectionLabel>
        {(data.announcements || []).length === 0 && !error ? (
          <Text style={styles.empty}>No announcements at the moment.</Text>
        ) : null}
        {(data.announcements || []).map((ann) => (
          <View key={ann.id} style={[styles.card, shadow]}>
            {ann.image_url ? (
              <Image
                source={{ uri: ann.image_url }}
                style={styles.cardImgPhoto}
                contentFit="cover"
                onError={() => {}}
              />
            ) : ann.has_image ? (
              <View style={styles.cardImg}>
                <AppIcon name={ICONS.megaphone} size={28} color="rgba(255,255,255,0.5)" />
              </View>
            ) : null}
            <View style={styles.cardBody}>
              <Text style={styles.cardTitle}>{ann.title}</Text>
              <Text style={styles.cardContent} numberOfLines={3}>
                {ann.content}
              </Text>
              <View style={styles.cardFoot}>
                <Text style={styles.date}>
                  {ann.created_at
                    ? new Date(ann.created_at).toLocaleDateString("en-PH", {
                        month: "short",
                        day: "numeric",
                        year: "numeric",
                      })
                    : ""}
                </Text>
                <TouchableOpacity>
                  <Text style={styles.more}>Read more →</Text>
                </TouchableOpacity>
              </View>
            </View>
          </View>
        ))}
      </View>

      {(data.skills || []).length > 0 ? (
        <View style={styles.section}>
          <SectionLabel>Skills & Proficiency</SectionLabel>
          {(data.skills || []).map((sk, i) => (
            <View key={`${sk.skill_name}-${i}`} style={[styles.skillCard, shadow]}>
              <View style={styles.skillRow}>
                <Text style={styles.skillName}>{sk.skill_name}</Text>
                <Text style={styles.skillPct}>{sk.proficiency}%</Text>
              </View>
              <View style={styles.skillTrack}>
                <View style={[styles.skillFill, { width: `${Math.min(100, sk.proficiency || 0)}%` }]} />
              </View>
            </View>
          ))}
        </View>
      ) : null}

      <View style={styles.section}>
        <SectionLabel>Upcoming Events</SectionLabel>
        {upcoming.length === 0 && !error ? (
          <Text style={styles.empty}>No upcoming events. Check back soon.</Text>
        ) : null}
        {upcoming.map((ev) => {
          const d = new Date(ev.event_date);
          return (
            <TouchableOpacity
              key={ev.id}
              style={[styles.eventRow, shadow]}
              activeOpacity={0.85}
              onPress={() => navigateRoot(navigation, "EventDetail", { event: ev })}
            >
              <View style={styles.dateBox}>
                <Text style={styles.dateDay}>{Number.isNaN(d.getTime()) ? "—" : d.getDate()}</Text>
                <Text style={styles.dateMon}>
                  {Number.isNaN(d.getTime())
                    ? ""
                    : d.toLocaleString("en", { month: "short" }).toUpperCase()}
                </Text>
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.eventTitle}>{ev.title}</Text>
                <View style={styles.eventLocRow}>
                  {ev.location ? (
                    <>
                      <AppIcon name={ICONS.location} size={12} color={T.silver} />
                      <Text style={styles.eventLoc} numberOfLines={1}>
                        {ev.location}
                      </Text>
                    </>
                  ) : null}
                </View>
              </View>
              <Badge>{ev.type}</Badge>
            </TouchableOpacity>
          );
        })}
      </View>

      <View style={styles.section}>
        <SectionLabel>Recent Job Opportunities</SectionLabel>
        {(data.jobs || []).length === 0 && !error ? (
          <Text style={styles.empty}>No jobs posted yet.</Text>
        ) : null}
        {(data.jobs || []).map((job) => (
          <TouchableOpacity
            key={job.id}
            style={[styles.jobCard, shadow]}
            activeOpacity={0.85}
            onPress={() => navigateRoot(navigation, "Career")}
          >
            <Text style={styles.jobTitle}>{job.title}</Text>
            <Text style={styles.jobCo}>
              {job.company}
              {job.location ? ` · ${job.location}` : ""}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      <View style={styles.section}>
        <SectionLabel>Your Profile</SectionLabel>
        <View style={[styles.profileCard, shadow]}>
          <View style={styles.profileRow}>
            <Avatar
              initials={initials}
              size={48}
              uri={user.profile_image}
              photo={user.photo}
              userId={user.id}
            />
            <View style={{ flex: 1 }}>
              <Text style={styles.profileName}>
                {user.firstname} {user.lastname}
              </Text>
              <Text style={styles.profileMeta}>{user.position || "—"}</Text>
              {user.company ? <Text style={styles.profileMeta}>{user.company}</Text> : null}
            </View>
          </View>
          <View style={styles.progressWrap}>
            <View style={styles.progressHead}>
              <Text style={styles.progressLabel}>Profile Completion</Text>
              <Text style={styles.progressPct}>{profilePct}%</Text>
            </View>
            <View style={styles.progressTrack}>
              <View style={[styles.progressFill, { width: `${profilePct}%` }]} />
            </View>
            <Text style={styles.progressHint}>Complete your profile to boost visibility</Text>
          </View>
        </View>
      </View>

      <View style={[styles.section, { marginBottom: 24 }]}>
        <SectionLabel>Recent Activity</SectionLabel>
        {(data.recent_activities || []).length === 0 && !error ? (
          <Text style={styles.empty}>Your activities will appear here.</Text>
        ) : null}
        {(data.recent_activities || []).map((act, i) => (
          <View key={`${act.type}-${i}`} style={[styles.actRow, shadow]}>
            <View
              style={[
                styles.actDot,
                act.color === "blue" ? styles.actBlue : styles.actGreen,
              ]}
            >
              <AppIcon
                name={act.icon === "briefcase" ? ICONS.jobApplications : ICONS.events}
                size={18}
                color={act.color === "blue" ? T.blue : T.moss}
              />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.actTitle}>{act.activity_title}</Text>
              <Text style={styles.actTime}>{formatActivityWhen(act.activity_date)}</Text>
            </View>
          </View>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: T.cream },
  errBanner: { margin: 14, padding: 12, backgroundColor: T.dangerPale, borderRadius: 12 },
  errText: { fontSize: 10, color: T.danger },
  empty: { fontSize: 10, color: T.silver, textAlign: "center", paddingVertical: 12 },
  padBottom: { paddingBottom: 16 },
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  muted: { color: T.silver },
  hero: {
    backgroundColor: T.forest,
    padding: 20,
    paddingBottom: 24,
    overflow: "hidden",
  },
  heroAvatar: {
    marginBottom: 10,
    borderWidth: 2,
    borderColor: "rgba(255,255,255,0.25)",
  },
  heroGlow: {
    position: "absolute",
    top: -40,
    right: -40,
    width: 140,
    height: 140,
    borderRadius: 70,
    backgroundColor: "rgba(184,146,42,0.15)",
  },
  greet: { fontSize: 12, color: "rgba(255,255,255,0.6)" },
  welcome: {
    fontSize: 22,
    fontWeight: "700",
    color: T.white,
    marginTop: 2,
    marginBottom: 6,
  },
  heroSub: { fontSize: 11, color: "rgba(255,255,255,0.55)", marginBottom: 12 },
  nameEm: { fontStyle: "italic", color: T.goldLt, fontWeight: "400" },
  chips: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: {
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 100,
    backgroundColor: "rgba(255,255,255,0.1)",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.15)",
  },
  chipText: { fontSize: 10, color: "rgba(255,255,255,0.75)" },
  stats: { flexDirection: "row", gap: 8, padding: 14, paddingBottom: 8 },
  statCard: {
    flex: 1,
    backgroundColor: T.white,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: T.mist,
    padding: 10,
    alignItems: "center",
  },
  statIcon: {
    width: 30,
    height: 30,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 5,
  },
  statNum: { fontSize: 20, fontWeight: "700", color: T.forest },
  statLbl: {
    fontSize: 7,
    fontWeight: "600",
    textTransform: "uppercase",
    color: T.slate,
    marginTop: 2,
    textAlign: "center",
  },
  section: { paddingHorizontal: 14, paddingTop: 8 },
  sectionLabel: { flexDirection: "row", alignItems: "center", gap: 6, marginBottom: 10 },
  sectionBar: { width: 14, height: 2, backgroundColor: T.gold, borderRadius: 1 },
  sectionText: {
    fontSize: 9,
    fontWeight: "700",
    letterSpacing: 2,
    textTransform: "uppercase",
    color: T.moss,
  },
  card: {
    backgroundColor: T.white,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: T.mist,
    marginBottom: 10,
    overflow: "hidden",
  },
  cardImg: {
    height: 80,
    backgroundColor: T.leaf,
    alignItems: "center",
    justifyContent: "center",
  },
  cardImgPhoto: { width: "100%", height: 120 },
  cardBody: { padding: 12 },
  cardTitle: { fontSize: 12, fontWeight: "600", color: T.ink, marginBottom: 3 },
  cardContent: { fontSize: 10, color: T.slate, lineHeight: 15 },
  cardFoot: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginTop: 6,
    alignItems: "center",
  },
  date: { fontSize: 9, color: T.silver },
  more: { fontSize: 9, fontWeight: "600", color: T.moss },
  skillCard: {
    backgroundColor: T.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: T.mist,
    padding: 12,
    marginBottom: 8,
  },
  skillRow: { flexDirection: "row", justifyContent: "space-between", marginBottom: 6 },
  skillName: { fontSize: 11, fontWeight: "600", color: T.ink },
  skillPct: { fontSize: 10, color: T.slate },
  skillTrack: { height: 6, backgroundColor: T.mist, borderRadius: 99, overflow: "hidden" },
  skillFill: { height: "100%", backgroundColor: T.leaf, borderRadius: 99 },
  eventRow: {
    flexDirection: "row",
    gap: 10,
    backgroundColor: T.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: T.mist,
    padding: 12,
    marginBottom: 8,
    alignItems: "flex-start",
  },
  dateBox: {
    width: 36,
    height: 40,
    borderRadius: 9,
    backgroundColor: T.forest,
    alignItems: "center",
    justifyContent: "center",
  },
  dateDay: { fontSize: 14, fontWeight: "700", color: T.white },
  dateMon: { fontSize: 7, fontWeight: "600", color: "rgba(255,255,255,0.8)" },
  eventTitle: { fontSize: 11, fontWeight: "600", color: T.ink },
  eventLocRow: { flexDirection: "row", alignItems: "center", gap: 4, marginTop: 2 },
  eventLoc: { fontSize: 9, color: T.silver, flex: 1 },
  jobCard: {
    backgroundColor: T.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: T.mist,
    padding: 12,
    marginBottom: 8,
  },
  jobTitle: { fontSize: 12, fontWeight: "600", color: T.ink },
  jobCo: { fontSize: 10, color: T.slate, marginTop: 4 },
  profileCard: {
    backgroundColor: T.white,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: T.mist,
    padding: 14,
    marginBottom: 10,
  },
  profileRow: { flexDirection: "row", alignItems: "center", gap: 12, marginBottom: 12 },
  profileName: { fontSize: 14, fontWeight: "700", color: T.forest },
  profileMeta: { fontSize: 10, color: T.slate, marginTop: 2 },
  progressWrap: { borderTopWidth: 1, borderTopColor: T.mist, paddingTop: 12 },
  progressHead: { flexDirection: "row", justifyContent: "space-between", marginBottom: 6 },
  progressLabel: { fontSize: 10, fontWeight: "600", color: T.charcoal },
  progressPct: { fontSize: 10, color: T.slate },
  progressTrack: { height: 6, backgroundColor: T.mist, borderRadius: 99, overflow: "hidden" },
  progressFill: { height: "100%", backgroundColor: T.leaf, borderRadius: 99 },
  progressHint: { fontSize: 9, color: T.silver, marginTop: 6 },
  actRow: {
    flexDirection: "row",
    gap: 10,
    backgroundColor: T.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: T.mist,
    padding: 12,
    marginBottom: 8,
    alignItems: "center",
  },
  actDot: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: "center",
    justifyContent: "center",
  },
  actGreen: { backgroundColor: T.mist },
  actBlue: { backgroundColor: T.bluePale },
  actTitle: { fontSize: 11, fontWeight: "600", color: T.ink },
  actTime: { fontSize: 9, color: T.silver, marginTop: 2 },
});
