document.addEventListener('DOMContentLoaded', () => {
    initCharts();
    initAssignDialog();
});

/*
 * Tilavärit vastaavat style.css:n tilamerkkejä.
 */
const STATUS_COLORS = {
    new: '#3b6fc4',
    in_progress: '#c98a1b',
    waiting_student: '#7b52c0',
    resolved: '#2e8a55',
    closed: '#8a93a0'
};

function initCharts() {
    const dataElement = document.getElementById('admin-chart-data');

    if (!dataElement) {
        return;
    }

    // Jos Chart.js ei latautunut, näytetään luvut taulukkoina.
    if (typeof window.Chart === 'undefined') {
        document.querySelectorAll('.chart-card').forEach((card) => {
            card.querySelector('.chart-canvas')?.remove();
            card.querySelector('.chart-table')?.setAttribute('open', '');
        });
        return;
    }

    let data;

    try {
        data = JSON.parse(dataElement.textContent);
    } catch {
        return;
    }

    const styles = getComputedStyle(document.documentElement);
    const textColor = styles.getPropertyValue('--text-muted').trim() || '#5a6472';
    const gridColor = styles.getPropertyValue('--border').trim() || '#dde1e6';
    const accent = styles.getPropertyValue('--accent').trim() || '#1f4f9c';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    Chart.defaults.font.family = styles.getPropertyValue('--font').trim() || 'system-ui, sans-serif';
    Chart.defaults.color = textColor;
    Chart.defaults.maintainAspectRatio = false;

    if (reduceMotion) {
        Chart.defaults.animation = false;
    }

    const integerAxis = {
        beginAtZero: true,
        ticks: { precision: 0 },
        grid: { color: gridColor }
    };

    new Chart(document.getElementById('chart-categories'), {
        type: 'bar',
        data: {
            labels: data.categories.labels,
            datasets: [{
                label: 'Tikettejä',
                data: data.categories.counts,
                backgroundColor: accent,
                borderRadius: 3,
                maxBarThickness: 28
            }]
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: integerAxis,
                y: { grid: { display: false } }
            }
        }
    });

    new Chart(document.getElementById('chart-statuses'), {
        type: 'doughnut',
        data: {
            labels: data.statuses.labels,
            datasets: [{
                data: data.statuses.counts,
                backgroundColor: data.statuses.keys.map((key) => STATUS_COLORS[key]),
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 14 }
                }
            }
        }
    });

    new Chart(document.getElementById('chart-monthly'), {
        type: 'bar',
        data: {
            labels: data.monthly.labels,
            datasets: [
                { label: 'Avoimet', data: data.monthly.open, backgroundColor: STATUS_COLORS.new },
                { label: 'Ratkaistu', data: data.monthly.resolved, backgroundColor: STATUS_COLORS.resolved },
                { label: 'Suljettu', data: data.monthly.closed, backgroundColor: STATUS_COLORS.closed }
            ]
        },
        options: {
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 14 }
                }
            },
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { ...integerAxis, stacked: true }
            }
        }
    });
}

/*
 * "Määritä käsittelijä" avaa modaalin. Ilman JavaScriptiä
 * linkki vie erilliselle assign-ticket.php-sivulle.
 */
function initAssignDialog() {
    const dialog = document.getElementById('assign-dialog');

    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    const form = dialog.querySelector('form');
    const ticketInput = form.elements.ticket_id;
    const select = form.elements.assigned_to;
    const ticketText = dialog.querySelector('[data-dialog-ticket]');
    const currentText = dialog.querySelector('[data-dialog-current]');
    const unassignButton = dialog.querySelector('[data-dialog-unassign]');
    let opener = null;

    document.querySelectorAll('[data-assign-ticket]').forEach((link) => {
        link.setAttribute('role', 'button');
        link.setAttribute('aria-haspopup', 'dialog');

        link.addEventListener('click', (event) => {
            event.preventDefault();
            opener = link;

            const assignedTo = link.dataset.assignedTo || '';

            ticketInput.value = link.dataset.assignTicket;
            ticketText.textContent = `#${link.dataset.assignTicket} ${link.dataset.ticketTitle}`;
            currentText.textContent = link.dataset.assignedName || 'Ei määritetty';
            select.value = assignedTo;
            unassignButton.hidden = assignedTo === '';

            dialog.showModal();
            select.focus();
        });

        // role="button" -linkin pitää toimia myös välilyönnillä.
        link.addEventListener('keydown', (event) => {
            if (event.key === ' ') {
                event.preventDefault();
                link.click();
            }
        });
    });

    dialog.querySelector('[data-dialog-close]').addEventListener('click', () => {
        dialog.close();
    });

    // Klikkaus taustalle sulkee modaalin.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    dialog.addEventListener('close', () => {
        opener?.focus();
    });
}
