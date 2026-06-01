import { View, Text, StyleSheet } from "react-native";
import { T } from "../constants/colors";

export default function StepIndicator({ current, labels }) {
  return (
    <View style={styles.wrap}>
      {labels.map((lbl, i) => {
        const done = i < current;
        const active = i === current;
        return (
          <View key={lbl} style={styles.stepRow}>
            <View style={styles.col}>
              <View
                style={[
                  styles.circle,
                  done && styles.circleDone,
                  active && styles.circleActive,
                  !done && !active && styles.circleIdle,
                ]}
              >
                <Text style={[styles.circleText, (done || active) && styles.circleTextOn]}>
                  {done ? "✓" : String(i + 1)}
                </Text>
              </View>
              <Text style={[styles.lbl, active && styles.lblActive, done && styles.lblDone]}>{lbl}</Text>
            </View>
            {i < labels.length - 1 ? (
              <View style={[styles.line, done && styles.lineDone]} />
            ) : null}
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 12,
    paddingHorizontal: 14,
    backgroundColor: T.white,
    borderBottomWidth: 1,
    borderBottomColor: T.mist,
  },
  stepRow: { flexDirection: "row", alignItems: "center" },
  col: { alignItems: "center", gap: 3 },
  circle: {
    width: 26,
    height: 26,
    borderRadius: 13,
    alignItems: "center",
    justifyContent: "center",
  },
  circleDone: { backgroundColor: T.leaf },
  circleActive: { backgroundColor: T.forest, borderWidth: 2, borderColor: T.goldLt },
  circleIdle: { backgroundColor: T.fog },
  circleText: { fontSize: 10, fontWeight: "700", color: T.silver },
  circleTextOn: { color: T.white },
  lbl: {
    fontSize: 7,
    fontWeight: "600",
    textTransform: "uppercase",
    letterSpacing: 0.5,
    color: T.silver,
  },
  lblActive: { color: T.forest },
  lblDone: { color: T.leaf },
  line: {
    width: 16,
    height: 2,
    backgroundColor: T.fog,
    marginHorizontal: 2,
    marginBottom: 14,
    borderRadius: 1,
  },
  lineDone: { backgroundColor: T.leaf },
});
