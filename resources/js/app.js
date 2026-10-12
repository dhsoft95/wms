import Collapse from '@alpinejs/collapse'

// Row-actions UID counter (module-scoped, survives hot-reload)
let _raNextId = 0

/**
 * rowActionsMenu — powers every kebab (⋮) trigger in tables.
 *
 * One menu open at a time: opening any instance broadcasts
 * 'row-actions:open' so every other instance closes itself.
 * The menu is teleported to <body> so it can't be clipped by
 * table overflow containers. Position is calculated with
 * getBoundingClientRect() and rendered with position:fixed.
 */
const rowActionsMenuDef = () => ({
    open:      false,
    menuX:     0,
    menuY:     0,
    menuAbove: false,
    _uid:      null,

    init() {
        this._uid = `ra-${++_raNextId}`
        this._closeOthers = (e) => {
            if (e.detail?.uid !== this._uid) this.open = false
        }
        document.addEventListener('row-actions:open', this._closeOthers)
    },

    destroy() {
        document.removeEventListener('row-actions:open', this._closeOthers)
    },

    openMenu() {
        const trigger = this.$refs.trigger
        if (!trigger) return

        const rect      = trigger.getBoundingClientRect()
        const menuWidth = 208

        this.menuAbove = (window.innerHeight - rect.bottom) < 260
        this.menuX     = Math.max(8, rect.right - menuWidth)
        this.menuY     = this.menuAbove ? rect.top - 4 : rect.bottom + 4

        // Close every other open menu before opening this one
        document.dispatchEvent(new CustomEvent('row-actions:open', {
            detail: { uid: this._uid },
        }))

        this.open = true
    },

    closeMenu() {
        this.open = false
    },
})

function registerWithAlpine(Alpine) {
    Alpine.plugin(Collapse)
    Alpine.data('rowActionsMenu', rowActionsMenuDef)
}

// Livewire's <script> tag has no `defer`, so it runs synchronously at end of
// body and starts Alpine — firing `alpine:init` — before this ES module (Vite
// output: type="module", deferred) gets to execute. By the time our module
// runs, window.Alpine is already set but the event has already fired.
// Check for an existing Alpine instance first; fall back to the event for any
// setup where the order is reversed (e.g. local dev with lazy imports).
if (window.Alpine) {
    registerWithAlpine(window.Alpine)
} else {
    document.addEventListener('alpine:init', () => registerWithAlpine(window.Alpine))
}
