/** A stand-in for the admin's `Routing`: a route becomes `/<name>` with its parameters in the query, so a test can read what was asked. */
export function installRouting(): void {
    (globalThis as { Routing?: unknown }).Routing = {
        generate: (name: string, parameters: Record<string, unknown> = {}): string => {
            const path = name.replace(/^masilia\.ibexa_ai\./, '/').replace(/\./g, '/');
            const query = Object.entries(parameters).map(([key, value]) => `${key}=${String(value)}`).join('&');

            return query === '' ? path : `${path}?${query}`;
        },
    };
}
