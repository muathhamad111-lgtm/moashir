import {
  ALERT_SIMILARITY_LIMIT,
  ALERT_STATUS_COLORS,
  HOVER_SIMILARITY_LIMIT,
} from '@/constants/branding'
import { perceptualDistance } from './brandColorMath'

// Advice that never blocks a save: a primary that reads as an alert, or a hover that cannot be
// told from the fill.
export function brandWarnings(scheme) {
  if (!scheme) return []
  const warnings = []
  const alert = Object.entries(ALERT_STATUS_COLORS).find(
    ([, hex]) => perceptualDistance(scheme.primary, hex) < ALERT_SIMILARITY_LIMIT,
  )
  if (alert) warnings.push({ key: 'nearAlert', status: alert[0] })
  if (perceptualDistance(scheme.primary, scheme.primaryHover) < HOVER_SIMILARITY_LIMIT) {
    warnings.push({ key: 'hoverIndistinct' })
  }
  return warnings
}
