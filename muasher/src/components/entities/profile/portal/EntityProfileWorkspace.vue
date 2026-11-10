<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import PortalAreaHeader from '@/components/portal/PortalAreaHeader.vue'
import ProfileCompletionPrompt from '@/components/entities/ProfileCompletionPrompt.vue'
import OfficialEmailCard from '@/components/entities/profile/OfficialEmailCard.vue'
import ProfileChangeHistory from '@/components/entities/ProfileChangeHistory.vue'
import ProfileBasicFields from './ProfileBasicFields.vue'
import ProfileContactFields from './ProfileContactFields.vue'
import ProfileOwnerFields from './ProfileOwnerFields.vue'
import ProfileCompletenessCard from './ProfileCompletenessCard.vue'
import EntityLogoCard from './EntityLogoCard.vue'
import PhoneVerificationCard from './PhoneVerificationCard.vue'
import { useEntityProfileForm } from '@/composables/useEntityProfileForm'
import { useUnsavedGuard } from '@/composables/useUnsavedGuard'
import { usePortalTab } from '@/composables/usePortalTab'
import { profileChangesService } from '@/services/profileChangesService'
import { localizedText } from '@/utils/localizedText'
import { PROFILE_TABS } from '@/constants/entityProfile'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const PANEL_ID = 'profile-panel'
const TAB_ICONS = {
  [PROFILE_TABS.BASIC]: 'building',
  [PROFILE_TABS.CONTACT]: 'envelope',
  [PROFILE_TABS.OWNER]: 'user-circle',
}

const props = defineProps({
  profile: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
  welcome: { type: Boolean, default: false },
  regionOptions: { type: Array, required: true },
  cityOptionsFor: { type: Function, required: true },
})

const emit = defineEmits(['refresh'])
const { t, locale } = useI18n()
const { tab } = usePortalTab(Object.values(PROFILE_TABS))
const { form, isDirty, dirtyFields, reset } = useEntityProfileForm(tab)
const { allowLeaving } = useUnsavedGuard(isDirty, { onDiscard: reset })

const entityName = computed(() => localizedText(props.profile.name, locale.value))
const breadcrumbs = computed(() => [
  {
    key: 'journey',
    label: t('portal.breadcrumb.journey'),
    to: { name: ROUTE_NAMES.APP_DASHBOARD },
  },
  { key: 'profile', label: t('portal.profile.eyebrowShort') },
])
const tabs = computed(() =>
  Object.values(PROFILE_TABS).map((value) => ({ value, label: t(`portal.profile.tabs.${value}`) })),
)
const canSave = computed(() => props.canEdit && dirtyFields.value.length > 0)
const phoneDirty = computed(() => dirtyFields.value.includes('phone'))
const loadHistory = (params) => profileChangesService.listMine(params)
const historyKey = ref(0)

watch(
  () => props.profile,
  () => {
    historyKey.value += 1
  },
)

async function switchTab(value) {
  if (value === tab.value) return
  if (await allowLeaving()) tab.value = value
}
</script>

<template>
  <section class="portal-area entity-profile-hub">
    <PortalAreaHeader
      :breadcrumbs="breadcrumbs"
      :eyebrow="t('portal.profile.eyebrowShort')"
      :title="t('portal.profile.title', { name: entityName })"
      :subtitle="t('portal.profile.subtitle')"
      :tabs="tabs"
      :tab="tab"
      :tabs-label="t('portal.profile.tabsLabel')"
      :panel-id="PANEL_ID"
      @update:tab="switchTab"
    >
      <template v-if="canEdit" #actions>
        <BaseButton
          variant="primary"
          icon="check-circle"
          :loading="form.submitting.value"
          :disabled="!canSave"
          @click="form.onSubmit"
        >
          {{ t('portal.profile.save') }}
        </BaseButton>
      </template>
    </PortalAreaHeader>

    <div class="portal-area__body portal-area__body--aside">
      <div class="portal-area__panel">
        <ProfileCompletionPrompt
          v-if="welcome && !profile.profileCompleted"
          :welcome="true"
          @complete="switchTab(PROFILE_TABS.BASIC)"
        />
        <form
          :id="PANEL_ID"
          class="portal-surface profile-form"
          role="tabpanel"
          :aria-labelledby="`${PANEL_ID}-tab-${tab}`"
          novalidate
          @submit.prevent="form.onSubmit"
        >
          <header class="profile-form__head">
            <span class="profile-form__icon" aria-hidden="true"
              ><AppIcon :name="TAB_ICONS[tab]"
            /></span>
            <div>
              <h2 class="portal-surface__title">{{ t(`portal.profile.tabs.${tab}`) }}</h2>
              <p class="portal-surface__text">{{ t(`portal.profile.captions.${tab}`) }}</p>
            </div>
          </header>
          <template v-if="tab === PROFILE_TABS.CONTACT">
            <OfficialEmailCard :profile="profile" @changed="emit('refresh')" />
            <PhoneVerificationCard
              :profile="profile"
              :can-edit="canEdit"
              :phone-dirty="phoneDirty"
              @verified="emit('refresh')"
            />
          </template>
          <fieldset class="profile-form__fields" :disabled="!canEdit || form.submitting.value">
            <ProfileBasicFields
              v-if="tab === PROFILE_TABS.BASIC"
              :region-options="regionOptions"
              :city-options-for="cityOptionsFor"
            />
            <ProfileContactFields v-else-if="tab === PROFILE_TABS.CONTACT" />
            <ProfileOwnerFields v-else :account-email="profile.officialEmail ?? ''" />
          </fieldset>
          <p v-if="isDirty" class="profile-form__dirty" role="status">
            {{ t('portal.profile.unsaved') }}
          </p>
          <BaseButton
            v-if="canEdit"
            class="profile-form__submit"
            type="submit"
            variant="primary"
            icon="check-circle"
            :loading="form.submitting.value"
            :disabled="!canSave"
          >
            {{ t('portal.profile.save') }}
          </BaseButton>
        </form>
      </div>
      <aside class="portal-area__aside" :aria-label="t('portal.profile.asideLabel')">
        <ProfileCompletenessCard :completion="profile.profileCompletion" />
        <EntityLogoCard :can-edit="canEdit" />
      </aside>
      <ProfileChangeHistory
        :key="historyKey"
        class="entity-profile-hub__history"
        :loader="loadHistory"
      />
    </div>
  </section>
</template>
