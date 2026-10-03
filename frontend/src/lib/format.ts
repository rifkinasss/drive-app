export function formatBytes(bytes: number): string {
  const numericBytes = Number(bytes);
  if (!Number.isFinite(numericBytes) || numericBytes <= 0) return "—";
  const units = ["B", "KB", "MB", "GB", "TB"];
  const index = Math.min(
    Math.floor(Math.log(numericBytes) / Math.log(1024)),
    units.length - 1,
  );
  return `${(numericBytes / 1024 ** index).toFixed(index > 1 ? 1 : 0)} ${units[index]}`;
}
export function percentageOf(value: number, total: number): number {
  const numericValue = Number(value);
  const numericTotal = Number(total);
  if (!Number.isFinite(numericValue) || !Number.isFinite(numericTotal) || numericTotal <= 0) return 0;
  return Math.min(100, Math.max(0, numericValue / numericTotal * 100));
}
export function formatDate(value: string): string {
  return new Intl.DateTimeFormat("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  }).format(new Date(value));
}
export function formatRelative(value: string): string {
  const days = Math.round((Date.now() - new Date(value).getTime()) / 86400000);
  return days <= 0 ? "Hari ini" : days === 1 ? "Kemarin" : `${days} hari lalu`;
}
