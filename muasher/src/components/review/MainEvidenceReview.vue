<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ReviewEvidenceList from './ReviewEvidenceList.vue'
import { evidenceOfMain } from '@/utils/accredited/evidenceRules'

// «شواهد المعيار الرئيسي» for the assessor and the committee: the main criterion's own attachments.
const props = defineProps({
  main: { type: Object, required: true },
  evidence: { type: Array, required: true },
  downloading: { type: Object, required: true },
  lateKeys: { type: Array, default: () => [] },
})

const emit = defineEmits(['download'])
const { t } = useI18n()

const files = computed(() => evidenceOfMain(props.evidence, props.main.id))
const groups = computed(() => [{ key: 'own', point: null, slots: props.main.attachments }])
// Shown only when there is something to review: a file, or a required attachment to flag.
const isShown = computed(
  () => files.value.length > 0 || props.main.attachments.some((slot) => slot.required),
)
</script>

<template>
  <section
    v-if="isShown"
    class="review-main-evidence"
    :aria-labelledby="`review-main-evidence-${main.id}`"
  >
    <h3 :id="`review-main-evidence-${main.id}`" class="review-main-evidence__title">
      {{ t('review.mainEvidenceTitle') }}
    </h3>
    <ReviewEvidenceList
      :files="files"
      :groups="groups"
      :late-keys="lateKeys"
      :downloading="downloading"
      @download="emit('download', $event)"
    />
  </section>
</template>
