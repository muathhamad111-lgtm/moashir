const idle = async () => {}

let beforeResolve = idle

export function onBeforeResolve(hook) {
  beforeResolve = hook ?? idle
}

export function prepareRequest(request) {
  return beforeResolve(request)
}
