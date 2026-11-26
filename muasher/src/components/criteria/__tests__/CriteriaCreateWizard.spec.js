import { afterEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { DOMWrapper, mount } from '@vue/test-utils'
import MainCriterionForm from '@/components/criteria/MainCriterionForm.vue'
import SubCriterionForm from '@/components/criteria/SubCriterionForm.vue'
import CriteriaCreateDialogs from '@/components/criteria/workspace/CriteriaCreateDialogs.vue'
import { useConfirm } from '@/composables/useConfirm'
import { createTestGlobals } from '@/test-utils'

const MAIN = {
  id: 11,
  distributeEqually: false,
  remainingWeight: '400.00',
  defaultScoringType: 'achievement',
}

let mounted = null

afterEach(() => {
  mounted?.unmount()
  mounted = null
})

// The wizard is a teleported drawer: mount it for real and read it from the document.
function mountReal(component, props) {
  mounted = mount(component, {
    attachTo: document.body,
    props,
    global: createTestGlobals({ locale: 'en' }),
  })
  return mounted
}

function mountWizard(component, props = {}) {
  return mountReal(component, {
    modelValue: true,
    wizard: true,
    save: vi.fn().mockResolvedValue({}),
    ...props,
  })
}

const drawer = () => new DOMWrapper(document.querySelector('.modal--drawer'))
const currentStep = () => drawer().find('[aria-current="step"]').text()
const field = (name) => drawer().find(`[name="${name}"]`)
const footerButton = (label) =>
  drawer()
    .findAll('.form-steps__footer button')
    .find((button) => button.text() === label)

function next() {
  return drawer().find('form').trigger('submit')
}

async function nextTo(step) {
  await next()
  await vi.waitFor(() => expect(currentStep()).toContain(step))
}

// Main fields are nameAr/nameEn; sub-criterion fields are subNameAr/subNameEn.
async function fillNames([arabic, english] = ['nameAr', 'nameEn']) {
  await field(arabic).setValue('الابتكار')
  await field(english).setValue('Innovation')
}

describe('Main criterion create wizard', () => {
  it('shows one step at a time and keeps Next on a step with an invalid field', async () => {
    mountWizard(MainCriterionForm)
    expect(
      drawer()
        .findAll('.form-steps__step')
        .map((step) => step.text()),
    ).toEqual(['1 Details', '2 Weight', '3 Evaluation', '4 Review'])
    expect(field('nameAr').isVisible()).toBe(true)
    expect(field('weight').isVisible()).toBe(false)
    await next()
    await vi.waitFor(() => expect(field('nameAr').attributes('aria-invalid')).toBe('true'))
    expect(currentStep()).toContain('Details')
  })

  it('goes back with the values kept and reviews them before adding', async () => {
    const save = vi.fn().mockResolvedValue({})
    const wrapper = mountWizard(MainCriterionForm, { save })
    await fillNames()
    await nextTo('Weight')
    await footerButton('Back').trigger('click')
    expect(currentStep()).toContain('Details')
    expect(field('nameEn').element.value).toBe('Innovation')
    await nextTo('Weight')
    await field('weight').setValue('500')
    await nextTo('Evaluation')
    await drawer().find('input[name="mainDefaultScoringType"][value="relative"]').setValue(true)
    await nextTo('Review')
    const review = drawer().find('.criteria-wizard-review').text()
    expect(review).toContain('Innovation')
    expect(review).toContain('500')
    expect(save).not.toHaveBeenCalled()

    await drawer().find('form').trigger('submit')
    await vi.waitFor(() => expect(save).toHaveBeenCalled())
    const { payload } = save.mock.calls[0][0]
    expect(payload).toMatchObject({
      name: { ar: 'الابتكار', en: 'Innovation' },
      weight: '500.00',
      defaultScoringType: 'relative',
    })
    await vi.waitFor(() => expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false]))
  })

  it('brings back the step that holds a server error', async () => {
    const rejection = Object.assign(new Error('invalid'), {
      normalized: {
        message: 'Invalid input',
        fieldErrors: { 'name.en': ['This name is already used.'] },
      },
    })
    mountWizard(MainCriterionForm, { save: vi.fn().mockRejectedValue(rejection) })
    await fillNames()
    await nextTo('Weight')
    await field('weight').setValue('500')
    await nextTo('Evaluation')
    await nextTo('Review')
    await drawer().find('form').trigger('submit')
    await vi.waitFor(() => expect(currentStep()).toContain('Details'))
    expect(field('nameEn').attributes('aria-invalid')).toBe('true')
  })
})

