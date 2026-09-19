import type { StorageSummary } from "@/types/cloud";
export const mockStorage: StorageSummary = {
  total: 100000000000,
  used: 37400000000,
  categories: {
    Documents: 7400000000,
    Images: 8900000000,
    Videos: 14300000000,
    Archives: 5800000000,
    Others: 1000000000,
  },
};
