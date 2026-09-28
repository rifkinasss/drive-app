import { env } from "@/config/env";
import { ApiError } from "@/lib/api/api-error";

export { ApiError } from "@/lib/api/api-error";

export const AUTH_SESSION_INVALIDATED_EVENT = "drive:auth-session-invalidated";

const apiUrl = env.apiUrl
  .replace(/\/+$/, "")
  .replace(/\/api$/i, "");
let csrfReady = false;
let csrfRequest: Promise<void> | null = null;
let networkRequestId = 0;

function nextNetworkRequestId(): number {
  networkRequestId += 1;
  return networkRequestId;
}

function notifyNetwork(eventName: "drive:network-failure" | "drive:network-recovered", requestId: number): void {
  if (typeof window !== "undefined") window.dispatchEvent(new CustomEvent(eventName, { detail: { requestId } }));
}

function networkError(requestId: number): ApiError {
  notifyNetwork("drive:network-failure", requestId);
  return new ApiError({
    status: 0,
    code: "NETWORK_UNAVAILABLE",
    message:
      "No internet connection. Drive by NasLabs requires an internet connection to access your files.",
  });
}

function networkResponse(response: Response, requestId: number): Response {
  notifyNetwork("drive:network-recovered", requestId);
  return response;
}

function shouldInvalidateSession(path: string): boolean {
  const normalizedPath = path.split("?", 1)[0];
  if (!normalizedPath.startsWith("/api/")) return false;
  return ![
    "/api/public/",
    "/api/invitations/",
    "/api/email-verification/",
    "/api/auth/login",
    "/api/auth/forgot-password",
    "/api/auth/reset-password",
  ].some((prefix) => normalizedPath.startsWith(prefix));
}

function notifySessionInvalidated(path: string, status: number): void {
  if (status !== 401 || !shouldInvalidateSession(path) || typeof window === "undefined") return;
  window.dispatchEvent(new Event(AUTH_SESSION_INVALIDATED_EVENT));
}

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
    const requestId = nextNetworkRequestId();
    let response: Response;
    try {
      response = networkResponse(await fetch(`${apiUrl}/sanctum/csrf-cookie`, {
        credentials: "include",
        headers: { Accept: "application/json" },
      }), requestId);
    } catch {
      throw networkError(requestId);
    }
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
  const messages: Record<number, string> = {
    401: "Your session has expired. Please sign in again.",
    403: "You do not have permission to perform this action.",
    404: "The requested item could not be found.",
    419: "Your secure session expired. Refresh and try again.",
    500: "Drive could not complete the request.",
    503: "Drive is temporarily unavailable. Please try again shortly.",
  };
  return new ApiError({
    status: response.status,
    code: body.code,
    message:
      messages[response.status] ??
      body.message ??
      response.statusText ??
      "Request failed.",
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
  let response: Response;
  const requestId = nextNetworkRequestId();
  try {
    response = networkResponse(await fetch(
      `${apiUrl}${path.startsWith("/") ? path : `/${path}`}`,
      { ...init, headers, credentials: "include" },
    ), requestId);
  } catch {
    throw networkError(requestId);
  }
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
  notifySessionInvalidated(path, error.status);
  const isLoginRequest = path.replace(/\/+$/, "") === "/api/auth/login";
  if (retryCsrf && error.status === 419 && isLoginRequest) {
    csrfReady = false;
    await csrfCookie();
    return apiFetch<T>(path, init, false);
  }
  throw error;
}

export async function apiBlob(
  path: string,
  isPublic = false,
  extraHeaders?: HeadersInit,
): Promise<Blob> {
  let response: Response;
  const requestId = nextNetworkRequestId();
  try {
    response = networkResponse(await fetch(
      `${apiUrl}${path.startsWith("/") ? path : `/${path}`}`,
      {
        credentials: isPublic ? "omit" : "include",
        headers: {
          Accept:
            "application/octet-stream, application/pdf, image/*, text/*, video/*, audio/*",
          ...extraHeaders,
        },
      },
    ), requestId);
  } catch {
    throw networkError(requestId);
  }
  if (!response.ok) {
    const error = await parseError(response);
    if (!isPublic) notifySessionInvalidated(path, error.status);
    throw error;
  }
  return response.blob();
}

export async function apiDownload(
  path: string,
  filename: string,
  isPublic = false,
  extraHeaders?: HeadersInit,
): Promise<void> {
  const blob = await apiBlob(path, isPublic, extraHeaders);
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
