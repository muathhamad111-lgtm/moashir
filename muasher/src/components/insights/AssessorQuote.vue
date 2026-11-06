<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  kind: {
    type: String,
    required: true,
    validator: (value) => ['strengths', 'improvements'].includes(value),
  },
  text: { type: String, required: true },
})

const { t } = useI18n()
</script>

<template>
  <figure class="assessor-quote" :class="`assessor-quote--${kind}`">
    <figcaption class="assessor-quote__label">
      <AppIcon name="chat" />
      {{ t(`insights.assessor.${kind}`) }}
    </figcaption>
    <blockquote class="assessor-quote__text" dir="auto" v-text="text"></blockquote>
  </figure>
</template>
