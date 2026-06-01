import { createContext, useContext } from "react";

export const AuthContext = createContext({
  user: null,
  token: null,
  setUser: () => {},
  setToken: () => {},
  refreshUser: async () => {},
});

export const useAuth = () => useContext(AuthContext);
