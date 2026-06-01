import { useState } from "react";
import { View, Text, ScrollView, TouchableOpacity, StyleSheet } from "react-native";
import { T, shadow } from "../constants/colors";
import ScreenHeader from "../components/ScreenHeader";
import AppIcon from "../components/AppIcon";

/** Reusable layout for About, Alumni Card, FAQs (web portal static pages). */
export default function PortalInfoScreen({ route, navigation }) {
  const { title, hero, sections, faqSections } = route.params || {};
  const [openFaq, setOpenFaq] = useState(null);
  const [faqFilter, setFaqFilter] = useState(
    faqSections ? Object.keys(faqSections)[0] : null
  );

  return (
    <View style={styles.root}>
      <ScreenHeader title={title || "Info"} navigation={navigation} showBack />
      <ScrollView contentContainerStyle={styles.pad}>
        {hero ? (
          <View style={[styles.hero, shadow]}>
            {hero.kicker ? <Text style={styles.kicker}>{hero.kicker}</Text> : null}
            <Text style={styles.heroTitle}>{hero.title}</Text>
            {hero.subtitle ? <Text style={styles.heroSub}>{hero.subtitle}</Text> : null}
            {hero.stats?.length ? (
              <View style={styles.stats}>
                {hero.stats.map((s) => (
                  <View key={s.label} style={styles.stat}>
                    <Text style={styles.statVal}>{s.value}</Text>
                    <Text style={styles.statLbl}>{s.label}</Text>
                  </View>
                ))}
              </View>
            ) : null}
          </View>
        ) : null}

        {sections?.map((sec, i) => (
          <View key={i} style={[styles.card, shadow]}>
            {sec.title ? <Text style={styles.cardTitle}>{sec.title}</Text> : null}
            {sec.body ? <Text style={styles.body}>{sec.body}</Text> : null}
            {sec.items?.map((item, j) => (
              <View key={j} style={styles.featureRow}>
                {item.icon ? (
                  typeof item.icon === "string" && !item.icon.includes(" ") ? (
                    <AppIcon name={item.icon} size={22} color={T.moss} style={styles.featureIconWrap} />
                  ) : (
                    <Text style={styles.featureIcon}>{item.icon}</Text>
                  )
                ) : null}
                <View style={{ flex: 1 }}>
                  {item.title ? <Text style={styles.featureTitle}>{item.title}</Text> : null}
                  {item.text ? <Text style={styles.body}>{item.text}</Text> : null}
                </View>
              </View>
            ))}
            {sec.list?.map((line, j) => (
              <Text key={j} style={styles.bullet}>
                • {line}
              </Text>
            ))}
            {sec.note ? <Text style={styles.note}>{sec.note}</Text> : null}
          </View>
        ))}

        {faqSections ? (
          <>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chips}>
              {Object.keys(faqSections).map((key) => (
                <TouchableOpacity
                  key={key}
                  onPress={() => {
                    setFaqFilter(key);
                    setOpenFaq(null);
                  }}
                  style={[styles.chip, faqFilter === key && styles.chipOn]}
                >
                  <Text style={[styles.chipText, faqFilter === key && styles.chipTextOn]}>
                    {key}
                  </Text>
                </TouchableOpacity>
              ))}
            </ScrollView>
            {(faqSections[faqFilter] || []).map((item, idx) => {
              const key = `${faqFilter}-${idx}`;
              const open = openFaq === key;
              return (
                <TouchableOpacity
                  key={key}
                  style={[styles.faqItem, shadow]}
                  onPress={() => setOpenFaq(open ? null : key)}
                  activeOpacity={0.85}
                >
                  <Text style={styles.faqQ}>{item.q}</Text>
                  {open ? <Text style={styles.faqA}>{item.a}</Text> : null}
                </TouchableOpacity>
              );
            })}
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: T.cream },
  pad: { padding: 14, paddingBottom: 32 },
  hero: {
    backgroundColor: T.forest,
    borderRadius: 16,
    padding: 20,
    marginBottom: 12,
  },
  kicker: { fontSize: 10, color: T.goldLt, marginBottom: 4 },
  heroTitle: { fontSize: 22, fontWeight: "700", color: T.white },
  heroSub: { fontSize: 12, color: "rgba(255,255,255,0.85)", marginTop: 6 },
  stats: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginTop: 14 },
  stat: {
    flex: 1,
    minWidth: "40%",
    backgroundColor: "rgba(255,255,255,0.1)",
    borderRadius: 10,
    padding: 10,
  },
  statVal: { fontSize: 16, fontWeight: "700", color: T.white },
  statLbl: { fontSize: 9, color: "rgba(255,255,255,0.75)", marginTop: 2 },
  card: {
    backgroundColor: T.white,
    borderRadius: 14,
    padding: 14,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: T.mist,
  },
  cardTitle: { fontSize: 16, fontWeight: "700", color: T.forest, marginBottom: 8 },
  body: { fontSize: 12, color: T.slate, lineHeight: 18 },
  featureRow: { flexDirection: "row", gap: 10, marginTop: 10 },
  featureIcon: { fontSize: 20 },
  featureIconWrap: { width: 28 },
  featureTitle: { fontSize: 12, fontWeight: "600", color: T.ink },
  bullet: { fontSize: 12, color: T.slate, marginTop: 6, lineHeight: 18 },
  note: { fontSize: 11, color: T.silver, marginTop: 10, fontStyle: "italic" },
  chips: { marginBottom: 10, maxHeight: 36 },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 100,
    backgroundColor: T.mist,
    marginRight: 6,
  },
  chipOn: { backgroundColor: T.forest },
  chipText: { fontSize: 10, fontWeight: "600", color: T.leaf },
  chipTextOn: { color: T.white },
  faqItem: {
    backgroundColor: T.white,
    borderRadius: 12,
    padding: 14,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: T.mist,
  },
  faqQ: { fontSize: 12, fontWeight: "600", color: T.ink },
  faqA: { fontSize: 11, color: T.slate, marginTop: 8, lineHeight: 17 },
});
