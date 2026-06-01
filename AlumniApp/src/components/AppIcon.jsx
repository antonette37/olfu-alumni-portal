import { Ionicons } from "@expo/vector-icons";
import { T } from "../constants/colors";

/**
 * Portal icon set — Ionicons, forest/gold palette.
 * @param {keyof typeof import('../constants/icons').ICONS | string} name
 */
export default function AppIcon({ name, size = 22, color = T.forest, style }) {
  return <Ionicons name={name} size={size} color={color} style={style} />;
}
