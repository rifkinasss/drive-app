import { api } from "@/lib/api/client";
import { env } from "@/config/env";

function keyBytes(value: string): Uint8Array {
  const padding = "=".repeat((4 - (value.length % 4)) % 4);
  const raw = atob((value + padding).replace(/-/g, "+").replace(/_/g, "/"));
  return Uint8Array.from(raw, (character) => character.charCodeAt(0));
}

export type PushState = "unsupported" | "default" | "granted" | "denied";

export const pushService = {
  supported(): boolean {
    return (
      typeof window !== "undefined" &&
      "serviceWorker" in navigator &&
      "PushManager" in window &&
      "Notification" in window
    );
  },
  permission(): PushState {
    if (!this.supported()) return "unsupported";
    return Notification.permission;
  },
  async state(): Promise<PushState> {
    const permission = this.permission();
    if (permission !== "granted") return permission;

    try {
      const registration = await navigator.serviceWorker.getRegistration("/");
      if (!registration) return "default";
      const subscription = await registration.pushManager.getSubscription();
      return subscription ? "granted" : "default";
    } catch {
      return "default";
    }
  },
  async subscribe(): Promise<void> {
    if (!this.supported())
      throw new Error("Push notifications are not supported by this browser.");
    const publicKey = env.vapidPublicKey;
    if (!publicKey)
      throw new Error(
        "Push notifications are not configured for this environment.",
      );
    const registration = await navigator.serviceWorker.ready;
    if (Notification.permission === "denied")
      throw new Error("Push notifications are blocked in this browser.");
    const permission =
      Notification.permission === "granted"
        ? "granted"
        : await Notification.requestPermission();
    if (permission !== "granted")
      throw new Error(
        permission === "denied"
          ? "Push notifications are blocked in this browser."
          : "Push notification permission was not granted.",
      );
    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: keyBytes(publicKey) as unknown as BufferSource,
    });
    await api.post("/api/push-subscriptions", subscription.toJSON());
  },
  async unsubscribe(): Promise<void> {
    if (!this.supported()) return;
    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();
    if (!subscription) return;
    await api.delete("/api/push-subscriptions/current", {
      body: JSON.stringify({ endpoint: subscription.endpoint }),
      headers: { "Content-Type": "application/json" },
    });
    await subscription.unsubscribe();
  },
};
