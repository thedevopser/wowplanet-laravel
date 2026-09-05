// Node exposes an experimental global localStorage accessor that stays undefined without
// --localstorage-file, and it takes precedence over the one happy-dom provides. Install
// happy-dom's own Storage on the globals so tests get a working implementation.
for (const key of ['localStorage', 'sessionStorage']) {
    Object.defineProperty(globalThis, key, {
        value: new globalThis.Storage(),
        configurable: true,
    });
}
