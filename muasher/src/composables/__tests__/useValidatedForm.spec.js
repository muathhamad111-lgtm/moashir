import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { object as yupObject, string as yupString } from 'yup'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { logger } from '@/utils/logger'

vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const schema = yupObject({
  name: yupString().required('هذا الحقل مطلوب'),
})

function harness(submit) {
  let form
  const Host = defineComponent({
    setup() {
      form = useValidatedForm({
        schema,
        initialValues: { name: '' },
        action: 'test.submit',
        submit,
      })
      return () => h('div')
    },
  })
  mount(Host)
  return () => form
}

describe('useValidatedForm', () => {
  it('blocks submission and surfaces field errors when the schema fails', async () => {
    const submit = vi.fn()
    const form = harness(submit)()
    await form.onSubmit()
    await flushPromises()
    expect(submit).not.toHaveBeenCalled()
    expect(form.errors.value.name).toBe('هذا الحقل مطلوب')
  })

  it('submits valid values with a breadcrumb, toggles busy, and guards double submits', async () => {
    let resolveSubmit
    const submit = vi.fn(() => new Promise((resolve) => (resolveSubmit = resolve)))
    const form = harness(submit)()
    form.setFieldValue('name', 'جمعية البر')
    const submission = form.onSubmit()
    await vi.waitFor(() => expect(submit).toHaveBeenCalledTimes(1))
    expect(form.submitting.value).toBe(true)
    await form.onSubmit()
    expect(submit).toHaveBeenCalledTimes(1)
    resolveSubmit()
    await submission
    expect(form.submitting.value).toBe(false)
    expect(submit).toHaveBeenCalledWith({ name: 'جمعية البر' })
    expect(logger.breadcrumb).toHaveBeenCalledWith('form', 'test.submit', { fields: ['name'] })
  })

  it('captures failures to Sentry with the payload SHAPE and maps server field errors', async () => {
    const error = new Error('422')
    error.normalized = {
      message: 'البيانات غير صحيحة',
      fieldErrors: { name: ['الاسم مستخدم مسبقًا'] },
      code: 422,
    }
    const submit = vi.fn().mockRejectedValue(error)
    const form = harness(submit)()
    form.setFieldValue('name', 'جمعية البر')
    await form.onSubmit()
    await flushPromises()
    expect(logger.error).toHaveBeenCalledWith(error, { action: 'test.submit', fields: ['name'] })
    expect(form.errors.value.name).toBe('الاسم مستخدم مسبقًا')
    expect(form.submitting.value).toBe(false)
  })
})
