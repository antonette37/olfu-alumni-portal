import { useState } from "react";
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Alert,
  KeyboardAvoidingView,
  Platform,
} from "react-native";
import { Image } from "expo-image";
import * as ImagePicker from "expo-image-picker";
import { T, shadow } from "../constants/colors";
import { useAuth } from "../context/AuthContext";
import { updateProfile } from "../api/alumni";
import { persistUser } from "../api/auth";
import Input from "../components/Input";
import Select from "../components/Select";
import PrimaryBtn from "../components/PrimaryBtn";

const EMPLOYMENT_OPTIONS = [
  { value: "Employed", label: "Employed" },
  { value: "Self-employed", label: "Self-employed" },
  { value: "Unemployed", label: "Unemployed" },
  { value: "Student", label: "Student" },
  { value: "Retired", label: "Retired" },
];

async function pickProfilePhoto() {
  const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
  if (status !== "granted") {
    Alert.alert("Permission needed", "Allow photo access to choose a profile picture.");
    return null;
  }
  const result = await ImagePicker.launchImageLibraryAsync({
    mediaTypes: ["images"],
    allowsEditing: true,
    aspect: [1, 1],
    quality: 0.85,
  });
  if (result.canceled || !result.assets?.[0]) return null;
  const asset = result.assets[0];
  const name = asset.fileName || `profile_${Date.now()}.jpg`;
  const type =
    asset.mimeType || (name.toLowerCase().endsWith(".png") ? "image/png" : "image/jpeg");
  return { uri: asset.uri, name, type };
}

export default function EditProfileScreen({ navigation }) {
  const { user, setUser } = useAuth();
  const [saving, setSaving] = useState(false);
  const [photoPreview, setPhotoPreview] = useState(null);
  const [photoAsset, setPhotoAsset] = useState(null);

  const [form, setForm] = useState({
    firstname: user?.firstname ?? "",
    lastname: user?.lastname ?? "",
    personal_contact: user?.personal_contact ?? "",
    address: user?.address ?? "",
    year_graduated: String(user?.year_graduated ?? ""),
    program: user?.program ?? "",
    employment_status: user?.employment_status ?? "",
    company: user?.company ?? "",
    position: user?.position ?? "",
  });

  if (!user) return null;

  const set = (key, val) => setForm((f) => ({ ...f, [key]: val }));

  const onPickPhoto = async () => {
    const picked = await pickProfilePhoto();
    if (!picked) return;
    setPhotoPreview(picked.uri);
    setPhotoAsset(picked);
  };

  const onSave = async () => {
    if (!form.firstname.trim() || !form.lastname.trim()) {
      Alert.alert("Required", "First and last name are required.");
      return;
    }
    setSaving(true);
    try {
      const updated = await updateProfile(form, photoAsset);
      setUser(updated);
      await persistUser(updated);
      Alert.alert("Saved", "Your profile has been updated.", [
        { text: "OK", onPress: () => navigation.goBack() },
      ]);
    } catch (e) {
      Alert.alert("Update failed", e.message || "Could not save profile.");
    } finally {
      setSaving(false);
    }
  };

  const displayPhoto =
    photoPreview ||
    user.profile_image_data ||
    user.profile_image ||
    null;

  const initials = `${(form.firstname || "?")[0]}${(form.lastname || "?")[0]}`.toUpperCase();

  return (
    <KeyboardAvoidingView
      style={styles.flex}
      behavior={Platform.OS === "ios" ? "padding" : undefined}
    >
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.hBtn} hitSlop={8}>
          <Text style={styles.hBack}>‹</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Edit Profile</Text>
        <View style={styles.hBtn} />
      </View>

      <ScrollView
        style={styles.flex}
        contentContainerStyle={styles.pad}
        keyboardShouldPersistTaps="handled"
      >
        <View style={[styles.photoCard, shadow]}>
          <TouchableOpacity onPress={onPickPhoto} style={styles.photoRing}>
            {displayPhoto ? (
              <Image source={{ uri: displayPhoto }} style={styles.photoImg} contentFit="cover" />
            ) : (
              <Text style={styles.photoInitials}>{initials}</Text>
            )}
            <View style={styles.photoBadge}>
              <Text style={styles.photoBadgeText}>📷</Text>
            </View>
          </TouchableOpacity>
          <Text style={styles.photoHint}>Tap to change profile photo</Text>
        </View>

        <View style={[styles.formCard, shadow]}>
          <Text style={styles.sectionLabel}>Personal</Text>
          <Input label="First name" value={form.firstname} onChangeText={(v) => set("firstname", v)} />
          <Input label="Last name" value={form.lastname} onChangeText={(v) => set("lastname", v)} />
          <Input
            label="Email"
            value={user.email}
            readOnly
            hint="Email cannot be changed here"
          />
          <Input
            label="Mobile"
            value={form.personal_contact}
            onChangeText={(v) => set("personal_contact", v)}
            keyboardType="phone-pad"
          />
          <Input
            label="Address"
            value={form.address}
            onChangeText={(v) => set("address", v)}
            placeholder="City, province"
          />
          <Input
            label="Program"
            value={form.program}
            onChangeText={(v) => set("program", v)}
            placeholder="e.g. BS Computer Science"
          />
          <Input
            label="Year graduated"
            value={form.year_graduated}
            onChangeText={(v) => set("year_graduated", v)}
            keyboardType="number-pad"
            maxLength={4}
          />
        </View>

        <View style={[styles.formCard, shadow]}>
          <Text style={styles.sectionLabel}>Career</Text>
          <Select
            label="Employment status"
            value={form.employment_status}
            onValueChange={(v) => set("employment_status", v)}
            options={EMPLOYMENT_OPTIONS}
          />
          <Input label="Position" value={form.position} onChangeText={(v) => set("position", v)} />
          <Input label="Company" value={form.company} onChangeText={(v) => set("company", v)} />
        </View>

        <PrimaryBtn onPress={onSave} loading={saving} style={styles.saveBtn}>
          Save changes
        </PrimaryBtn>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: T.cream },
  header: {
    height: 56,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 14,
    backgroundColor: T.forest,
  },
  hBtn: { padding: 6, minWidth: 36 },
  hBack: { fontSize: 28, color: T.white, lineHeight: 32 },
  headerTitle: { color: T.white, fontSize: 19, fontWeight: "600" },
  pad: { padding: 16, paddingBottom: 32 },
  photoCard: {
    backgroundColor: T.white,
    borderRadius: 16,
    padding: 20,
    alignItems: "center",
    marginBottom: 14,
  },
  photoRing: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: T.mist,
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
    borderWidth: 3,
    borderColor: T.fog,
  },
  photoImg: { width: 100, height: 100 },
  photoInitials: { fontSize: 32, fontWeight: "700", color: T.leaf },
  photoBadge: {
    position: "absolute",
    bottom: 4,
    right: 4,
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: T.forest,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 2,
    borderColor: T.white,
  },
  photoBadgeText: { fontSize: 12 },
  photoHint: { marginTop: 10, fontSize: 10, color: T.slate },
  formCard: {
    backgroundColor: T.white,
    borderRadius: 16,
    padding: 16,
    marginBottom: 14,
  },
  sectionLabel: {
    fontSize: 9,
    fontWeight: "700",
    letterSpacing: 1,
    textTransform: "uppercase",
    color: T.silver,
    marginBottom: 10,
  },
  saveBtn: { marginTop: 4 },
});
