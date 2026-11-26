import { COLUMN_KINDS } from '@/utils/tableColumns'

// The permissions table's optional detail columns, in display order (all start hidden).
export const PERMISSION_DETAIL_COLUMNS = Object.freeze([
  { key: 'confidential', kind: COLUMN_KINDS.STATUS },
  { key: 'grantable', kind: COLUMN_KINDS.STATUS },
  { key: 'system', kind: COLUMN_KINDS.STATUS },
  { key: 'lockedToRoles' },
])

export function permissionDetailColumns(t) {
  return PERMISSION_DETAIL_COLUMNS.map((column) => ({
    ...column,
    label: t(`permissions.columns.${column.key}`),
  }))
}
