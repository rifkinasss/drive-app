import { RefreshCw } from "lucide-react";

export function DataErrorState({ message, onRetry }: { message: string; onRetry: () => void }) {
  return <div className="empty-state compact" role="alert"><RefreshCw size={22} aria-hidden="true" /><h2>Data belum tersedia</h2><p>{message}</p><button type="button" className="button secondary" onClick={onRetry}>Coba lagi</button></div>;
}
