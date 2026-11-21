export function installFakeMoyasar(target, texts, outcomeOrder) {
  const doc = target.document
  const FULFIL_URL = 'https://api.moyasar.com/v1/payment_intents'

  function node(tag, attributes = {}, children = []) {
    const element = doc.createElement(tag)
    Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, value))
    children.forEach((child) => element.append(child))
    return element
  }

  function choice(name, label, options) {
    const id = `fake-moyasar-${name}`
    const select = node('select', { id, name, class: 'fake-moyasar__control' })
    options.forEach(([value, text]) => select.append(node('option', { value }, [text])))
    return {
      select,
      field: node('label', { class: 'fake-moyasar__field', for: id }, [label, select]),
    }
  }

  function amountLabel(config) {
    return `${(config.amount / 100).toFixed(2)} ${config.currency}`
  }

  function returnUrl(config, payment) {
    const query = new URLSearchParams({
      id: payment.id,
      status: payment.status,
      message: payment.source?.message ?? '',
    })
    return `${config.callback_url}?${query}`
  }

  async function fulfil(config, outcome, method) {
    const response = await target.fetch(`${FULFIL_URL}/${config.payment_intent.id}/fulfill`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        publishable_api_key: config.publishable_api_key,
        client_secret: config.payment_intent.client_secret,
        metadata: config.metadata,
        source: { type: method, sandbox_outcome: outcome },
      }),
    })
    if (!response.ok) throw new Error('fulfil_failed')
    return response.json()
  }

  function render(config) {
    const host =
      typeof config.element === 'string' ? doc.querySelector(config.element) : config.element
    if (!host) throw new Error('element_not_found')
    const copy = texts[config.language] ?? texts.ar
    const outcome = choice(
      'sandbox-outcome',
      copy.outcomeLabel,
      outcomeOrder.map((key) => [key, copy.outcomes[key]]),
    )
    const method = choice(
      'sandbox-method',
      copy.methodLabel,
      config.methods.map((key) => [key, copy.methods[key] ?? key]),
    )
    const error = node('p', { class: 'fake-moyasar__error', role: 'alert', hidden: '' })
    const pay = node('button', { type: 'submit', class: 'fake-moyasar__pay' }, [
      `${copy.pay} ${amountLabel(config)}`,
    ])
    const form = node(
      'form',
      { class: 'fake-moyasar', 'data-fake-moyasar': 'form', novalidate: '' },
      [
        node('p', { class: 'fake-moyasar__notice' }, [copy.notice]),
        outcome.field,
        method.field,
        error,
        pay,
      ],
    )

    function fail(message) {
      error.textContent = message
      error.hidden = false
      pay.disabled = false
      pay.textContent = `${copy.pay} ${amountLabel(config)}`
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault()
      if (!config.use_payment_intent || !config.payment_intent) return fail(copy.failed)
      pay.disabled = true
      pay.textContent = copy.processing
      error.hidden = true
      let payment
      try {
        payment = await fulfil(config, outcome.select.value, method.select.value)
      } catch {
        return fail(copy.failed)
      }
      try {
        if (config.on_completed) await config.on_completed(payment)
      } catch {
        return fail(copy.interrupted)
      }
      target.location.assign(returnUrl(config, payment))
      return null
    })

    host.replaceChildren(form)
  }

  target.Moyasar = { init: render }
}

export function fakeMoyasarScriptSource(texts, outcomeOrder) {
  return `(${installFakeMoyasar.toString()})(window, ${JSON.stringify(texts)}, ${JSON.stringify(outcomeOrder)});`
}
