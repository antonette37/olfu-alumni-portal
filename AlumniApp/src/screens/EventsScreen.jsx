import { useEffect, useState } from "react";
import { View, Text, ScrollView, TouchableOpacity, StyleSheet } from "react-native";
import { T, shadow } from "../constants/colors";
import Badge from "../components/Badge";
import AppIcon from "../components/AppIcon";
import { ICONS } from "../constants/icons";
import { fetchEvents } from "../api/alumni";

function navigateRoot(navigation, screen, params) {
  let nav = navigation;
  while (nav?.getParent?.()) {
    nav = nav.getParent();
  }
  nav?.navigate(screen, params);
}

export default function EventsScreen({ navigation }) {
  const [tab, setTab] = useState("upcoming");
  const [events, setEvents] = useState([]);
  const [error, setError] = useState("");

  useEffect(() => {
    setError("");
    fetchEvents()
      .then(setEvents)
      .catch((e) => {
        setError(e.message);
        setEvents([]);
      });
  }, []);

  const now = new Date();
  now.setHours(0, 0, 0, 0);
  const upcoming = events.filter((e) => {
    const d = new Date(e.event_date);
    d.setHours(23, 59, 59, 999);
    return d >= now;
  });
  const past = events.filter((e) => {
    const d = new Date(e.event_date);
    d.setHours(23, 59, 59, 999);
    return d < now;
  });
  const list = tab === "upcoming" ? upcoming : past;

  const openEvent = (ev) => navigateRoot(navigation, "EventDetail", { event: ev });

  return (
    <View style={styles.flex}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Events</Text>
      </View>
      <View style={styles.tabs}>
        {["upcoming", "past"].map((t) => (
          <TouchableOpacity
            key={t}
            onPress={() => setTab(t)}
            style={[styles.tab, tab === t && styles.tabOn]}
          >
            <Text style={[styles.tabText, tab === t && styles.tabTextOn]}>
              {t} ({t === "upcoming" ? upcoming.length : past.length})
            </Text>
          </TouchableOpacity>
        ))}
      </View>
      <ScrollView style={styles.list} contentContainerStyle={styles.listPad}>
        {error ? <Text style={styles.empty}>⚠ {error}</Text> : null}
        {!error && list.length === 0 ? <Text style={styles.empty}>No events yet.</Text> : null}
        {list.map((ev, idx) => {
          const d = new Date(ev.event_date);
          const day = d.getDate();
          const mon = d.toLocaleString("en", { month: "short" });
          if (idx === 0 && tab === "upcoming") {
            return (
              <TouchableOpacity
                key={ev.id}
                style={[styles.featured, shadow]}
                activeOpacity={0.85}
                onPress={() => openEvent(ev)}
              >
                <View style={styles.featuredImg}>
                  <AppIcon name={ICONS.eventsActive} size={40} color="rgba(255,255,255,0.35)" />
                  <View style={styles.featuredBadges}>
                    <Badge bg={T.goldLt} color={T.forest}>
                      {ev.type}
                    </Badge>
                    {ev.spots_left != null && ev.spots_left <= 5 ? (
                      <Badge bg="rgba(255,255,255,0.2)" color={T.white}>
                        {ev.spots_left} spots left
                      </Badge>
                    ) : null}
                  </View>
                </View>
                <View style={styles.featuredBody}>
                  <Text style={styles.featuredTitle}>{ev.title}</Text>
                  <View style={styles.metaRow}>
                    <AppIcon name={ICONS.events} size={14} color={T.slate} />
                    <Text style={styles.meta}>
                      {d.toLocaleDateString("en-PH", { month: "long", day: "numeric", year: "numeric" })}
                    </Text>
                  </View>
                  {ev.location ? (
                    <View style={styles.metaRow}>
                      <AppIcon name={ICONS.location} size={14} color={T.slate} />
                      <Text style={styles.meta}>{ev.location}</Text>
                    </View>
                  ) : null}
                  <View style={styles.viewDetail}>
                    <Text style={styles.viewDetailText}>View details</Text>
                    <AppIcon name={ICONS.chevronForward} size={16} color={T.moss} />
                  </View>
                </View>
              </TouchableOpacity>
            );
          }
          return (
            <TouchableOpacity
              key={ev.id}
              style={[styles.row, shadow]}
              activeOpacity={0.85}
              onPress={() => openEvent(ev)}
            >
              <View style={styles.dateBox}>
                <Text style={styles.dateDay}>{day}</Text>
                <Text style={styles.dateMon}>{mon.toUpperCase()}</Text>
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.rowTitle}>{ev.title}</Text>
                <Text style={styles.meta} numberOfLines={1}>
                  {ev.location}
                </Text>
                <View style={styles.badges}>
                  <Badge>{ev.type}</Badge>
                  <Badge bg={T.mist} color={T.leaf}>
                    {ev.registered_count} registered
                  </Badge>
                  {ev.is_registered === 1 ? (
                    <Badge bg="#f0fdf4" color="#059669">
                      Registered
                    </Badge>
                  ) : null}
                </View>
              </View>
              <AppIcon name={ICONS.chevronForward} size={18} color={T.fog} />
            </TouchableOpacity>
          );
        })}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: T.cream },
  header: {
    backgroundColor: T.forest,
    paddingVertical: 14,
    paddingHorizontal: 16,
  },
  headerTitle: { fontSize: 20, fontWeight: "700", color: T.white },
  tabs: { flexDirection: "row", gap: 6, padding: 14, paddingBottom: 8 },
  tab: {
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 100,
    backgroundColor: T.mist,
  },
  tabOn: { backgroundColor: T.forest },
  tabText: { fontSize: 9, fontWeight: "700", color: T.leaf, textTransform: "capitalize" },
  tabTextOn: { color: T.white },
  list: { flex: 1 },
  listPad: { paddingHorizontal: 14, paddingBottom: 16 },
  empty: { fontSize: 10, color: T.silver, textAlign: "center", padding: 20 },
  featured: {
    backgroundColor: T.white,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: T.mist,
    marginBottom: 12,
    overflow: "hidden",
  },
  featuredImg: {
    height: 100,
    backgroundColor: T.leaf,
    alignItems: "center",
    justifyContent: "center",
  },
  featuredBadges: {
    position: "absolute",
    bottom: 10,
    left: 12,
    flexDirection: "row",
    gap: 6,
  },
  featuredBody: { padding: 12 },
  featuredTitle: { fontSize: 13, fontWeight: "600", color: T.ink, marginBottom: 4 },
  metaRow: { flexDirection: "row", alignItems: "center", gap: 6, marginBottom: 4 },
  meta: { fontSize: 10, color: T.slate, flex: 1 },
  viewDetail: { flexDirection: "row", alignItems: "center", gap: 4, marginTop: 8 },
  viewDetailText: { fontSize: 10, fontWeight: "600", color: T.moss },
  row: {
    flexDirection: "row",
    gap: 10,
    backgroundColor: T.white,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: T.mist,
    padding: 12,
    marginBottom: 8,
    alignItems: "center",
  },
  dateBox: {
    width: 44,
    height: 48,
    borderRadius: 10,
    backgroundColor: T.forest,
    alignItems: "center",
    justifyContent: "center",
  },
  dateDay: { fontSize: 16, fontWeight: "700", color: T.white },
  dateMon: { fontSize: 7, fontWeight: "600", color: "rgba(255,255,255,0.8)" },
  rowTitle: { fontSize: 11, fontWeight: "600", color: T.ink },
  badges: { flexDirection: "row", flexWrap: "wrap", gap: 4, marginTop: 5 },
});
