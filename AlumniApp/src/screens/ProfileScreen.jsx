import { useCallback, useState } from "react";
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Alert,
  RefreshControl,
} from "react-native";
import { useFocusEffect } from "@react-navigation/native";
import { T } from "../constants/colors";
import { clearSession } from "../api/auth";
import { useAuth } from "../context/AuthContext";
import Avatar from "../components/Avatar";

export default function ProfileScreen({ navigation }) {
  const { user, setUser, setToken, refreshUser } = useAuth();
  const [refreshing, setRefreshing] = useState(false);

  useFocusEffect(
    useCallback(() => {
      refreshUser();
    }, [refreshUser])
  );

  if (!user) return null;

  const onRefresh = async () => {
    setRefreshing(true);
    await refreshUser();
    setRefreshing(false);
  };

  const initials = `${(user.firstname || "?")[0]}${(user.lastname || "?")[0]}`.toUpperCase();
  const completion = user.profile_completion ?? 50;

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
          navigation.reset({ index: 0, routes: [{ name: "Login" }] });
        },
      },
    ]);
  };

  const sections = [
    {
      title: "Contact Info",
      rows: [
        ["📧", user.email],
        ["📱", user.personal_contact || "—"],
        ["📍", user.address || "—"],
      ],
    },
    {
      title: "Career",
      rows: [
        ["💼", user.position || "—"],
        ["🏢", user.company || "—"],
        ["📊", user.employment_status || "—"],
      ],
    },
  ];

  return (
    <View style={styles.root}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.hBtn} hitSlop={8}>
          <Text style={styles.hBack}>‹</Text>
        </TouchableOpacity>
        <Text style={styles.hTitle}>Profile</Text>
        <TouchableOpacity
          style={styles.hBtn}
          onPress={() => navigation.navigate("EditProfile")}
          hitSlop={8}
        >
          <Text style={styles.hEdit}>✏️</Text>
        </TouchableOpacity>
      </View>

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.pad}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={T.forest} />
        }
      >
        <View style={styles.banner}>
          <View style={styles.bannerGlow} />
          <Avatar
            initials={initials}
            size={72}
            uri={user.profile_image}
            photo={user.photo}
            userId={user.id}
            style={styles.bannerAvatar}
          />
          <Text style={styles.bannerName}>
            {user.firstname} {user.lastname}
          </Text>
          <Text style={styles.bannerSub}>
            {user.program} · Class of {user.year_graduated || "—"}
          </Text>
          <View style={styles.progressTrack}>
            <View style={[styles.progressFill, { width: `${completion}%` }]} />
          </View>
          <Text style={styles.progressHint}>
            Profile {completion}% complete ·{" "}
            <Text style={{ color: T.goldLt }} onPress={() => navigation.navigate("EditProfile")}>
              Add more info
            </Text>
          </Text>
        </View>

        {sections.map((sec) => (
          <View key={sec.title} style={styles.section}>
            <Text style={styles.sectionTitle}>{sec.title}</Text>
            {sec.rows.map(([icon, val]) => (
              <View key={icon + val} style={styles.infoRow}>
                <Text style={styles.infoIcon}>{icon}</Text>
                <Text style={styles.infoVal}>{val}</Text>
              </View>
            ))}
          </View>
        ))}

        {(user.skills || []).length > 0 ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Skills</Text>
            <View style={styles.skillWrap}>
              {(user.skills || []).map((s, i) => (
                <View key={i} style={styles.skillChip}>
                  <Text style={styles.skillChipText}>{s}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Account</Text>
          {[
            { icon: "🔒", label: "Privacy Settings", color: T.charcoal, onPress: () => {} },
            { icon: "🔔", label: "Notifications", color: T.charcoal, onPress: () => {} },
            {
              icon: "✏️",
              label: "Edit Profile",
              color: T.charcoal,
              onPress: () => navigation.navigate("EditProfile"),
            },
            { icon: "🚪", label: "Log Out", color: T.danger, onPress: handleLogout },
          ].map((item, i, arr) => (
            <TouchableOpacity
              key={item.label}
              onPress={item.onPress}
              style={[styles.settingsRow, i < arr.length - 1 && styles.settingsBorder]}
            >
              <View style={styles.settingsLeft}>
                <Text style={styles.infoIcon}>{item.icon}</Text>
                <Text style={[styles.settingsLabel, { color: item.color }]}>{item.label}</Text>
              </View>
              <Text style={styles.chevron}>›</Text>
            </TouchableOpacity>
          ))}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: T.cream },
  scroll: { flex: 1 },
  pad: { paddingBottom: 32 },
  header: {
    height: 56,
    backgroundColor: T.forest,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 14,
  },
  hBtn: { padding: 6, minWidth: 36 },
  hBack: { fontSize: 28, color: T.white, lineHeight: 32 },
  hTitle: { fontSize: 19, fontWeight: "600", color: T.white },
  hEdit: { fontSize: 18 },
  banner: {
    backgroundColor: T.forest,
    paddingHorizontal: 20,
    paddingBottom: 28,
    paddingTop: 24,
    alignItems: "center",
    overflow: "hidden",
  },
  bannerGlow: {
    position: "absolute",
    top: -40,
    right: -40,
    width: 130,
    height: 130,
    borderRadius: 65,
    backgroundColor: "rgba(184,146,42,0.15)",
  },
  bannerAvatar: {
    borderWidth: 3,
    borderColor: "rgba(255,255,255,0.2)",
    marginBottom: 10,
  },
  bannerName: {
    fontSize: 20,
    fontWeight: "700",
    color: T.white,
    textAlign: "center",
  },
  bannerSub: {
    fontSize: 11,
    color: "rgba(255,255,255,0.6)",
    marginTop: 3,
    marginBottom: 14,
    textAlign: "center",
  },
  progressTrack: {
    width: "85%",
    height: 5,
    backgroundColor: "rgba(255,255,255,0.15)",
    borderRadius: 99,
    overflow: "hidden",
    marginBottom: 6,
  },
  progressFill: { height: "100%", backgroundColor: T.goldLt, borderRadius: 99 },
  progressHint: { fontSize: 9, color: "rgba(255,255,255,0.6)", textAlign: "center" },
  section: {
    backgroundColor: T.white,
    marginHorizontal: 14,
    marginTop: 12,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: T.mist,
    padding: 14,
  },
  sectionTitle: {
    fontSize: 9,
    fontWeight: "700",
    textTransform: "uppercase",
    letterSpacing: 1,
    color: T.silver,
    marginBottom: 10,
  },
  infoRow: { flexDirection: "row", alignItems: "center", gap: 10, marginBottom: 8 },
  infoIcon: { fontSize: 15, width: 20, textAlign: "center" },
  infoVal: { fontSize: 11, color: T.charcoal, flex: 1 },
  skillWrap: { flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 2 },
  skillChip: {
    paddingVertical: 4,
    paddingHorizontal: 10,
    backgroundColor: T.mist,
    borderRadius: 100,
  },
  skillChipText: { fontSize: 9, fontWeight: "600", color: T.leaf },
  settingsRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingVertical: 12,
  },
  settingsBorder: { borderBottomWidth: 1, borderBottomColor: T.mist },
  settingsLeft: { flexDirection: "row", alignItems: "center", gap: 12 },
  settingsLabel: { fontSize: 13 },
  chevron: { fontSize: 18, color: T.silver },
});
