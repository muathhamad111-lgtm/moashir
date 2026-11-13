<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import ReviewSubPanel from './ReviewSubPanel.vue'
import { localizedText } from '@/utils/localizedText'

defineProps({
  main: { type: Object, required: true },
  workspace: { type: Object, required: true },
})

const emit = defineEmits(['download'])
const { t, locale } = useI18n()
</script>

<template>
  <Card class="review-main">
    <template #header>
      <h2 class="review-main__title">
        <span class="review-main__code u-ltr">{{ main.code }}</span>
        {{ localizedText(main.name, locale) }}
      </h2>
      <span class="review-main__weight">{{
        t('assessment.self.tree.mainMeta', { weight: main.weight })
      }}</span>
    </template>
    <ul class="review-main__subs">
      <ReviewSubPanel
        v-for="sub in main.subs"
        :key="sub.id"
        :sub="sub"
        :workspace="workspace"
        @download="emit('download', $event)"
      />
    </ul>
  </Card>
</template>
