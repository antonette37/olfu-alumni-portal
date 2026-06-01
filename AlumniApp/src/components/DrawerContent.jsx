import { useState } from "react";
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Switch,
  Alert,
  ScrollView,
} from "react-native";
import { T } from "../constants/colors";
import Avatar from "./Avatar";
import AppIcon from "./AppIcon";
import { ICONS } from "../constants/icons";
import { clearSession } from "../api/auth";
import { useAuth } from "../context/AuthContext";

export default function DrawerContent({ navigation, onClose }) {
  const { user, setUser, setToken } = useAuth();
  const [notificationsOn, setNotificationsOn] = useState(true);
  const [privacyMode, setPrivacyMode] = useState(false);

  if (!user) return null;

  const initials = `${(user.firstname || "?")[0]}${(user.lastname || "?")[0]}`.toUpperCase();
  const fullName = `${user.firstname || ""} ${user.lastname || ""}`.trim().toUpperCase();

  const handleLogout = () => {
    Alert.alert("Log Out", "Are you sure you want to log out?", [
      { text: "Cancel", style: "cancel" },
      {
        text: "Log Out",
        style: "destructive",
        onPress: async () => {
          await clearSession();
          setUser(null);
          setToken(null);
          onClose?.();
          navigation.reset({ index: 0, routes: [{ name: "Login" }] });
        },
      },
    ]);
  };

  const goTo = (screen) => {
    onClose?.();
    setTimeout(() => navigation.navigate(screen), 260);
  };

  const menuItems = [
    { icon: ICONS.career, label: "Career Center", onPress: () => goTo("Career") },
    { icon: ICONS.myCareer, label: "My Career", onPress: () => goTo("MyCareer") },
    { icon: ICONS.alumniCard, label: "Alumni Card", onPress: () => goTo("AlumniCard") },
    { icon: ICONS.about, label: "About OLFU Alumni", onPress: () => goTo("About") },
    { icon: ICONS.faqs, label: "FAQs", onPress: () => goTo("Faqs") },
  ];

  return (
    <View style={styles.container}>
      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scroll}>
        <View style={styles.profileHeader}>
          <Avatar
            initials={initials}
            size={72}
            uri={user.profile_image}
            photo={user.photo}
            userId={user.id}
            style={styles.avatar}
          />
          <Text style={styles.name}>{fullName}</Text>
          <Text style={styles.email}>{user.email}</Text>
        </View>

        <View style={styles.divider} />

        {menuItems.map((item) => (
          <MenuRow key={item.label} icon={item.icon} label={item.label} onPress={item.onPress} />
        ))}

        <View style={styles.divider} />

        <Text style={styles.optionsHeading}>OPTIONS</Text>

        <View style={styles.toggleRow}>
          <View style={styles.toggleLeft}>
            <AppIcon name={ICONS.pushNotifications} size={22} color={T.moss} />
            <Text style={styles.menuLabel}>Push Notifications</Text>
          </View>
          <Switch
            value={notificationsOn}
            onValueChange={setNotificationsOn}
            trackColor={{ false: T.fog, true: T.leaf }}
            thumbColor={T.white}
          />
        </View>

        <View style={styles.toggleRow}>
          <View style={styles.toggleLeft}>
            <AppIcon name={ICONS.privacy} size={22} color={T.moss} />
            <Text style={styles.menuLabel}>Privacy Mode</Text>
          </View>
          <Switch
            value={privacyMode}
            onValueChange={setPrivacyMode}
            trackColor={{ false: T.fog, true: T.leaf }}
            thumbColor={T.white}
          />
        </View>

        <View style={styles.divider} />

        <MenuRow icon={ICONS.profile} label="My Profile" onPress={() => goTo("ProfileView")} />
        <MenuRow icon={ICONS.editProfile} label="Edit Profile" onPress={() => goTo("EditProfile")} />
        <MenuRow icon={ICONS.logout} label="Log Out" onPress={handleLogout} labelStyle={styles.logout} />
      </ScrollView>

      <Text style={styles.version}>v. 1.0.0</Text>
    </View>
  );
}

function MenuRow({ icon, label, onPress, labelStyle }) {
  return (
    <TouchableOpacity style={styles.menuRow} onPress={onPress} activeOpacity={0.65}>
      <AppIcon name={icon} size={22} color={T.moss} />
      <Text style={[styles.menuLabel, labelStyle]}>{label}</Text>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: T.white,
    paddingTop: 52,
    paddingBottom: 20,
  },
  scroll: { flexGrow: 1 },
  profileHeader: { paddingHorizontal: 20, paddingBottom: 20 },
  avatar: { marginBottom: 14 },
  name: { fontSize: 18, fontWeight: "700", color: T.ink, marginBottom: 4 },
  email: { fontSize: 12, color: T.slate },
  divider: { height: 1, backgroundColor: "#e0e0e0", marginVertical: 8 },
  optionsHeading: {
    fontSize: 11,
    fontWeight: "700",
    color: T.silver,
    letterSpacing: 1.5,
    textTransform: "uppercase",
    paddingHorizontal: 20,
    paddingTop: 8,
    paddingBottom: 4,
  },
  menuRow: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 15,
    paddingHorizontal: 20,
    gap: 18,
  },
  toggleRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingVertical: 10,
    paddingHorizontal: 20,
  },
  toggleLeft: { flexDirection: "row", alignItems: "center", gap: 18, flex: 1 },
  menuLabel: { fontSize: 16, color: T.ink },
  logout: { color: T.danger },
  version: { fontSize: 12, color: T.silver, paddingHorizontal: 20, paddingTop: 8 },
});
