import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import LegalVersionList from '@/components/legal/admin/LegalVersionList.vue'
import { useAuthStore } from '@/stores/auth'
import { useTableColumnsStore } from '@/stores/tableColumns'
import { sessionUserFor } from '@/test-utils/session'
import { createTestGlobals } from '@/test-utils'

describe('LegalVersionList', () => {
  it('names the publisher of a version once the viewer chooses that column', async () => {
    const globals = createTestGlobals({ locale: 'en' })
    setActivePinia(globals.pinia)
    useAuthStore().setUser(sessionUserFor(['content_officer']))
    const wrapper = mount(LegalVersionList, {
      props: {
        status: 'success',
        versions: [
          {
            id: 14,
            version: '2026-11',
            status: 'current',
            title: { ar: 'سياسة الخصوصية', en: 'Privacy Policy' },
            publishedAt: '2026-11-04T11:20:05Z',
            publishedBy: 'منيرة بنت خالد العتيبي',
            createdAt: '2026-11-01T09:00:00Z',
            updatedAt: '2026-11-04T11:20:05Z',
          },
        ],
      },
      global: globals,
    })
    await flushPromises()
    useTableColumnsStore().choices = { 'legal-versions': ['publishedBy'] }
    await flushPromises()
    expect(wrapper.findAll('tbody td')[1].text()).toBe('منيرة بنت خالد العتيبي')
  })
})
