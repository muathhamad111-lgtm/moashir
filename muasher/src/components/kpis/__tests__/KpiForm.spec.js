import { afterEach, describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import KpiForm from '@/components/kpis/KpiForm.vue'
import { createTestGlobals } from '@/test-utils'

let wrapper

function factory(props = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  wrapper = mount(KpiForm, {
    props: { modelValue: true, kpi: null, ...props },
    attachTo: document.body,
    global: { ...globals, stubs: { teleport: true } },
  })
  return wrapper
}

function submitForm() {
  return wrapper.vm.form.onSubmit()
}

async function fillValid() {
  await wrapper.find('input[name="nameAr"]').setValue('المتطوعون')
  await wrapper.find('input[name="nameEn"]').setValue('Volunteers')
  await wrapper.find('input[name="value"]').setValue('214000')
  await wrapper.find('input[name="target"]').setValue('300000')
  await wrapper.find('input[name="unitAr"]').setValue('متطوع')
  await wrapper.find('input[name="unitEn"]').setValue('volunteers')
  wrapper.vm.form.setFieldValue('category', 'human-resources')
  wrapper.vm.form.setFieldValue('status', 'good')
}

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
})

describe('KpiForm', () => {
  it('renders the create title and the bilingual name/value fields', () => {
    factory()
    const title = document.querySelector('.modal__title')
    expect(title.textContent).toContain('Add performance indicator')
    expect(document.querySelector('input[name="nameAr"]')).toBeTruthy()
    expect(document.querySelector('input[name="nameEn"]')).toBeTruthy()
    expect(document.querySelector('input[name="value"]')).toBeTruthy()
    expect(document.querySelector('input[name="unitAr"]')).toBeTruthy()
  })

  it('offers token-based color swatches (no free hex field)', () => {
    factory()
    const groups = document.querySelectorAll('.kpi-form__swatches')
    expect(groups).toHaveLength(3)
    groups.forEach((group) => {
      expect(group.querySelectorAll('.kpi-form__swatch')).toHaveLength(5)
    })
    expect(document.querySelector('input[type="color"]')).toBeNull()
  })

  it('blocks submit and surfaces field errors when the form is empty', async () => {
    factory()
    await submitForm()
    await flushPromises()
    expect(wrapper.emitted('saved')).toBeUndefined()
    expect(document.querySelector('.field__error')).toBeTruthy()
  })

  it('emits saved with an { ar, en } bilingual payload on a valid submit', async () => {
    factory()
    await fillValid()
    await flushPromises()
    await submitForm()
    await flushPromises()
    const saved = wrapper.emitted('saved')
    expect(saved).toHaveLength(1)
    const { id, payload } = saved[0][0]
    expect(id).toBeNull()
    expect(payload.name).toEqual({ ar: 'المتطوعون', en: 'Volunteers' })
    expect(payload.unit).toEqual({ ar: 'متطوع', en: 'volunteers' })
    expect(payload.value).toBe(214000)
    expect(payload.target).toBe(300000)
    expect(payload.color).toBe('primary')
    expect(payload.category).toBe('human-resources')
    expect(payload.status).toBe('good')
    expect(payload.badgeColor).toBe('primary')
    expect(payload.cardColor).toBe('primary')
    expect(payload.showOnHomepage).toBe(false)
    expect(payload.owner).toEqual({ ar: '', en: '' })
    expect(payload.published).toBe(false)
  })

  it('reflects the chosen color token in the payload', async () => {
    factory()
    await fillValid()
    await wrapper.findAll('.kpi-form__swatch')[1].trigger('click')
    await flushPromises()
    await submitForm()
    await flushPromises()
    const { payload } = wrapper.emitted('saved')[0][0]
    expect(payload.color).toBe('success')
  })

  it('prefills fields and carries the id in edit mode', async () => {
    const kpi = {
      id: 42,
      name: { ar: 'المستفيدون', en: 'Beneficiaries' },
      description: { ar: 'وصف', en: 'Description' },
      value: 4800000,
      target: 6000000,
      unit: { ar: 'مستفيد', en: 'beneficiaries' },
      icon: 'fa-solid fa-hand-holding-heart',
      color: 'success',
      order: 6,
      published: true,
    }
    factory({ kpi })
    await flushPromises()
    expect(document.querySelector('input[name="nameAr"]').value).toBe('المستفيدون')
    expect(document.querySelector('input[name="nameEn"]').value).toBe('Beneficiaries')
    expect(document.querySelector('input[name="displayOrder"]').value).toBe('6')
    const title = document.querySelector('.modal__title')
    expect(title.textContent).toContain('Edit performance indicator')
  })

  it('sends a null target when the target field is left empty', async () => {
    factory()
    await wrapper.find('input[name="nameAr"]').setValue('مؤشر')
    await wrapper.find('input[name="nameEn"]').setValue('Indicator')
    await wrapper.find('input[name="value"]').setValue('10')
    await wrapper.find('input[name="unitAr"]').setValue('وحدة')
    await wrapper.find('input[name="unitEn"]').setValue('unit')
    wrapper.vm.form.setFieldValue('category', 'performance')
    wrapper.vm.form.setFieldValue('status', 'good')
    await flushPromises()
    await submitForm()
    await flushPromises()
    const { payload } = wrapper.emitted('saved')[0][0]
    expect(payload.target).toBeNull()
  })
})
