import { useState } from "react";
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Alert,
  ActivityIndicator,
  TextInput,
} from "react-native";
import { T, shadow } from "../constants/colors";
import ScreenHeader from "../components/ScreenHeader";
import AppIcon from "../components/AppIcon";
import { ICONS } from "../constants/icons";
import { applyToJob } from "../api/alumni";

export default function JobDetailScreen({ route, navigation }) {
  const { job: initial } = route.params || {};
  const [job, setJob] = useState(initial);
  const [note, setNote] = useState("");
  const [applying, setApplying] = useState(false);

  if (!job) {
    return (
      <View style={styles.root}>
        <ScreenHeader title="Job" navigation={navigation} showBack />
        <Text style={styles.err}>Job not found.</Text>
      </View>
    );
  }

  const hasApplied = Number(job.has_applied) > 0;

  const handleApply = () => {
    Alert.alert(
      "Apply for this job",
      "Send your interest to the employer? You can add an optional note.",
      [
        { text: "Cancel", style: "cancel" },
        {
          text: "Apply",
          onPress: async () => {
            setApplying(true);
            try {
              const res = await applyToJob(job.id, note.trim());
              setJob((j) => ({ ...j, has_applied: 1 }));
              Alert.alert(
                res.already_applied ? "Already applied" : "Application sent",
                res.message || "Your application was sent to the employer."
              );
            } catch (e) {
              Alert.alert("Could not apply", e.message || "Please try again.");
            } finally {
              setApplying(false);
            }
          },
        },
      ]
    );
  };

  return (
    <View style={styles.root}>
      <ScreenHeader title="Job Details" navigation={navigation} showBack />
      <ScrollView contentContainerStyle={styles.pad} keyboardShouldPersistTaps="handled">
        <View style={[styles.hero, shadow]}>
          <View style={styles.heroIcon}>
            <AppIcon name={ICONS.career} size={28} color={T.white} />
          </View>
          <Text style={styles.title}>{job.title}</Text>
          <Text style={styles.company}>{job.company}</Text>
          {job.location ? <Text style={styles.meta}>{job.location}</Text> : null}
          <View style={styles.tagRow}>
            {job.job_type ? <Text style={styles.tag}>{job.job_type}</Text> : null}
            {job.salary_range ? <Text style={styles.tag}>{job.salary_range}</Text> : null}
          </View>
        </View>

        {job.description ? (
          <View style={[styles.card, shadow]}>
            <Text style={styles.sectionTitle}>Description</Text>
            <Text style={styles.body}>{job.description}</Text>
          </View>
        ) : null}

        {job.requirements ? (
          <View style={[styles.card, shadow]}>
            <Text style={styles.sectionTitle}>Requirements</Text>
            <Text style={styles.body}>{job.requirements}</Text>
          </View>
        ) : null}

        {!hasApplied ? (
          <View style={[styles.card, shadow]}>
            <Text style={styles.sectionTitle}>Optional note to employer</Text>
            <TextInput
              value={note}
              onChangeText={setNote}
              placeholder="Add a short message (optional)…"
              placeholderTextColor={T.silver}
              style={styles.noteInput}
              multiline
              textAlignVertical="top"
            />
          </View>
        ) : null}

        <TouchableOpacity
          style={[styles.applyBtn, hasApplied && styles.applyBtnDone]}
          onPress={handleApply}
          disabled={applying || hasApplied}
        >
          {applying ? (
            <ActivityIndicator color={T.white} />
          ) : (
            <>
              <AppIcon
                name={hasApplied ? ICONS.checkmark : ICONS.apply}
                size={20}
                color={hasApplied ? T.moss : T.white}
                style={{ marginRight: 8 }}
              />
              <Text style={[styles.applyBtnText, hasApplied && styles.applyBtnTextDone]}>
                {hasApplied ? "Applied" : "Apply Now"}
              </Text>
            </>
          )}
        </TouchableOpacity>
      </ScrollView>
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
    width: 52,
    height: 52,
    borderRadius: 14,
    backgroundColor: T.forest,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 10,
  },
  title: { fontSize: 18, fontWeight: "700", color: T.ink, textAlign: "center" },
  company: { fontSize: 14, color: T.moss, fontWeight: "600", marginTop: 4 },
  meta: { fontSize: 12, color: T.slate, marginTop: 4 },
  tagRow: { flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 10, justifyContent: "center" },
  tag: {
    fontSize: 10,
    fontWeight: "600",
    color: T.forest,
    backgroundColor: T.mist,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 100,
  },
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
  noteInput: {
    minHeight: 80,
    borderWidth: 1,
    borderColor: T.fog,
    borderRadius: 10,
    padding: 12,
    fontSize: 13,
    color: T.ink,
    backgroundColor: T.snow,
  },
  applyBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: T.forest,
    paddingVertical: 14,
    borderRadius: 12,
  },
  applyBtnDone: { backgroundColor: T.mist },
  applyBtnText: { fontSize: 15, fontWeight: "700", color: T.white },
  applyBtnTextDone: { color: T.moss },
});
