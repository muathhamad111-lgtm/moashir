<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { formatDate } from '@/utils/datetime'
import { CRITERIA_VERSION_STATUSES as STATUS } from '@/constants/criteria'
import { SCALE_KINDS } from '@/constants/assessment'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  version: { type: Object, required: true },
  openDraft: { type: Object, default: null },
})

const { t, locale } = useI18n()

const VARIANTS = {
  [STATUS.DRAFT]: { tone: 'info', icon: 'fa-solid fa-pen-ruler' },
  [STATUS.PUBLISHED]: { tone: 'success', icon: 'fa-solid fa-lock' },
  [STATUS.ARCHIVED]: { tone: 'muted', icon: 'fa-solid fa-box-archive' },
}

const SCALE_LABELS = {
  [SCALE_KINDS.OF_10]: 'criteria.scale.of10',
  [SCALE_KINDS.RELATIVE]: 'criteria.scale.relative',
}

const variant = computed(() => VARIANTS[props.version.status] ?? VARIANTS[STATUS.ARCHIVED])
const number = computed(() => props.version.versionNumber)
const title = computed(() =>
  t(`criteria.version.${props.version.status}Title`, { number: number.value }),
)
const message = computed(() => t(`criteria.version.${props.version.status}Message`))
const showDraftNote = computed(
  () => Boolean(props.openDraft) && props.openDraft.id !== props.version.id,
)

function dateOf(value) {
  return formatDate(value, locale.value, { dateStyle: 'medium', timeZone: 'Asia/Riyadh' })
}

const facts = computed(() => {
  const items = []
  if (props.version.publishedAt)
    items.push(t('criteria.version.publishedAt', { date: dateOf(props.version.publishedAt) }))
  if (props.version.archivedAt)
    items.push(t('criteria.version.archivedAt', { date: dateOf(props.version.archivedAt) }))
  if (props.version.scaleKind)
    items.push(t('criteria.version.scale', { scale: t(SCALE_LABELS[props.version.scaleKind]) }))
  return items
})
</script>

<template>
  <section
    class="criteria-version"
    :class="`criteria-version--${variant.tone}`"
    :aria-labelledby="`criteria-version-${version.id}`"
  >
    <span class="criteria-version__icon" aria-hidden="true"><i :class="variant.icon"></i></span>
    <div class="criteria-version__body">
      <div class="criteria-version__head">
        <h2 :id="`criteria-version-${version.id}`" class="criteria-version__title">{{ title }}</h2>
        <StatusBadge :status="version.status" />
      </div>
      <p class="criteria-version__message">{{ message }}</p>
      <ul v-if="facts.length" class="criteria-version__facts">
        <li v-for="fact in facts" :key="fact">{{ fact }}</li>
      </ul>
      <p v-if="showDraftNote" class="criteria-version__draft-note">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        {{ t('criteria.version.draftOpenNote', { number: openDraft.versionNumber }) }}
        <RouterLink
          class="criteria-version__link"
          :to="{ name: ROUTE_NAMES.ADMIN_CRITERIA, query: { version: openDraft.id } }"
        >
          {{ t('criteria.version.openDraft') }}
        </RouterLink>
      </p>
    </div>
    <div v-if="$slots.actions" class="criteria-version__actions">
      <slot name="actions" />
    </div>
  </section>
</template>
