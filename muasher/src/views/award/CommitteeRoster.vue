<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import AwardPageHeader from '@/components/committee/AwardPageHeader.vue'
import CommitteeMemberTable from '@/components/committee/CommitteeMemberTable.vue'
import CommitteeCandidateDialog from '@/components/committee/CommitteeCandidateDialog.vue'
import { useCommitteeRoster } from '@/composables/useCommitteeRoster'
import { useRosterActions } from '@/composables/useRosterActions'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const route = useRoute()
const cycleId = computed(() => Number(route.params.id))
const roster = useCommitteeRoster(cycleId)
const { cycle, status, errorMessage, members, canManage } = roster
const actions = useRosterActions({ cycleId, roster })
const appointOpen = ref(false)

useHead({ title: () => t('award.roster.title') })

const substituteOpen = computed({
  get: () => actions.substituting.value !== null,
  set: (open) => {
    if (!open) actions.substituting.value = null
  },
})

function onAppointed(member) {
  roster.refresh('award.roster.toasts.appoint', { name: member.user.name })
}

function onSubstituted(member) {
  roster.refresh('award.roster.toasts.substitute', { name: member.user.name })
}
</script>

<template>
  <section class="cycles-page committee-roster">
    <AwardPageHeader
      :title="t('award.roster.title')"
      :cycle="cycle"
      :back="{ name: ROUTE_NAMES.AWARD_COMMITTEE_MANAGEMENT }"
      :back-label="t('award.common.backToCycles')"
    >
      <BaseButton v-if="canManage" variant="primary" icon="user" @click="appointOpen = true">{{
        t('award.roster.actions.appoint')
      }}</BaseButton>
    </AwardPageHeader>
    <AssessmentNotice
      variant="info"
      :title="t('award.roster.ruleTitle')"
      :message="t('award.roster.ruleMessage')"
    />
    <AssessmentNotice
      v-if="!canManage"
      variant="info"
      :title="t('award.roster.readOnlyTitle')"
      :message="t('award.roster.readOnlyMessage')"
    />
    <Card>
      <CommitteeMemberTable
        :members="members"
        :status="status"
        :error-message="errorMessage"
        :actions-for="canManage ? actions.actionsFor : null"
        @action="actions.start"
        @retry="roster.load"
      />
    </Card>
    <CommitteeCandidateDialog
      v-if="canManage"
      key="appoint"
      v-model="appointOpen"
      :cycle-id="cycleId"
      @done="onAppointed"
    />
    <CommitteeCandidateDialog
      v-if="actions.substituting.value"
      key="substitute"
      v-model="substituteOpen"
      :cycle-id="cycleId"
      :member="actions.substituting.value"
      @done="onSubstituted"
    />
    <ReasonDialog
      v-if="actions.reasonDialog.value"
      v-model="actions.reasonOpen.value"
      :title="actions.reasonDialog.value.title"
      :subtitle="actions.reasonDialog.value.subtitle"
      :destructive="actions.reasonDialog.value.destructive"
      :submit-label="actions.reasonDialog.value.submitLabel"
      :optional="actions.reasonDialog.value.optional"
      :action="actions.runWithReason"
      action-name="committee.roster.member"
    />
  </section>
</template>
