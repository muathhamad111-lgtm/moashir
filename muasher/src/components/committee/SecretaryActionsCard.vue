<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  flags: { type: Object, required: true },
  decides: { type: Boolean, default: false },
  recomputing: { type: Boolean, default: false },
  pendingSubstitutions: { type: Number, default: 0 },
})

const emit = defineEmits(['recompute', 'open'])
const { t } = useI18n()
</script>

<template>
  <Card class="filtering-actions" :title="t('award.secretary.actions.title')">
    <p v-if="!decides" class="cycle-actions__note">{{ t('award.secretary.readOnly') }}</p>
    <p v-else-if="pendingSubstitutions" class="cycle-actions__note">
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
        v-if="flags.recompute"
        variant="secondary"
        icon="calculator"
        :loading="recomputing"
        @click="emit('recompute')"
        >{{ t('award.secretary.actions.recompute') }}</BaseButton
      >
      <BaseButton
        v-if="flags.approve"
        variant="primary"
        icon="check-circle"
        @click="emit('open', 'approve')"
        >{{ t('award.secretary.actions.approve') }}</BaseButton
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
    <p class="cycle-actions__note">{{ t('award.secretary.averageNote') }}</p>
  </Card>
</template>
