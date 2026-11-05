<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePathGuide } from '@/composables/usePathGuide'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'

const props = defineProps({
  path: { type: String, required: true },
})

const { t } = useI18n()
const { status, intro, steps, afterSubmission, pathLabel, load } = usePathGuide(
  computed(() => props.path),
)

onMounted(load)
</script>

<template>
  <section class="path-guide-card" :aria-labelledby="`path-guide-${path}`">
    <h2 :id="`path-guide-${path}`" class="path-guide-card__title">
      {{ pathLabel || t(`portal.home.paths.${path}`) }}
    </h2>
    <Skeleton v-if="status === 'loading' || status === 'idle'" variant="text" :count="3" />
    <ErrorState v-else-if="status === 'error'" @retry="load" />
    <template v-else>
      <p v-if="intro" class="path-guide-card__intro">{{ intro }}</p>
      <ol v-if="steps.length" class="path-guide-card__steps">
        <li v-for="step in steps" :key="step">{{ step }}</li>
      </ol>
      <p v-if="afterSubmission" class="path-guide-card__after">{{ afterSubmission }}</p>
      <p v-if="!intro && !steps.length" class="path-guide-card__empty">
        {{ t('portal.help.noGuide') }}
      </p>
    </template>
  </section>
</template>
