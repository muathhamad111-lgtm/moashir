<script setup>
import { useI18n } from 'vue-i18n'
import { useCriteriaVersionText } from '@/composables/useCriteriaVersionText'
import CriteriaChangeGroups from './CriteriaChangeGroups.vue'

// The text corrections made to a version after it was published: they never touch scores.
defineProps({
  corrections: { type: Array, required: true },
})

const { t } = useI18n()
const { dateOf } = useCriteriaVersionText()
</script>

<template>
  <section class="criteria-version-corrections">
    <h2 class="criteria-version-corrections__title">{{ t('criteria.changes.corrections') }}</h2>
    <article
      v-for="event in corrections"
      :key="event.id"
      class="criteria-version-corrections__item"
    >
      <h3 class="criteria-version-corrections__head">
        {{ dateOf(event.createdAt) }}
        <span v-if="event.editedBy?.name">· {{ event.editedBy.name }}</span>
      </h3>
      <p v-if="event.reason" class="criteria-page__sub-count">
        {{ t('criteria.versions.reason') }} <bdi>{{ event.reason }}</bdi>
      </p>
      <CriteriaChangeGroups v-if="event.changes.length" :changes="event.changes" />
      <p v-else class="criteria-page__sub-count">
        {{ t('criteria.changes.correctionCount', { count: event.changeCount }, event.changeCount) }}
      </p>
    </article>
  </section>
</template>
