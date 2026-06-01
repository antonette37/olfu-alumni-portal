import { useEffect, useState } from "react";
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  ActivityIndicator,
  TouchableOpacity,
  Alert,
} from "react-native";
import { T, shadow } from "../constants/colors";
import ScreenHeader from "../components/ScreenHeader";
import AppIcon from "../components/AppIcon";
import { ICONS } from "../constants/icons";
import { fetchJobs, applyToJob } from "../api/alumni";

export default function CareerScreen({ navigation }) {
  const [jobs, setJobs] = useState([]);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [applyingId, setApplyingId] = useState(null);

  const load = () => {
    setLoading(true);
    setError("");
    fetchJobs()
      .then(setJobs)
      .catch((e) => setError(e.message || "Could not load jobs"))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    load();
  }, []);

  const openJob = (job) => navigation.navigate("JobDetail", { job });

  const quickApply = (job) => {
    if (Number(job.has_applied) > 0) return;
    Alert.alert("Apply for this job", `Send your interest for "${job.title}"?`, [
      { text: "Cancel", style: "cancel" },
      {
        text: "Apply",
        onPress: async () => {
          setApplyingId(job.id);
          try {
            await applyToJob(job.id);
            setJobs((list) =>
              list.map((j) => (j.id === job.id ? { ...j, has_applied: 1 } : j))
            );
            Alert.alert("Application sent", "The employer has been notified.");
          } catch (e) {
            Alert.alert("Could not apply", e.message || "Please try again.");
          } finally {
            setApplyingId(null);
          }
        },
      },
    ]);
  };

  return (
    <View style={styles.root}>
      <ScreenHeader title="Career Center" navigation={navigation} showBack />
      <TouchableOpacity
        style={styles.myCareerLink}
        onPress={() => navigation.navigate("MyCareer")}
      >
        <AppIcon name={ICONS.myCareer} size={16} color={T.forest} />
        <Text style={styles.myCareerText}>My Career</Text>
        <AppIcon name={ICONS.chevronForward} size={16} color={T.forest} />
      </TouchableOpacity>
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator color={T.forest} />
        </View>
      ) : error ? (
        <Text style={styles.err}>⚠ {error}</Text>
      ) : (
        <ScrollView contentContainerStyle={styles.pad}>
          <Text style={styles.sectionTitle}>Job Board</Text>
          {jobs.length === 0 ? (
            <Text style={styles.empty}>No active job postings right now.</Text>
          ) : (
            jobs.map((j) => {
              const applied = Number(j.has_applied) > 0;
              return (
                <TouchableOpacity
                  key={j.id}
                  style={[styles.card, shadow]}
                  activeOpacity={0.85}
                  onPress={() => openJob(j)}
                >
                  <Text style={styles.jobTitle}>{j.title}</Text>
                  <Text style={styles.company}>
                    {j.company}
                    {j.location ? ` · ${j.location}` : ""}
                  </Text>
                  {j.job_type ? (
                    <View style={styles.tagRow}>
                      <Text style={styles.tag}>{j.job_type}</Text>
                      {j.salary_range ? <Text style={styles.tag}>{j.salary_range}</Text> : null}
                    </View>
                  ) : null}
                  {j.description ? (
                    <Text style={styles.desc} numberOfLines={3}>
                      {j.description}
                    </Text>
                  ) : null}
                  <View style={styles.actions}>
                    <TouchableOpacity
                      style={[styles.applyBtn, applied && styles.applyBtnDone]}
                      onPress={(e) => {
                        e?.stopPropagation?.();
                        quickApply(j);
                      }}
                      disabled={applied || applyingId === j.id}
                    >
                      {applyingId === j.id ? (
                        <ActivityIndicator size="small" color={T.white} />
                      ) : (
                        <>
                          <AppIcon
                            name={applied ? ICONS.checkmark : ICONS.apply}
                            size={16}
                            color={applied ? T.moss : T.white}
                          />
                          <Text style={[styles.applyBtnText, applied && styles.applyBtnTextDone]}>
                            {applied ? "Applied" : "Apply"}
                          </Text>
                        </>
                      )}
                    </TouchableOpacity>
                    <TouchableOpacity style={styles.detailsBtn} onPress={() => openJob(j)}>
                      <Text style={styles.detailsBtnText}>Details</Text>
                    </TouchableOpacity>
                  </View>
                </TouchableOpacity>
              );
            })
          )}
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: T.cream },
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  err: { padding: 20, color: T.danger, textAlign: "center" },
  myCareerLink: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 16,
    paddingVertical: 10,
    alignSelf: "flex-end",
  },
  myCareerText: { fontSize: 12, fontWeight: "600", color: T.forest },
  pad: { padding: 14, paddingBottom: 32 },
  sectionTitle: {
    fontSize: 10,
    fontWeight: "700",
    letterSpacing: 1,
    textTransform: "uppercase",
    color: T.silver,
    marginBottom: 10,
  },
  empty: { fontSize: 12, color: T.silver, textAlign: "center", padding: 24 },
  card: {
    backgroundColor: T.white,
    borderRadius: 14,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: T.mist,
  },
  jobTitle: { fontSize: 14, fontWeight: "700", color: T.ink },
  company: { fontSize: 11, color: T.slate, marginTop: 4 },
  tagRow: { flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 8 },
  tag: {
    fontSize: 9,
    fontWeight: "600",
    color: T.forest,
    backgroundColor: T.mist,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 100,
  },
  desc: { fontSize: 11, color: T.slate, marginTop: 8, lineHeight: 16 },
  actions: { flexDirection: "row", gap: 8, marginTop: 12 },
  applyBtn: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    backgroundColor: T.forest,
    paddingVertical: 10,
    borderRadius: 10,
  },
  applyBtnDone: { backgroundColor: T.mist },
  applyBtnText: { fontSize: 12, fontWeight: "700", color: T.white },
  applyBtnTextDone: { color: T.moss },
  detailsBtn: {
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: T.fog,
    justifyContent: "center",
  },
  detailsBtnText: { fontSize: 12, fontWeight: "600", color: T.moss },
});
