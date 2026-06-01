import { createContext, useContext, useRef, useCallback } from "react";

const DrawerContext = createContext({
  openDrawer: () => {},
  closeDrawer: () => {},
  registerControls: () => {},
});

export function DrawerProvider({ children }) {
  const controls = useRef({ open: () => {}, close: () => {} });

  const registerControls = useCallback((open, close) => {
    controls.current = { open, close };
  }, []);

  const openDrawer = useCallback(() => {
    controls.current.open?.();
  }, []);

  const closeDrawer = useCallback(() => {
    controls.current.close?.();
  }, []);

  return (
    <DrawerContext.Provider value={{ openDrawer, closeDrawer, registerControls }}>
      {children}
    </DrawerContext.Provider>
  );
}

export const useDrawer = () => useContext(DrawerContext);
