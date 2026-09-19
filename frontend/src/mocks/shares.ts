import type { InternalShare, PublicShareLink } from "@/types/cloud";

export const mockInternalShares: InternalShare[] = [
  {
    id: "share-portfolio-sinta",
    itemId: "portfolio",
    ownerId: "user-rifki",
    recipientUserId: "user-sinta",
    permission: "viewer",
    createdAt: "2026-09-15T10:00:00.000Z",
  },
];

export const mockPublicLinks: PublicShareLink[] = [
  {
    id: "public-portfolio",
    itemId: "portfolio",
    itemType: "file",
    ownerId: "user-rifki",
    token: "f7Kx9Qa2",
    enabled: true,
    permission: "viewer",
    createdAt: "2026-09-15T10:00:00.000Z",
  },
];
