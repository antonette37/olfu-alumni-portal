import { useCallback, useState } from "react";

import {

  View,

  Text,

  ScrollView,

  TouchableOpacity,

  StyleSheet,

  RefreshControl,

} from "react-native";

import { useFocusEffect } from "@react-navigation/native";

import { T } from "../constants/colors";

import Avatar from "../components/Avatar";

import ComposeMessageModal from "../components/ComposeMessageModal";

import { fetchConversations } from "../api/alumni";
import AppIcon from "../components/AppIcon";
import { ICONS } from "../constants/icons";



export default function MessagesScreen() {

  const [tab, setTab] = useState("all");

  const [conversations, setConversations] = useState([]);

  const [error, setError] = useState("");

  const [refreshing, setRefreshing] = useState(false);

  const [composeOpen, setComposeOpen] = useState(false);



  const load = useCallback(() => {

    setError("");

    return fetchConversations()

      .then(setConversations)

      .catch((e) => {

        setError(e.message);

        setConversations([]);

      });

  }, []);



  useFocusEffect(

    useCallback(() => {

      load();

    }, [load])

  );



  const onRefresh = async () => {

    setRefreshing(true);

    await load();

    setRefreshing(false);

  };



  const unreadCount = conversations.filter((c) => c.unread > 0).length;

  const list =

    tab === "unread" ? conversations.filter((c) => c.unread > 0) : conversations;



  return (

    <View style={styles.flex}>

      <View style={styles.header}>

        <Text style={styles.headerTitle}>Messages</Text>

      </View>

      <View style={styles.tabs}>

        {[

          { id: "all", label: "All" },

          { id: "unread", label: `Unread (${unreadCount})` },

        ].map((t) => (

          <TouchableOpacity

            key={t.id}

            onPress={() => setTab(t.id)}

            style={[styles.tab, tab === t.id && styles.tabOn]}

          >

            <Text style={[styles.tabText, tab === t.id && styles.tabTextOn]}>{t.label}</Text>

          </TouchableOpacity>

        ))}

      </View>

      <ScrollView

        style={styles.list}

        contentContainerStyle={styles.listContent}

        refreshControl={

          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={T.forest} />

        }

      >

        {error ? (

          <Text style={styles.empty}>⚠ {error}</Text>

        ) : null}

        {!error && list.length === 0 ? (

          <Text style={styles.empty}>No conversations yet.</Text>

        ) : null}

        {list.map((c) => (

          <TouchableOpacity

            key={c.id}

            style={[styles.row, c.unread > 0 && styles.rowUnread]}

          >

            <View>

              <Avatar

                initials={c.initials}

                color={c.color}

                size={44}

                uri={c.profile_image}

                userId={c.other_user_id}

                profileImageData={c.profile_image_data}

              />

              {c.online ? <View style={styles.online} /> : null}

            </View>

            <View style={styles.rowBody}>

              <Text style={styles.name}>{c.name}</Text>

              <Text

                style={[styles.preview, c.unread > 0 && styles.previewBold]}

                numberOfLines={1}

              >

                {c.preview}

              </Text>

            </View>

            <View style={styles.meta}>

              <Text style={styles.time}>{c.time}</Text>

              {c.unread > 0 ? (

                <View style={styles.badge}>

                  <Text style={styles.badgeText}>{c.unread}</Text>

                </View>

              ) : null}

            </View>

          </TouchableOpacity>

        ))}

      </ScrollView>



      <TouchableOpacity

        style={styles.fab}

        onPress={() => setComposeOpen(true)}

        activeOpacity={0.85}

        accessibilityLabel="New message"

      >

        <AppIcon name={ICONS.add} size={32} color={T.white} />

      </TouchableOpacity>



      <ComposeMessageModal

        visible={composeOpen}

        onClose={() => setComposeOpen(false)}

        onSent={load}

      />

    </View>

  );

}



const styles = StyleSheet.create({

  flex: { flex: 1, backgroundColor: T.white },

  header: {

    backgroundColor: T.forest,

    paddingVertical: 14,

    paddingHorizontal: 16,

  },

  headerTitle: { fontSize: 20, fontWeight: "700", color: T.white },

  tabs: { flexDirection: "row", gap: 6, padding: 14, paddingBottom: 8 },

  tab: {

    paddingVertical: 4,

    paddingHorizontal: 10,

    borderRadius: 100,

    backgroundColor: T.mist,

  },

  tabOn: { backgroundColor: T.forest },

  tabText: { fontSize: 9, fontWeight: "700", color: T.leaf },

  tabTextOn: { color: T.white },

  list: { flex: 1 },
  listContent: { paddingBottom: 88 },

  empty: { fontSize: 10, color: T.silver, textAlign: "center", padding: 24 },

  row: {

    flexDirection: "row",

    alignItems: "center",

    gap: 10,

    paddingVertical: 12,

    paddingHorizontal: 14,

    borderBottomWidth: 1,

    borderBottomColor: T.mist,

    backgroundColor: T.white,

  },

  rowUnread: { backgroundColor: T.snow },

  rowBody: { flex: 1, minWidth: 0 },

  name: { fontSize: 12, fontWeight: "600", color: T.ink },

  preview: { fontSize: 10, color: T.slate },

  previewBold: { color: T.ink, fontWeight: "600" },

  meta: { alignItems: "flex-end", gap: 4 },

  time: { fontSize: 9, color: T.silver },

  badge: {

    width: 17,

    height: 17,

    borderRadius: 9,

    backgroundColor: T.leaf,

    alignItems: "center",

    justifyContent: "center",

  },

  badgeText: { fontSize: 8, fontWeight: "700", color: T.white },

  online: {

    position: "absolute",

    bottom: 1,

    right: 1,

    width: 11,

    height: 11,

    borderRadius: 6,

    backgroundColor: T.fern,

    borderWidth: 2,

    borderColor: T.white,

  },

  fab: {

    position: "absolute",

    right: 20,

    bottom: 24,

    width: 56,

    height: 56,

    borderRadius: 28,

    backgroundColor: T.forest,

    alignItems: "center",

    justifyContent: "center",

    elevation: 8,

    shadowColor: "#000",

    shadowOffset: { width: 0, height: 4 },

    shadowOpacity: 0.25,

    shadowRadius: 8,

  },

  fabIcon: {

    fontSize: 32,

    fontWeight: "300",

    color: T.white,

    lineHeight: 34,

    marginTop: -2,

  },

});

