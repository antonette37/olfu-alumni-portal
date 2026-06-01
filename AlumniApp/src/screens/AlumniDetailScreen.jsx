import { useEffect, useState } from "react";
import { View, Text, ScrollView, StyleSheet, ActivityIndicator } from "react-native";
import { T, shadow } from "../constants/colors";
import ScreenHeader from "../components/ScreenHeader";
import Avatar from "../components/Avatar";
import { fetchAlumniProfile } from "../api/alumni";

export default function AlumniDetailScreen({ route, navigation }) {
  const { alumniId, preview } = route.params || {};
  const [user, setUser] = useState(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (preview) {
      setUser(preview);
      setLoading(false);
      return;
    }
    if (!alumniId) {
      setError("Alumni not found");
      setLoading(false);
      return;
    }
    fetchAlumniProfile(alumniId)
      .then(setUser)
      .catch((e) => setError(e.message || "Could not load profile"))
      .finally(() => setLoading(false));
  }, [alumniId, preview]);

  const initials = user
    ? `${(user.firstname || "?")[0]}${(user.lastname || "?")[0]}`.toUpperCase()
    : "?";

  return (
    <View style={styles.root}>
      <ScreenHeader title="Alumni Profile" navigation={navigation} showBack />
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator color={T.forest} />
        </View>
      ) : error ? (
        <Text style={styles.err}>⚠ {error}</Text>
      ) : user ? (
        <ScrollView contentContainerStyle={styles.pad}>
          <View style={[styles.hero, shadow]}>
            <Avatar
              initials={initials}
              size={80}
              uri={user.profile_image}
              photo={user.photo}
              userId={user.id}
            />
            <Text style={styles.name}>
              {user.firstname} {user.lastname}
            </Text>
            <Text style={styles.sub}>
              {user.program || user.course} · Class of {user.year_graduated || user.batch || "—"}
            </Text>
          </View>
          <InfoSection title="Career">
            <InfoRow icon="💼" label="Position" value={user.position || "—"} />
            <InfoRow icon="🏢" label="Company" value={user.company || "—"} />
            <InfoRow icon="📊" label="Status" value={user.employment_status || "—"} />
          </InfoSection>
          <InfoSection title="Contact">
            <InfoRow icon="📧" label="Email" value={user.email || "—"} />
            <InfoRow icon="📱" label="Phone" value={user.personal_contact || user.phone || "—"} />
            <InfoRow icon="📍" label="Address" value={user.address || "—"} />
          </InfoSection>
        </ScrollView>
      ) : null}
    </View>
  );
}

function InfoSection({ title, children }) {
  return (
    <View style={[styles.section, shadow]}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {children}
    </View>
  );
}

function InfoRow({ icon, label, value }) {
  return (
    <View style={styles.row}>
      <Text style={styles.icon}>{icon}</Text>
      <View style={{ flex: 1 }}>
        <Text style={styles.label}>{label}</Text>
        <Text style={styles.value}>{value}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: T.cream },
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  err: { padding: 20, color: T.danger, textAlign: "center" },
  pad: { padding: 14, paddingBottom: 32 },
  hero: {
    backgroundColor: T.white,
    borderRadius: 16,
    padding: 24,
    alignItems: "center",
    marginBottom: 12,
    borderWidth: 1,
    borderColor: T.mist,
  },
  name: { fontSize: 20, fontWeight: "700", color: T.forest, marginTop: 12 },
  sub: { fontSize: 11, color: T.slate, marginTop: 4, textAlign: "center" },
  section: {
    backgroundColor: T.white,
    borderRadius: 14,
    padding: 14,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: T.mist,
  },
  sectionTitle: {
    fontSize: 9,
    fontWeight: "700",
    letterSpacing: 1,
    textTransform: "uppercase",
    color: T.silver,
    marginBottom: 10,
  },
  row: { flexDirection: "row", gap: 10, marginBottom: 10 },
  icon: { width: 22, textAlign: "center", fontSize: 16 },
  label: { fontSize: 9, color: T.silver, fontWeight: "600" },
  value: { fontSize: 12, color: T.ink, marginTop: 2 },
});
