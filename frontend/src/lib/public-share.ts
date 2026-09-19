export function buildPublicShareUrl(token: string): string {
  const configuredBase = process.env.NEXT_PUBLIC_APP_URL;
  const browserBase = typeof window !== "undefined" ? window.location.origin : "";
  const base = (configuredBase || browserBase).replace(/\/$/, "");
  return `${base}/s/${encodeURIComponent(token)}`;
}
