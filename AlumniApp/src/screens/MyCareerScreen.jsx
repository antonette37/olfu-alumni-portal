import { useEffect, useState } from "react";
import { View, Text, ScrollView, StyleSheet, ActivityIndicator } from "react-native";
import { T, shadow } from "../constants/colors";
import ScreenHeader from "../components/ScreenHeader";
import { fetchCareer } from "../api/alumni";
import { useAuth } from "../context/AuthContext";

export default function MyCareerScreen({ navigation }) {
  const { user } = useAuth();
  const [career, setCareer] = useState(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchCareer()
      .then(setCareer)
      .catch((e) => setError(e.message || "Could not load career data"))
      .finally(() => setLoading(false));
  }, []);

  const position = career?.position || user?.position || "—";
  const company = career?.company || user?.company || "—";

  return (
    <View style={styles.root}>
      <ScreenHeader title="My Career" navigation={navigation} showBack />
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator color={T.forest} />
        </View>
      ) : error ? (
        <Text style={styles.err}>⚠ {error}</Text>
      ) : (
        <ScrollView contentContainerStyle={styles.pad}>
          <View style={[styles.card, shadow]}>
            <Text style={styles.label}>Current role</Text>
            <Text style={styles.value}>{position}</Text>
            <Text style={styles.label}>Company</Text>
            <Text style={styles.value}>{company}</Text>
          </View>

          <View style={[styles.card, shadow]}>
            <Text style={styles.sectionTitle}>Skills</Text>
            {(career?.skills || []).length ? (
              <View style={styles.sectionBody}>
                {(career?.skills || []).map((s, i) => (
                  <Text key={i} style={styles.chip}>
                    {typeof s === "string" ? s : s.skill_name || s.name}
                  </Text>
                ))}
              </View>
            ) : (
              <Text style={styles.empty}>No skills listed yet.</Text>
            )}
          </View>

          <View style={[styles.card, shadow]}>
            <Text style={styles.sectionTitle}>Career history</Text>
            {(career?.career_history || []).length ? (
              (career?.career_history || []).map((h) => (
                <View key={h.id} style={styles.historyRow}>
                  <Text style={styles.historyTitle}>
                    {h.position} @ {h.company}
                  </Text>
                  <Text style={styles.historyMeta}>
                    {h.start_date || "—"} – {h.end_date || "Present"}
                  </Text>
                  {h.description ? <Text style={styles.historyDesc}>{h.description}</Text> : null}
                </View>
              ))
            ) : (
              <Text style={styles.empty}>No career history entries yet.</Text>
            )}
          </View>

          <View style={[styles.card, shadow]}>
            <Text style={styles.sectionTitle}>Achievements</Text>
            {(career?.achievements || []).length ? (
              (career?.achievements || []).map((a) => (
                <View key={a.id} style={styles.historyRow}>
                  <Text style={styles.historyTitle}>{a.achievement_title}</Text>
                  {a.achievement_description ? (
                    <Text style={styles.historyDesc}>{a.achievement_description}</Text>
                  ) : null}
                </View>
              ))
            ) : (
              <Text style={styles.empty}>No achievements yet.</Text>
            )}
          </View>
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: T.cream },
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  err: { padding: 20, color: T.danger, textAlign: "center" },
  pad: { padding: 14, paddingBottom: 32 },
  card: {
    backgroundColor: T.white,
    borderRadius: 14,
    padding: 14,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: T.mist,
  },
  label: { fontSize: 9, color: T.silver, fontWeight: "600", marginTop: 8 },
  value: { fontSize: 14, fontWeight: "600", color: T.ink, marginTop: 2 },
  sectionTitle: {
    fontSize: 10,
    fontWeight: "700",
    letterSpacing: 1,
    textTransform: "uppercase",
    color: T.silver,
    marginBottom: 10,
  },
  sectionBody: { flexDirection: "row", flexWrap: "wrap", gap: 6 },
  chip: {
    fontSize: 11,
    color: T.forest,
    backgroundColor: T.mist,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 100,
  },
  historyRow: { marginBottom: 12 },
  historyTitle: { fontSize: 12, fontWeight: "600", color: T.ink },
  historyMeta: { fontSize: 10, color: T.silver, marginTop: 2 },
  historyDesc: { fontSize: 11, color: T.slate, marginTop: 4, lineHeight: 16 },
  empty: { fontSize: 11, color: T.silver },
});
