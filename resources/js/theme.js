const KEY = 'zentra-theme';

export function currentTheme() {
    return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
}

export function chartPalette() {
    const s = getComputedStyle(document.documentElement);
    const v = (name, fallback) => s.getPropertyValue(name).trim() || fallback;
    const light = currentTheme() === 'light';

    return {
        tick: v('--ui-faint', '#64748b'),
        grid: light ? 'rgba(155, 181, 173, 0.55)' : v('--ui-border', '#1e293b'),
        legend: v('--ui-muted', '#94a3b8'),
        tooltipBg: v('--ui-surface', '#0f172a'),
        tooltipBorder: v('--ui-border-strong', '#334155'),
        tooltipTitle: v('--ui-text', '#e2e8f0'),
        tooltipBody: v('--ui-text-secondary', '#cbd5e1'),
    };
}

function paintChart(chart) {
    if (!chart?.options) {
        return;
    }
    const p = chartPalette();
    const scales = chart.options.scales || {};
    Object.keys(scales).forEach((key) => {
        const sc = scales[key];
        if (sc?.ticks) {
            sc.ticks.color = p.tick;
        }
        if (sc?.grid) {
            sc.grid.color = p.grid;
        }
    });
    if (chart.options.plugins?.legend?.labels) {
        chart.options.plugins.legend.labels.color = p.legend;
    }
    if (chart.options.plugins?.tooltip) {
        const t = chart.options.plugins.tooltip;
        t.backgroundColor = p.tooltipBg;
        t.borderColor = p.tooltipBorder;
        t.titleColor = p.tooltipTitle;
        t.bodyColor = p.tooltipBody;
    }
}

function chartInstances() {
    const Chart = window.Chart;
    if (!Chart?.instances) {
        return [];
    }
    if (Chart.instances instanceof Map) {
        return [...Chart.instances.values()];
    }

    return Object.values(Chart.instances);
}

export function registerChartTheme() {
    const Chart = window.Chart;
    if (!Chart?.register || Chart.__zentraTheme) {
        return Boolean(Chart);
    }
    Chart.__zentraTheme = true;
    Chart.register({
        id: 'zentraTheme',
        beforeUpdate(chart) {
            paintChart(chart);
        },
    });

    return true;
}

export function refreshCharts() {
    registerChartTheme();
    chartInstances().forEach((chart) => {
        try {
            chart.update('none');
        } catch {
            // ignore
        }
    });
}

export function setTheme(theme) {
    const next = theme === 'light' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try {
        localStorage.setItem(KEY, next);
    } catch {
        // ignore
    }
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        meta.setAttribute('content', next === 'light' ? '#eaf4f1' : '#020617');
    }
    window.dispatchEvent(new CustomEvent('zentra-theme', { detail: { theme: next } }));
    refreshCharts();
}

export function toggleTheme() {
    setTheme(currentTheme() === 'light' ? 'dark' : 'light');
}

window.ZentraTheme = {
    current: currentTheme,
    set: setTheme,
    toggle: toggleTheme,
    chartPalette,
    refreshCharts,
};

[0, 80, 300, 1000, 2500].forEach((ms) => {
    window.setTimeout(registerChartTheme, ms);
});
window.addEventListener('load', () => {
    registerChartTheme();
    refreshCharts();
});
window.addEventListener('zentra-theme', refreshCharts);
