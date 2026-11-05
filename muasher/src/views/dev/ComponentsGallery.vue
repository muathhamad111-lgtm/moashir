<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { object as yupObject, string as yupString, boolean as yupBoolean } from 'yup'
import BaseButton from '@/components/ui/BaseButton.vue'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Card from '@/components/ui/Card.vue'
import Tabs from '@/components/ui/Tabs.vue'
import Accordion from '@/components/ui/Accordion.vue'
import Tooltip from '@/components/ui/Tooltip.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import LocaleSwitcher from '@/components/ui/LocaleSwitcher.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseSelect from '@/components/form/BaseSelect.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import BaseRadio from '@/components/form/BaseRadio.vue'
import BaseFileUpload from '@/components/form/BaseFileUpload.vue'
import SearchSelect from '@/components/form/SearchSelect.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import ActionsDropdown from '@/components/data-list/ActionsDropdown.vue'
import BaseModal from '@/components/modal/BaseModal.vue'
import FormModal from '@/components/modal/FormModal.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import WizardStepper from '@/components/wizard/WizardStepper.vue'
import NotificationItem from '@/components/notifications/NotificationItem.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useWizard } from '@/composables/useWizard'
import { useNotifications } from '@/composables/useNotifications'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useToast } from '@/composables/useToast'

const { t, locale } = useI18n()
const toast = useToast()

const statusSamples = [
  'draft',
  'submitted',
  'under-review',
  'needs-completion',
  'completed',
  'qualified',
  'disqualified',
  'awaiting-secretary-approval',
  'paid',
  'failed',
  'overdue',
  'not-yet-available',
]

const activeTab = ref('current')
const tabItems = computed(() => [
  { value: 'current', label: t('gallery.demo.tab1') },
  { value: 'previous', label: t('gallery.demo.tab2') },
  { value: 'certificates', label: t('gallery.demo.tab3'), disabled: false },
])

const accordionItems = computed(() => [
  {
    id: 'leadership',
    title: t('gallery.demo.accordion1'),
    content: t('gallery.demo.accordion1Body'),
  },
  {
    id: 'strategy',
    title: t('gallery.demo.accordion2'),
    content: t('gallery.demo.accordion2Body'),
  },
  {
    id: 'beneficiaries',
    title: t('gallery.demo.accordion3'),
    content: t('gallery.demo.accordion3Body'),
  },
])

const dropdownValue = ref(null)
const dropdownOptions = computed(() => [
  { value: 'draft', label: t('status.draft') },
  { value: 'submitted', label: t('status.submitted') },
  { value: 'completed', label: t('status.completed') },
])

const regionOptions = computed(() => [
  { value: 'riyadh', label: t('gallery.demo.regionRiyadh') },
  { value: 'makkah', label: t('gallery.demo.regionMakkah') },
  { value: 'eastern', label: t('gallery.demo.regionEastern') },
  { value: 'asir', label: t('gallery.demo.regionAsir') },
])

const singleSelectValue = ref(null)
const multiSelectValue = ref(['riyadh', 'makkah'])

const rowActions = computed(() => [
  { key: 'view', label: t('routes.requests.detail.title'), icon: 'fa-solid fa-eye' },
  { key: 'edit', label: t('actions.save'), icon: 'fa-solid fa-pen' },
  { key: 'remove', label: t('actions.cancel'), icon: 'fa-solid fa-trash-can', variant: 'danger' },
])

const inputValue = ref('')
const textareaValue = ref('')
const selectValue = ref(null)
const dateValue = ref('')
const checkboxValue = ref(true)
const radioValue = ref('association')
const radioOptions = computed(() => [
  { value: 'association', label: t('gallery.form.typeAssociation') },
  { value: 'foundation', label: t('gallery.form.typeFoundation') },
])
const uploadFiles = ref([
  { id: 1, name: 'الخطة-الاستراتيجية-2026.pdf', status: 'done' },
  { id: 2, name: 'governance-report-q2.xlsx', status: 'uploading', progress: 62 },
  { id: 3, name: 'محضر-مجلس-الإدارة.docx', status: 'failed' },
])

function removeUpload(id) {
  uploadFiles.value = uploadFiles.value.filter((file) => file.id !== id)
}

const demoSchema = computed(() =>
  yupObject({
    entityName: yupString()
      .required(t('validation.required'))
      .min(3, t('validation.minChars', { min: 3 })),
    email: yupString().required(t('validation.required')).email(t('validation.email')),
    entityType: yupString().required(t('validation.required')),
    about: yupString().required(t('validation.required')),
    foundingDate: yupString().required(t('validation.required')),
    agree: yupBoolean().oneOf([true], t('validation.mustAgree')),
  }),
)

