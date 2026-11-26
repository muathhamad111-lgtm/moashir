const STUB_ROUTE = {
  path: '/composable-under-test',
  name: 'composable-under-test',
  component: { render: () => null },
}

// Composables that write the query (portal tabs) navigate from the router's start
// location. That first navigation would load the lazy home page, which a cold transform
// can delay past vi.waitFor's timeout. A synchronous stub route keeps it instant.
export async function visitStubRoute(router) {
  if (!router.hasRoute(STUB_ROUTE.name)) router.addRoute(STUB_ROUTE)
  await router.push({ name: STUB_ROUTE.name })
}
