<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { EXCLUSION_REASONS } from '@/constants/insights'

const props = defineProps({
  overview: { type: Object, required: true },
})

const { t } = useI18n()
const exclusions = computed(() => props.overview.exclusions ?? [])
const fallbackNumber = computed(() => props.overview.selected?.number ?? '')

function iconOf(exclusion) {
  return exclusion.reason === EXCLUSION_REASONS.UNDER_REVIEW ? 'warning-triangle' : 'info-circle'
}
</script>

<template>
  <div v-if="exclusions.length || overview.inProgressRequest" class="insights-notices">
    <div
      v-for="exclusion in exclusions"
      :key="exclusion.assessmentId"
      class="insights-notice"
      :class="`insights-notice--${exclusion.reason}`"
      role="note"
      data-testid="insights-exclusion"
    >
      <AppIcon :name="iconOf(exclusion)" class="insights-notice__icon" />
      <div class="insights-notice__body">
        <i18n-t
          :keypath="`insights.exclusion.${exclusion.reason}`"
          tag="p"
          class="insights-notice__title"
        >
          <template #number>
            <bdi dir="ltr">{{ exclusion.number }}</bdi>
          </template>
        </i18n-t>
        <i18n-t
          v-if="fallbackNumber"
          keypath="insights.exclusion.fallback"
          tag="p"
          class="insights-notice__text"
        >
          <template #number>
            <bdi dir="ltr">{{ fallbackNumber }}</bdi>
          </template>
        </i18n-t>
      </div>
    </div>
    <p
      v-if="overview.inProgressRequest"
      class="insights-notice insights-notice--progress"
      role="note"
    >
      <AppIcon name="clock" class="insights-notice__icon" />
      <span class="insights-notice__text">{{ t('insights.inProgress') }}</span>
    </p>
  </div>
</template>
