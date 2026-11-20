<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import CriteriaChangeGroups from './CriteriaChangeGroups.vue'

// Step 2 of the review: what the draft changes against the published version, by main criterion.
const props = defineProps({
  // usePublicationPreview(): { preview, loading, failed, load }
  preview: { type: Object, required: true },
  mainNames: { type: Map, default: () => new Map() },
  versionId: { type: Number, required: true },
})

const { t } = useI18n()

const data = computed(() => props.preview.preview.value)
const changes = computed(() => data.value?.changes ?? [])
</script>

<template>
  <section class="criteria-review-page__section" :aria-busy="preview.loading.value || undefined">
    <p v-if="preview.loading.value" class="criteria-details__note">
      {{ t('criteria.publish.preview.loading') }}
    </p>
    <div v-else-if="preview.failed.value" class="criteria-details__note" role="alert">
      {{ t('criteria.publish.preview.error') }}
      <BaseButton variant="secondary" size="sm" @click="preview.load(versionId)">
        {{ t('actions.retry') }}
      </BaseButton>
    </div>
    <template v-else-if="data">
      <p class="criteria-review-page__count">
        <template v-if="changes.length">
          {{
            t(
              'criteria.publish.preview.summary',
              { count: data.changeCount, scoreAffecting: data.scoreAffectingCount },
              data.changeCount,
            )
          }}
        </template>
        <template v-else>
          {{ t('criteria.publish.preview.none', { number: data.replaces?.versionNumber ?? '' }) }}
        </template>
      </p>
      <CriteriaChangeGroups v-if="changes.length" :changes="changes" :main-names="mainNames" />
    </template>
  </section>
</template>
