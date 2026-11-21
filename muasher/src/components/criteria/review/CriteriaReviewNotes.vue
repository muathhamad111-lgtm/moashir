<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import CriteriaProblemList from '@/components/criteria/CriteriaProblemList.vue'

// Step 1 of the review: what blocks the publication, each with a link to the criterion to fix.
defineProps({
  checking: { type: Boolean, default: false },
  problems: { type: Array, required: true },
  versionId: { type: Number, required: true },
})

const { t } = useI18n()
</script>

<template>
  <section class="criteria-review-page__section" :aria-busy="checking || undefined">
    <p v-if="checking" class="criteria-details__note">
      {{ t('criteria.workspace.review.checking') }}
    </p>
    <CriteriaProblemList
      v-else-if="problems.length"
      :problems="problems"
      :version-id="versionId"
      :title="t('criteria.publish.blockedTitle')"
    />
    <p v-else class="criteria-review__ready" role="status">
      <AppIcon name="check-circle" />
      {{ t('criteria.workspace.review.ready') }}
    </p>
  </section>
</template>
