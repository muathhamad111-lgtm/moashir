<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

// The secretary's decisions as a bar at the end of the page (sticky on desktop), only when one is possible.
// The buttons still come from the API flags; nothing renders for a reader (operator 2026-11-22).
const props = defineProps({
  flags: { type: Object, required: true },
  recomputing: { type: Boolean, default: false },
  pendingSubstitutions: { type: Number, default: 0 },
})

const emit = defineEmits(['recompute', 'open'])
const { t } = useI18n()
const hasAction = computed(() => Object.values(props.flags).some(Boolean))
</script>

<template>
  <div
    v-if="hasAction || pendingSubstitutions"
    class="filtering-actions award-action-bar review-actions--sticky"
    role="group"
    :aria-label="t('award.secretary.actions.title')"
  >
    <p v-if="pendingSubstitutions" class="cycle-actions__note">
      {{
        t(
          'award.secretary.substitutionFirst',
          { count: pendingSubstitutions },
          pendingSubstitutions,
        )
      }}
    </p>
    <div class="filtering-actions__buttons">
      <BaseButton
        v-if="flags.approve"
        variant="primary"
        icon="check-circle"
        @click="emit('open', 'approve')"
        >{{ t('award.secretary.actions.approve') }}</BaseButton
      >
      <BaseButton
        v-if="flags.recompute"
        variant="secondary"
        icon="calculator"
        :loading="recomputing"
        @click="emit('recompute')"
        >{{ t('award.secretary.actions.recompute') }}</BaseButton
      >
      <BaseButton
        v-if="flags.discussion"
        variant="secondary"
        icon="chat"
        @click="emit('open', 'openDiscussion')"
        >{{ t('award.secretary.actions.openDiscussion') }}</BaseButton
      >
      <BaseButton
        v-if="flags.returnToCommittee"
        variant="danger"
        icon="undo"
        @click="emit('open', 'returnToCommittee')"
        >{{ t('award.secretary.actions.returnToCommittee') }}</BaseButton
      >
    </div>
  </div>
</template>
