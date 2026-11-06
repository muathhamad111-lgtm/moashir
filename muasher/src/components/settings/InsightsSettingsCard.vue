<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import InsightsRulesPreview from './InsightsRulesPreview.vue'
import { useInsightsSettings } from '@/composables/useInsightsSettings'
import { isAscending, isRuleValue } from '@/utils/insights'
import { formatRiyadhDate } from '@/utils/datetime'
import { RULE_GROUPS } from '@/utils/insightsSettingsForm'

const { t, n, locale } = useI18n()
const { status, settings, draft, aligned, saving, reasonOpen, load, submit, reset, confirmReason } =
  useInsightsSettings()

function usable(group) {
  const values = RULE_GROUPS[group].map((key) => draft.value[group][key])
  return values.every(isRuleValue) && isAscending(values)
}

const previewGaps = computed(() =>
  usable('gapThresholds') ? draft.value.gapThresholds : settings.value.gapThresholds,
)
const previewBands = computed(() =>
  usable('maturityBands') ? draft.value.maturityBands : settings.value.maturityBands,
)
const versionLine = computed(() =>
  t('settings.insights.version', {
    gaps: n(settings.value.gapThresholds.version),
    bands: n(settings.value.maturityBands.version),
  }),
)

onMounted(load)
</script>

<template>
  <div class="insights-settings" data-testid="insights-settings">
    <Skeleton v-if="status === 'loading'" variant="block" :count="2" />
    <ErrorState
      v-else-if="status === 'error'"
      :message="t('settings.insights.loadError')"
      @retry="load"
    />
    <form
      v-else
      id="insights-settings-form"
      class="settings-form"
      novalidate
      @submit.prevent="submit"
    >
      <header class="insights-settings__header">
        <p class="insights-settings__lead">{{ t('settings.insights.description') }}</p>
        <p class="insights-settings__version" data-testid="insights-settings-version">
          {{ versionLine }}
          <template v-if="settings.effectiveSince">
            ·
            {{
              t('settings.insights.savedAt', {
                date: formatRiyadhDate(settings.effectiveSince, locale),
              })
            }}
          </template>
        </p>
      </header>
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.insights.gapsTitle') }}</legend>
        <div class="settings-form__grid">
          <FieldInput
            name="criticalBelow"
            :label="t('settings.insights.fields.criticalBelow')"
            inputmode="decimal"
            input-dir="ltr"
            required
          />
          <FieldInput
            name="strengthFrom"
            :label="t('settings.insights.fields.strengthFrom')"
            inputmode="decimal"
            input-dir="ltr"
            required
          />
        </div>
      </fieldset>
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.insights.bandsTitle') }}</legend>
        <div class="settings-form__grid">
          <FieldInput
            name="developingFrom"
            :label="t('settings.insights.fields.developingFrom')"
            inputmode="decimal"
            input-dir="ltr"
            required
          />
          <FieldInput
            name="matureFrom"
            :label="t('settings.insights.fields.matureFrom')"
            inputmode="decimal"
            input-dir="ltr"
            required
          />
          <FieldInput
            name="leadingFrom"
            :label="t('settings.insights.fields.leadingFrom')"
            inputmode="decimal"
            input-dir="ltr"
            required
          />
        </div>
      </fieldset>
      <InsightsRulesPreview :gaps="previewGaps" :bands="previewBands" />
      <div
        v-if="!aligned"
        class="insights-settings__warning"
        role="status"
        data-testid="insights-alignment"
      >
        <AppIcon name="warning-triangle" />
        <div>
          <p class="insights-settings__warning-title">
            {{ t('settings.insights.alignmentTitle') }}
          </p>
          <p class="insights-settings__warning-text">
            {{ t('settings.insights.alignmentMessage') }}
          </p>
        </div>
      </div>
      <div class="path-settings__actions">
        <BaseButton type="submit" variant="primary" :loading="saving" icon="save">
          {{ t('settings.insights.save') }}
        </BaseButton>
        <BaseButton type="button" variant="ghost" @click="reset">{{
          t('actions.cancel')
        }}</BaseButton>
      </div>
    </form>
    <ReasonDialog
      v-model="reasonOpen"
      :title="t('settings.insights.reasonTitle')"
      :message="t('settings.insights.reasonMessage')"
      :submit-label="t('actions.save')"
      :action="confirmReason"
      action-name="settings.insights.update"
    />
  </div>
</template>
