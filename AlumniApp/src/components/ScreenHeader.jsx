import { View, Text, TouchableOpacity, StyleSheet } from "react-native";
import { T } from "../constants/colors";
import { useDrawer } from "../context/DrawerContext";
import AppIcon from "./AppIcon";
import { ICONS } from "../constants/icons";

export default function ScreenHeader({ title, navigation, showBack, onBack }) {
  const { openDrawer } = useDrawer();

  return (
    <View style={styles.header}>
      {showBack ? (
        <TouchableOpacity onPress={onBack || (() => navigation?.goBack())} style={styles.btn} hitSlop={8}>
          <AppIcon name={ICONS.back} size={28} color={T.white} />
        </TouchableOpacity>
      ) : (
        <TouchableOpacity onPress={openDrawer} style={styles.btn} hitSlop={8}>
          <AppIcon name={ICONS.menu} size={26} color={T.white} />
        </TouchableOpacity>
      )}
      <Text style={styles.title} numberOfLines={1}>
        {title}
      </Text>
      <View style={styles.btn} />
    </View>
  );
}

const styles = StyleSheet.create({
  header: {
    height: 56,
    backgroundColor: T.forest,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 14,
    elevation: 4,
    shadowColor: "#000",
    shadowOpacity: 0.15,
    shadowOffset: { width: 0, height: 2 },
    shadowRadius: 4,
  },
  btn: { padding: 6, minWidth: 36, justifyContent: "center" },
  back: { fontSize: 28, color: T.white, lineHeight: 32 },
  title: {
    flex: 1,
    fontSize: 19,
    fontWeight: "600",
    color: T.white,
    textAlign: "center",
  },
  ham: { gap: 5, justifyContent: "center" },
  hamLine: {
    width: 22,
    height: 2.5,
    backgroundColor: T.white,
    borderRadius: 2,
  },
});