describe('Sub-criterion create wizard', () => {
  async function reachReview() {
    await fillNames(['subNameAr', 'subNameEn'])
    await nextTo('Weight')
    await field('subWeight').setValue('300')
    await nextTo('Evaluation')
  }

  it('starts new indicators with the main criterion default scoring type', async () => {
    const save = vi.fn().mockResolvedValue({})
    mountWizard(SubCriterionForm, { main: MAIN, save })
    await reachReview()
    expect(drawer().find('input[name="subScoringType"][value="achievement"]').element.checked).toBe(
      true,
    )
    await nextTo('Attachments')
    await nextTo('Review')
    expect(drawer().find('.criteria-wizard-review').text()).toContain('300')
    await drawer().find('form').trigger('submit')
    await vi.waitFor(() => expect(save).toHaveBeenCalled())
    const { payload } = save.mock.calls[0][0]
    expect(payload).toMatchObject({
      mainCriterionId: 11,
      weight: '300.00',
      evaluationType: 'guiding-points',
      defaultPointScoringType: 'achievement',
    })
    expect(payload).not.toHaveProperty('scoringType')
  })

  it('sends the scoring type of a directly scored sub-criterion', async () => {
    const save = vi.fn().mockResolvedValue({})
    mountWizard(SubCriterionForm, { main: MAIN, save })
    await reachReview()
    await drawer().find('input[name="evaluationType"][value="direct"]').setValue(true)
    await drawer().find('input[name="subScoringType"][value="score"]').setValue(true)
    await nextTo('Attachments')
    await nextTo('Review')
    await drawer().find('form').trigger('submit')
    await vi.waitFor(() => expect(save).toHaveBeenCalled())
    const { payload } = save.mock.calls[0][0]
    expect(payload).toMatchObject({ evaluationType: 'direct', scoringType: 'score' })
    expect(payload).not.toHaveProperty('defaultPointScoringType')
  })

  it('keeps the weight step on a weight above what the main criterion has left', async () => {
    mountWizard(SubCriterionForm, { main: MAIN })
    await fillNames(['subNameAr', 'subNameEn'])
    await nextTo('Weight')
    await field('subWeight').setValue('450')
    await next()
    await vi.waitFor(() => expect(field('subWeight').attributes('aria-invalid')).toBe('true'))
    expect(currentStep()).toContain('Weight')
  })
})

describe('Saving from the last step', () => {
  async function reachMainReview(save) {
    mountWizard(MainCriterionForm, { save })
    await fillNames()
    await nextTo('Weight')
    await field('weight').setValue('500')
    await nextTo('Evaluation')
    await nextTo('Review')
  }

  it('sends one save however often the editor submits while it is in flight', async () => {
    let finish
    const save = vi.fn(() => new Promise((resolve) => (finish = resolve)))
    await reachMainReview(save)
    await next()
    await vi.waitFor(() => expect(save).toHaveBeenCalledTimes(1))
    const add = footerButton('Add')
    expect(add.attributes('disabled')).toBeDefined()
    await next()
    await next()
    expect(save).toHaveBeenCalledTimes(1)
    finish({})
  })

  it('shows a conflict in the drawer and keeps what was entered', async () => {
    const rejection = Object.assign(new Error('conflict'), {
      normalized: {
        status: 409,
        code: 'criteria.version_not_draft',
        message: 'Only a draft criteria version can be changed this way.',
        fieldErrors: { version_status: ['published'] },
      },
    })
    await reachMainReview(vi.fn().mockRejectedValue(rejection))
    await next()
    await vi.waitFor(() =>
      expect(drawer().find('.form-steps__error').text()).toContain('Only a draft criteria version'),
    )
    expect(drawer().find('.form-steps__error').attributes('role')).toBe('alert')
    expect(currentStep()).toContain('Review')
    expect(drawer().find('.criteria-wizard-review').text()).toContain('Innovation')
  })
})

describe('Unsaved entries in an add wizard', () => {
  const { confirmState, accept, dismiss } = useConfirm()

  function mountDialogs(create = vi.fn().mockResolvedValue({})) {
    const creating = ref({ type: 'main', parent: null })
    const editing = {
      creating,
      create: vi.fn(async (args) => {
        await create(args)
        creating.value = null
      }),
      cancelCreate: vi.fn(() => (creating.value = null)),
    }
    mountReal(CriteriaCreateDialogs, { editing })
    return editing
  }

  const closeButton = () => drawer().find('.modal__close')

  it('closes at once when nothing was entered', async () => {
    const editing = mountDialogs()
    await closeButton().trigger('click')
    await vi.waitFor(() => expect(editing.cancelCreate).toHaveBeenCalled())
    expect(confirmState.isOpen).toBe(false)
  })

  it('asks before dropping entered data and keeps it when the editor stays', async () => {
    const editing = mountDialogs()
    await fillNames()
    await nextTo('Weight')
    await closeButton().trigger('click')
    await vi.waitFor(() => expect(confirmState.isOpen).toBe(true))
    expect(confirmState.title).toBe('Discard the changes?')
    dismiss()
    await vi.waitFor(() => expect(confirmState.isOpen).toBe(false))
    expect(editing.cancelCreate).not.toHaveBeenCalled()
    await footerButton('Back').trigger('click')
    expect(field('nameEn').element.value).toBe('Innovation')

    await closeButton().trigger('click')
    await vi.waitFor(() => expect(confirmState.isOpen).toBe(true))
    accept()
    await vi.waitFor(() => expect(editing.cancelCreate).toHaveBeenCalled())
  })

  it('closes without asking once the new criterion is saved', async () => {
    const editing = mountDialogs()
    await fillNames()
    await nextTo('Weight')
    await field('weight').setValue('500')
    await nextTo('Evaluation')
    await nextTo('Review')
    await next()
    await vi.waitFor(() => expect(editing.create).toHaveBeenCalled())
    await vi.waitFor(() => expect(document.querySelector('.modal--drawer')).toBeNull())
    expect(confirmState.isOpen).toBe(false)
  })
})
