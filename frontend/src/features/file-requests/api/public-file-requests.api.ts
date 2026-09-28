import { api } from "@/lib/api/client";

export type PublicFileRequest = { title: string; ownerDisplayName: string };

export const publicFileRequestsApi = {
  get: (token: string) =>
    api.get<PublicFileRequest>(`/api/public/file-requests/${encodeURIComponent(token)}`, { credentials: "omit" }),
  upload: (token: string, file: File) => {
    const body = new FormData();
    body.append("file", file);
    return api.post(`/api/public/file-requests/${encodeURIComponent(token)}/upload`, body);
  },
};
