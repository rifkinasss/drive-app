import { env } from '@/config/env'

export function buildPublicShareUrl(token: string): string {
  const configuredBase = env.appUrl;
  const browserBase = typeof window !== "undefined" ? window.location.origin : "";
  const base = (configuredBase || browserBase).replace(/\/$/, "");
  return `${base}/s/${encodeURIComponent(token)}`;
}
