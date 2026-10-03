"use client";

import { useEffect, useSyncExternalStore } from "react";

export type ThemePreference = "light" | "dark" | "system";
const storageKey = "cloud-theme";
let currentTheme: ThemePreference = "system";
let initialized = false;
const listeners = new Set<() => void>();
const subscribe = (listener: () => void) => {
  listeners.add(listener);
  return () => listeners.delete(listener);
};
const getSnapshot = () => currentTheme;
const getServerSnapshot = () => "system" as ThemePreference;

function isTheme(value: string | null): value is ThemePreference {
  return value === "light" || value === "dark" || value === "system";
}
function resolvedTheme(theme: ThemePreference) {
  return theme === "system" && typeof window !== "undefined"
    ? window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light"
    : theme === "system"
      ? "light"
      : theme;
}
export function applyTheme(theme: ThemePreference) {
  if (typeof document === "undefined") return;

  const activeTheme = resolvedTheme(theme);
  document.documentElement.dataset.theme = activeTheme;
  document.querySelectorAll('meta[name="theme-color"]').forEach((meta) => {
    meta.removeAttribute("media");
    meta.setAttribute("content", activeTheme === "dark" ? "#0F172A" : "#F4F7FB");
  });
}
export function initializeTheme() {
  if (initialized || typeof window === "undefined") return;
  const saved = window.localStorage.getItem(storageKey);
  currentTheme = isTheme(saved) ? saved : "system";
  initialized = true;
  applyTheme(currentTheme);
  listeners.forEach((listener) => listener());
}
export function useThemePreference() {
  const theme = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
  useEffect(() => {
    initializeTheme();
  }, []);
  const setTheme = (nextTheme: ThemePreference) => {
    currentTheme = nextTheme;
    initialized = true;
    window.localStorage.setItem(storageKey, nextTheme);
    applyTheme(nextTheme);
    listeners.forEach((listener) => listener());
  };
  return { theme, setTheme };
}
