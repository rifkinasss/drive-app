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
