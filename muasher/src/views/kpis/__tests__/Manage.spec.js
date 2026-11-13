import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Manage from '@/views/kpis/Manage.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import FormModal from '@/components/modal/FormModal.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import KpiFormDialog from '@/components/kpis/KpiFormDialog.vue'
import KpiReorderDialog from '@/components/kpis/KpiReorderDialog.vue'
import SortableList from '@/components/ui/SortableList.vue'
import { kpisAdminService } from '@/services/kpisAdminService'
import { kpiCatalogService } from '@/services/kpiCatalogService'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/kpisAdminService', () => ({
  kpisAdminService: {
    list: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    archive: vi.fn(),
    setPublished: vi.fn(),
    move: vi.fn(),
    reorder: vi.fn(),
    listValues: vi.fn(),
    recordValue: vi.fn(),
    uploadImage: vi.fn(),
  },
}))
vi.mock('@/services/kpiCatalogService', () => ({
  kpiCatalogService: {
    categories: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
    types: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
  },
}))

function kpi(id, overrides = {}) {
  return {
    id,
    slug: `kpi-${id}`,
    name: { ar: `مؤشر ${id}`, en: `Indicator ${id}` },
    value: '10',
    target: '20',
    unit: { ar: 'جهة', en: 'entities' },
    kpiCategoryId: 1,
    kpiTypeId: null,
    status: 'good',
    icon: 'chart-line',
    image: null,
    cardColorToken: 'primary',
    badgeColorToken: 'info',
    showOnHomepage: false,
    published: true,
    order: id,
    ...overrides,
  }
}

const ROWS = [kpi(1), kpi(2, { published: false }), kpi(3, { showOnHomepage: true })]
const LIST = {
  data: ROWS,
  meta: {
    pagination: { page: 1, perPage: 15, total: 3, lastPage: 1 },
    stats: { total: 3, published: 2, unpublished: 1, featured: 1 },
  },
}

async function mountView() {
  const wrapper = mount(Manage, {
    global: createTestGlobals({ locale: 'en' }),
    attachTo: document.body,
  })
  await flushPromises()
  return wrapper
}

function rowActions(wrapper, index) {
  return wrapper.findAllComponents(AppMenu)[index]
}

beforeEach(() => {
  vi.clearAllMocks()
  kpisAdminService.list.mockResolvedValue(LIST)
  kpiCatalogService.categories.list.mockResolvedValue({
    data: [
      {
        id: 1,
        key: 'beneficiaries',
        name: { ar: 'المستفيدون', en: 'Beneficiaries' },
        active: true,
        order: 1,
      },
    ],
  })
  kpiCatalogService.types.list.mockResolvedValue({ data: [] })
})

