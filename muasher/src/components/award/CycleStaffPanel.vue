<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import SearchSelect from '@/components/form/SearchSelect.vue'
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
  <Card class="cycle-staff" :title="t('cycles.staff.title')">
    <p class="cycle-staff__hint">{{ t('cycles.staff.segregation') }}</p>
    <section class="cycle-staff__group" :aria-label="t('cycles.staff.members.title')">
      <h3 class="cycle-staff__subtitle">{{ t('cycles.staff.members.title') }}</h3>
      <SearchSelect
        v-for="role in MEMBER_FIELDS"
        :key="role"
        multiple
        :label="t(`cycles.staff.roles.${role}`)"
        :placeholder="t('cycles.staff.choose')"
        :options="staff.optionsFor(role)"
        :model-value="staff.selection.value[role]"
        :loading="staff.loading.value"
        :disabled="!editable"
        @update:model-value="staff.select(role, $event)"
      />
      <BaseButton v-if="editable" variant="secondary" icon="fa-solid fa-user-check" :loading="staff.saving.value === 'members'" @click="staff.saveMembers">
        {{ t('cycles.staff.members.save') }}
      </BaseButton>
    </section>
    <section class="cycle-staff__group" :aria-label="t('cycles.staff.assessors.title')">
      <h3 class="cycle-staff__subtitle">{{ t('cycles.staff.assessors.title') }}</h3>
      <SearchSelect
        multiple
        :label="t(`cycles.staff.roles.${CANDIDATE_ROLES.INITIAL_ASSESSOR}`)"
        :placeholder="t('cycles.staff.choose')"
        :options="staff.optionsFor(CANDIDATE_ROLES.INITIAL_ASSESSOR)"
        :model-value="staff.selection.value[CANDIDATE_ROLES.INITIAL_ASSESSOR]"
        :loading="staff.loading.value"
        :disabled="!editable"
        @update:model-value="staff.select(CANDIDATE_ROLES.INITIAL_ASSESSOR, $event)"
      />
      <BaseButton v-if="editable" variant="secondary" icon="fa-solid fa-user-check" :loading="staff.saving.value === 'assessors'" @click="staff.saveInitialAssessors">
        {{ t('cycles.staff.assessors.save') }}
      </BaseButton>
    </section>
  </Card>
</template>
