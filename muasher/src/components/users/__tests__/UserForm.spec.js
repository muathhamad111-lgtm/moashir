import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import UserForm from '@/components/users/UserForm.vue'
import { createTestGlobals } from '@/test-utils'

let wrapper
let save

function factory(props = {}) {
  save = vi.fn().mockResolvedValue(undefined)
  const globals = createTestGlobals({ locale: 'en' })
  wrapper = mount(UserForm, {
    props: { modelValue: true, user: null, save, ...props },
    attachTo: document.body,
    global: { ...globals, stubs: { teleport: true } },
  })
  return wrapper
}

function submitForm() {
  return wrapper.vm.form.onSubmit()
}

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
})

describe('UserForm', () => {
  it('renders the create title and the person/roles fields', () => {
    factory()
    const title = document.querySelector('.modal__title')
    expect(title.textContent).toContain('Invite user')
    expect(document.querySelector('input[name="name"]')).toBeTruthy()
    expect(document.querySelector('input[name="email"]')).toBeTruthy()
    expect(
      document.querySelectorAll('.user-form__roles input[type="checkbox"]').length,
    ).toBeGreaterThan(1)
  })

  it('blocks submit and surfaces field errors when the form is empty', async () => {
    factory()
    await submitForm()
    await flushPromises()
    expect(wrapper.emitted('saved')).toBeUndefined()
    expect(document.querySelector('.field__error')).toBeTruthy()
  })

  it('saves a name/email/roles payload through the save prop, then emits saved and closes', async () => {
    factory()
    await wrapper.find('input[name="name"]').setValue('Abdullah Al-Qahtani')
    await wrapper.find('input[name="email"]').setValue('abdullah@moashir.example.sa')
    await wrapper.findAll('.user-form__roles input[type="checkbox"]')[0].setValue(true)
    await flushPromises()
    await submitForm()
    await flushPromises()
    expect(save).toHaveBeenCalledWith({
      id: null,
      payload: {
        name: 'Abdullah Al-Qahtani',
        email: 'abdullah@moashir.example.sa',
        roles: ['system_admin'],
      },
    })
    expect(wrapper.emitted('saved')).toEqual([[{ id: null }]])
    expect(wrapper.emitted('update:modelValue')).toEqual([[false]])
  })

  it('prefills fields and carries the id in edit mode', async () => {
    const user = {
      id: 42,
      name: 'Sarah Whitfield',
      email: 'sarah@moashir.example.sa',
      roles: ['content_officer'],
      status: 'suspended',
    }
    factory({ user })
    await flushPromises()
    expect(document.querySelector('input[name="name"]').value).toBe('Sarah Whitfield')
    expect(document.querySelector('input[name="email"]').value).toBe('sarah@moashir.example.sa')
    const title = document.querySelector('.modal__title')
    expect(title.textContent).toContain('Edit user')
  })

  it('shows the read-only role-capability hint once a role is chosen', async () => {
    factory()
    expect(document.querySelector('.user-form__hint')).toBeNull()
    await wrapper.findAll('.user-form__roles input[type="checkbox"]')[0].setValue(true)
    await flushPromises()
    expect(document.querySelector('.user-form__hint')).toBeTruthy()
    expect(document.querySelector('.user-form__hint-item')).toBeTruthy()
  })
})
