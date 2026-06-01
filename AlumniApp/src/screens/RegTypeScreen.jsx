import { View, Text, ScrollView, TouchableOpacity, StyleSheet } from "react-native";
import { T, shadow } from "../constants/colors";

export default function RegTypeScreen({ navigation }) {
  return (
    <View style={styles.flex}>
      <View style={styles.hero}>
        <View style={styles.heroGlow} />
        <TouchableOpacity onPress={() => navigation.goBack()}>
          <Text style={styles.back}>‹</Text>
        </TouchableOpacity>
        <Text style={styles.heroTitle}>Create Account</Text>
        <Text style={styles.heroSub}>How would you like to register?</Text>
      </View>
      <ScrollView style={styles.body} contentContainerStyle={styles.bodyPad}>
        <Text style={styles.intro}>
          Choose the registration type that applies to you. Both require uploading a valid ID for
          verification.
        </Text>

        <TouchableOpacity
          style={[styles.card, shadow]}
          onPress={() => navigation.navigate("Register", { regType: "new" })}
        >
          <View style={[styles.iconBox, { backgroundColor: T.leaf }]}>
            <Text style={styles.iconEmoji}>🎓</Text>
          </View>
          <View style={styles.cardBody}>
            <Text style={styles.cardTitle}>New Alumni Registration</Text>
            <Text style={styles.cardDesc}>
              I was a student at OLFU CCS and have my Student ID card. My Alumni ID will be assigned
              by the office after my ID is verified.
            </Text>
            <View style={[styles.pill, { backgroundColor: T.mist }]}>
              <Text style={[styles.pillText, { color: T.leaf }]}>📋 Upload Student ID (front & back)</Text>
            </View>
          </View>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.card, shadow]}
          onPress={() => navigation.navigate("Register", { regType: "legacy" })}
        >
          <View style={[styles.iconBox, { backgroundColor: T.gold }]}>
            <Text style={styles.iconEmoji}>🪪</Text>
          </View>
          <View style={styles.cardBody}>
            <Text style={styles.cardTitle}>Legacy Alumni Registration</Text>
            <Text style={styles.cardDesc}>
              I already have an OLFU Alumni ID card with a 16-digit card number. I want to link my
              existing ID to a new portal account.
            </Text>
            <View style={[styles.pill, { backgroundColor: T.goldPale }]}>
              <Text style={[styles.pillText, { color: T.gold }]}>🪪 Upload Alumni Card (front & back)</Text>
            </View>
          </View>
        </TouchableOpacity>

        <View style={styles.info}>
          <Text style={styles.infoText}>
            ℹ Both registration types require admin verification. You'll receive an email once your
            account is approved.
          </Text>
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: T.cream },
  hero: {
    backgroundColor: T.forest,
    paddingTop: 16,
    paddingHorizontal: 20,
    paddingBottom: 28,
    overflow: "hidden",
  },
  heroGlow: {
    position: "absolute",
    top: -40,
    right: -40,
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: "rgba(184,146,42,0.15)",
  },
  back: { fontSize: 28, color: "rgba(255,255,255,0.7)", marginBottom: 12 },
  heroTitle: { fontSize: 22, fontWeight: "700", color: T.white },
  heroSub: { fontSize: 11, color: "rgba(255,255,255,0.6)", marginTop: 6 },
  body: { flex: 1 },
  bodyPad: { padding: 24, paddingBottom: 32 },
  intro: { fontSize: 11, color: T.slate, lineHeight: 18, marginBottom: 22 },
  card: {
    flexDirection: "row",
    backgroundColor: T.white,
    borderWidth: 1.5,
    borderColor: T.mist,
    borderRadius: 16,
    padding: 18,
    marginBottom: 14,
    gap: 14,
  },
  iconBox: {
    width: 48,
    height: 48,
    borderRadius: 14,
    alignItems: "center",
    justifyContent: "center",
  },
  iconEmoji: { fontSize: 22 },
  cardBody: { flex: 1 },
  cardTitle: { fontSize: 13, fontWeight: "700", color: T.ink, marginBottom: 4 },
  cardDesc: { fontSize: 10, color: T.slate, lineHeight: 16 },
  pill: {
    alignSelf: "flex-start",
    marginTop: 8,
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 100,
  },
  pillText: { fontSize: 9, fontWeight: "700" },
  info: {
    backgroundColor: T.amberPale,
    borderWidth: 1,
    borderColor: "rgba(217,119,6,0.2)",
    borderRadius: 12,
    padding: 12,
  },
  infoText: { fontSize: 10, color: "#92400e", lineHeight: 16 },
});
