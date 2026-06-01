import { useState } from "react";
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  KeyboardAvoidingView,
  Platform,
} from "react-native";
import { T } from "../constants/colors";
import Input from "../components/Input";
import PrimaryBtn from "../components/PrimaryBtn";
import { login } from "../api/auth";
import { useAuth } from "../context/AuthContext";

export default function LoginScreen({ navigation }) {
  const { setUser, setToken } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const handleLogin = async () => {
    setError("");
    if (!email.trim() || !password) {
      setError("Please enter your email and password.");
      return;
    }
    setLoading(true);
    try {
      const result = await login(email, password);
      setUser(result.user);
      setToken(result.token);
      navigation.reset({
        index: 0,
        routes: [{ name: "MainTabs" }],
      });
    } catch (e) {
      setError(e.message || "Login failed");
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.flex}
      behavior={Platform.OS === "ios" ? "padding" : undefined}
    >
      <ScrollView style={styles.flex} contentContainerStyle={styles.flexGrow} keyboardShouldPersistTaps="handled">
        <View style={styles.hero}>
          <View style={styles.heroGlow} />
          <Text style={styles.heroEmoji}>🎓</Text>
          <Text style={styles.heroTitle}>
            Welcome back,{"\n"}
            <Text style={styles.heroEm}>Alumna/Alumnus.</Text>
          </Text>
          <Text style={styles.heroSub}>Sign in to your CCS Alumni account</Text>
        </View>
        <View style={styles.body}>
          {error ? (
            <View style={styles.errBox}>
              <Text style={styles.errText}>⚠ {error}</Text>
            </View>
          ) : null}
          <Input
            label="Email Address"
            type="email"
            value={email}
            onChangeText={setEmail}
            placeholder="your@email.com"
            icon="📧"
          />
          <Input
            label="Password"
            type="password"
            value={password}
            onChangeText={setPassword}
            placeholder="Enter password"
            icon="🔒"
          />
          <TouchableOpacity style={styles.forgot}>
            <Text style={styles.forgotText}>Forgot password?</Text>
          </TouchableOpacity>
          <PrimaryBtn onPress={handleLogin} loading={loading}>
            Sign In
          </PrimaryBtn>
          <View style={styles.dividerRow}>
            <View style={styles.divider} />
            <Text style={styles.dividerText}>or</Text>
            <View style={styles.divider} />
          </View>
          <TouchableOpacity style={styles.outlineBtn} onPress={() => navigation.navigate("RegType")}>
            <Text style={styles.outlineText}>Create an Account</Text>
          </TouchableOpacity>
          <Text style={styles.legal}>
            By signing in you agree to the CCS Alumni Portal Terms of Service & Privacy Policy
          </Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: T.cream },
  flexGrow: { flexGrow: 1 },
  hero: {
    backgroundColor: T.forest,
    paddingTop: 48,
    paddingHorizontal: 24,
    paddingBottom: 42,
    overflow: "hidden",
  },
  heroGlow: {
    position: "absolute",
    top: -50,
    right: -50,
    width: 140,
    height: 140,
    borderRadius: 70,
    backgroundColor: "rgba(184,146,42,0.15)",
  },
  heroEmoji: { fontSize: 32, marginBottom: 14 },
  heroTitle: { fontSize: 24, fontWeight: "700", color: T.white, lineHeight: 30 },
  heroEm: { fontStyle: "italic", color: T.goldLt, fontWeight: "400" },
  heroSub: { fontSize: 11, color: "rgba(255,255,255,0.55)", marginTop: 6 },
  body: { padding: 24, paddingBottom: 28 },
  errBox: {
    backgroundColor: T.dangerPale,
    borderWidth: 1,
    borderColor: T.danger,
    borderRadius: 12,
    padding: 12,
    marginBottom: 14,
  },
  errText: { fontSize: 10, color: T.danger },
  forgot: { alignSelf: "flex-end", marginTop: -6, marginBottom: 16 },
  forgotText: { fontSize: 10, color: T.moss, fontWeight: "600" },
  dividerRow: { flexDirection: "row", alignItems: "center", gap: 8, marginVertical: 18 },
  divider: { flex: 1, height: 1, backgroundColor: T.fog },
  dividerText: { fontSize: 10, color: T.silver },
  outlineBtn: {
    paddingVertical: 12,
    borderRadius: 13,
    borderWidth: 1.5,
    borderColor: T.fog,
    alignItems: "center",
  },
  outlineText: { fontSize: 12, fontWeight: "600", color: T.forest },
  legal: {
    textAlign: "center",
    fontSize: 9,
    color: T.silver,
    marginTop: 20,
    lineHeight: 14,
  },
});
