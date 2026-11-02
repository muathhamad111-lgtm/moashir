# components/layout

The three layouts, wired through `meta.layout` (`public` | `auth` | `app`) in `src/App.vue`:

- `AppLayout` — back-office shell: skip link, full-height `Sidebar` at the inline start, then a
  column with `TopNavbar` and the `<main id="app-main">` slot. Loads the shared branding theme
  (`stores/branding.js`) once; `App.vue` applies `data-accent` on `<html>` for `app` routes only.
- `Sidebar` — brand logo (branding override or the brand default), items from
  `src/constants/navigation.js` filtered by role and grouped by module (`NAV_GROUPS`), one caption
  per group, no role switcher. The active item (route name or a child route name) gets
  `aria-current="page"` and the "Curved Active Tab" shape. Icons-only rail below `md`;
  collapsible from `md` up through the ui store.
- `TopNavbar` (`.navbar.navbar--app`) — sidebar toggle, page title, LocaleSwitcher, ThemeToggle,
  settings shortcut, NotificationBell, avatar user menu (My account, entity profile, sign out).
- `PublicLayout` / `PublicNavbar` / `AppFooter` — public pages (base `.navbar` styles).
- `AuthLayout` — brand panel + centered card; also hosts the maintenance page.

Styling: `_sidebar.scss` (sidebar + curved tab via `mixins/_curved-tab.scss`), `_navbar.scss`,
`_app-layout.scss`, `_public-layout.scss`, `_auth-layout.scss`, `_footer.scss`. No style block or
inline style in any `.vue`.
