// Which editor-sheet tab holds each field of the criteria forms, so an error can mark its tab.
export const MAIN_FIELD_SECTIONS = Object.freeze({
  nameAr: 'basics',
  nameEn: 'basics',
  weight: 'basics',
  group: 'basics',
  descriptionAr: 'basics',
  descriptionEn: 'basics',
})

export const SUB_FIELD_SECTIONS = Object.freeze({
  nameAr: 'basics',
  nameEn: 'basics',
  descriptionAr: 'basics',
  descriptionEn: 'basics',
  noteAr: 'advanced',
  noteEn: 'advanced',
})

export const POINT_FIELD_SECTIONS = Object.freeze({
  descriptionAr: 'basics',
  descriptionEn: 'basics',
  scoringType: 'evaluation',
})

// Create wizards (UX-A2): the steps, the form sections each step shows, and the fields Next validates.
export const MAIN_WIZARD = Object.freeze({
  steps: ['basics', 'weight', 'evaluation', 'review'],
  views: { basics: ['basics'], weight: ['weight'], evaluation: ['evaluation'], review: ['review'] },
  stepFields: {
    basics: ['nameAr', 'nameEn', 'group', 'descriptionAr', 'descriptionEn'],
    weight: ['weight'],
    evaluation: ['defaultScoringType'],
  },
})

export const SUB_WIZARD = Object.freeze({
  steps: ['basics', 'weight', 'evaluation', 'attachments', 'review'],
  views: {
    basics: ['basics'],
    weight: ['weight'],
    evaluation: ['evaluation', 'advanced'],
    attachments: ['attachments'],
    review: ['review'],
  },
  stepFields: {
    basics: ['nameAr', 'nameEn', 'descriptionAr', 'descriptionEn'],
    weight: ['weight'],
    evaluation: ['evaluationType', 'scoringType', 'noteAr', 'noteEn'],
  },
})

// field → step, so an error found on submit brings back the step that holds it.
export function fieldStepsOf(wizard) {
  return Object.fromEntries(
    Object.entries(wizard.stepFields).flatMap(([step, fields]) =>
      fields.map((field) => [field, step]),
    ),
  )
}
