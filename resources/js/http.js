function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

// Не бросает на 4xx/5xx, только при обрыве сети: смотрите ok и status
export async function send(method, url, body = null) {
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: body === null ? null : JSON.stringify(body),
    });
    const data = res.status === 204 ? null : await res.json().catch(() => null);
    return { ok: res.ok, status: res.status, data };
}

// Ключ попытки: повтор с ним сервер не проведёт дважды. randomUUID есть только на https и localhost
export function newRequestKey() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();

    const b = window.crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}