const demoForm = useValidatedForm({
  schema: demoSchema,
  initialValues: {
    entityName: '',
    email: '',
    entityType: '',
    about: '',
    foundingDate: '',
    agree: false,
  },
  action: 'gallery.demoForm.submit',
  submit: async () => {
    await new Promise((resolve) => setTimeout(resolve, 600))
    toast.success(t('gallery.form.success'))
    demoForm.resetForm()
  },
})

const [entityName] = demoForm.defineField('entityName')
const [email] = demoForm.defineField('email')
const [entityType] = demoForm.defineField('entityType')
const [about] = demoForm.defineField('about')
const [foundingDate] = demoForm.defineField('foundingDate')
const [agree] = demoForm.defineField('agree')

const tableStatus = ref('success')
const tableSearch = ref('')
const tablePage = ref(2)
const tableColumns = computed(() => [
  { key: 'entity', label: t('gallery.demo.columnEntity') },
  { key: 'path', label: t('gallery.demo.columnPath') },
  { key: 'status', label: t('gallery.demo.columnStatus') },
  { key: 'score', label: t('gallery.demo.columnScore') },
  { key: 'actions', label: t('components.actions.label') },
])
const tableRows = [
  {
    id: 1,
    entity: { ar: 'جمعية البر بمنطقة الرياض', en: 'Al-Birr Association, Riyadh' },
    pathKey: 'gallery.demo.pathAccredited',
    status: 'under-review',
    score: '—',
  },
  {
    id: 2,
    entity: { ar: 'جمعية إنسان لرعاية الأيتام', en: 'Ensan Orphan Care Association' },
    pathKey: 'gallery.demo.pathAdvanced',
    status: 'qualified',
    score: '82.5',
  },
  {
    id: 3,
    entity: { ar: 'جمعية فتاة الأحساء الخيرية', en: 'Fatat Al-Ahsa Charity' },
    pathKey: 'gallery.demo.pathSelf',
    status: 'result-available',
    score: '67.0',
  },
]

const galleryStats = computed(() => [
  { id: 1, icon: 'fa-solid fa-building', label: t('gallery.demo.statLabel1'), value: '148' },
  { id: 2, icon: 'fa-solid fa-clipboard-check', label: t('gallery.demo.statLabel2'), value: '342' },
  { id: 3, icon: 'fa-solid fa-award', label: t('gallery.demo.statLabel3'), value: '96' },
  { id: 4, icon: 'fa-solid fa-trophy', label: t('gallery.demo.statLabel4'), value: '1' },
])

const modalOpen = ref(false)
const formModalOpen = ref(false)
const formModalBusy = ref(false)
const confirmResult = ref('')
const { confirm } = useConfirm()

async function openConfirm() {
  const accepted = await confirm({
    message: t('gallery.labels.confirmMessage'),
    variant: 'danger',
  })
  confirmResult.value = accepted ? t('gallery.labels.confirmed') : t('gallery.labels.dismissed')
}

function submitFormModal() {
  formModalBusy.value = true
  setTimeout(() => {
    formModalBusy.value = false
    formModalOpen.value = false
    toast.success(t('gallery.form.success'))
  }, 800)
}

const wizardStepDefs = [
  { id: 'data', labelKey: 'gallery.demo.wizardStep1' },
  { id: 'evidence', labelKey: 'gallery.demo.wizardStep2' },
  { id: 'review', labelKey: 'gallery.demo.wizardStep3' },
  { id: 'submit', labelKey: 'gallery.demo.wizardStep4' },
]
const wizard = useWizard(wizardStepDefs)
wizard.setValidity('evidence', false)
const wizardDisplaySteps = computed(() =>
  wizard.steps.value.map((step) => ({ ...step, label: t(step.labelKey) })),
)

const notifications = useNotifications({ perPage: 3 })
</script>

