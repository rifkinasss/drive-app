import { Suspense } from 'react'
import { CloudApp } from '@/components/cloud/CloudApp'

export default function TrashPage() {
  return <Suspense fallback={null}><CloudApp /></Suspense>
}
