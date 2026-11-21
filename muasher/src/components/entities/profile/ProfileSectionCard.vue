<script setup>
import { nextTick, ref, useId, watch } from 'vue'
import ProfileSectionHeader from './ProfileSectionHeader.vue'
import ProfileSectionRead from './ProfileSectionRead.vue'
import ProfileSectionForm from './ProfileSectionForm.vue'
import ProfileSectionFooter from './ProfileSectionFooter.vue'

const props = defineProps({
  section: { type: Object, required: true },
  profile: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
  editing: { type: Boolean, default: false },
  saved: { type: Boolean, default: false },
  focusField: { type: String, default: null },
  regionOptions: { type: Array, default: () => [] },
  cityOptionsFor: { type: Function, required: true },
})

const emit = defineEmits(['edit', 'cancel', 'saved', 'dirty', 'history', 'refresh'])
const titleId = useId()
const root = ref(null)

watch(
  () => props.editing,
  (editing, wasEditing) => {
    if (editing || !wasEditing) return
    nextTick(() => root.value?.querySelector('.profile-section__edit')?.focus())
  },
)
</script>

<template>
  <section
    :id="`profile-section-${section.key}`"
    ref="root"
    class="profile-section"
    :class="{ 'profile-section--editing': editing }"
    :aria-labelledby="titleId"
    :data-section="section.key"
  >
    <ProfileSectionHeader
      :section="section"
      :title-id="titleId"
      :editing="editing"
      :can-edit="canEdit"
      @edit="emit('edit', null)"
    />
    <ProfileSectionForm
      v-if="editing"
      :section="section"
      :profile="profile"
      :focus-field="focusField"
      :region-options="regionOptions"
      :city-options-for="cityOptionsFor"
      @saved="emit('saved')"
      @cancel="emit('cancel')"
      @dirty="emit('dirty', $event)"
    />
    <template v-else>
      <ProfileSectionRead
        :section="section"
        :profile="profile"
        :can-edit="canEdit"
        @add="emit('edit', $event)"
        @refresh="emit('refresh')"
      />
      <ProfileSectionFooter :saved="saved" @history="emit('history')" />
    </template>
  </section>
</template>
