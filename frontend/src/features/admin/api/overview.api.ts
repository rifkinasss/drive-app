import { api } from '@/lib/api/client'
import type { AdminOverview } from '@/features/admin/types/overview.types'

export const adminOverviewApi = {
  get: () => api.get<AdminOverview>('/api/admin/overview'),
}
