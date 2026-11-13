import { describe, expect, it } from 'vitest'
import { toQueueRow, toRequestDetail } from '@/services/mappers/requests'

const LAST_LEVEL = {
  level: 'silver',
  validUntil: '2026-11-01',
  validity: 'expired',
  daysUntilExpiry: null,
}

const ENTITY = {
  id: 6002,
  reference: 'ENT-006002',
  name: { ar: 'جمعية إحسان' },
  status: 'active',
  level: null,
  lastLevel: LAST_LEVEL,
}

describe('request mappers — entity level', () => {
  it('keeps the counted level and the last level of the entity on a queue row', () => {
    expect(toQueueRow({ id: 9101, entity: ENTITY }).entity).toMatchObject({
      level: null,
      lastLevel: LAST_LEVEL,
    })
  })

  it('carries the last level into the request detail and defaults it to null', () => {
    expect(toRequestDetail({ id: 9101, entity: ENTITY }).entity.lastLevel).toEqual(LAST_LEVEL)
    expect(
      toQueueRow({ id: 9102, entity: { ...ENTITY, lastLevel: undefined } }).entity.lastLevel,
    ).toBeNull()
  })
})
