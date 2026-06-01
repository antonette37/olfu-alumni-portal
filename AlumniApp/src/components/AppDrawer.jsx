import { useEffect, useRef, useCallback, useState } from "react";
import {
  View,
  StyleSheet,
  Animated,
  Dimensions,
  TouchableWithoutFeedback,
  Easing,
} from "react-native";
import { T } from "../constants/colors";
import DrawerContent from "./DrawerContent";
import { useDrawer } from "../context/DrawerContext";

const { width: SCREEN_W } = Dimensions.get("window");
const DRAWER_W = Math.min(SCREEN_W * 0.82, 320);

export default function AppDrawer({ children, navigation }) {
  const { registerControls } = useDrawer();
  const slideX = useRef(new Animated.Value(-DRAWER_W)).current;
  const overlay = useRef(new Animated.Value(0)).current;
  const isOpenRef = useRef(false);
  const animatingRef = useRef(false);
  const [overlayActive, setOverlayActive] = useState(false);

  const runOpen = useCallback(() => {
    if (isOpenRef.current || animatingRef.current) return;
    animatingRef.current = true;
    isOpenRef.current = true;
    setOverlayActive(true);
    Animated.parallel([
      Animated.timing(slideX, {
        toValue: 0,
        duration: 260,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
      Animated.timing(overlay, {
        toValue: 1,
        duration: 260,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
    ]).start(() => {
      animatingRef.current = false;
    });
  }, [slideX, overlay]);

  const runClose = useCallback(() => {
    if (!isOpenRef.current || animatingRef.current) return;
    animatingRef.current = true;
    Animated.parallel([
      Animated.timing(slideX, {
        toValue: -DRAWER_W,
        duration: 220,
        easing: Easing.in(Easing.cubic),
        useNativeDriver: true,
      }),
      Animated.timing(overlay, {
        toValue: 0,
        duration: 220,
        easing: Easing.in(Easing.cubic),
        useNativeDriver: true,
      }),
    ]).start(() => {
      isOpenRef.current = false;
      animatingRef.current = false;
      setOverlayActive(false);
    });
  }, [slideX, overlay]);

  useEffect(() => {
    registerControls(runOpen, runClose);
    return () => registerControls(() => {}, () => {});
  }, [registerControls, runOpen, runClose]);

  const overlayOpacity = overlay.interpolate({
    inputRange: [0, 1],
    outputRange: [0, 0.45],
  });

  return (
    <View style={styles.root}>
      {children}

      <Animated.View
        pointerEvents={overlayActive ? "auto" : "none"}
        style={[styles.overlayWrap, { opacity: overlayOpacity }]}
      >
        <TouchableWithoutFeedback onPress={runClose}>
          <View style={StyleSheet.absoluteFill} />
        </TouchableWithoutFeedback>
      </Animated.View>

      <Animated.View
        pointerEvents={overlayActive ? "auto" : "none"}
        style={[styles.drawer, { width: DRAWER_W, transform: [{ translateX: slideX }] }]}
      >
        <DrawerContent navigation={navigation} onClose={runClose} />
      </Animated.View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  overlayWrap: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: "#000",
    zIndex: 10,
  },
  drawer: {
    position: "absolute",
    top: 0,
    left: 0,
    bottom: 0,
    backgroundColor: T.white,
    zIndex: 20,
    elevation: 24,
    shadowColor: "#000",
    shadowOpacity: 0.25,
    shadowOffset: { width: 4, height: 0 },
    shadowRadius: 12,
  },
});
