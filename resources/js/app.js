import Collapse from '@alpinejs/collapse'
import { Chart, registerables } from 'chart.js'
Chart.register(...registerables)

// ─── chartJs Alpine component (drop-in replacement for apexChart) ─────────────
const palette = [
    '#a22631', '#94a3b8', '#16a34a', '#d97706', '#dc2626',
    '#cbd5e1', '#78716c', '#a16207', '#166534', '#7f1d1d',
]

const apexChartDef = (config) => ({
    config,
    chart: null,
    _darkObserver: null,
    _navHandler: null,

    isDark() {
        return document.documentElement.classList.contains('dark')
    },

    themeColors() {
        const dark = this.isDark()
        return {
            text: dark ? '#94a3b8' : '#475569',
            grid: dark ? 'rgba(255,255,255,0.08)' : '#e2e8f0',
        }
    },

    fmtVal(v) {
        if (v === null || v === undefined) return '—'
        return v.toLocaleString() + (this.config.unit ? ' ' + this.config.unit : '')
    },

    buildConfig() {
        const t = this.themeColors()
        const cfg = this.config

        // ── Doughnut (was radialBar) ─────────────────────────────────────────
        if (cfg.type === 'radialBar') {
            const value = Number(cfg.series[0] ?? 0)
            return {
                type: 'doughnut',
                data: {
                    labels: cfg.categories.length ? cfg.categories : ['Used', 'Free'],
                    datasets: [{
                        data: [value, Math.max(0, 100 - value)],
                        backgroundColor: [palette[0], this.isDark() ? 'rgba(255,255,255,0.1)' : '#e2e8f0'],
                        borderWidth: 0,
                        hoverOffset: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ctx.dataIndex === 0
                                    ? ` ${value.toFixed(1)}%`
                                    : ` ${(100 - value).toFixed(1)}% free`,
                            },
                        },
                    },
                },
            }
        }

        // ── Line / Bar ───────────────────────────────────────────────────────
        const isLine = cfg.type === 'line'
        const isHorizontal = cfg.horizontal === true

        const datasets = (cfg.series || []).map((s, i) => {
            const color = palette[i % palette.length]
            const data  = Array.isArray(s) ? s : (s?.data ?? [])
            const label = s?.name ?? `Series ${i + 1}`

            if (isLine) {
                return {
                    label,
                    data,
                    borderColor:     color,
                    backgroundColor: color + '33',
                    borderWidth:     2,
                    pointRadius:     0,
                    pointHoverRadius: 4,
                    fill:            false,
                    tension:         0.4,
                }
            }

            return {
                label,
                data,
                backgroundColor: color,
                borderRadius:    isHorizontal ? 3 : 4,
                borderSkipped:   false,
            }
        })

        const showLegend = datasets.length > 1

        return {
            type: isLine ? 'line' : 'bar',
            data: { labels: cfg.categories, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: isHorizontal ? 'y' : 'x',
                animation: false,
                plugins: {
                    legend: {
                        display: showLegend,
                        labels: {
                            color:    t.text,
                            boxWidth: 12,
                            padding:  16,
                            font:     { size: 12 },
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ` ${ctx.dataset.label}: ${this.fmtVal(ctx.raw)}`,
                        },
                    },
                },
                scales: {
                    x: {
                        stacked: cfg.stacked,
                        grid: {
                            color:       isHorizontal ? t.grid : 'transparent',
                            drawBorder:  false,
                        },
                        border: { color: t.grid },
                        ticks: {
                            color:        t.text,
                            maxRotation:  isHorizontal ? 0 : 30,
                            font:         { size: 11 },
                        },
                    },
                    y: {
                        stacked: cfg.stacked,
                        grid: {
                            color:      isHorizontal ? 'transparent' : t.grid,
                            drawBorder: false,
                        },
                        border: { color: t.grid },
                        ticks: {
                            color: t.text,
                            font:  { size: 11 },
                        },
                    },
                },
            },
        }
    },

    init() {
        if (this.config.isEmpty) return

        this.$nextTick(() => {
            const canvas = document.getElementById(this.config.chartId)
            if (!canvas) return
            this.chart = new Chart(canvas, this.buildConfig())
        })

        this._darkObserver = new MutationObserver(() => {
            if (!this.chart) return
            const rebuilt = this.buildConfig()
            this.chart.data    = rebuilt.data
            this.chart.options = rebuilt.options
            this.chart.update('none')
        })
        this._darkObserver.observe(document.documentElement, {
            attributes: true, attributeFilter: ['class'],
        })

        document.addEventListener('livewire:navigated', this._navHandler = () => this.destroy())
    },

    destroy() {
        if (this.chart)         { this.chart.destroy(); this.chart = null }
        if (this._darkObserver) { this._darkObserver.disconnect(); this._darkObserver = null }
        if (this._navHandler)   {
            document.removeEventListener('livewire:navigated', this._navHandler)
            this._navHandler = null
        }
    },
})

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
    Alpine.data('apexChart', apexChartDef)
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
