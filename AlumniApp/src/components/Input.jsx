import { useState } from "react";
import { View, Text, TextInput, TouchableOpacity, StyleSheet } from "react-native";
import { T } from "../constants/colors";

export default function Input({
  label,
  type = "text",
  value,
  onChangeText,
  placeholder,
  error,
  icon,
  readOnly,
  hint,
  keyboardType,
  maxLength,
}) {
  const [show, setShow] = useState(false);
  const isPass = type === "password";

  return (
    <View style={styles.wrap}>
      {label ? <Text style={styles.label}>{label}</Text> : null}
      <View style={styles.row}>
        {icon ? <Text style={styles.icon}>{icon}</Text> : null}
        <TextInput
          value={value}
          onChangeText={onChangeText}
          placeholder={placeholder}
          editable={!readOnly}
          secureTextEntry={isPass && !show}
          keyboardType={keyboardType || (type === "email" ? "email-address" : "default")}
          autoCapitalize={type === "email" ? "none" : "sentences"}
          maxLength={maxLength}
          style={[
            styles.input,
            icon && styles.inputIcon,
            isPass && styles.inputPass,
            error && styles.inputError,
            readOnly && styles.readOnly,
          ]}
          placeholderTextColor={T.silver}
        />
        {isPass ? (
          <TouchableOpacity onPress={() => setShow((s) => !s)} style={styles.eye}>
            <Text style={styles.eyeText}>{show ? "🙈" : "👁️"}</Text>
          </TouchableOpacity>
        ) : null}
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
  row: { position: "relative" },
  icon: {
    position: "absolute",
    left: 12,
    top: 12,
    fontSize: 14,
    color: T.silver,
    zIndex: 1,
  },
  input: {
    width: "100%",
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: 12,
    borderWidth: 1.5,
    borderColor: T.fog,
    backgroundColor: T.white,
    fontSize: 11,
    color: T.ink,
  },
  inputIcon: { paddingLeft: 34 },
  inputPass: { paddingRight: 38 },
  inputError: { borderColor: T.danger },
  readOnly: { backgroundColor: "rgba(13,46,24,0.04)" },
  eye: { position: "absolute", right: 10, top: 10 },
  eyeText: { fontSize: 13 },
  hint: { fontSize: 9, color: T.silver, marginTop: 3 },
  error: { fontSize: 9, color: T.danger, marginTop: 3 },
});
