<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import ProfileCompletionPrompt from '@/components/entities/ProfileCompletionPrompt.vue'
import EntityIdentityHeader from './EntityIdentityHeader.vue'
import EntityLogoDialog from './EntityLogoDialog.vue'
import ProfileCompletenessCard from './ProfileCompletenessCard.vue'
import ProfileHistoryCard from './ProfileHistoryCard.vue'
import ProfileHistoryDrawer from './ProfileHistoryDrawer.vue'
import ProfileSectionCard from './ProfileSectionCard.vue'
import { useProfileEditor } from '@/composables/useProfileEditor'
import { useEntityLogo } from '@/composables/useEntityLogo'
import { firstMissingOf, sectionViewsOf } from '@/utils/entityProfile/sections'
import { ACCOUNT_STATUS_KEYS } from '@/constants/entityProfile'

const props = defineProps({
  profile: { type: Object, required: true },
  tier: { type: String, required: true },
  certificate: { type: Object, default: null },
  welcome: { type: Boolean, default: false },
  regionOptions: { type: Array, required: true },
  cityOptionsFor: { type: Function, required: true },
})

const emit = defineEmits(['refresh'])
const { t } = useI18n()
const editor = useProfileEditor()
const logo = useEntityLogo()
const logoOpen = ref(false)
const historyOpen = ref(false)
const historySection = ref('')

const canEdit = computed(() => props.profile.canEdit === true)
const sections = computed(() => sectionViewsOf(props.profile))
const sectionKeys = computed(() => sections.value.map((section) => section.key))
const readOnlyKey = computed(() =>
  props.profile.status === 'active'
    ? 'generic'
    : (ACCOUNT_STATUS_KEYS[props.profile.status] ?? 'generic'),
)

watch(canEdit, (editable) => {
  if (!editable) editor.cancel()
})

function openHistory(section = '') {
  historySection.value = section
  historyOpen.value = true
}

function completeProfile() {
  const target = firstMissingOf(sections.value)
  if (target) editor.open(target.section, target.field)
}

async function removeLogo() {
  logoOpen.value = false
  await logo.remove()
  if (logo.error.value) logoOpen.value = true
}

function fixSection(section) {
  editor.open(section.key, section.missingFields[0] ?? null)
}
</script>

<template>
  <section class="entity-profile" data-testid="entity-profile">
    <EntityIdentityHeader
      :profile="profile"
      :tier="tier"
      :certificate="certificate"
      :can-edit="canEdit"
      @change-logo="logoOpen = true"
      @logo-expired="logo.refreshExpiredLink"
    />
    <div class="entity-profile__body">
      <p
        v-if="!canEdit"
        class="entity-profile__banner"
        role="status"
        data-testid="profile-read-only"
      >
        <AppIcon name="lock" />
        <span>{{ t(`portal.profile.readOnly.${readOnlyKey}`) }}</span>
      </p>
      <ProfileCompletionPrompt
        v-if="welcome && canEdit && !profile.profileCompleted"
        welcome
        @complete="completeProfile"
      />
      <div class="entity-profile__layout">
        <aside class="entity-profile__aside" :aria-label="t('portal.profile.asideLabel')">
          <ProfileCompletenessCard
            :completion="profile.profileCompletion"
            :sections="sections"
            :can-edit="canEdit"
            @fix="fixSection"
            @complete="completeProfile"
          />
          <ProfileHistoryCard @open="openHistory()" />
        </aside>
        <div class="entity-profile__sections">
          <ProfileSectionCard
            v-for="section in sections"
            :key="section.key"
            :section="section"
            :profile="profile"
            :can-edit="canEdit"
            :editing="editor.active.value === section.key"
            :saved="editor.savedSection.value === section.key"
            :focus-field="editor.active.value === section.key ? editor.focusField.value : null"
            :region-options="regionOptions"
            :city-options-for="cityOptionsFor"
            @edit="editor.open(section.key, $event)"
            @cancel="editor.cancel"
            @saved="editor.markSaved(section.key)"
            @dirty="editor.setDirty"
            @history="openHistory(section.key)"
            @refresh="emit('refresh')"
          />
        </div>
      </div>
    </div>
    <ProfileHistoryDrawer
      v-model="historyOpen"
      :section="historySection"
      :sections="sectionKeys"
      :entity-user-name="profile.authorizedPersonName ?? ''"
    />
    <EntityLogoDialog
      v-model="logoOpen"
      :logo="logo.logo.value"
      :busy="logo.busy.value"
      :error="logo.error.value"
      @upload="logo.upload"
      @remove="removeLogo"
    />
  </section>
</template>
