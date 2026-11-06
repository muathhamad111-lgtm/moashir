import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import LegalVersionsPanel from '@/components/legal/admin/LegalVersionsPanel.vue'
import ConfirmModal from '@/components/modal/ConfirmModal.vue'
import { legalVersionsService } from '@/services/legalVersionsService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/legalVersionsService', () => ({
  legalVersionsService: {
    list: vi.fn(),
    get: vi.fn(),
    createDraft: vi.fn(),
    updateDraft: vi.fn(),
    discardDraft: vi.fn(),
    publish: vi.fn(),
  },
}))

const TITLE = { ar: 'سياسة الخصوصية — منصة مؤشر', en: 'Privacy Policy — Moashir' }
const BODY = { ar: '## 1. مقدمة\n\n- زوار المنصة؛', en: '## 1. Introduction\n\n- visitors;' }
const CURRENT = {
  id: 14,
  kind: 'privacy',
  version: '2026-11-draft',
  status: 'current',
  title: TITLE,
  body: BODY,
  publishedAt: '2026-11-04T11:20:05+00:00',
}
const DRAFT = {
  id: 16,
  kind: 'privacy',
  version: '2026-12',
  status: 'draft',
  title: TITLE,
  body: BODY,
}

async function mountPanel({ superAdmin = false, versions = [CURRENT] } = {}) {
  legalVersionsService.list.mockResolvedValue({ data: versions.map((v) => ({ ...v, body: null })) })
  const globals = createTestGlobals({ locale: 'en' })
  useAuthStore(globals.pinia).setUser(
    sessionUserFor(['content_officer'], { isSuperAdmin: superAdmin }),
  )
  const wrapper = mount(
    {
      components: { LegalVersionsPanel, ConfirmModal },
      template: '<LegalVersionsPanel kind="privacy" /><ConfirmModal />',
    },
    { global: globals, attachTo: document.body },
  )
  await flushPromises()
  return wrapper
}

function button(wrapper, label) {
  return wrapper.findAll('button').find((item) => item.text() === label)
}

async function acceptConfirm(label) {
  ;[...document.querySelectorAll('.modal button')]
    .find((item) => item.textContent.includes(label))
    .click()
  await flushPromises()
}

beforeEach(() => {
  vi.clearAllMocks()
  legalVersionsService.get.mockImplementation((id) =>
    Promise.resolve({ data: id === 14 ? CURRENT : DRAFT }),
  )
})

describe('legal versions management (Content Officer)', () => {
  it('lists the versions and opens a published one read-only with the rendered blocks', async () => {
    const wrapper = await mountPanel()
    expect(wrapper.find('tbody').text()).toContain('2026-11-draft')
    expect(wrapper.find('tbody').text()).toContain('Current version')
    await button(wrapper, 'View').trigger('click')
    await flushPromises()
    expect(wrapper.find('.legal-viewer').text()).toContain('read-only')
    expect(wrapper.find('.legal-viewer textarea').exists()).toBe(false)
    expect(wrapper.findAll('.legal-preview h4').map((h) => h.text())).toEqual([
      '1. مقدمة',
      '1. Introduction',
    ])
    wrapper.unmount()
  })

  it('starts a draft from the current text and creates it', async () => {
    legalVersionsService.createDraft.mockResolvedValue({ data: DRAFT })
    const wrapper = await mountPanel()
    await button(wrapper, 'New draft from the current text').trigger('click')
    await flushPromises()
    expect(wrapper.find('textarea[name="body.ar"]').element.value).toBe(BODY.ar)
    await wrapper.find('input[name="version"]').setValue('2026-12')
    await wrapper.find('form.legal-editor').trigger('submit')
    await vi.waitFor(() => expect(legalVersionsService.createDraft).toHaveBeenCalled())
    expect(legalVersionsService.createDraft).toHaveBeenCalledWith('privacy', {
      version: '2026-12',
      title: TITLE,
      body: BODY,
    })
    wrapper.unmount()
  })

  it('updates only the changed fields and previews the text live', async () => {
    legalVersionsService.updateDraft.mockResolvedValue({
      data: { ...DRAFT, body: { ...BODY, en: '## 2. Scope' } },
    })
    const wrapper = await mountPanel({ versions: [DRAFT, CURRENT] })
    await button(wrapper, 'Continue the draft').trigger('click')
    await flushPromises()
    await wrapper.find('textarea[name="body.en"]').setValue('## 2. Scope')
    await flushPromises()
    expect(wrapper.findAll('.legal-preview h4').map((h) => h.text())).toContain('2. Scope')
    await wrapper.find('form.legal-editor').trigger('submit')
    await vi.waitFor(() => expect(legalVersionsService.updateDraft).toHaveBeenCalled())
    expect(legalVersionsService.updateDraft).toHaveBeenCalledWith(16, {
      body: { ar: BODY.ar, en: '## 2. Scope' },
    })
    wrapper.unmount()
  })

  it('shows the server refusal of HTML markup on the field', async () => {
    legalVersionsService.updateDraft.mockRejectedValue({
      normalized: {
        status: 422,
        message: 'Check the data',
        fieldErrors: { 'body.en': ['HTML markup is not accepted.'] },
      },
    })
    const wrapper = await mountPanel({ versions: [DRAFT] })
    await button(wrapper, 'Continue the draft').trigger('click')
    await flushPromises()
    await wrapper.find('textarea[name="body.en"]').setValue('<p>Text</p>')
    await wrapper.find('form.legal-editor').trigger('submit')
    await vi.waitFor(() => expect(wrapper.text()).toContain('HTML markup is not accepted.'))
    wrapper.unmount()
  })

  it('publishes after confirmation with the Super Admin reason', async () => {
    legalVersionsService.publish.mockResolvedValue({ data: { ...DRAFT, status: 'current' } })
    const wrapper = await mountPanel({ superAdmin: true, versions: [DRAFT, CURRENT] })
    await button(wrapper, 'Continue the draft').trigger('click')
    await flushPromises()
    await button(wrapper, 'Publish the version').trigger('click')
    await flushPromises()
    await acceptConfirm('Publish the version')
    const reason = wrapper.findAllComponents({ name: 'ReasonDialog' }).at(-1)
    await reason.props('action')('نشر النسخة التي اعتمدها الفريق القانوني')
    await flushPromises()
    expect(legalVersionsService.publish).toHaveBeenCalledWith(16, {
      reason: 'نشر النسخة التي اعتمدها الفريق القانوني',
    })
    expect(wrapper.find('.legal-viewer').exists()).toBe(true)
    wrapper.unmount()
  })

  it('goes back to the list after a not-draft conflict', async () => {
    legalVersionsService.updateDraft.mockRejectedValue({
      normalized: {
        status: 409,
        code: 'legal.version_not_draft',
        message: 'Published',
        fieldErrors: {},
      },
    })
    const wrapper = await mountPanel({ versions: [DRAFT] })
    await button(wrapper, 'Continue the draft').trigger('click')
    await flushPromises()
    await wrapper.find('input[name="version"]').setValue('2026-12b')
    await wrapper.find('form.legal-editor').trigger('submit')
    await vi.waitFor(() => expect(legalVersionsService.list).toHaveBeenCalledTimes(2))
    expect(wrapper.find('.legal-editor').exists()).toBe(false)
    wrapper.unmount()
  })
})
