import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import EntityHeaderFacts from '@/components/entities/profile/EntityHeaderFacts.vue'
import { createTestGlobals } from '@/test-utils'

const PROFILE = { reference: 'ENT-006005', createdAt: null, verifiedAt: null }

function factory(certificate) {
  const { plugins, directives } = createTestGlobals()
  return mount(EntityHeaderFacts, {
    props: { profile: PROFILE, certificate },
    global: { plugins, directives },
  })
}

describe('EntityHeaderFacts certificate fact', () => {
  it('shows «صالحة حتى <date>» and not the number when the certificate has an expiry date', () => {
    const wrapper = factory({ number: 'CRT-2026-000011', validUntil: '2027-03-17' })
    const fact = wrapper.find('[data-testid="profile-certificate"]')
    expect(fact.text()).toContain('صالحة حتى')
    expect(fact.text()).not.toContain('CRT-2026-000011')
    expect(fact.find('[data-testid="profile-view-certificate"]').exists()).toBe(true)
  })

  it('shows the certificate number only when there is no expiry date, with the eye link', () => {
    const wrapper = factory({ number: 'CRT-2026-000011', validUntil: null })
    const fact = wrapper.find('[data-testid="profile-certificate"]')
    expect(fact.text()).toContain('CRT-2026-000011')
    expect(fact.text()).not.toContain('صالحة حتى')
    expect(fact.find('[data-testid="profile-view-certificate"]').exists()).toBe(true)
  })

  it('hides the certificate fact when the entity has no level certificate', () => {
    const wrapper = factory(null)
    expect(wrapper.find('[data-testid="profile-certificate"]').exists()).toBe(false)
  })
})
