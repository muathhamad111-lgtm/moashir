import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { useForm } from 'vee-validate'
import { mount } from '@vue/test-utils'
import NationalIdPolicyFields from '@/components/settings/general/NationalIdPolicyFields.vue'
import { createTestGlobals } from '@/test-utils'

function mountFields(values, fileAccessRoles, locale = 'ar') {
  const Host = defineComponent({
    setup() {
      useForm({ initialValues: values })
      return () => h(NationalIdPolicyFields, { fileAccessRoles })
    },
  })
  return mount(Host, { global: createTestGlobals({ locale }) })
}

describe('national ID policy fields', () => {
  it('marks the roles that open assessment evidence', () => {
    const wrapper = mountFields(
      { nationalIdEnabled: true, nationalIdRoles: [] },
      ['assessor'],
      'en',
    )
    expect(wrapper.text()).toContain('Assessor (opens assessment evidence)')
  })

  it('warns about roles that open assessment evidence but are not selected', () => {
    const wrapper = mountFields(
      { nationalIdEnabled: true, nationalIdRoles: ['assessor'] },
      ['assessor', 'filtering_officer'],
      'en',
    )
    const warning = wrapper.find('[data-testid="national-id-uncovered"]')
    expect(warning.exists()).toBe(true)
    expect(warning.text()).toContain('Filtering Officer')
    expect(warning.text()).not.toContain('Assessor')
  })

  it('shows no warning while the requirement is off', () => {
    const wrapper = mountFields({ nationalIdEnabled: false, nationalIdRoles: [] }, ['assessor'])
    expect(wrapper.find('[data-testid="national-id-uncovered"]').exists()).toBe(false)
  })
})
