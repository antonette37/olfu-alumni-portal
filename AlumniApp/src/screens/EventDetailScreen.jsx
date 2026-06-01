import { useMemo, useState } from "react";
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Alert,
  ActivityIndicator,
} from "react-native";
import { T, shadow } from "../constants/colors";
import ScreenHeader from "../components/ScreenHeader";
import Badge from "../components/Badge";
import AppIcon from "../components/AppIcon";
import { ICONS } from "../constants/icons";
import { registerForEvent } from "../api/alumni";

export default function EventDetailScreen({ route, navigation }) {
  const { event: initial } = route.params || {};
  const [event, setEvent] = useState(initial);
  const [registering, setRegistering] = useState(false);

  const d = useMemo(() => new Date(event?.event_date || ""), [event?.event_date]);
  const isPast = useMemo(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const ed = new Date(event?.event_date || "");
    ed.setHours(23, 59, 59, 999);
    return ed < today;
  }, [event?.event_date]);

  if (!event) {
    return (
      <View style={styles.root}>
        <ScreenHeader title="Event" navigation={navigation} showBack />
        <Text style={styles.err}>Event not found.</Text>
      </View>
    );
  }

  const handleRegister = async () => {
    if (isPast) return;
    setRegistering(true);
    try {
      const res = await registerForEvent(event.id);
      setEvent((e) => ({ ...e, is_registered: 1 }));
      Alert.alert(
        res.already_registered ? "Already registered" : "Registered",
        res.message || "You are registered for this event."
      );
    } catch (e) {
      Alert.alert("Registration failed", e.message || "Please try again.");
    } finally {
      setRegistering(false);
    }
  };

  const dateLabel = Number.isNaN(d.getTime())
    ? "—"
    : d.toLocaleDateString("en-PH", { weekday: "long", month: "long", day: "numeric", year: "numeric" });

  return (
    <View style={styles.root}>
      <ScreenHeader title="Event Details" navigation={navigation} showBack />
      <ScrollView contentContainerStyle={styles.pad}>
        <View style={[styles.hero, shadow]}>
          <View style={styles.heroIcon}>
            <AppIcon name={ICONS.eventsActive} size={32} color={T.white} />
          </View>
          <Text style={styles.title}>{event.title}</Text>
          <View style={styles.badgeRow}>
            <Badge>{event.type}</Badge>
            {isPast ? <Badge bg={T.mist} color={T.slate}>Past</Badge> : <Badge bg={T.goldPale} color={T.forest}>Upcoming</Badge>}
          </View>
        </View>

        <View style={[styles.card, shadow]}>
          <DetailRow icon={ICONS.events} label="Date" value={dateLabel} />
          {event.event_time ? (
            <DetailRow icon={ICONS.time} label="Time" value={event.event_time} />
          ) : null}
          {event.location ? (
            <DetailRow icon={ICONS.location} label="Location" value={event.location} />
          ) : null}
          {event.registered_count != null ? (
            <DetailRow
              icon={ICONS.directory}
              label="Attendees"
              value={`${event.registered_count} registered`}
            />
          ) : null}
          {event.spots_left != null ? (
            <DetailRow icon={ICONS.upcomingEvents} label="Spots left" value={String(event.spots_left)} />
          ) : null}
        </View>

        {event.description ? (
          <View style={[styles.card, shadow]}>
            <Text style={styles.sectionTitle}>About this event</Text>
            <Text style={styles.body}>{event.description}</Text>
          </View>
        ) : null}

        {!isPast ? (
          <TouchableOpacity
            style={[styles.regBtn, event.is_registered ? styles.regBtnDone : null]}
            onPress={handleRegister}
            disabled={registering || event.is_registered}
          >
            {registering ? (
              <ActivityIndicator color={T.white} />
            ) : (
              <>
                {event.is_registered ? (
                  <AppIcon name={ICONS.checkmark} size={18} color={T.moss} style={{ marginRight: 6 }} />
                ) : null}
                <Text style={[styles.regBtnText, event.is_registered && styles.regBtnTextDone]}>
                  {event.is_registered ? "Registered" : "Register Now"}
                </Text>
              </>
            )}
          </TouchableOpacity>
        ) : null}
      </ScrollView>
    </View>
  );
}

function DetailRow({ icon, label, value }) {
  return (
    <View style={styles.row}>
      <AppIcon name={icon} size={18} color={T.moss} />
      <View style={{ flex: 1 }}>
        <Text style={styles.rowLabel}>{label}</Text>
        <Text style={styles.rowValue}>{value}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: T.cream },
  err: { padding: 20, textAlign: "center", color: T.danger },
  pad: { padding: 14, paddingBottom: 32 },
  hero: {
    backgroundColor: T.white,
    borderRadius: 16,
    padding: 20,
    alignItems: "center",
    marginBottom: 12,
    borderWidth: 1,
    borderColor: T.mist,
  },
  heroIcon: {
    width: 56,
    height: 56,
    borderRadius: 16,
    backgroundColor: T.forest,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 12,
  },
  title: { fontSize: 18, fontWeight: "700", color: T.ink, textAlign: "center" },
  badgeRow: { flexDirection: "row", gap: 6, marginTop: 10 },
  card: {
    backgroundColor: T.white,
    borderRadius: 14,
    padding: 14,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: T.mist,
  },
  sectionTitle: {
    fontSize: 10,
    fontWeight: "700",
    letterSpacing: 1,
    textTransform: "uppercase",
    color: T.silver,
    marginBottom: 8,
  },
  body: { fontSize: 13, color: T.slate, lineHeight: 20 },
  row: { flexDirection: "row", gap: 12, marginBottom: 12 },
  rowLabel: { fontSize: 9, color: T.silver, fontWeight: "600" },
  rowValue: { fontSize: 13, color: T.ink, marginTop: 2 },
  regBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: T.forest,
    paddingVertical: 14,
    borderRadius: 12,
    marginTop: 4,
  },
  regBtnDone: { backgroundColor: T.mist },
  regBtnText: { fontSize: 14, fontWeight: "700", color: T.white },
  regBtnTextDone: { color: T.moss },
});
