//================================ NAMESPACES
// Dades GraphQL per als gràfics del dashboard admin (bucles explícits, sense .map).

//================================ CONSTANTS

export const ADMIN_DASH_CHARTS_GQL = `
  query AdminDashCharts {
    adminDashboardRevenueByDay(days: 30) { date revenue }
    adminDashboardOrdersPaidByDay(days: 30) { date count }
  }
`;

//================================ FUNCIONS PÚBLIQUES

/**
 * A. Crida GraphQL i retorna sèries o missatge d’error.
 */
export async function fetchAdminDashboardChartData (postGraphql) {
  const res = await postGraphql(ADMIN_DASH_CHARTS_GQL);
  const errs = res.errors;
  if (Array.isArray(errs) && errs.length > 0) {
    let detail = '';
    if (errs[0] && typeof errs[0].message === 'string') {
      detail = errs[0].message;
    }
    if (detail !== '') {
      return { error: 'Gràfics: ' + detail };
    }
    return { error: 'No s’han pogut carregar els gràfics.' };
  }
  const d = res.data;
  if (!d) {
    return { error: 'Resposta GraphQL sense dades.' };
  }
  const rev = d.adminDashboardRevenueByDay;
  const ord = d.adminDashboardOrdersPaidByDay;
  if (!rev || !ord || !Array.isArray(rev) || !Array.isArray(ord)) {
    return { error: 'Dades dels gràfics incompletes.' };
  }
  return { error: '', revenuePoints: rev, ordersPoints: ord };
}

/**
 * B. Construeix labels i valors amb bucles (Chart.js només al client).
 * Retorna una funció destroy() per alliberar els charts.
 */
export async function mountAdminDashboardCharts (revCanvas, ordCanvas, revenuePoints, ordersPoints) {
  const mod = await import('chart.js');
  const Chart = mod.Chart;
  const registerables = mod.registerables;
  Chart.register(...registerables);

  const labels = [];
  const revData = [];
  const ordData = [];
  for (let i = 0; i < revenuePoints.length; i++) {
    labels.push(revenuePoints[i].date);
    revData.push(parseFloat(revenuePoints[i].revenue));
  }
  for (let j = 0; j < ordersPoints.length; j++) {
    ordData.push(ordersPoints[j].count);
  }

  const tickFont = { family: 'Inter, system-ui, sans-serif', size: 10 };
  const gridColor = 'rgba(229, 226, 225, 0.06)';
  const borderMuted = 'rgba(74, 71, 51, 0.35)';

  let revChart = new Chart(revCanvas, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Ingressos (EUR)',
          data: revData,
          borderColor: '#f7e628',
          backgroundColor: 'rgba(247, 230, 40, 0.14)',
          tension: 0.2,
          borderWidth: 2,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      layout: {
        padding: { top: 8, right: 8, bottom: 4, left: 4 },
      },
      plugins: {
        legend: {
          labels: {
            color: '#ccc7ac',
            font: tickFont,
          },
        },
      },
      scales: {
        x: {
          ticks: { color: '#959178', maxRotation: 45, font: tickFont },
          grid: { color: gridColor },
          border: { color: borderMuted },
        },
        y: {
          ticks: { color: '#959178', font: tickFont },
          grid: { color: gridColor },
          border: { color: borderMuted },
        },
      },
    },
  });

  let ordChart = new Chart(ordCanvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Comandes pagades',
          data: ordData,
          backgroundColor: 'rgba(210, 201, 122, 0.4)',
          borderColor: '#d2c97a',
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      layout: {
        padding: { top: 8, right: 8, bottom: 4, left: 4 },
      },
      plugins: {
        legend: {
          labels: {
            color: '#ccc7ac',
            font: tickFont,
          },
        },
      },
      scales: {
        x: {
          ticks: { color: '#959178', maxRotation: 45, font: tickFont },
          grid: { display: false },
          border: { color: borderMuted },
        },
        y: {
          ticks: { color: '#959178', stepSize: 1, font: tickFont },
          grid: { color: gridColor },
          border: { color: borderMuted },
        },
      },
    },
  });

  function destroy () {
    if (revChart) {
      revChart.destroy();
      revChart = null;
    }
    if (ordChart) {
      ordChart.destroy();
      ordChart = null;
    }
  }

  return { destroy };
}
