import { Suspense } from 'react'
import { CloudApp } from '@/components/cloud/CloudApp'

export default function SharedPage() {
  return <Suspense fallback={null}><CloudApp /></Suspense>
}
