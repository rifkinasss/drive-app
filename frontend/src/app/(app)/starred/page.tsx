import { Suspense } from 'react'
import { CloudApp } from '@/components/cloud/CloudApp'

export default function StarredPage() {
  return <Suspense fallback={null}><CloudApp /></Suspense>
}
