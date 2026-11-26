<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import NdaStatusCell from '@/components/nda/NdaStatusCell.vue'

// One optional detail column of the users table (USER_DETAIL_COLUMNS); a missing value stays empty.
const props = defineProps({
  field: { type: String, required: true },
  user: { type: Object, required: true },
  ndaVersion: { type: String, default: null },
})

const { t } = useI18n()

const DATE_FIELDS = ['lastLoginAt', 'createdAt']

const date = computed(() => (DATE_FIELDS.includes(props.field) ? props.user[props.field] : null))
// The API sends out_of_scope; the agreement labels use out-of-scope.
const ndaStatus = computed(() => props.user.ndaStatus?.replaceAll('_', '-') ?? null)
const qualifications = computed(() =>
  (props.user.assessorQualifications ?? [])
    .map((qualification) => t(`users.pathAccess.qualifications.${qualification}`))
    .join(t('common.listSeparator')),
)
</script>

<template>
  <DateStamp v-if="date" :value="date" :with-time="false" />
  <Badge v-else-if="field === 'mfa'" :variant="user.mfaEnabled ? 'success' : 'muted'">{{
    t(user.mfaEnabled ? 'users.columns.mfaOn' : 'users.columns.mfaOff')
  }}</Badge>
  <NdaStatusCell v-else-if="field === 'nda'" :status="ndaStatus" :version="ndaVersion" />
  <template v-else-if="field === 'qualifications'">{{ qualifications }}</template>
</template>
