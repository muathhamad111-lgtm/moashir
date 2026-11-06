import { describe, expect, it } from 'vitest'
import {
  changedRules,
  fieldErrorsOf,
  formValuesOf,
  insightsSettingsSchema,
} from '@/utils/insightsSettingsForm'

const SETTINGS = {
  gapThresholds: { criticalBelow: '50.00', strengthFrom: '80.00', version: 1 },
  maturityBands: { developingFrom: '50.00', matureFrom: '70.00', leadingFrom: '80.00', version: 1 },
}
const t = (key) => key

describe('insights settings form', () => {
  it('flattens the two groups into form values', () => {
    expect(formValuesOf(SETTINGS)).toEqual({
      criticalBelow: '50.00',
      strengthFrom: '80.00',
      developingFrom: '50.00',
      matureFrom: '70.00',
      leadingFrom: '80.00',
    })
  })

  it('sends only the changed group, normalized to two decimals', () => {
    const values = { ...formValuesOf(SETTINGS), criticalBelow: '٥٥' }
    expect(changedRules(SETTINGS, values)).toEqual({
      gapThresholds: { criticalBelow: '55.00', strengthFrom: '80.00' },
    })
    expect(changedRules(SETTINGS, { ...formValuesOf(SETTINGS), strengthFrom: '80' })).toEqual({})
  })

  it('maps a group error from the server to its last field', () => {
    expect(fieldErrorsOf({ maturityBands: ['not ascending'], other: ['x'] })).toEqual({
      leadingFrom: 'not ascending',
    })
  })

  it('refuses a bound that is not above the previous one', async () => {
    const schema = insightsSettingsSchema(t)
    await expect(
      schema.validate({ ...formValuesOf(SETTINGS), matureFrom: '45' }, { abortEarly: false }),
    ).rejects.toMatchObject({ errors: ['settings.insights.validation.ascending'] })
    await expect(schema.validate(formValuesOf(SETTINGS))).resolves.toBeTruthy()
  })
})
