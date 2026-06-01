const { getDefaultConfig } = require("expo/metro-config");
const path = require("path");

/** @type {import('expo/metro-config').MetroConfig} */
const config = getDefaultConfig(__dirname);

// Only ignore AlumniApp/dist (expo export output), NOT node_modules/*/dist
const exportDist = path.resolve(__dirname, "dist").replace(/\\/g, "/");
const escaped = exportDist.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

const existing = Array.isArray(config.resolver.blockList)
  ? config.resolver.blockList
  : config.resolver.blockList
    ? [config.resolver.blockList]
    : [];

config.resolver.blockList = [...existing, new RegExp(`^${escaped}/.*`)];

module.exports = config;
