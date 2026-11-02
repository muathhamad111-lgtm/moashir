import { boolean as yupBoolean, object as yupObject } from 'yup'
import {
  AI_PLACEMENTS,
  AI_PROVIDERS,
  ANTHROPIC_INFERENCE_GEOS,
  INTEGRATION_LIMITS,
  INTEGRATION_MODES,
  NO_ACTIVE_PROVIDER,
  OPENAI_REGIONS,
  SECRET_PATTERNS,
} from '@/constants/integrations'
import { choiceOf, integerBetween, optionalText, requiredText, secretRule } from './secretSchema'

function identifierRule(t, prefix = '') {
  return optionalText(t, INTEGRATION_LIMITS.AI_IDENTIFIER_MAX)
    .test('whitespace', t('integrations.validation.noWhitespace'), (value) => !value || SECRET_PATTERNS.NO_WHITESPACE.test(value))
    .test('prefix', t('integrations.validation.prefix', { prefix }), (value) => !prefix || !value || value.startsWith(prefix))
}

function modelRule(t) {
  return optionalText(t, INTEGRATION_LIMITS.AI_IDENTIFIER_MAX).test(
    'pattern',
    t('integrations.ai.validation.modelPattern'),
    (value) => !value || SECRET_PATTERNS.AI_MODEL.test(value),
  )
}

function commonRules(t) {
  const tokens = INTEGRATION_LIMITS.AI_MAX_OUTPUT_TOKENS
  const timeout = INTEGRATION_LIMITS.AI_TIMEOUT
  return {
    apiKey: secretRule(t, { min: 20, max: 512 }),
    model: modelRule(t),
    maxOutputTokens: integerBetween(t, { min: tokens.MIN, max: tokens.MAX }),
    timeoutSeconds: integerBetween(t, { min: timeout.MIN, max: timeout.MAX }),
    dataTransferAcknowledged: yupBoolean(),
  }
}

function activeProviderRule(t) {
  return choiceOf(t, [NO_ACTIVE_PROVIDER, ...Object.values(AI_PROVIDERS)]).test(
    'acknowledged',
    t('integrations.ai.validation.acknowledgementRequired'),
    function check(provider) {
      return provider === NO_ACTIVE_PROVIDER || this.parent.providers?.[provider]?.dataTransferAcknowledged === true
    },
  )
}

export function aiSchema(t) {
  const noticeMax = INTEGRATION_LIMITS.AI_NOTICE_MAX
  return yupObject({
    activeProvider: activeProviderRule(t),
    mode: choiceOf(t, Object.values(INTEGRATION_MODES)),
    placement: choiceOf(t, AI_PLACEMENTS),
    notice: yupObject({ ar: requiredText(t, noticeMax), en: requiredText(t, noticeMax) }),
    providers: yupObject({
      openai: yupObject({
        ...commonRules(t),
        region: choiceOf(t, OPENAI_REGIONS),
        organizationId: identifierRule(t),
        projectId: identifierRule(t),
      }),
      anthropic: yupObject({
        ...commonRules(t),
        workspaceId: identifierRule(t, SECRET_PATTERNS.ANTHROPIC_WORKSPACE_PREFIX),
        inferenceGeo: choiceOf(t, ANTHROPIC_INFERENCE_GEOS),
      }),
    }),
  })
}
