import { useEffect, useMemo, useState } from "react";
import {
  View,
  Text,
  Modal,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  ScrollView,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { T } from "../constants/colors";
import Avatar from "./Avatar";
import AppIcon from "./AppIcon";
import { ICONS } from "../constants/icons";
import { fetchDirectory, sendMessage } from "../api/alumni";
import { useAuth } from "../context/AuthContext";

export default function ComposeMessageModal({ visible, onClose, onSent }) {
  const { user } = useAuth();
  const [recipients, setRecipients] = useState([]);
  const [loadingRecipients, setLoadingRecipients] = useState(false);
  const [recipientQuery, setRecipientQuery] = useState("");
  const [selected, setSelected] = useState(null);
  const [body, setBody] = useState("");
  const [sending, setSending] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!visible) return;
    setError("");
    setBody("");
    setSelected(null);
    setRecipientQuery("");
    setLoadingRecipients(true);
    fetchDirectory()
      .then((list) => setRecipients(list.filter((a) => a.id !== user?.id)))
      .catch((e) => setError(e.message || "Could not load alumni list"))
      .finally(() => setLoadingRecipients(false));
  }, [visible, user?.id]);

  const filteredRecipients = useMemo(() => {
    const q = recipientQuery.trim().toLowerCase();
    if (!q) return recipients.slice(0, 40);
    return recipients
      .filter((a) => {
        const name = `${a.firstname} ${a.lastname}`.toLowerCase();
        return name.includes(q) || (a.program || "").toLowerCase().includes(q);
      })
      .slice(0, 40);
  }, [recipients, recipientQuery]);

  const handleSend = async () => {
    if (!selected) {
      setError("Choose who to message.");
      return;
    }
    const text = body.trim();
    if (!text) {
      setError("Write a message.");
      return;
    }
    setSending(true);
    setError("");
    try {
      await sendMessage(selected.id, text);
      onSent?.();
      onClose();
    } catch (e) {
      setError(e.message || "Could not send message");
    } finally {
      setSending(false);
    }
  };

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose}>
      <SafeAreaView style={styles.safe} edges={["top", "bottom"]}>
        <KeyboardAvoidingView
          style={styles.flex}
          behavior={Platform.OS === "ios" ? "padding" : "height"}
          keyboardVerticalOffset={Platform.OS === "ios" ? 4 : 0}
        >
          <View style={styles.header}>
            <TouchableOpacity onPress={onClose} hitSlop={10} style={styles.headerBtn}>
              <AppIcon name={ICONS.close} size={26} color={T.slate} />
            </TouchableOpacity>
            <Text style={styles.headerTitle}>New Message</Text>
            <TouchableOpacity onPress={handleSend} disabled={sending} hitSlop={10} style={styles.headerBtn}>
              {sending ? (
                <ActivityIndicator size="small" color={T.forest} />
              ) : (
                <Text style={[styles.send, (!selected || !body.trim()) && styles.sendDisabled]}>Send</Text>
              )}
            </TouchableOpacity>
          </View>

          <ScrollView
            style={styles.scroll}
            contentContainerStyle={styles.scrollContent}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}
          >
            <Text style={styles.label}>To</Text>
            {selected ? (
              <Pressable style={styles.selectedChip} onPress={() => setSelected(null)}>
                <Text style={styles.selectedName}>
                  {selected.firstname} {selected.lastname}
                </Text>
                <AppIcon name={ICONS.close} size={18} color={T.slate} />
              </Pressable>
            ) : (
              <View style={styles.searchRow}>
                <AppIcon name={ICONS.search} size={18} color={T.silver} />
                <TextInput
                  value={recipientQuery}
                  onChangeText={setRecipientQuery}
                  placeholder="Search alumni…"
                  placeholderTextColor={T.silver}
                  style={styles.searchInput}
                  autoCapitalize="words"
                />
              </View>
            )}

            {!selected ? (
              <View style={styles.recipientBlock}>
                {loadingRecipients ? (
                  <ActivityIndicator color={T.forest} style={{ marginVertical: 16 }} />
                ) : (
                  filteredRecipients.map((a) => (
                    <TouchableOpacity
                      key={a.id}
                      style={styles.recipientRow}
                      onPress={() => {
                        setSelected(a);
                        setRecipientQuery("");
                      }}
                    >
                      <Avatar
                        initials={a.initials}
                        color={a.color}
                        size={40}
                        uri={a.profile_image}
                        userId={a.id}
                      />
                      <View style={{ flex: 1 }}>
                        <Text style={styles.recipientName}>
                          {a.firstname} {a.lastname}
                        </Text>
                        <Text style={styles.recipientMeta}>{a.program}</Text>
                      </View>
                      <AppIcon name={ICONS.chevronForward} size={18} color={T.fog} />
                    </TouchableOpacity>
                  ))
                )}
                {!loadingRecipients && filteredRecipients.length === 0 ? (
                  <Text style={styles.hint}>No alumni match your search.</Text>
                ) : null}
              </View>
            ) : null}

            {selected ? (
              <View style={styles.messageBlock}>
                <Text style={styles.label}>Message</Text>
                <TextInput
                  value={body}
                  onChangeText={setBody}
                  placeholder="Write your message…"
                  placeholderTextColor={T.silver}
                  style={styles.messageInput}
                  multiline
                  textAlignVertical="top"
                />
              </View>
            ) : null}

            {error ? <Text style={styles.error}>⚠ {error}</Text> : null}
          </ScrollView>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: T.white },
  flex: { flex: 1 },
  header: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: T.mist,
  },
  headerBtn: { minWidth: 56, alignItems: "center" },
  headerTitle: { fontSize: 17, fontWeight: "700", color: T.ink },
  send: { fontSize: 15, fontWeight: "700", color: T.forest },
  sendDisabled: { color: T.silver },
  scroll: { flex: 1 },
  scrollContent: { padding: 16, paddingBottom: 40, flexGrow: 1 },
  label: {
    fontSize: 11,
    fontWeight: "700",
    letterSpacing: 0.5,
    textTransform: "uppercase",
    color: T.silver,
    marginBottom: 8,
  },
  searchRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    borderColor: T.fog,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: T.snow,
    marginBottom: 12,
  },
  searchInput: { flex: 1, fontSize: 15, color: T.ink, padding: 0 },
  selectedChip: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: T.mist,
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 12,
    marginBottom: 16,
  },
  selectedName: { flex: 1, fontSize: 15, fontWeight: "600", color: T.forest },
  recipientBlock: { marginBottom: 8, maxHeight: 220 },
  recipientRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: T.mist,
  },
  recipientName: { fontSize: 14, fontWeight: "600", color: T.ink },
  recipientMeta: { fontSize: 11, color: T.silver },
  messageBlock: { flex: 1, minHeight: 200 },
  messageInput: {
    flex: 1,
    minHeight: 160,
    maxHeight: 280,
    borderWidth: 1,
    borderColor: T.fog,
    borderRadius: 12,
    padding: 14,
    fontSize: 15,
    color: T.ink,
    backgroundColor: T.snow,
    lineHeight: 22,
  },
  hint: { textAlign: "center", color: T.silver, fontSize: 12, padding: 16 },
  error: { color: T.danger, fontSize: 12, marginTop: 12 },
});
