"use client";

import { internalSharesApi as shareService } from "@/features/sharing/api/internal-shares.api";

export function useShareStore() { return shareService; }
