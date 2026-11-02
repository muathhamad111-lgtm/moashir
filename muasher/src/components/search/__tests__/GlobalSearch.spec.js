import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import GlobalSearch from '@/components/search/GlobalSearch.vue'
import { searchService } from '@/services/searchService'
import { useAuthStore } from '@/stores/auth'
import { useSearchHandoffStore } from '@/stores/searchHandoff'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/searchService', () => ({
  searchService: { search: vi.fn() },
}))

const groups = [
  {
    type: 'entities',
    total: 3,
    items: [{ type: 'entities', key: 'entities:6002', id: 6002, title: { ar: 'جمعية إحسان الخيرية', en: 'Ehsan Charitable Society' }, status: 'active' }],
  },
  {
    type: 'requests',
    total: 1,
    items: [
      {
        type: 'requests',
        key: 'requests:9108',
        id: 9108,
        title: { ar: 'جمعية إحسان الخيرية', en: 'Ehsan Charitable Society' },
        reference: 'ACC-2026-000008',
        path: 'accredited',
        status: 'submitted',
      },
    ],
  },
  {
    type: 'users',
    total: 1,
    items: [
      {
        type: 'users',
        key: 'users:12',
        id: 12,
        title: 'هند بنت علي الزهراني',
        email: 'hind.alzahrani@example.sa',
        roles: ['request_follower'],
        status: 'active',
      },
    ],
  },
]

async function mountSearch({ locale = 'ar', userId = 7001 } = {}) {
  const globals = createTestGlobals({ locale })
  useAuthStore(globals.pinia).setUser({ id: userId, name: 'نورة', roles: ['request_follower'] })
  await globals.router.push('/dashboard')
  const wrapper = mount(GlobalSearch, { global: globals, attachTo: document.body })
  return { wrapper, globals, input: wrapper.find('input[role="combobox"]') }
}

async function typeAndSettle(input, text) {
  await input.trigger('focus')
  await input.setValue(text)
  vi.advanceTimersByTime(300)
  await flushPromises()
}

beforeEach(() => {
  vi.useFakeTimers()
  vi.clearAllMocks()
  localStorage.clear()
  searchService.search.mockResolvedValue({ data: { groups } })
})

afterEach(() => {
  vi.useRealTimers()
  document.body.innerHTML = ''
})

