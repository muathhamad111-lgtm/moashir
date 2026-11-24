<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import CycleBlockedRequests from './CycleBlockedRequests.vue'
import { CYCLE_CLOSABLE_STATUSES, CYCLE_STATUSES, AWARD_CODES } from '@/constants/advanced'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  cycle: { type: Object, required: true },
  busy: { type: String, default: '' },
  openBlock: { type: Object, default: null },
  blocked: { type: Object, default: null },
  canReopen: { type: Boolean, default: false },
})

const emit = defineEmits(['run', 'reopen'])
const { t, locale } = useI18n()

const status = computed(() => props.cycle.status)
const manage = computed(() => props.cycle.canManage)
const blockedOpen = computed(
  () => status.value === CYCLE_STATUSES.DRAFT && Boolean(props.openBlock?.cycle),
)
const blockedOpenMessage = computed(() => {
  if (!blockedOpen.value) return ''
  const key =
    props.openBlock.code === AWARD_CODES.ANOTHER_OPEN_FOR_SUBMISSION
      ? 'cycles.actions.blockedOpen.staggeredMessage'
      : 'cycles.actions.blockedOpen.message'
  const blocking = props.openBlock.cycle
  return t(key, { name: localizedText(blocking.name, locale.value), year: blocking.year })
})
const blockedRequests = computed(() =>
  props.blocked?.code === AWARD_CODES.REQUESTS_PENDING ? props.blocked.requests : [],
)
const anotherOpen = computed(() => props.blocked?.code === AWARD_CODES.ANOTHER_OPEN)
const anotherOpenForSubmission = computed(
  () => props.blocked?.code === AWARD_CODES.ANOTHER_OPEN_FOR_SUBMISSION,
)
// A cycle opens only with a linked initial assessor, so its requests can be assigned (AJ-28). A cycle opened
// before that rule, or whose links were later removed, is flagged too: its requests cannot be assigned.
const ASSIGNING_STATUSES = [CYCLE_STATUSES.SUBMISSION_OPEN, CYCLE_STATUSES.SUBMISSION_CLOSED]
const lacksAssessors = computed(
  () =>
    props.cycle.revealsStaff === true &&
    !(props.cycle.initialAssessors ?? []).some((link) => link.isActive),
)
const needsAssessors = computed(() => status.value === CYCLE_STATUSES.DRAFT && lacksAssessors.value)
const assigningBlocked = computed(
  () => ASSIGNING_STATUSES.includes(status.value) && lacksAssessors.value,
)
</script>

<template>
  <section class="cycle-actions" :aria-label="t('cycles.actions.title')">
    <div class="cycle-actions__buttons">
      <template v-if="manage">
        <BaseButton
          v-if="status === CYCLE_STATUSES.DRAFT"
          variant="primary"
          icon="door-open"
          :loading="busy === 'open'"
          :disabled="blockedOpen || needsAssessors"
          @click="emit('run', 'open')"
        >
          {{ t('cycles.actions.open.label') }}
        </BaseButton>
        <BaseButton
          v-if="status === CYCLE_STATUSES.SUBMISSION_OPEN"
          variant="secondary"
          icon="lock"
          :loading="busy === 'closeSubmissions'"
          @click="emit('run', 'closeSubmissions')"
        >
          {{ t('cycles.actions.closeSubmissions.label') }}
        </BaseButton>
        <BaseButton
          v-if="status === CYCLE_STATUSES.SUBMISSION_CLOSED"
          variant="primary"
          icon="users-two"
          :loading="busy === 'startCommittee'"
          @click="emit('run', 'startCommittee')"
        >
          {{ t('cycles.actions.startCommittee.label') }}
        </BaseButton>
        <BaseButton
          v-if="CYCLE_CLOSABLE_STATUSES.includes(status)"
          variant="danger"
          icon="docs"
          :loading="busy === 'close'"
          @click="emit('run', 'close')"
        >
          {{ t('cycles.actions.close.label') }}
        </BaseButton>
      </template>
      <BaseButton
        v-if="canReopen && status === CYCLE_STATUSES.CLOSED"
        variant="secondary"
        icon="undo"
        :loading="busy === 'reopen'"
        @click="emit('reopen')"
      >
        {{ t('cycles.actions.reopen.label') }}
      </BaseButton>
    </div>
    <AssessmentNotice
      v-if="blockedOpen"
      variant="warning"
      :title="t('cycles.actions.blockedOpen.title')"
      :message="blockedOpenMessage"
    />
    <AssessmentNotice
      v-if="anotherOpen"
      variant="danger"
      live
      :title="t('cycles.actions.anotherOpen.title')"
      :message="t('cycles.actions.anotherOpen.message')"
    />
    <AssessmentNotice
      v-if="anotherOpenForSubmission"
      variant="danger"
      live
      :title="t('cycles.actions.anotherOpen.staggeredTitle')"
      :message="t('cycles.actions.anotherOpen.staggeredMessage')"
    />
    <AssessmentNotice
      v-if="needsAssessors"
      variant="warning"
      :title="t('cycles.actions.needsAssessors.title')"
      :message="t('cycles.actions.needsAssessors.message')"
    />
    <AssessmentNotice
      v-if="assigningBlocked"
      variant="danger"
      :title="t('cycles.actions.assigningBlocked.title')"
      :message="t('cycles.actions.assigningBlocked.message')"
    />
    <CycleBlockedRequests v-if="blockedRequests.length" :requests="blockedRequests" />
    <p v-if="!manage" class="cycle-actions__note">{{ t('cycles.actions.readOnly') }}</p>
  </section>
</template>
