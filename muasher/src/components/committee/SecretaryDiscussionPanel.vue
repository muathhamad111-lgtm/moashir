<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import AwardStateBadge from './AwardStateBadge.vue'
import DiscussionEntryList from './DiscussionEntryList.vue'
import { DISCUSSION_STATUSES } from '@/constants/committee'

defineProps({
  discussions: { type: Array, required: true },
  canClose: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])
const { t } = useI18n()
</script>

<template>
  <div class="secretary-discussions">
    <p v-if="!discussions.length" class="filtering-decisions__empty">
      {{ t('award.secretary.noDiscussions') }}
    </p>
    <article
      v-for="discussion in discussions"
      :key="discussion.id"
      class="secretary-discussions__item"
    >
      <div class="filtering-decisions__head">
        <Badge variant="info">{{ t(`award.discussion.modes.${discussion.mode}`) }}</Badge>
        <AwardStateBadge kind="discussion" :status="discussion.status" />
        <DateStamp :value="discussion.openedAt" />
      </div>
      <p v-if="discussion.deadlineAt" class="filtering-decisions__meta">
        {{ t('award.discussion.deadline') }} <DateStamp :value="discussion.deadlineAt" />
      </p>
      <p v-if="discussion.agreedOutcome" class="filtering-decisions__note">
        {{ discussion.agreedOutcome }}
      </p>
      <DiscussionEntryList :entries="discussion.entries" />
      <p v-if="discussion.summary" class="filtering-decisions__note">
        {{ t('award.secretary.summary', { text: discussion.summary }) }}
      </p>
      <BaseButton
        v-if="canClose && discussion.status === DISCUSSION_STATUSES.OPEN"
        variant="secondary"
        size="sm"
        icon="lock"
        @click="emit('close', discussion)"
        >{{ t('award.secretary.actions.closeDiscussion') }}</BaseButton
      >
    </article>
  </div>
</template>
