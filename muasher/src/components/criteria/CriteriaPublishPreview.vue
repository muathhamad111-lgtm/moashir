<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import CriteriaChangeList from './CriteriaChangeList.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  preview: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  failed: { type: Boolean, default: false },
  scope: { type: String, default: null },
})

const emit = defineEmits(['retry'])
const { t } = useI18n()

const replacedNumber = computed(() => props.preview?.replaces?.versionNumber ?? '')
const changes = computed(() => props.preview?.changes ?? [])

const impact = computed(() => {
  if (!props.preview?.replaces) return ''
  if (!props.scope) return t('criteria.publish.preview.impact.pick')
  const count =
    props.preview.reboundOpenAssessments?.find((entry) => entry.scope === props.scope)?.count ?? 0
  return t(
    `criteria.publish.preview.impact.${props.scope}`,
    { count, number: replacedNumber.value },
    count,
  )
})
</script>

<template>
  <section class="criteria-publish-preview" :aria-busy="loading || undefined">
    <h3 class="criteria-publish-preview__title">{{ t('criteria.publish.preview.title') }}</h3>

    <p v-if="loading" class="criteria-publish-preview__note">
      {{ t('criteria.publish.preview.loading') }}
    </p>
    <div v-else-if="failed" class="criteria-publish-preview__note" role="alert">
      {{ t('criteria.publish.preview.error') }}
      <BaseButton variant="secondary" size="sm" @click="emit('retry')">
        {{ t('actions.retry') }}
      </BaseButton>
    </div>
    <template v-else-if="preview?.replaces">
      <p class="criteria-publish-preview__summary">
        <template v-if="preview.changeCount">
          {{
            t(
              'criteria.publish.preview.summary',
              { count: preview.changeCount, scoreAffecting: preview.scoreAffectingCount },
              preview.changeCount,
            )
          }}
        </template>
        <template v-else>{{
          t('criteria.publish.preview.none', { number: replacedNumber })
        }}</template>
      </p>
      <p class="criteria-publish-preview__impact">
        <AppIcon name="shield-info" />
        <span aria-live="polite">{{ impact }}</span>
      </p>
      <details v-if="changes.length" class="criteria-publish-preview__details">
        <summary>{{ t('criteria.publish.preview.showList') }}</summary>
        <CriteriaChangeList :changes="changes" />
      </details>
    </template>
  </section>
</template>
