<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import AppCombobox from '@/components/form/AppCombobox.vue'
import CycleStepCounter from './CycleStepCounter.vue'
import { CANDIDATE_ROLES, MEMBER_ROLES } from '@/constants/advanced'

const props = defineProps({
  staff: { type: Object, required: true },
  editable: { type: Boolean, default: false },
})

const { t } = useI18n()
const MEMBER_FIELDS = [MEMBER_ROLES.FILTERING_OFFICER, MEMBER_ROLES.AWARD_SECRETARY]

onMounted(() => {
  if (props.editable) props.staff.loadCandidates()
})
</script>

<template>
  <Card class="cycle-staff">
    <template #header>
      {{ t('cycles.staff.title') }}
      <CycleStepCounter />
    </template>
    <p class="cycle-staff__hint">{{ t('cycles.staff.segregation') }}</p>
    <section class="cycle-staff__group" :aria-label="t('cycles.staff.members.title')">
      <h3 class="cycle-staff__subtitle">{{ t('cycles.staff.members.title') }}</h3>
      <AppCombobox
        v-for="role in MEMBER_FIELDS"
        :key="role"
        multiple
        :name="`staff-${role}`"
        :label="t(`cycles.staff.roles.${role}`)"
        :placeholder="t('cycles.staff.choose')"
        :options="staff.optionsFor(role)"
        :model-value="staff.selection.value[role]"
        :error="staff.errors.value[role] ?? ''"
        :loading="staff.loading.value"
        :disabled="!editable"
        @update:model-value="staff.select(role, $event)"
      />
    </section>
    <section class="cycle-staff__group" :aria-label="t('cycles.staff.assessors.title')">
      <h3 class="cycle-staff__subtitle">{{ t('cycles.staff.assessors.title') }}</h3>
      <AppCombobox
        multiple
        :name="`staff-${CANDIDATE_ROLES.INITIAL_ASSESSOR}`"
        :label="t(`cycles.staff.roles.${CANDIDATE_ROLES.INITIAL_ASSESSOR}`)"
        :placeholder="t('cycles.staff.choose')"
        :options="staff.optionsFor(CANDIDATE_ROLES.INITIAL_ASSESSOR)"
        :model-value="staff.selection.value[CANDIDATE_ROLES.INITIAL_ASSESSOR]"
        :error="staff.errors.value[CANDIDATE_ROLES.INITIAL_ASSESSOR] ?? ''"
        :loading="staff.loading.value"
        :disabled="!editable"
        @update:model-value="staff.select(CANDIDATE_ROLES.INITIAL_ASSESSOR, $event)"
      />
    </section>
  </Card>
</template>
