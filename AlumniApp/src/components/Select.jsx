import { View, Text, StyleSheet } from "react-native";
import { Picker } from "@react-native-picker/picker";
import { T } from "../constants/colors";

export default function Select({ label, value, onValueChange, options, error, hint }) {
  return (
    <View style={styles.wrap}>
      {label ? <Text style={styles.label}>{label}</Text> : null}
      <View style={[styles.pickerWrap, error && styles.pickerError]}>
        <Picker
          selectedValue={value || ""}
          onValueChange={(v) => onValueChange(v === "" ? "" : v)}
          style={styles.picker}
          dropdownIconColor={T.slate}
        >
          <Picker.Item label="— Select —" value="" color={T.silver} />
          {options.map((o) => (
            <Picker.Item key={o.value} label={o.label} value={o.value} />
          ))}
        </Picker>
      </View>
      {hint ? <Text style={styles.hint}>{hint}</Text> : null}
      {error ? <Text style={styles.error}>⚠ {error}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: 14 },
  label: {
    fontSize: 11,
    fontWeight: "600",
    color: T.slate,
    marginBottom: 5,
  },
  pickerWrap: {
    borderWidth: 1.5,
    borderColor: T.fog,
    borderRadius: 12,
    backgroundColor: T.white,
    overflow: "hidden",
  },
  pickerError: { borderColor: T.danger },
  picker: { height: 44 },
  hint: { fontSize: 9, color: T.silver, marginTop: 3 },
  error: { fontSize: 9, color: T.danger, marginTop: 3 },
});