describe('GlobalSearch', () => {
  it('exposes the ARIA 1.2 combobox pattern with a labelled input and a listbox popup', async () => {
    const { wrapper, input } = await mountSearch()
    expect(input.attributes('aria-expanded')).toBe('false')
    expect(input.attributes('aria-autocomplete')).toBe('list')
    expect(input.attributes('aria-keyshortcuts')).toBe('Control+K Meta+K /')
    const listbox = wrapper.find('[role="listbox"]')
    expect(input.attributes('aria-controls')).toBe(listbox.attributes('id'))
    expect(wrapper.find(`label[for="${input.attributes('id')}"]`).text()).toBe('ابحث عن جهة أو طلب أو مستخدم')
    wrapper.unmount()
  })

  it('asks for at least 2 characters without calling the API', async () => {
    const { wrapper, input } = await mountSearch({ locale: 'en' })
    await typeAndSettle(input, 'a')
    expect(searchService.search).not.toHaveBeenCalled()
    expect(wrapper.find('.global-search__state').text()).toBe('Type at least 2 characters to search.')
    expect(input.attributes('aria-expanded')).toBe('true')
    wrapper.unmount()
  })

  it('renders the server groups as labelled option groups with counts, in the server order', async () => {
    const { wrapper, input } = await mountSearch({ locale: 'en' })
    await typeAndSettle(input, 'Ehsan')
    const groupEls = wrapper.findAll('[role="group"]')
    expect(groupEls).toHaveLength(3)
    groupEls.forEach((group) => {
      expect(wrapper.find(`#${group.attributes('aria-labelledby')}`).exists()).toBe(true)
    })
    expect(groupEls[0].text()).toContain('Entities')
    expect(groupEls[0].text()).toContain('1 of 3')
    expect(groupEls[1].text()).toContain('Request ACC-2026-000008 · Accredited')
    expect(groupEls[1].text()).toContain('Submitted')
    expect(groupEls[2].text()).toContain('Assessment Requests Follower')
    expect(wrapper.find('[role="status"]').text()).toBe('Results found: 3')
    wrapper.unmount()
  })

  it('moves the active option with the arrow keys via aria-activedescendant', async () => {
    const { wrapper, input } = await mountSearch()
    await typeAndSettle(input, 'إحسان')
    await input.trigger('keydown', { key: 'ArrowDown' })
    const options = wrapper.findAll('[role="option"]')
    expect(input.attributes('aria-activedescendant')).toBe(options[0].attributes('id'))
    expect(options[0].attributes('aria-selected')).toBe('true')
    await input.trigger('keydown', { key: 'ArrowUp' })
    expect(input.attributes('aria-activedescendant')).toBe(options[2].attributes('id'))
    await input.trigger('keydown', { key: 'Escape' })
    expect(input.attributes('aria-expanded')).toBe('false')
    expect(input.attributes('aria-activedescendant')).toBeUndefined()
    await input.trigger('keydown', { key: 'Escape' })
    expect(input.element.value).toBe('')
    wrapper.unmount()
  })

  it('opens a request with Enter, remembers the search and closes the popup', async () => {
    const { wrapper, input, globals } = await mountSearch()
    await typeAndSettle(input, 'إحسان')
    const push = vi.spyOn(globals.router, 'push').mockResolvedValue()
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'Enter' })
    await flushPromises()
    expect(push).toHaveBeenCalledWith({ name: 'requests.detail', params: { id: 9108 } })
    expect(globals.router.resolve(push.mock.calls[0][0]).fullPath).toBe('/admin/requests/9108')
    expect(JSON.parse(localStorage.getItem('muasher.search.recent.7001'))).toEqual(['إحسان'])
    expect(input.attributes('aria-expanded')).toBe('false')
    wrapper.unmount()
  })

  it('opens the entity record for an entity result and hands no name to another list', async () => {
    const { wrapper, input, globals } = await mountSearch()
    await typeAndSettle(input, 'إحسان')
    const push = vi.spyOn(globals.router, 'push').mockResolvedValue()
    await wrapper.findAll('[role="option"]')[0].trigger('click')
    await flushPromises()
    expect(push).toHaveBeenCalledWith({ name: 'admin.entities.record', params: { id: 6002 } })
    expect(globals.router.resolve(push.mock.calls[0][0]).fullPath).toBe('/admin/entities/6002')
    expect(useSearchHandoffStore(globals.pinia).pending).toEqual({})
    wrapper.unmount()
  })

  it('never remembers a search that led to a person', async () => {
    const { wrapper, input, globals } = await mountSearch()
    await typeAndSettle(input, 'هند')
    vi.spyOn(globals.router, 'push').mockResolvedValue()
    await wrapper.findAll('[role="option"]')[2].trigger('click')
    await flushPromises()
    expect(localStorage.getItem('muasher.search.recent.7001')).toBeNull()
    wrapper.unmount()
  })

  it('offers the recent searches of this user when focused empty, and clears them', async () => {
    localStorage.setItem('muasher.search.recent.7001', JSON.stringify(['نماء', 'البر']))
    const { wrapper, input } = await mountSearch()
    await input.trigger('focus')
    expect(input.attributes('aria-expanded')).toBe('true')
    const options = wrapper.findAll('[role="option"]')
    expect(options.map((option) => option.text())).toEqual(['نماء', 'البر'])
    await options[1].trigger('click')
    expect(input.element.value).toBe('البر')
    await input.setValue('')
    await wrapper.find('.global-search__footer .btn').trigger('click')
    expect(localStorage.getItem('muasher.search.recent.7001')).toBeNull()
    wrapper.unmount()
  })

  it('shows the empty state and the error state with retry', async () => {
    searchService.search.mockResolvedValueOnce({ data: { groups: [] } })
    const { wrapper, input } = await mountSearch({ locale: 'en' })
    await typeAndSettle(input, 'zzz')
    expect(wrapper.find('.global-search__state').text()).toContain('No results match “zzz”.')
    searchService.search.mockRejectedValueOnce(new Error('offline'))
    await typeAndSettle(input, 'zzzz')
    expect(wrapper.find('.global-search__state--error').text()).toContain('Search is unavailable right now.')
    await wrapper.find('.global-search__state--error .btn').trigger('click')
    await flushPromises()
    expect(wrapper.findAll('[role="option"]')).toHaveLength(3)
    wrapper.unmount()
  })

  it('shows a skeleton while loading', async () => {
    searchService.search.mockReturnValue(new Promise(() => {}))
    const { wrapper, input } = await mountSearch()
    await input.trigger('focus')
    await input.setValue('سقيا')
    expect(wrapper.find('.global-search__state .skeleton').exists()).toBe(true)
    wrapper.unmount()
  })

  it('focuses the search with Ctrl+K on any keyboard layout and with slash outside fields', async () => {
    const { wrapper, input } = await mountSearch()
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ن', code: 'KeyK', ctrlKey: true }))
    expect(document.activeElement).toBe(input.element)
    input.element.blur()
    document.body.dispatchEvent(new KeyboardEvent('keydown', { key: '/', bubbles: true }))
    expect(document.activeElement).toBe(input.element)
    wrapper.unmount()
  })

  it('shows the user e-mail under the name, left-to-right, and includes it in the accessible label', async () => {
    const { wrapper, input } = await mountSearch()
    await typeAndSettle(input, 'هند')
    const user = wrapper.findAll('[role="option"]')[2]
    const lines = user.findAll('.global-search__option-text > span')
    expect(lines.map((line) => line.classes()[0])).toEqual([
      'global-search__option-title',
      'global-search__option-email',
      'global-search__option-subtitle',
    ])
    expect(lines[1].text()).toBe('hind.alzahrani@example.sa')
    expect(lines[1].attributes('dir')).toBe('ltr')
    expect(user.attributes('aria-label')).toBe(
      'هند بنت علي الزهراني، البريد الإلكتروني hind.alzahrani@example.sa، متابع طلبات التقييم',
    )
    wrapper.unmount()
  })

  it('labels the user option with its e-mail in English and omits empty roles', async () => {
    const users = [{ ...groups[2].items[0], roles: [] }]
    searchService.search.mockResolvedValue({ data: { groups: [{ type: 'users', total: 1, items: users }] } })
    const { wrapper, input } = await mountSearch({ locale: 'en' })
    await typeAndSettle(input, 'Hind')
    const user = wrapper.find('[role="option"]')
    expect(user.find('.global-search__option-email').text()).toBe('hind.alzahrani@example.sa')
    expect(user.find('.global-search__option-subtitle').exists()).toBe(false)
    expect(user.attributes('aria-label')).toBe('هند بنت علي الزهراني, email hind.alzahrani@example.sa')
    wrapper.unmount()
  })

  it('keeps the content-derived name for entities, requests and users without an e-mail', async () => {
    const users = [{ ...groups[2].items[0], email: '' }]
    searchService.search.mockResolvedValue({ data: { groups: [...groups.slice(0, 2), { type: 'users', total: 1, items: users }] } })
    const { wrapper, input } = await mountSearch()
    await typeAndSettle(input, 'إحسان')
    const options = wrapper.findAll('[role="option"]')
    options.forEach((option) => expect(option.attributes('aria-label')).toBeUndefined())
    expect(wrapper.find('.global-search__option-email').exists()).toBe(false)
    wrapper.unmount()
  })

  it('does not filter results on the client: every returned item is rendered', async () => {
    const { wrapper, input } = await mountSearch()
    await typeAndSettle(input, 'إحسان')
    expect(wrapper.findAll('[role="option"]')).toHaveLength(3)
    wrapper.unmount()
  })
})
