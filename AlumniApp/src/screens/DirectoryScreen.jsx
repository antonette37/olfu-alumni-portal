import { useEffect, useState } from "react";
import { View, Text, TextInput, ScrollView, TouchableOpacity, StyleSheet } from "react-native";
import { T, shadow } from "../constants/colors";
import Avatar from "../components/Avatar";
import AppIcon from "../components/AppIcon";
import { ICONS } from "../constants/icons";
import { fetchDirectory } from "../api/alumni";

const FILTERS = ["All", "BSCS", "BSIT", "ACT"];

function navigateRoot(navigation, screen, params) {
  let nav = navigation;
  while (nav?.getParent?.()) {
    nav = nav.getParent();
  }
  nav?.navigate(screen, params);
}

export default function DirectoryScreen({ navigation }) {
  const [query, setQuery] = useState("");
  const [filter, setFilter] = useState("All");
  const [alumni, setAlumni] = useState([]);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    setError("");
    fetchDirectory(query)
      .then(setAlumni)
      .catch((e) => {
        setError(e.message);
        setAlumni([]);
      })
      .finally(() => setLoading(false));
  }, [query]);

  const filtered = alumni.filter((a) => {
    const name = `${a.firstname} ${a.lastname}`.toLowerCase();
    const matchQ =
      name.includes(query.toLowerCase()) ||
      (a.company || "").toLowerCase().includes(query.toLowerCase());
    const matchF =
      filter === "All" || (a.program || "").toLowerCase().includes(filter.toLowerCase());
    return matchQ && matchF;
  });

  return (
    <View style={styles.flex}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Alumni Directory</Text>
      </View>
      <View style={styles.searchRow}>
        <Text style={styles.searchIcon}>🔍</Text>
        <TextInput
          value={query}
          onChangeText={setQuery}
          placeholder="Search alumni…"
          placeholderTextColor={T.silver}
          style={styles.searchInput}
        />
      </View>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filters} contentContainerStyle={styles.filtersPad}>
        {FILTERS.map((p) => (
          <TouchableOpacity
            key={p}
            onPress={() => setFilter(p)}
            style={[styles.filterBtn, filter === p && styles.filterBtnOn]}
          >
            <Text style={[styles.filterText, filter === p && styles.filterTextOn]}>{p}</Text>
          </TouchableOpacity>
        ))}
      </ScrollView>
      <ScrollView style={styles.list} contentContainerStyle={styles.listPad}>
        {error ? <Text style={styles.empty}>⚠ {error}</Text> : null}
        {!loading && !error && filtered.length === 0 ? (
          <Text style={styles.empty}>No alumni found.</Text>
        ) : null}
        {filtered.map((a) => (
          <TouchableOpacity
            key={a.id}
            style={[styles.row, shadow]}
            activeOpacity={0.75}
            onPress={() =>
              navigateRoot(navigation, "AlumniDetail", {
                alumniId: a.id,
                preview: {
                  id: a.id,
                  firstname: a.firstname,
                  lastname: a.lastname,
                  program: a.program,
                  year_graduated: a.year_graduated,
                  position: a.position,
                  company: a.company,
                  profile_image: a.profile_image,
                  photo: a.photo,
                },
              })
            }
          >
            <Avatar
              initials={a.initials}
              color={a.color}
              size={42}
              uri={a.profile_image}
              photo={a.photo}
              userId={a.id}
            />
            <View style={styles.rowBody}>
              <Text style={styles.name}>
                {a.firstname} {a.lastname}
              </Text>
              <Text style={styles.program}>{a.program}</Text>
              <Text style={styles.meta}>
                Class of {a.year_graduated} · {a.position} @ {a.company}
              </Text>
            </View>
            <TouchableOpacity style={styles.msgBtn} onPress={(e) => e.stopPropagation?.()}>
              <AppIcon name={ICONS.messages} size={16} color={T.moss} />
            </TouchableOpacity>
          </TouchableOpacity>
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: T.cream },
  header: { backgroundColor: T.forest, paddingVertical: 14, paddingHorizontal: 16 },
  headerTitle: { fontSize: 20, fontWeight: "700", color: T.white },
  searchRow: {
    flexDirection: "row",
    alignItems: "center",
    margin: 14,
    marginBottom: 8,
    backgroundColor: T.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: T.fog,
    paddingHorizontal: 12,
    gap: 6,
  },
  searchIcon: { color: T.silver },
  searchInput: { flex: 1, fontSize: 11, color: T.ink, paddingVertical: 10 },
  filters: { maxHeight: 36, marginBottom: 4 },
  filtersPad: { paddingHorizontal: 14, gap: 6 },
  filterBtn: {
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 100,
    backgroundColor: T.mist,
  },
  filterBtnOn: { backgroundColor: T.forest },
  filterText: { fontSize: 9, fontWeight: "700", color: T.leaf },
  filterTextOn: { color: T.white },
  list: { flex: 1 },
  listPad: { paddingHorizontal: 14, paddingBottom: 16 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    backgroundColor: T.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: T.mist,
    padding: 12,
    marginBottom: 8,
  },
  rowBody: { flex: 1 },
  name: { fontSize: 12, fontWeight: "600", color: T.ink },
  program: { fontSize: 10, color: T.slate },
  meta: { fontSize: 9, color: T.silver, marginTop: 2 },
  empty: { fontSize: 10, color: T.silver, textAlign: "center", padding: 20 },
  msgBtn: {
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: T.mist,
    alignItems: "center",
    justifyContent: "center",
  },
});
