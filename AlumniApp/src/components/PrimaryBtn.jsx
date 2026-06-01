import { TouchableOpacity, Text, ActivityIndicator, StyleSheet } from "react-native";
import { T } from "../constants/colors";

export default function PrimaryBtn({ children, onPress, disabled = false, loading = false, style }) {
  return (
    <TouchableOpacity
      onPress={onPress}
      disabled={disabled || loading}
      style={[styles.btn, (disabled || loading) && styles.disabled, style]}
      activeOpacity={0.85}
    >
      {loading ? (
        <ActivityIndicator color={T.white} size="small" />
      ) : (
        <Text style={styles.label}>{children}</Text>
      )}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  btn: {
    width: "100%",
    paddingVertical: 12,
    borderRadius: 13,
    backgroundColor: T.forest,
    alignItems: "center",
  },
  disabled: {
    backgroundColor: T.silver,
  },
  label: {
    color: T.white,
    fontSize: 12,
    fontWeight: "600",
    letterSpacing: 0.2,
  },
});
