<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import CriteriaRebindDetails from './CriteriaRebindDetails.vue'
import { useDismissedNotice } from '@/composables/useDismissedNotice'
import { usePermissions } from '@/composables/usePermissions'
import { accessFor } from '@/constants/routeAccess'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatDate } from '@/utils/datetime'

// A move to a new criteria version, as one compact line (design §B.3.6, AS-10): the version, the
// date and how many items need a new score. «عرض التحديثات» unfolds the full list; staff who can
// read the criteria also get the version's «ما الذي تغيّر» page. A viewer may hide the line; a
// small chip keeps the counts and brings it back.
const props = defineProps({
  update: { type: Object, required: true },
  assessmentId: { type: Number, default: null },
  context: {
    type: String,
    default: 'self',
    validator: (value) => ['self', 'request', 'review'].includes(value),
  },
})

const TITLE_KEYS = {
  self: 'assessment.self.rebind.title',
  request: 'assessment.self.rebind.titleRequest',
  review: 'assessment.self.rebind.titleReview',
}

const { t, locale } = useI18n()
const { canAccess } = usePermissions()
const detailsId = useId()
const open = ref(false)
const noticeKey = computed(
  () =>
    `criteria-move.${props.assessmentId ?? props.context}.${props.update.toVersionId ?? props.update.toVersionNumber}.${props.update.movedAt ?? ''}`,
)
const { dismissed, dismiss, restore } = useDismissedNotice(noticeKey)

const resetCount = computed(() => props.update.reset.length)
const title = computed(() =>
  t(TITLE_KEYS[props.context], {
    from: props.update.fromVersionNumber,
    to: props.update.toVersionNumber,
  }),
)
const movedOn = computed(() =>
  props.update.movedAt
    ? formatDate(props.update.movedAt, locale.value, {
        dateStyle: 'medium',
        timeZone: 'Asia/Riyadh',
      })
    : '',
)
const triggersKey = computed(() => (props.context === 'self' ? 'triggers' : 'triggersRequest'))
const cause = computed(() =>
  t(`assessment.self.rebind.${triggersKey.value}.${props.update.trigger}`, {
    date: movedOn.value,
    scope: t(`assessment.self.rebind.scopes.${props.update.scope}`),
  }),
)
const countLine = computed(() =>
  t('assessment.self.rebind.reset', { count: resetCount.value }, resetCount.value),
)
const changesRoute = computed(() => {
  const versionId = props.update.toVersionId
  if (props.context === 'self' || !versionId) return null
  if (!canAccess(accessFor(ROUTE_NAMES.ADMIN_CRITERIA_VERSION_CHANGES))) return null
  return { name: ROUTE_NAMES.ADMIN_CRITERIA_VERSION_CHANGES, params: { versionId } }
})
</script>

<template>
  <button
    v-if="dismissed"
    type="button"
    class="rebind-chip"
    :aria-label="t('assessment.self.rebind.showAgain')"
    @click="restore"
  >
    <AppIcon name="info-circle" />
    <span>{{
      t('assessment.self.rebind.chip', { to: update.toVersionNumber, count: resetCount })
    }}</span>
  </button>
  <AssessmentNotice
    v-else
    variant="info"
    class="assessment-notice--compact rebind-notice"
    :title="title"
    :message="countLine"
  >
    <CriteriaRebindDetails v-if="open" :id="detailsId" :update="update" :cause="cause" />
    <template #action>
      <BaseButton
        variant="secondary"
        size="sm"
        :icon-end="open ? 'chevron-up' : 'chevron-down'"
        :aria-expanded="String(open)"
        :aria-controls="detailsId"
        @click="open = !open"
      >
        {{
          open ? t('assessment.self.rebind.hideUpdates') : t('assessment.self.rebind.showUpdates')
        }}
      </BaseButton>
      <BaseButton v-if="changesRoute" variant="ghost" size="sm" :to="changesRoute">
        {{ t('assessment.self.rebind.whatChanged') }}
      </BaseButton>
      <BaseButton
        variant="ghost"
        size="sm"
        icon="x"
        icon-only
        :aria-label="t('assessment.self.rebind.dismiss')"
        @click="dismiss"
      />
    </template>
  </AssessmentNotice>
</template>
