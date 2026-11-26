<script setup>
import { useI18n } from 'vue-i18n'
import AwardStateBadge from '@/components/committee/AwardStateBadge.vue'
import { localizedText } from '@/utils/localizedText'
import { formatRiyadhDate } from '@/utils/datetime'

// The cycle committees the user sits on: a cycle appointment, not a permanent role.
defineProps({
  appointments: { type: Array, required: true },
})

const { t, locale } = useI18n()
</script>

<template>
  <ul class="committee-seats" :aria-label="t('account.roles.committeeSeats')">
    <li v-for="seat in appointments" :key="seat.id" class="committee-seats__item">
      <span class="committee-seats__role">{{
        t(seat.isSubstitute ? 'account.roles.substituteSeat' : 'account.roles.committeeSeat')
      }}</span>
      <span class="committee-seats__cycle">{{ localizedText(seat.cycleName, locale) }}</span>
      <i18n-t keypath="account.roles.committeePeriod" tag="span" class="committee-seats__period">
        <template #start>
          <bdi class="committee-seats__date">{{
            formatRiyadhDate(seat.committeeStart, locale)
          }}</bdi>
        </template>
        <template #end>
          <bdi class="committee-seats__date">{{ formatRiyadhDate(seat.committeeEnd, locale) }}</bdi>
        </template>
      </i18n-t>
      <AwardStateBadge kind="seat" :status="seat.status" />
    </li>
  </ul>
</template>