describe('KPI administration (Content Officer)', () => {
  it('shows the four stats, the rows with category names and the homepage badge', async () => {
    const wrapper = await mountView()
    expect(wrapper.findAll('.stats-card__value').map((value) => value.text())).toEqual([
      '3',
      '2',
      '1',
      '1',
    ])
    expect(wrapper.findAll('.kpis-admin__category')[0].text()).toBe('Beneficiaries')
    expect(wrapper.findAll('.kpis-admin__featured')).toHaveLength(1)
    wrapper.unmount()
  })

  it('publishes only after confirmation and features through the editor update', async () => {
    kpisAdminService.setPublished.mockResolvedValue({ data: kpi(2) })
    kpisAdminService.update.mockResolvedValue({ data: kpi(1, { showOnHomepage: true }) })
    const wrapper = await mountView()
    rowActions(wrapper, 1).vm.$emit('select', 'publish')
    await flushPromises()
    expect(kpisAdminService.setPublished).not.toHaveBeenCalled()
    useConfirm().accept()
    await flushPromises()
    expect(kpisAdminService.setPublished).toHaveBeenCalledWith(2, true)
    rowActions(wrapper, 0).vm.$emit('select', 'feature')
    await flushPromises()
    expect(kpisAdminService.update).toHaveBeenCalledWith(1, { showOnHomepage: true })
    wrapper.unmount()
  })

  it('shows the featured limit refusal returned by the server', async () => {
    kpisAdminService.update.mockRejectedValue(
      Object.assign(new Error('limit'), {
        normalized: {
          code: 'kpi.featured_limit_reached',
          message: 'At most 6 indicators can be shown on the homepage.',
        },
      }),
    )
    const wrapper = await mountView()
    rowActions(wrapper, 0).vm.$emit('select', 'feature')
    await flushPromises()
    expect(useToast().toasts.at(-1)).toMatchObject({
      type: 'error',
      message: 'At most 6 indicators can be shown on the homepage.',
    })
    wrapper.unmount()
  })

  it('moves an indicator down one step', async () => {
    kpisAdminService.move.mockResolvedValue({ data: [] })
    const wrapper = await mountView()
    await wrapper.findAll('.kpis-admin__reorder')[0].findAll('button')[1].trigger('click')
    await flushPromises()
    expect(kpisAdminService.move).toHaveBeenCalledWith(1, 'down')
    wrapper.unmount()
  })

  it('creates an indicator with a single-language name and no publish or order fields', async () => {
    kpisAdminService.create.mockResolvedValue({ data: kpi(9) })
    const wrapper = await mountView()
    await wrapper.find('.toolbar__actions .btn--primary').trigger('click')
    await flushPromises()
    const dialog = wrapper.findComponent(KpiFormDialog)
    const field = (name) =>
      dialog.findAllComponents(BaseInput).find((input) => input.props('name') === name)
    await field('name.ar').find('input').setValue('عدد المتطوعين')
    await field('value').find('input').setValue('1250')
    dialog.findComponent(FormModal).vm.$emit('submit')
    await vi.waitFor(() => expect(kpisAdminService.create).toHaveBeenCalled())
    const payload = kpisAdminService.create.mock.calls[0][0]
    expect(payload).toMatchObject({
      name: { ar: 'عدد المتطوعين', en: '' },
      value: '1250',
      showOnHomepage: false,
    })
    expect(payload).not.toHaveProperty('published')
    expect(payload).not.toHaveProperty('order')
    wrapper.unmount()
  })

  it('reorders every indicator in the drag-and-drop dialog and saves the full order', async () => {
    kpisAdminService.reorder.mockResolvedValue({ data: [kpi(2), kpi(1), kpi(3)] })
    const wrapper = await mountView()
    await wrapper.find('.toolbar__actions .btn--secondary').trigger('click')
    await flushPromises()
    const dialog = wrapper.findComponent(KpiReorderDialog)
    dialog.findComponent(SortableList).vm.$emit('reorder', [2, 1, 3])
    await flushPromises()
    const buttons = dialog.findAllComponents({ name: 'BaseButton' })
    await buttons[buttons.length - 1].trigger('click')
    await flushPromises()
    expect(kpisAdminService.list).toHaveBeenCalledWith({ sort: 'order', page: 1, perPage: 100 })
    expect(kpisAdminService.reorder).toHaveBeenCalledWith([2, 1, 3])
    wrapper.unmount()
  })

  it('explains why a category in use cannot be deleted', async () => {
    kpiCatalogService.categories.remove.mockRejectedValue(
      Object.assign(new Error('in use'), {
        normalized: { code: 'category.in_use', meta: { kpiCount: 4 }, message: 'In use' },
      }),
    )
    const wrapper = await mountView()
    await wrapper.findAll('.tabs__tab')[1].trigger('click')
    await flushPromises()
    await wrapper.find('.kpi-catalog__actions .btn--ghost').trigger('click')
    useConfirm().accept()
    await flushPromises()
    expect(kpiCatalogService.categories.remove).toHaveBeenCalledWith(1)
    expect(useToast().toasts.at(-1).message).toBe(
      'This entry is used by 4 indicators. Deactivate it instead.',
    )
    wrapper.unmount()
  })
})
