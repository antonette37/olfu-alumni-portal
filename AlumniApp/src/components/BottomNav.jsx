import { View, Text, TouchableOpacity, StyleSheet } from "react-native";
import { T } from "../constants/colors";
import AppIcon from "./AppIcon";
import { ICONS } from "../constants/icons";

const TABS = [
  { id: "Dashboard", label: "Home", icon: ICONS.home, iconActive: ICONS.homeActive },
  { id: "Directory", label: "Directory", icon: ICONS.directory, iconActive: ICONS.directoryActive },
  { id: "Events", label: "Events", icon: ICONS.events, iconActive: ICONS.eventsActive },
  { id: "Messages", label: "Messages", icon: ICONS.messages, iconActive: ICONS.messagesActive, badge: true },
];

export default function BottomNav({ state, navigation }) {
  return (
    <View style={styles.bar}>
      {state.routes.map((route, index) => {
        const tab = TABS.find((t) => t.id === route.name);
        if (!tab) return null;
        const active = state.index === index;
        const onPress = () => {
          const e = navigation.emit({
            type: "tabPress",
            target: route.key,
            canPreventDefault: true,
          });
          if (!active && !e.defaultPrevented) navigation.navigate(route.name);
        };
        return (
          <TouchableOpacity
            key={route.key}
            onPress={onPress}
            style={[styles.tab, active && styles.tabActive]}
          >
            <View>
              <AppIcon
                name={active ? tab.iconActive : tab.icon}
                size={22}
                color={active ? T.leaf : T.silver}
              />
              {tab.badge && !active ? <View style={styles.dot} /> : null}
            </View>
            <Text style={[styles.label, active && styles.labelActive]}>{tab.label}</Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    height: 62,
    backgroundColor: T.white,
    borderTopWidth: 1,
    borderTopColor: T.mist,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-around",
    paddingHorizontal: 4,
  },
  tab: {
    alignItems: "center",
    gap: 3,
    paddingVertical: 6,
    paddingHorizontal: 14,
    borderRadius: 12,
  },
  tabActive: { backgroundColor: T.mist },
  label: {
    fontSize: 8,
    fontWeight: "600",
    textTransform: "uppercase",
    letterSpacing: 0.5,
    color: T.silver,
  },
  labelActive: { color: T.leaf },
  dot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: T.gold,
    position: "absolute",
    top: 0,
    right: -4,
  },
});
