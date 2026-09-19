export interface ApiErrorShape {
  status: number;
  code?: string;
  message: string;
  errors?: Record<string, string[]>;
  data?: unknown;
}

export class ApiError extends Error {
  readonly status: number;
  readonly code?: string;
  readonly errors?: Record<string, string[]>;
  readonly data?: unknown;

  constructor(error: ApiErrorShape) {
    super(error.message);
    this.name = "ApiError";
    this.status = error.status;
    this.code = error.code;
    this.errors = error.errors;
    this.data = error.data;
  }
}

const apiUrl = (
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"
).replace(/\/+$/, "").replace(/\/api$/i, "");
let csrfReady = false;
let csrfRequest: Promise<void> | null = null;

function readCookie(name: string): string | undefined {
  if (typeof document === "undefined") return undefined;
  const value = document.cookie
    .split("; ")
    .find((cookie) => cookie.startsWith(`${name}=`));
  return value ? decodeURIComponent(value.slice(name.length + 1)) : undefined;
}

export async function csrfCookie(): Promise<void> {
  if (csrfRequest) return csrfRequest;
  csrfRequest = (async () => {
    const response = await fetch(`${apiUrl}/sanctum/csrf-cookie`, {
      credentials: "include",
      headers: { Accept: "application/json" },
    });
    if (!response.ok)
      throw new ApiError({
        status: response.status,
        message: "Unable to initialize a secure session.",
      });
    csrfReady = true;
  })();
  try {
    await csrfRequest;
  } finally {
    csrfRequest = null;
  }
}

async function parseError(response: Response): Promise<ApiError> {
  let body: {
    message?: string;
    code?: string;
    errors?: Record<string, string[]>;
    data?: unknown;
  } = {};
  try {
    body = await response.json();
  } catch {
    /* non-JSON server response */
  }
  return new ApiError({
    status: response.status,
    code: body.code,
    message: body.message ?? response.statusText ?? "Request failed.",
    errors: body.errors,
    data: body.data,
  });
}

export async function apiFetch<T>(
  path: string,
  init: RequestInit = {},
  retryCsrf = true,
): Promise<T> {
  const method = (init.method ?? "GET").toUpperCase();
  if (!csrfReady && !["GET", "HEAD", "OPTIONS"].includes(method)) {
    await csrfCookie();
  }
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  if (init.body && !(init.body instanceof FormData))
    headers.set("Content-Type", "application/json");
  const token = readCookie("XSRF-TOKEN");
  if (token) headers.set("X-XSRF-TOKEN", token);
  const response = await fetch(
    `${apiUrl}${path.startsWith("/") ? path : `/${path}`}`,
    { ...init, headers, credentials: "include" },
  );
  if (response.ok) {
    if (response.status === 204) return undefined as T;
    const payload = (await response.json()) as { data?: T } & T;
    return (
      Object.prototype.hasOwnProperty.call(payload, "data")
        ? payload.data
        : payload
    ) as T;
  }
  const error = await parseError(response);
  const isLoginRequest = path.replace(/\/+$/, "") === "/api/auth/login";
  if (retryCsrf && error.status === 419 && isLoginRequest) {
    csrfReady = false;
    await csrfCookie();
    return apiFetch<T>(path, init, false);
  }
  throw error;
}

export async function apiBlob(path: string, isPublic = false): Promise<Blob> {
  const response = await fetch(`${apiUrl}${path.startsWith("/") ? path : `/${path}`}`, {
    credentials: isPublic ? "omit" : "include",
    headers: { Accept: "application/octet-stream, application/pdf, image/*, text/*, video/*, audio/*" },
  });
  if (!response.ok) throw await parseError(response);
  return response.blob();
}

export async function apiDownload(path: string, filename: string, isPublic = false): Promise<void> {
  const blob = await apiBlob(path, isPublic);
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}

export const api = {
  get: <T>(path: string, init?: RequestInit) =>
    apiFetch<T>(path, { ...init, method: "GET" }),
  post: <T>(path: string, body?: unknown, init?: RequestInit) =>
    apiFetch<T>(path, {
      ...init,
      method: "POST",
      body:
        body instanceof FormData
          ? body
          : body === undefined
            ? undefined
            : JSON.stringify(body),
    }),
  patch: <T>(path: string, body?: unknown, init?: RequestInit) =>
    apiFetch<T>(path, {
      ...init,
      method: "PATCH",
      body: body === undefined ? undefined : JSON.stringify(body),
    }),
  delete: <T>(path: string, init?: RequestInit) =>
    apiFetch<T>(path, { ...init, method: "DELETE" }),
};

export function apiBaseUrl(): string {
  return apiUrl;
}
