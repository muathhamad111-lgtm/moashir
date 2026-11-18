import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import PermissionModuleCard from '@/components/account/PermissionModuleCard.vue'

function countFor(size, locale = 'ar') {
  const items = Array.from({ length: size }, (_, index) => ({
    key: `p${index}`,
    label: `P${index}`,
  }))
  return mount(PermissionModuleCard, {
    props: { module: 'dashboard', items, localized: (label) => label },
    global: createTestGlobals({ locale }),
  })
    .find('.permission-module__count')
    .text()
}

describe('PermissionModuleCard', () => {
  it('counts the permissions with the Arabic plural forms', () => {
    expect(countFor(1)).toBe('صلاحية واحدة')
    expect(countFor(2)).toBe('صلاحيتان')
    expect(countFor(3)).toBe('3 صلاحيات')
    expect(countFor(11)).toBe('11 صلاحية')
  })

  it('counts them in English too', () => {
    expect(countFor(1, 'en')).toBe('1 permission')
    expect(countFor(3, 'en')).toBe('3 permissions')
  })
})
