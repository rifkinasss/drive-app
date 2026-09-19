import { SystemState } from '@/components/system/SystemState'

export default function NotFound() {
  return <SystemState icon="file-question" code="404" title="Page not found" description="The page you're looking for doesn't exist or may have moved." primaryAction={{ label: 'Go home', href: '/home' }} secondaryAction={{ label: 'Go to My Files', href: '/files' }} />
}
