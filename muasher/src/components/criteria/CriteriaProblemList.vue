<script setup>
import { toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCriteria } from '@/composables/useCriteria'
import { useCriteriaProblemNodes } from '@/composables/useCriteriaProblemNodes'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  problems: { type: Array, required: true },
  versionId: { type: Number, required: true },
  title: { type: String, default: '' },
})

const emit = defineEmits(['navigate'])
const { t } = useI18n()
const { localized, weight } = useCriteria()
const { nodeOf, linkFor } = useCriteriaProblemNodes(
  toRef(props, 'versionId'),
  toRef(props, 'problems'),
)

function nameOf(problem) {
  return localized(nodeOf(problem)?.name)
}

function describe(problem) {
  return t(`criteria.problems.${problem.problem}`, {
    name: nameOf(problem),
    expected: problem.expectedWeight ? weight(problem.expectedWeight) : '',
    actual: problem.actualWeight ? weight(problem.actualWeight) : '',
  })
}

function fixLabel(problem) {
  const name = nameOf(problem)
  return name ? t('criteria.problems.fix', { name }) : t('criteria.problems.fixVersion')
}

function keyOf(problem, index) {
  return `${problem.nodeType}-${problem.nodeId ?? 'version'}-${problem.problem}-${index}`
}
</script>

<template>
  <section class="criteria-problems" role="alert">
    <header class="criteria-problems__head">
      <AppIcon name="warning-triangle" />
      <h3 class="criteria-problems__title">{{ title || t('criteria.problems.title') }}</h3>
    </header>
    <ul class="criteria-problems__list">
      <li
        v-for="(problem, index) in problems"
        :key="keyOf(problem, index)"
        class="criteria-problems__item"
      >
        <span class="criteria-problems__text">{{ describe(problem) }}</span>
        <RouterLink class="criteria-problems__fix" :to="linkFor(problem)" @click="emit('navigate')">
          {{ fixLabel(problem) }}
          <AppIcon name="arrow-right" />
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
