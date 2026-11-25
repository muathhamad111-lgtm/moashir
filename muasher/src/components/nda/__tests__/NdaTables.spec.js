import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import NdaAcceptanceList from '@/components/nda/NdaAcceptanceList.vue'
import NdaRegisterTable from '@/components/nda/admin/NdaRegisterTable.vue'
import NdaVersionsTable from '@/components/nda/admin/NdaVersionsTable.vue'
import { useAuthStore } from '@/stores/auth'
import { useTableColumnsStore } from '@/stores/tableColumns'
import { sessionUserFor } from '@/test-utils/session'
import { createTestGlobals } from '@/test-utils'

async function mountChoosing(component, props, tableKey, keys) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore().setUser(sessionUserFor(['system_admin']))
  const wrapper = mount(component, { props, global: globals })
  await flushPromises()
  useTableColumnsStore().choices = { [tableKey]: keys }
  await flushPromises()
  return wrapper
}

const cellsOf = (wrapper) => wrapper.findAll('tbody td').map((cell) => cell.text())

describe('agreement tables: optional columns', () => {
  it('shows the title of each accepted version in the history', async () => {
    const wrapper = await mountChoosing(
      NdaAcceptanceList,
      {
        status: 'success',
        acceptances: [
          {
            id: 'a-1',
            version: '1.1',
            title: { ar: 'اتفاقية عدم الإفصاح', en: 'Non-disclosure agreement' },
            effectiveFrom: '2026-11-01',
            acceptedAt: '2026-11-02T09:00:00Z',
            acceptedRoles: ['assessor'],
            ipMasked: '10.0.12.•',
          },
        ],
      },
      'nda-history',
      ['title'],
    )
    expect(cellsOf(wrapper)[1]).toBe('Non-disclosure agreement')
  })

  it('shows the last accepted version in the register, without any e-mail', async () => {
    const wrapper = await mountChoosing(
      NdaRegisterTable,
      {
        status: 'success',
        currentVersion: '1.2',
        rows: [
          {
            user: { id: 7, name: 'هند الزهراني', roles: ['assessor'] },
            status: 'pending',
            lastAcceptedVersion: '1.1',
            acceptedAt: null,
            acceptedRoles: [],
            ipMasked: null,
          },
        ],
      },
      'nda-register',
      ['lastAcceptedVersion'],
    )
    expect(cellsOf(wrapper)).toEqual(['هند الزهراني', '1.1'])
  })

  it('shows the change summary of a version once the viewer chooses it', async () => {
    const wrapper = await mountChoosing(
      NdaVersionsTable,
      {
        status: 'success',
        versions: [
          {
            id: 3,
            version: '1.1',
            status: 'current',
            changeSummary: { ar: 'بند جديد', en: 'A new clause' },
            acceptancesCount: 2,
            updatedAt: '2026-11-01T09:00:00Z',
            createdAt: '2026-10-30T09:00:00Z',
          },
        ],
      },
      'nda-versions',
      ['changeSummary'],
    )
    expect(cellsOf(wrapper)[1]).toBe('A new clause')
  })
})
