<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import AwardStateBadge from './AwardStateBadge.vue'
import DiscussionEntryList from './DiscussionEntryList.vue'
import DiscussionEntryForm from './DiscussionEntryForm.vue'
import { DISCUSSION_MODES } from '@/constants/committee'
import { committeeEntityLabel } from '@/utils/award/committeeEntity'

defineProps({
  discussion: { type: Object, required: true },
  respond: { type: Function, required: true },
})

const { t, locale } = useI18n()
</script>

<template>
  <Card class="discussion-card">
    <template #header>
      <h2 class="discussion-card__title">
        <bdi :class="{ 'u-ltr': discussion.entity?.code }">{{
          committeeEntityLabel(discussion.entity, locale)
        }}</bdi>
      </h2>
      <span class="discussion-card__badges">
        <Badge variant="info">{{ t(`award.discussion.modes.${discussion.mode}`) }}</Badge>
        <AwardStateBadge kind="discussion" :status="discussion.status" />
      </span>
    </template>
    <p v-if="discussion.deadlineAt" class="discussion-card__deadline">
      {{ t('award.discussion.deadline') }} <DateStamp :value="discussion.deadlineAt" />
    </p>
    <div
      v-if="discussion.mode === DISCUSSION_MODES.AGREED_OUTCOME"
      class="discussion-card__outcome"
    >
      <p class="discussion-card__label">{{ t('award.discussion.agreedOutcome') }}</p>
      <p class="discussion-card__text">{{ discussion.agreedOutcome }}</p>
    </div>
    <DiscussionEntryList :entries="discussion.entries" :anonymous="true" />
    <DiscussionEntryForm
      v-if="discussion.acceptsEntries"
      :discussion="discussion"
      :respond="respond"
    />
    <p v-else class="discussion-card__closed">{{ t('award.discussion.noEntries') }}</p>
  </Card>
</template>
