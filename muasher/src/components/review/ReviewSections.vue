<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Tabs from '@/components/ui/Tabs.vue'
import ReviewRequestTab from './ReviewRequestTab.vue'

defineProps({
  requestId: { type: Number, required: true },
})

const SECTIONS = { ASSESSMENT: 'assessment', REQUEST: 'request', ENTITY: 'entity' }

const { t } = useI18n()
const section = ref(SECTIONS.ASSESSMENT)
const sections = computed(() =>
  Object.values(SECTIONS).map((value) => ({ value, label: t(`review.sections.${value}`) })),
)
</script>

<template>
  <Tabs v-model="section" class="review-sections" :tabs="sections">
    <!-- The assessment stays mounted so unsaved scores survive a tab switch. -->
    <div v-show="section === SECTIONS.ASSESSMENT" class="review-sections__panel">
      <slot name="assessment" />
    </div>
    <div v-if="section === SECTIONS.REQUEST" class="review-sections__panel">
      <ReviewRequestTab :request-id="requestId" />
    </div>
    <div v-if="section === SECTIONS.ENTITY" class="review-sections__panel">
      <slot name="entity" />
    </div>
  </Tabs>
</template>
