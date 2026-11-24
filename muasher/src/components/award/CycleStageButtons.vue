<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useCycleStage } from '@/composables/useCycleStage'
import { CYCLE_CLOSABLE_STATUSES, CYCLE_STATUSES } from '@/constants/advanced'

// The stage actions beside «تعديل الدورة» in the cycle header. Closing actions read as danger; each
// still asks for confirmation before it runs.
const props = defineProps({
  cycle: { type: Object, required: true },
  busy: { type: String, default: '' },
  openBlock: { type: Object, default: null },
  canReopen: { type: Boolean, default: false },
})

const emit = defineEmits(['run', 'reopen'])
const { t } = useI18n()
const { status, manage, blockedOpen, needsAssessors } = useCycleStage(props)
</script>

<template>
  <div class="cycle-actions__buttons" role="group" :aria-label="t('cycles.actions.title')">
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
        variant="danger"
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
</template>