<template>
  <div class="gallery">
    <header class="gallery__section">
      <h1>{{ t('gallery.title') }}</h1>
      <p class="gallery__intro">{{ t('gallery.intro') }}</p>
      <div class="gallery__row">
        <LocaleSwitcher />
        <ThemeToggle />
      </div>
    </header>

    <section class="gallery__section" aria-labelledby="gallery-buttons">
      <h2 id="gallery-buttons" class="gallery__heading">{{ t('gallery.sections.buttons') }}</h2>
      <div class="gallery__row">
        <BaseButton variant="primary">{{ t('gallery.labels.primary') }}</BaseButton>
        <BaseButton variant="secondary">{{ t('gallery.labels.secondary') }}</BaseButton>
        <BaseButton variant="ghost">{{ t('gallery.labels.ghost') }}</BaseButton>
        <BaseButton variant="danger">{{ t('gallery.labels.danger') }}</BaseButton>
        <BaseButton variant="primary" size="sm">{{ t('gallery.labels.small') }}</BaseButton>
        <BaseButton variant="primary" size="lg">{{ t('gallery.labels.large') }}</BaseButton>
        <BaseButton variant="primary" loading>{{ t('gallery.labels.loading') }}</BaseButton>
        <BaseButton variant="primary" disabled>{{ t('gallery.labels.disabled') }}</BaseButton>
        <BaseButton
          variant="secondary"
          icon-only
          icon="fa-solid fa-plus"
          :aria-label="t('gallery.labels.iconOnly')"
        />
      </div>
    </section>

    <section class="gallery__section" aria-labelledby="gallery-badges">
      <h2 id="gallery-badges" class="gallery__heading">{{ t('gallery.sections.badges') }}</h2>
      <div class="gallery__row">
        <Badge variant="success">{{ t('status.completed') }}</Badge>
        <Badge variant="warning">{{ t('status.needs_completion') }}</Badge>
        <Badge variant="danger">{{ t('status.failed') }}</Badge>
        <Badge variant="info">{{ t('status.under_review') }}</Badge>
        <Badge variant="muted">{{ t('status.draft') }}</Badge>
      </div>
      <div class="gallery__row">
        <StatusBadge v-for="status in statusSamples" :key="status" :status="status" />
      </div>
    </section>

    <section class="gallery__section" aria-labelledby="gallery-cards">
      <h2 id="gallery-cards" class="gallery__heading">{{ t('gallery.sections.cards') }}</h2>
      <StatsCards :stats="galleryStats" />
      <StatsCards loading :skeleton-count="2" />
      <div class="gallery__grid">
        <Card :title="t('gallery.demo.accordion1')">
          {{ t('gallery.demo.accordion1Body') }}
          <template #footer>
            <BaseButton variant="ghost" size="sm">{{ t('common.retry') }}</BaseButton>
          </template>
        </Card>
        <Card :title="t('gallery.demo.accordion2')">
          {{ t('gallery.demo.accordion2Body') }}
        </Card>
      </div>
    </section>

    <section class="gallery__section" aria-labelledby="gallery-tabs">
      <h2 id="gallery-tabs" class="gallery__heading">{{ t('gallery.sections.tabs') }}</h2>
      <Tabs v-model="activeTab" :tabs="tabItems">
        <template #default="{ active }">
          <p v-if="active === 'current'">{{ t('gallery.demo.tabContent1') }}</p>
          <p v-else-if="active === 'previous'">{{ t('gallery.demo.tabContent2') }}</p>
          <p v-else>{{ t('gallery.demo.tabContent3') }}</p>
        </template>
      </Tabs>
    </section>

    <section class="gallery__section" aria-labelledby="gallery-accordion">
      <h2 id="gallery-accordion" class="gallery__heading">{{ t('gallery.sections.accordion') }}</h2>
      <Accordion :items="accordionItems" />
    </section>

    <section class="gallery__section" aria-labelledby="gallery-tooltip">
      <h2 id="gallery-tooltip" class="gallery__heading">{{ t('gallery.sections.tooltip') }}</h2>
      <div class="gallery__row">
        <Tooltip :text="t('gallery.labels.tooltipText')">
          <BaseButton
            variant="secondary"
            icon-only
            icon="fa-solid fa-circle-question"
            :aria-label="t('gallery.labels.tooltipTrigger')"
          />
        </Tooltip>
      </div>
    </section>

    <section class="gallery__section" aria-labelledby="gallery-dropdowns">
      <h2 id="gallery-dropdowns" class="gallery__heading">{{ t('gallery.sections.dropdowns') }}</h2>
      <div class="gallery__row">
        <DropdownList
          v-model="dropdownValue"
          :options="dropdownOptions"
          :placeholder="t('gallery.demo.selectStatus')"
        />
        <DropdownList
          :model-value="null"
          :options="dropdownOptions"
          :placeholder="t('gallery.labels.disabled')"
          disabled
        />
        <ActionsDropdown :actions="rowActions" />
      </div>
      <div class="gallery__grid">
        <SearchSelect
          v-model="singleSelectValue"
          :options="regionOptions"
          :placeholder="t('gallery.demo.selectRegion')"
          :label="t('gallery.demo.selectRegion')"
        />
        <SearchSelect
          v-model="multiSelectValue"
          :options="regionOptions"
          multiple
          :placeholder="t('gallery.demo.selectRegion')"
          :label="t('gallery.demo.selectRegion')"
        />
        <SearchSelect
          :model-value="null"
          :options="[]"
          loading
          :placeholder="t('components.searchSelect.loading')"
          :label="t('components.searchSelect.loading')"
        />
        <SearchSelect
          :model-value="null"
          :options="regionOptions"
          disabled
          :placeholder="t('gallery.labels.disabled')"
          :label="t('gallery.labels.disabled')"
        />
      </div>
    </section>

    <section class="gallery__section" aria-labelledby="gallery-forms">
      <h2 id="gallery-forms" class="gallery__heading">{{ t('gallery.sections.forms') }}</h2>
      <div class="gallery__grid">
        <BaseInput
          v-model="inputValue"
          :label="t('gallery.form.entityName')"
          name="demoName"
          required
        />
        <BaseInput
          model-value=""
          :label="t('gallery.form.email')"
          name="demoEmailError"
          type="email"
          :error="t('validation.email')"
        />
        <BaseSelect
          v-model="selectValue"
          :label="t('gallery.form.entityType')"
          name="demoType"
          :options="radioOptions"
          :placeholder="t('common.select')"
        />
        <BaseDatePicker
          v-model="dateValue"
          :label="t('gallery.form.foundingDate')"
          name="demoDate"
        />
        <BaseTextarea
          v-model="textareaValue"
          :label="t('gallery.form.about')"
          name="demoAbout"
          :rows="3"
        />
        <div class="gallery__stack">
          <BaseCheckbox v-model="checkboxValue" :label="t('gallery.form.agree')" name="demoAgree" />
          <BaseRadio
            v-model="radioValue"
            :label="t('gallery.form.entityType')"
            name="demoRadio"
            :options="radioOptions"
            inline
          />
        </div>
      </div>
      <BaseFileUpload
        :files="uploadFiles"
        :label="t('gallery.sections.forms')"
        name="demoFiles"
        multiple
        @remove="removeUpload"
      />
      <h3 class="gallery__subheading">{{ t('gallery.form.heading') }}</h3>
      <form class="gallery__stack" novalidate @submit="demoForm.onSubmit">
        <BaseInput
          v-model="entityName"
          :label="t('gallery.form.entityName')"
          name="entityName"
          :error="demoForm.errors.value.entityName"
          required
        />
        <BaseInput
          v-model="email"
          :label="t('gallery.form.email')"
          name="email"
          type="email"
          :error="demoForm.errors.value.email"
          required
        />
        <BaseSelect
          v-model="entityType"
          :label="t('gallery.form.entityType')"
          name="entityType"
          :options="radioOptions"
          :placeholder="t('common.select')"
          :error="demoForm.errors.value.entityType"
          required
        />
        <BaseTextarea
          v-model="about"
          :label="t('gallery.form.about')"
          name="about"
          :rows="3"
          :error="demoForm.errors.value.about"
          required
        />
        <BaseDatePicker
          v-model="foundingDate"
          :label="t('gallery.form.foundingDate')"
          name="foundingDate"
          :error="demoForm.errors.value.foundingDate"
          required
        />
        <BaseCheckbox
          v-model="agree"
          :label="t('gallery.form.agree')"
          name="agree"
          :error="demoForm.errors.value.agree"
        />
        <div class="gallery__row">
          <BaseButton type="submit" variant="primary" :loading="demoForm.submitting.value">
            {{ t('gallery.form.submit') }}
          </BaseButton>
        </div>
      </form>
    </section>

    <section class="gallery__section" aria-labelledby="gallery-data-list">
      <h2 id="gallery-data-list" class="gallery__heading">{{ t('gallery.sections.dataList') }}</h2>
      <div class="gallery__row">
        <BaseButton
          v-for="candidate in ['loading', 'empty', 'error', 'success']"
          :key="candidate"
          variant="secondary"
          size="sm"
          @click="tableStatus = candidate"
        >
          {{ candidate }}
        </BaseButton>
      </div>
      <ListToolbar
        v-model:search="tableSearch"
        :search-placeholder="t('gallery.demo.searchEntities')"
      >
        <template #filters>
          <DropdownList
            v-model="dropdownValue"
            :options="dropdownOptions"
            :placeholder="t('gallery.demo.selectStatus')"
          />
        </template>
        <template #actions>
          <BaseButton variant="primary" size="sm" icon="fa-solid fa-plus">
            {{ t('gallery.labels.iconOnly') }}
          </BaseButton>
        </template>
      </ListToolbar>
      <DataTable
        :columns="tableColumns"
        :items="tableRows"
        :status="tableStatus"
        @retry="tableStatus = 'success'"
      >
        <template #cell-entity="{ item }">{{ item.entity[locale] }}</template>
        <template #cell-path="{ item }">{{ t(item.pathKey) }}</template>
        <template #cell-status="{ item }">
          <StatusBadge :status="item.status" />
        </template>
        <template #cell-actions>
          <ActionsDropdown :actions="rowActions" />
        </template>
      </DataTable>
      <Pagination v-model:page="tablePage" :last-page="7" />
    </section>

    <section class="gallery__section" aria-labelledby="gallery-modals">
      <h2 id="gallery-modals" class="gallery__heading">{{ t('gallery.sections.modals') }}</h2>
      <div class="gallery__row">
        <BaseButton variant="secondary" @click="modalOpen = true">
          {{ t('gallery.labels.openModal') }}
        </BaseButton>
        <BaseButton variant="danger" @click="openConfirm">
          {{ t('gallery.labels.openConfirm') }}
        </BaseButton>
        <BaseButton variant="primary" @click="formModalOpen = true">
          {{ t('gallery.labels.openFormModal') }}
        </BaseButton>
        <Badge v-if="confirmResult" variant="info">{{ confirmResult }}</Badge>
      </div>
      <BaseModal v-model="modalOpen" :title="t('gallery.labels.modalTitle')">
        <p>{{ t('gallery.labels.modalBody') }}</p>
        <template #footer>
          <BaseButton variant="secondary" @click="modalOpen = false">
            {{ t('actions.close') }}
          </BaseButton>
        </template>
      </BaseModal>
      <FormModal
        v-model="formModalOpen"
        :title="t('gallery.form.heading')"
        :busy="formModalBusy"
        @submit="submitFormModal"
      >
        <BaseInput
          v-model="inputValue"
          :label="t('gallery.form.entityName')"
          name="modalEntityName"
          required
        />
      </FormModal>
    </section>

    <section class="gallery__section" aria-labelledby="gallery-feedback">
      <h2 id="gallery-feedback" class="gallery__heading">{{ t('gallery.sections.feedback') }}</h2>
      <div class="gallery__grid">
        <div class="gallery__stack">
          <Skeleton variant="text" :count="3" />
          <Skeleton variant="circle" />
          <Skeleton variant="block" />
        </div>
        <EmptyState
          :title="t('common.empty')"
          :message="t('components.notifications.emptyMessage')"
        >
          <template #action>
            <BaseButton variant="primary" size="sm">{{ t('actions.reload') }}</BaseButton>
          </template>
        </EmptyState>
        <ErrorState />
      </div>
    </section>

    <section class="gallery__section" aria-labelledby="gallery-wizard">
      <h2 id="gallery-wizard" class="gallery__heading">{{ t('gallery.sections.wizard') }}</h2>
      <WizardStepper
        :steps="wizardDisplaySteps"
        :current-index="wizard.currentIndex.value"
        @select="wizard.goTo"
      />
      <div class="gallery__row">
        <BaseButton
          variant="secondary"
          size="sm"
          :disabled="wizard.isFirst.value"
          @click="wizard.previous"
        >
          {{ t('gallery.labels.previous') }}
        </BaseButton>
        <BaseButton
          variant="primary"
          size="sm"
          :disabled="wizard.isLast.value"
          @click="wizard.next"
        >
          {{ t('gallery.labels.next') }}
        </BaseButton>
      </div>
    </section>

    <section class="gallery__section" aria-labelledby="gallery-notifications">
      <h2 id="gallery-notifications" class="gallery__heading">
        {{ t('gallery.sections.notifications') }}
      </h2>
      <div class="gallery__stack">
        <Skeleton v-if="notifications.status.value === 'loading'" variant="text" :count="3" />
        <ErrorState
          v-else-if="notifications.status.value === 'error'"
          @retry="notifications.load"
        />
        <EmptyState
          v-else-if="notifications.status.value === 'empty'"
          :title="t('components.notifications.emptyTitle')"
          :message="t('components.notifications.emptyMessage')"
        />
        <template v-else>
          <NotificationItem
            v-for="item in notifications.items.value"
            :key="item.id"
            :notification="item"
          />
        </template>
      </div>
    </section>
  </div>
</template>
