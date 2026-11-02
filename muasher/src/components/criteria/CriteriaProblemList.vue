<script setup>
import { toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCriteria } from '@/composables/useCriteria'
import { useCriteriaProblemLinks } from '@/composables/useCriteriaProblemLinks'

const props = defineProps({
  problems: { type: Array, required: true },
  versionId: { type: Number, required: true },
  title: { type: String, default: '' },
})

const emit = defineEmits(['navigate'])
const { t } = useI18n()
const { weight } = useCriteria()
const { linkFor } = useCriteriaProblemLinks(toRef(props, 'versionId'), toRef(props, 'problems'))

function describe(problem) {
  return t(`criteria.problems.${problem.problem}`, {
    code: problem.code ?? '',
    expected: problem.expectedWeight ? weight(problem.expectedWeight) : '',
    actual: problem.actualWeight ? weight(problem.actualWeight) : '',
  })
}

function fixLabel(problem) {
  return problem.code
    ? t('criteria.problems.fix', { code: problem.code })
    : t('criteria.problems.fixVersion')
}

function keyOf(problem, index) {
  return `${problem.nodeType}-${problem.nodeId ?? 'version'}-${problem.problem}-${index}`
}
</script>

<template>
  <section class="criteria-problems" role="alert">
    <header class="criteria-problems__head">
      <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
      <h3 class="criteria-problems__title">{{ title || t('criteria.problems.title') }}</h3>
    </header>
    <ul class="criteria-problems__list">
      <li
        v-for="(problem, index) in problems"
        :key="keyOf(problem, index)"
        class="criteria-problems__item"
      >
        <span v-if="problem.code" class="criteria-problems__code">{{ problem.code }}</span>
        <span class="criteria-problems__text">{{ describe(problem) }}</span>
        <RouterLink class="criteria-problems__fix" :to="linkFor(problem)" @click="emit('navigate')">
          {{ fixLabel(problem) }}
          <i class="fa-solid fa-arrow-right u-flip-in-rtl" aria-hidden="true"></i>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
