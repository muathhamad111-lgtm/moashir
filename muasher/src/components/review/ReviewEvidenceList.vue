<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import ReviewEvidenceCards from './ReviewEvidenceCards.vue'
import { formatNumber } from '@/utils/formatNumber'
import { localizedText } from '@/utils/localizedText'

// groups: [{ key, point, slots }] as slotGroupsOf gives them — point null for the criterion's own
// attachments, else the indicator they belong to. lateKeys: required attachments that came after
// the submission (D-5).
const props = defineProps({
  files: { type: Array, required: true },
  downloading: { type: Object, required: true },
  groups: { type: Array, default: () => [] },
  lateKeys: { type: Array, default: () => [] },
})

const emit = defineEmits(['download'])
const { t, n, locale } = useI18n()

function slotOf(slot) {
  return {
    key: slot.key,
    name: localizedText(slot.name, locale.value),
    required: slot.required === true,
    late: props.lateKeys.includes(slot.key),
    files: props.files.filter((file) => file.attachmentKey === slot.key),
  }
}

const sections = computed(() => {
  const withSlots = props.groups.filter((group) => group.slots?.length)
  if (!withSlots.length) return null
  const keys = new Set(withSlots.flatMap((group) => group.slots.map((slot) => slot.key)))
  const others = props.files.filter((file) => !keys.has(file.attachmentKey))
  const bySlot = withSlots
    .map((group) => ({
      key: group.key,
      title: group.point ? localizedText(group.point.description, locale.value) : '',
      slots: group.slots.map(slotOf).filter((slot) => slot.files.length || slot.required),
    }))
    .filter((section) => section.slots.length)
  const other = { key: '', name: t('review.evidenceOther'), required: false, files: others }
  const all = others.length ? [...bySlot, { key: 'other', title: '', slots: [other] }] : bySlot
  return all.length ? all : null
})
</script>

<!-- Evidence as small download cards: the file name, its type and size, one clear download button. -->
<template>
  <div class="review-evidence">
    <p class="review-evidence__title">
      {{ t('review.evidenceTitle', { count: formatNumber(n, files.length) }) }}
    </p>
    <template v-if="sections">
      <template v-for="section in sections" :key="section.key">
        <p v-if="section.title" class="review-evidence__indicator">
          {{ t('review.evidenceIndicator', { name: section.title }) }}
        </p>
        <section v-for="item in section.slots" :key="item.key" class="review-evidence__group">
          <p class="review-evidence__slot">
            <span>{{ item.name }}</span>
            <Badge v-if="item.required" variant="info">{{ t('review.evidenceRequired') }}</Badge>
            <Badge v-if="item.late" variant="warning">{{ t('review.evidenceLate') }}</Badge>
          </p>
          <ReviewEvidenceCards
            v-if="item.files.length"
            :files="item.files"
            :downloading="downloading"
            @download="emit('download', $event)"
          />
          <p v-else class="review-evidence__empty">{{ t('review.evidenceMissing') }}</p>
        </section>
      </template>
    </template>
    <ReviewEvidenceCards
      v-else-if="files.length"
      :files="files"
      :downloading="downloading"
      @download="emit('download', $event)"
    />
    <p v-else class="review-evidence__empty">{{ t('review.noEvidence') }}</p>
  </div>
</template>
