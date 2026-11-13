import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import { useFilters } from '@/composables/useFilters'
import { levelFromQuery, useLevelQueryFilter } from '@/composables/useLevelQueryFilter'

async function harness(query) {
  const globals = createTestGlobals()
  await globals.router.push({ path: '/admin/entities', query })
  let exposed = null
  const Probe = defineComponent({
    setup() {
      const levelQuery = useLevelQueryFilter()
      const filters = useFilters({ status: '', level: levelQuery.initialLevel })
      levelQuery.bind(filters)
      exposed = filters
      return () => h('div')
    },
  })
  mount(Probe, { global: globals })
  return { filters: exposed, router: globals.router }
}

describe('useLevelQueryFilter', () => {
  it('accepts only the server filter values', () => {
    expect(levelFromQuery('gold')).toBe('gold')
    expect(levelFromQuery('none')).toBe('none')
    expect(levelFromQuery('expired')).toBe('expired')
    expect(levelFromQuery('in_progress')).toBe('')
    expect(levelFromQuery(['gold'])).toBe('')
    expect(levelFromQuery(undefined)).toBe('')
  })

  it('starts from the level in the query string and sends it as filter[level]', async () => {
    const { filters } = await harness({ level: 'gold' })
    expect(filters.filters.level).toBe('gold')
    expect(filters.toParams()).toMatchObject({ 'filter[level]': 'gold' })
  })

  it('ignores an unknown level in the query string', async () => {
    const { filters } = await harness({ level: 'platinum' })
    expect(filters.filters.level).toBe('')
    expect(filters.toParams()).not.toHaveProperty('filter[level]')
  })

  it('writes the chosen level to the query string and removes it when cleared', async () => {
    const { filters, router } = await harness({ page: '2' })
    filters.setFilter('level', 'diamond')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ page: '2', level: 'diamond' })
    filters.setFilter('level', '')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ page: '2' })
  })

  it('follows a level link opened on the same page', async () => {
    const { filters, router } = await harness({})
    await router.replace({ query: { level: 'participation' } })
    await flushPromises()
    expect(filters.filters.level).toBe('participation')
  })
})
