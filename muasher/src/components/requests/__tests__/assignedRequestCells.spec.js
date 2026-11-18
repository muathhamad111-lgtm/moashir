import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import AssignedRequestActions from '@/components/requests/AssignedRequestActions.vue'
import AssignedRequestProgress from '@/components/requests/AssignedRequestProgress.vue'

function actions(status, locale = 'ar') {
  return mount(AssignedRequestActions, {
    props: { requestId: 9107, requestNumber: 'ACC-2026-000007', status },
    global: createTestGlobals({ locale }),
  })
}

describe('AssignedRequestActions', () => {
  it('offers an eye icon «عرض» and the review action named after the stage', () => {
    const labels = (status) =>
      actions(status)
        .findAll('.btn')
        .map((button) => button.attributes('aria-label') ?? button.text())
    expect(labels('assigned')).toEqual(['عرض', 'بدء التقييم'])
    expect(labels('under-review')).toEqual(['عرض', 'استكمال التقييم'])
    expect(labels('under-initial-assessment')).toEqual(['عرض', 'استكمال التقييم'])
    expect(labels('completed')).toEqual(['عرض', 'عرض التقييم'])
    expect(labels('awaiting-filtering')).toEqual(['عرض', 'عرض التقييم'])
    expect(actions('assigned').find('.btn--icon').text()).toBe('')
  })

  it('links the view to the request page and the review action to the review screen', () => {
    const links = actions('assigned')
      .findAll('a')
      .map((link) => link.attributes('href'))
    expect(links).toEqual(['/admin/requests/9107', '/admin/requests/9107/review'])
  })

  it('names the button group after the request and speaks English in English', () => {
    const wrapper = actions('under-review', 'en')
    expect(wrapper.attributes('aria-label')).toBe('ACC-2026-000007')
    expect(wrapper.text()).toContain('Continue the assessment')
  })
})

describe('AssignedRequestProgress', () => {
  function progress(value, locale = 'ar') {
    return mount(AssignedRequestProgress, {
      props: { progress: value },
      global: createTestGlobals({ locale }),
    })
  }

  it('shows how many items are scored out of the total and how many are left', () => {
    const wrapper = progress({ scored: 10, required: 26 })
    expect(wrapper.find('.progress-meter__value').text()).toBe('10 / 26')
    expect(wrapper.find('.progress-meter__label').text()).toBe('المتبقي 16')
    expect(wrapper.find('[role="progressbar"]').attributes('aria-valuenow')).toBe('10')
  })

  it('says the scoring is complete when nothing is left', () => {
    expect(progress({ scored: 26, required: 26 }, 'en').find('.progress-meter__label').text()).toBe(
      'Scoring complete',
    )
  })

  it('renders nothing without progress data', () => {
    expect(progress(null).find('.progress-meter').exists()).toBe(false)
  })
})
