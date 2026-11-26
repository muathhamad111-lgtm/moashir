import { pathLabelOf } from '../../fixtures/pathLabels'
import { REVIEWED_PATHS } from '../assessments/reviewedPaths'

const QUALIFICATION_PATHS = { accredited: 'accredited', advanced_initial: 'advanced' }
const TAB_KEYS = { new: 'new', in_progress: 'inProgress', completed: 'completed' }
const DEFAULT_PATH = 'accredited'

export function assignedPathCounts(records, qualifications, tabs) {
  const qualified = qualifications.map((value) => QUALIFICATION_PATHS[value])
  const paths = REVIEWED_PATHS.filter(
    (path) => qualified.includes(path) || records.some((record) => record.path === path),
  )
  return paths.map((path) => {
    const own = records.filter((record) => record.path === path)
    return {
      path,
      pathLabel: pathLabelOf(path),
      ...Object.fromEntries(
        Object.entries(TAB_KEYS).map(([tab, key]) => [
          key,
          own.filter((record) => tabs[tab].includes(record.status)).length,
        ]),
      ),
    }
  })
}

export function assignedPathOf(requested, pathCounts) {
  if (requested) return requested
  return pathCounts[0]?.path ?? DEFAULT_PATH
}
