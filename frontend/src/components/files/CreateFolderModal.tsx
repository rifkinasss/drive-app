"use client";

import { useState } from "react";
import { Modal } from "@/components/ui/Modal";

interface CreateFolderModalProps {
  onClose: () => void;
  onCreate: (name: string) => Promise<boolean>;
  errorFromStore?: string;
}

export function CreateFolderModal({
  onClose,
  onCreate,
  errorFromStore,
}: CreateFolderModalProps) {
  const [folderName, setFolderName] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    const trimmed = folderName.trim();
    if (!trimmed) {
      setError("Nama folder tidak boleh kosong.");
      return;
    }
    if (submitting) return;
    setSubmitting(true);
    setError(null);
    try {
      const success = await onCreate(trimmed);
      if (success) {
        onClose();
      } else {
        setError(errorFromStore || "Gagal membuat folder. Silakan coba lagi.");
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal membuat folder.");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Modal title="New folder" onClose={onClose}>
      <form onSubmit={handleSubmit}>
        {error && (
          <p className="form-hint error" role="alert" style={{ marginBottom: "12px" }}>
            {error}
          </p>
        )}
        <label className="field-label" htmlFor="create-folder-name-input">
          Folder name
        </label>
        <input
          id="create-folder-name-input"
          autoFocus
          className="text-input"
          value={folderName}
          onChange={(event) => {
            setFolderName(event.target.value);
            if (error) setError(null);
          }}
          placeholder="e.g. Reference material"
          disabled={submitting}
        />
        <div className="modal-actions">
          <button type="button" className="button secondary" onClick={onClose} disabled={submitting}>
            Cancel
          </button>
          <button className="button primary" type="submit" disabled={submitting || !folderName.trim()}>
            {submitting ? "Creating…" : "Create folder"}
          </button>
        </div>
      </form>
    </Modal>
  );
}
