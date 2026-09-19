"use client";

import { useEffect, useSyncExternalStore } from "react";

const storageKey = "sidebarCollapsed";
let collapsed = false;
let mounted = false;
const listeners = new Set<() => void>();

const subscribe = (listener: () => void) => {
  listeners.add(listener);
  return () => listeners.delete(listener);
};
const getSnapshot = () => collapsed;
const getServerSnapshot = () => false;

function notify() {
  listeners.forEach((listener) => listener());
}

export function initializeSidebarPreference() {
  if (mounted || typeof window === "undefined") return;
  mounted = true;
  collapsed = window.localStorage.getItem(storageKey) === "true";
  notify();
}

export function setSidebarCollapsed(next: boolean) {
  collapsed = next;
  if (typeof window !== "undefined") window.localStorage.setItem(storageKey, String(next));
  notify();
}

export function useSidebarPreference() {
  const value = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
  useEffect(() => { initializeSidebarPreference(); }, []);
  return { collapsed: value, setCollapsed: setSidebarCollapsed };
}
