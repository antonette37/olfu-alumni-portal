import { Text, StyleSheet } from "react-native";
import { T } from "../constants/colors";

export default function Badge({ children, bg = T.goldPale, color = T.gold, style }) {
  return <Text style={[styles.badge, { backgroundColor: bg, color }, style]}>{children}</Text>;
}

const styles = StyleSheet.create({
  badge: {
    fontSize: 8,
    fontWeight: "700",
    paddingVertical: 3,
    paddingHorizontal: 8,
    borderRadius: 100,
    overflow: "hidden",
  },
});
