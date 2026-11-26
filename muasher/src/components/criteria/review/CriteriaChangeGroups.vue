<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCriteria } from '@/composables/useCriteria'
import { groupByMain } from '@/utils/criteriaChangeView'
import CriteriaChangeRow from './CriteriaChangeRow.vue'

// The changes of a version, one card per main criterion.
const props = defineProps({
  changes: { type: Array, required: true },
  // main code → name, for main criteria that did not change themselves
  mainNames: { type: Map, default: () => new Map() },
})

const { t } = useI18n()
const { localized } = useCriteria()

const groups = computed(() => groupByMain(props.changes, props.mainNames))

function titleOf(group) {
  if (!group.code) return t('criteria.review.versionLevel')
  return localized(group.label) || group.code
}
</script>

<template>
  <div class="criteria-change-groups">
    <section
      v-for="group in groups"
      :key="group.code || 'version'"
      class="criteria-change-group"
      :aria-label="titleOf(group)"
    >
      <header class="criteria-change-group__head">
        <bdi v-if="group.code" class="criteria-code">{{ group.code }}</bdi>
        <h3 class="criteria-change-group__title">{{ titleOf(group) }}</h3>
        <span class="criteria-change-group__count">
          {{
            t('criteria.review.groupCount', { count: group.changes.length }, group.changes.length)
          }}
        </span>
      </header>
      <ul class="criteria-change-group__list">
        <CriteriaChangeRow
          v-for="change in group.changes"
          :key="`${change.nodeType}:${change.code}`"
          :change="change"
        />
      </ul>
    </section>
  </div>
</template>
