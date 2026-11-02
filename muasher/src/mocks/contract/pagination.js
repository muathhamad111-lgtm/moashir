const MAX_PER_PAGE = 100

export function paginate(items, query, defaultPerPage = 15) {
  const perPage = Math.min(MAX_PER_PAGE, Math.max(1, Number(query.get('perPage') ?? defaultPerPage)))
  const lastPage = Math.max(1, Math.ceil(items.length / perPage))
  const page = Math.min(lastPage, Math.max(1, Number(query.get('page') ?? 1)))
  return {
    items: items.slice((page - 1) * perPage, page * perPage),
    pagination: { page, perPage, total: items.length, lastPage },
  }
}
