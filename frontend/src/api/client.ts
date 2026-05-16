import type { ApiErrorBody, ApiSuccess } from './types';

const TOKEN_KEY = 'bizspine_access_token';

export class ApiRequestError extends Error {
  readonly status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = 'ApiRequestError';
    this.status = status;
  }
}

/** Base URL for API requests. In dev, defaults to Vite proxy `/backend`. */
export function getApiBaseUrl(): string {
  const fromEnv = import.meta.env.VITE_API_BASE_URL?.trim();
  if (fromEnv) {
    return fromEnv.replace(/\/$/, '');
  }
  if (import.meta.env.DEV) {
    return '/backend';
  }
  return '';
}

export function getStoredToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setStoredToken(token: string | null): void {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token);
  } else {
    localStorage.removeItem(TOKEN_KEY);
  }
}

export async function apiRequest<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const base = getApiBaseUrl();
  const url = `${base}${path.startsWith('/') ? path : `/${path}`}`;

  const headers = new Headers(options.headers);
  if (!headers.has('Content-Type') && options.body) {
    headers.set('Content-Type', 'application/json');
  }

  const token = getStoredToken();
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  const response = await fetch(url, { ...options, headers });
  let payload: ApiSuccess<T> | ApiErrorBody;

  try {
    payload = (await response.json()) as ApiSuccess<T> | ApiErrorBody;
  } catch {
    throw new ApiRequestError(
      response.ok ? 'Invalid JSON response' : `Request failed (${response.status})`,
      response.status,
    );
  }

  if (!payload.success) {
    throw new ApiRequestError(payload.error, response.status);
  }

  return payload.data;
}
