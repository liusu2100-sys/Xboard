export function unwrapPayload(payload) {
  if (payload && typeof payload === 'object' && 'data' in payload && Object.keys(payload).length <= 3) {
    return payload.data
  }
  return payload
}

export function listFromPayload(payload) {
  const data = unwrapPayload(payload)
  if (Array.isArray(data)) return data
  if (Array.isArray(data?.data)) return data.data
  if (Array.isArray(data?.items)) return data.items
  return []
}

export function objectFromPayload(payload, fallback = {}) {
  const data = unwrapPayload(payload)
  return data && typeof data === 'object' && !Array.isArray(data) ? data : fallback
}
