import { View, Text, TouchableOpacity, StyleSheet } from "react-native";
import { T } from "../constants/colors";

export default function IdUploadCard({ label, icon, onSelect, fileName }) {
  return (
    <TouchableOpacity
      onPress={onSelect}
      style={[styles.card, { borderColor: fileName ? T.moss : T.fog }]}
      activeOpacity={0.8}
    >
      <Text style={styles.label}>{label}</Text>
      <View style={styles.preview}>
        <Text style={{ fontSize: 24, opacity: fileName ? 1 : 0.3 }}>{icon}</Text>
        <Text style={[styles.hint, fileName && styles.done]}>
          {fileName ? `✓ ${fileName}` : "Tap to upload"}
        </Text>
      </View>
      <View style={styles.btn}>
        <Text style={styles.btnText}>{fileName ? "Replace" : "📎 Choose file"}</Text>
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  card: {
    flex: 1,
    backgroundColor: T.white,
    borderWidth: 1.5,
    borderStyle: "dashed",
    borderRadius: 14,
    padding: 14,
  },
  label: {
    fontSize: 9,
    fontWeight: "700",
    textTransform: "uppercase",
    letterSpacing: 0.6,
    color: T.silver,
    marginBottom: 8,
  },
  preview: {
    height: 72,
    backgroundColor: T.snow,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 8,
    gap: 4,
  },
  hint: { fontSize: 9, color: T.silver, textAlign: "center", paddingHorizontal: 4 },
  done: { color: T.moss, fontWeight: "600" },
  btn: {
    alignItems: "center",
    paddingVertical: 5,
    paddingHorizontal: 10,
    borderRadius: 8,
    backgroundColor: T.mist,
  },
  btnText: { fontSize: 9, fontWeight: "600", color: T.moss },
});
