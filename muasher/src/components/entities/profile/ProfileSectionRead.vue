<script setup>
import { useI18n } from 'vue-i18n'
import ProfileFieldCell from './ProfileFieldCell.vue'
import PhoneField from './PhoneField.vue'
import OfficialEmailField from './OfficialEmailField.vue'
import { useProfileFieldDisplay } from '@/composables/useProfileFieldDisplay'
import { fieldLabelKey } from '@/utils/entityProfile/sections'

const SPECIAL_CELLS = Object.freeze({ phone: PhoneField, officialEmail: OfficialEmailField })

defineProps({
  section: { type: Object, required: true },
  profile: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['add', 'refresh'])
const { t } = useI18n()
const display = useProfileFieldDisplay()
</script>

<template>
  <dl class="profile-section__grid">
    <template v-for="field in section.fields" :key="field.name">
      <component
        :is="SPECIAL_CELLS[field.name]"
        v-if="SPECIAL_CELLS[field.name]"
        :field="field"
        :profile="profile"
        :can-edit="canEdit"
        @add="emit('add', field.name)"
        @verified="emit('refresh')"
        @changed="emit('refresh')"
      />
      <ProfileFieldCell
        v-else
        :label="t(fieldLabelKey(field.name))"
        :value="display.valueOf(profile, field.name)"
        :ltr="display.isLtr(field.name)"
        :missing="field.missing"
        :can-add="canEdit"
        :data-field="field.name"
        @add="emit('add', field.name)"
      />
    </template>
  </dl>
</template>
