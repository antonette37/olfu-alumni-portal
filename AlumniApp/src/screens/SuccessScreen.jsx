import { View, Text, TouchableOpacity, StyleSheet } from "react-native";
import { T } from "../constants/colors";

export default function SuccessScreen({ route, navigation }) {
  const regType = route.params?.regType ?? "new";
  const isLegacy = regType === "legacy";

  return (
    <View style={styles.root}>
      <View style={styles.iconWrap}>
        <Text style={styles.icon}>✅</Text>
      </View>
      <Text style={styles.title}>Registration Submitted!</Text>
      <Text style={styles.body}>
        {isLegacy
          ? "Your Alumni Card information has been submitted for verification. The CCS Alumni Office will review and link your existing ID."
          : "Your Student ID has been submitted for verification. The CCS Alumni Office will assign your Alumni ID and activate your account."}
      </Text>
      <View style={styles.box}>
        <Text style={styles.boxText}>
          ⏱ Approval usually takes <Text style={styles.gold}>1–3 business days</Text>
        </Text>
        <Text style={styles.boxSub}>
          {isLegacy
            ? "Your existing Alumni ID will be linked upon verification."
            : "Your Alumni ID will be emailed to you upon approval."}
        </Text>
      </View>
      <TouchableOpacity style={styles.btn} onPress={() => navigation.navigate("Login")}>
        <Text style={styles.btnText}>Back to Login</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    padding: 28,
    backgroundColor: T.forest,
  },
  iconWrap: {
    width: 76,
    height: 76,
    borderRadius: 38,
    backgroundColor: "rgba(255,255,255,0.13)",
    borderWidth: 2,
    borderColor: "rgba(255,255,255,0.25)",
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 20,
  },
  icon: { fontSize: 32 },
  title: { fontSize: 22, fontWeight: "700", color: T.white, marginBottom: 8, textAlign: "center" },
  body: {
    fontSize: 11,
    color: "rgba(255,255,255,0.65)",
    lineHeight: 18,
    textAlign: "center",
    marginBottom: 24,
  },
  box: {
    width: "100%",
    backgroundColor: "rgba(255,255,255,0.1)",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.18)",
    borderRadius: 14,
    padding: 16,
    marginBottom: 24,
  },
  boxText: { fontSize: 10, color: "rgba(255,255,255,0.7)", textAlign: "center" },
  gold: { color: T.goldLt, fontWeight: "700" },
  boxSub: {
    fontSize: 10,
    color: "rgba(255,255,255,0.55)",
    marginTop: 4,
    textAlign: "center",
  },
  btn: {
    width: "100%",
    paddingVertical: 12,
    borderRadius: 13,
    backgroundColor: "rgba(255,255,255,0.14)",
    borderWidth: 1.5,
    borderColor: "rgba(255,255,255,0.28)",
    alignItems: "center",
  },
  btnText: { fontSize: 12, fontWeight: "600", color: T.white },
});
