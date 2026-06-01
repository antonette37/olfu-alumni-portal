import { useEffect, useState } from "react";
import { View, Text, StyleSheet, Animated } from "react-native";
import { T } from "../constants/colors";

export default function SplashScreen({ navigation }) {
  const [phase, setPhase] = useState(0);
  const fade = useState(() => new Animated.Value(1))[0];

  useEffect(() => {
    const t1 = setTimeout(() => setPhase(1), 800);
    const t2 = setTimeout(() => setPhase(2), 2200);
    const t3 = setTimeout(() => {
      Animated.timing(fade, { toValue: 0, duration: 700, useNativeDriver: true }).start(() => {
        navigation.replace("Login");
      });
    }, 2900);
    return () => {
      clearTimeout(t1);
      clearTimeout(t2);
      clearTimeout(t3);
    };
  }, [navigation, fade]);

  return (
    <Animated.View style={[styles.root, { opacity: fade }]}>
      {[180, 270, 360].map((s, i) => (
        <View
          key={s}
          style={[
            styles.ring,
            {
              width: s,
              height: s,
              borderColor: `rgba(255,255,255,${0.06 - i * 0.015})`,
            },
          ]}
        />
      ))}
      <View style={styles.glow} />
      <View style={[styles.center, phase >= 0 && styles.visible]}>
        <View style={styles.logoBox}>
          <Text style={styles.logoEmoji}>🎓</Text>
        </View>
        <Text style={styles.title}>CCS Alumni</Text>
        <Text style={styles.subtitle}>Our Lady of Fatima University</Text>
      </View>
      {phase >= 1 ? (
        <Text style={styles.tagline}>Connected. Growing. Always.</Text>
      ) : null}
      <View style={styles.dots}>
        {[0, 1, 2].map((i) => (
          <View key={i} style={[styles.dot, { opacity: 0.5 - i * 0.12 }]} />
        ))}
      </View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: T.forest,
  },
  ring: {
    position: "absolute",
    borderRadius: 999,
    borderWidth: 1,
  },
  glow: {
    position: "absolute",
    top: -50,
    right: -50,
    width: 160,
    height: 160,
    borderRadius: 80,
    backgroundColor: "rgba(184,146,42,0.18)",
  },
  center: { alignItems: "center", opacity: 0.3 },
  visible: { opacity: 1 },
  logoBox: {
    width: 80,
    height: 80,
    borderRadius: 22,
    backgroundColor: "rgba(255,255,255,0.1)",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.18)",
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 18,
  },
  logoEmoji: { fontSize: 34 },
  title: { fontSize: 26, fontWeight: "700", color: T.white },
  subtitle: { fontSize: 14, fontStyle: "italic", color: T.goldLt, marginTop: 3 },
  tagline: {
    position: "absolute",
    bottom: 72,
    fontSize: 10,
    color: "rgba(255,255,255,0.45)",
    letterSpacing: 2,
    textTransform: "uppercase",
  },
  dots: { position: "absolute", bottom: 48, flexDirection: "row", gap: 5 },
  dot: { width: 5, height: 5, borderRadius: 3, backgroundColor: T.white },
});
