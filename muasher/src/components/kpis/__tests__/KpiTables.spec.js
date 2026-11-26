import { afterEach, describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import KpiAdminTable from '@/components/kpis/KpiAdminTable.vue'
import KpiCatalogPanel from '@/components/kpis/KpiCatalogPanel.vue'
import { KPI_ADMIN_ACTIONS_ID } from '@/constants/kpis'
import { useAuthStore } from '@/stores/auth'
import { useTableColumnsStore } from '@/stores/tableColumns'
import { sessionUserFor } from '@/test-utils/session'
import { createTestGlobals } from '@/test-utils'

const localized = (content) => (content ? content.en || content.ar : '')

function mountWith(component, props) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore().setUser(sessionUserFor(['content_officer']))
  return mount(component, { props, global: globals, attachTo: document.body })
}

async function choose(tableKey, keys) {
  await flushPromises()
  useTableColumnsStore().choices = { [tableKey]: keys }
  await flushPromises()
}

const KPI = {
  id: 1,
  slug: 'participating-associations',
  name: { ar: 'الجمعيات المشاركة', en: 'Participating associations' },
  value: '486',
  target: '700',
  baseline: '320',
  kpiCategoryId: 3,
  kpiTypeId: 2,
  status: 'good',
  published: true,
  showOnHomepage: false,
  order: 1,
  updatedBy: { id: 31, name: 'ريم بنت سعد القحطاني' },
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('KpiAdminTable', () => {
  it('shows the indicator type and the last editor once the viewer chooses them', async () => {
    const wrapper = mountWith(KpiAdminTable, {
      items: [KPI],
      status: 'success',
      localized,
      types: [{ id: 2, name: { ar: 'مؤشر نتائج', en: 'Outcome indicator' } }],
    })
    await choose('kpis-admin', ['type', 'updatedBy', 'slug'])
    const cells = wrapper.findAll('tbody td')
    expect(cells[1].text()).toBe('Outcome indicator')
    expect(cells[2].text()).toBe('ريم بنت سعد القحطاني')
    expect(cells[3].text()).toBe('participating-associations')
    wrapper.unmount()
  })
})

describe('KpiCatalogPanel', () => {
  it('shows how many indicators use each category once the viewer chooses the count', async () => {
    const target = document.createElement('div')
    target.id = KPI_ADMIN_ACTIONS_ID
    document.body.appendChild(target)
    const catalog = {
      entries: ref([
        {
          id: 3,
          key: 'beneficiaries',
          name: { ar: 'المستفيدون', en: 'Beneficiaries' },
          order: 3,
          kpisCount: 4,
        },
      ]),
      status: ref('success'),
      load: () => {},
      save: () => {},
      remove: () => {},
    }
    const wrapper = mountWith(KpiCatalogPanel, { kind: 'categories', catalog, localized })
    await choose('kpis-catalog', ['kpisCount'])
    expect(wrapper.findAll('tbody td')[1].text()).toBe('4')
    wrapper.unmount()
  })
})
