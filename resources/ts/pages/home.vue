<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useTheme } from 'vuetify'
import ApexCharts from 'apexcharts'

// 1. Types & Interfaces
interface MicroMetric {
  title: string
  value: string | number
  subtext: string
  trendColor: string
  sparklineData: number[]
  icon: string
}

interface ProjectHealth {
  name: string
  client: string
  progress: number
  status: 'Deploying' | 'Active Sprint' | 'Code Review' | 'UAT Testing'
  color: string
}

// Get current theme
const { global } = useTheme()

// 2. Metrics & Datasets (Software Agency Context)
const statCards = ref<MicroMetric[]>([
  {
    title: 'Monthly Recurring Revenue',
    value: '$48,250',
    subtext: '+12.4% vs last month',
    trendColor: 'success',
    sparklineData: [10, 18, 14, 22, 19, 28, 25],
    icon: 'tabler-currency-dollar'
  },
  {
    title: 'Active Projects',
    value: '14',
    subtext: '2 nearing completion',
    trendColor: 'info',
    sparklineData: [15, 17, 16, 20, 22, 22, 24],
    icon: 'tabler-folders'
  },
  {
    title: 'Team Utilization',
    value: '88.5%',
    subtext: 'Target 85%',
    trendColor: 'warning',
    sparklineData: [12, 18, 15, 24, 20, 25, 27],
    icon: 'tabler-users'
  }
])

const productionProjects = ref<ProjectHealth[]>([
  { name: 'Quantum ERP custom modules', client: 'Omran Construction Group', progress: 85, status: 'Active Sprint', color: 'primary' },
  { name: 'Mobile CRM Native Application', client: 'Ariana Real Estate', progress: 98, status: 'UAT Testing', color: 'success' },
  { name: 'Patient Management Engine', client: 'Kabul Dental Complex', progress: 40, status: 'Code Review', color: 'warning' },
  { name: 'Core API Infrastructure Rewrite', client: 'Internal Product Architecture', progress: 15, status: 'Deploying', color: 'error' }
])

// Helper to construct dynamic smooth SVG paths
const generateSvgPath = (data: number[], width: number, height: number): string => {
  if (data.length === 0) return ''
  const max = Math.max(...data)
  const min = Math.min(...data)
  const range = max - min === 0 ? 1 : max - min

  return data.map((val, index) => {
    const x = (index / (data.length - 1)) * width
    const y = height - ((val - min) / range) * (height - 4) - 2
    return `${index === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`
  }).join(' ')
}

// Main velocity chart data
const velocityChartOptions = ref<ApexCharts.ApexOptions>({
  chart: {
    type: 'line',
    height: 300,
    toolbar: {
      show: false
    },
    zoom: {
      enabled: false
    }
  },
  colors: ['rgba(var(--v-theme-primary), 1)', 'rgba(var(--v-theme-info), 1)'],
  series: [
    {
      name: 'Commits',
      data: [120, 150, 130, 180, 160, 200, 170]
    },
    {
      name: 'Deployments',
      data: [80, 90, 85, 110, 100, 120, 115]
    }
  ],
  stroke: {
    width: 3,
    curve: 'smooth'
  },
  fill: {
    type: 'solid',
    opacity: 0.1
  },
  xaxis: {
    categories: ['WK 23', 'WK 24', 'WK 25', 'WK 26', 'WK 27', 'WK 28', 'WK 29'],
    labels: {
      style: {
        colors: 'rgba(var(--v-theme-on-surface), 0.6)'
      }
    }
  },
  yaxis: {
    labels: {
      style: {
        colors: 'rgba(var(--v-theme-on-surface), 0.6)'
      }
    }
  },
  grid: {
    borderColor: 'rgba(var(--v-theme-border), 0.1)'
  },
  tooltip: {
    theme: global.current.value.dark ? 'dark' : 'light'
  },
  legend: {
    show: false
  }
})

// CI/CD Success Rate chart
const cicdChartOptions = ref<ApexCharts.ApexOptions>({
  chart: {
    type: 'bar',
    height: 100,
    sparkline: {
      enabled: true
    },
    toolbar: {
      show: false
    }
  },
  colors: ['rgba(var(--v-theme-success), 0.75)', 'rgba(var(--v-theme-success), 0.75)',
          'rgba(var(--v-theme-error), 0.75)', 'rgba(var(--v-theme-success), 0.75)',
          'rgba(var(--v-theme-success), 0.75)', 'rgba(var(--v-theme-success), 0.75)',
          'rgba(var(--v-theme-success), 0.75)'],
  series: [{
    data: [90, 95, 35, 98, 85, 100, 92]
  }],
  plotOptions: {
    bar: {
      borderRadius: 4,
      columnWidth: '60%'
    }
  },
  xaxis: {
    categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    labels: {
      show: false
    }
  },
  yaxis: {
    labels: {
      show: false
    }
  },
  tooltip: {
    theme: global.current.value.dark ? 'dark' : 'light',
    y: {
      formatter: (val: number) => `${val}%`
    }
  }
})

// API Response Time chart
const apiChartOptions = ref<ApexCharts.ApexOptions>({
  chart: {
    type: 'area',
    height: 80,
    sparkline: {
      enabled: true
    },
    toolbar: {
      show: false
    }
  },
  colors: ['rgba(var(--v-theme-info), 0.7)'],
  series: [{
    data: [0, 32, 42, 18, 8]
  }],
  stroke: {
    width: 2,
    curve: 'smooth'
  },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 0.1,
      opacityFrom: 0.3,
      opacityTo: 0,
      stops: [0, 90, 100],
      colorStops: [
        {
          offset: 0,
          color: 'rgba(var(--v-theme-info), 0.3)',
          opacity: 0.3
        },
        {
          offset: 100,
          color: 'rgba(var(--v-theme-info), 0.05)',
          opacity: 0.05
        }
      ]
    }
  },
  xaxis: {
    labels: {
      show: false
    }
  },
  yaxis: {
    labels: {
      show: false
    }
  },
  tooltip: {
    theme: global.current.value.dark ? 'dark' : 'light'
  }
})

// Chart refs
const velocityChart = ref<HTMLElement | null>(null)
const cicdChart = ref<HTMLElement | null>(null)
const apiChart = ref<HTMLElement | null>(null)

onMounted(() => {
  // Initialize charts after component is mounted
  if (velocityChart.value) {
    const chart = new ApexCharts(velocityChart.value, velocityChartOptions.value)
    chart.render()
  }

  if (cicdChart.value) {
    const chart = new ApexCharts(cicdChart.value, cicdChartOptions.value)
    chart.render()
  }

  if (apiChart.value) {
    const chart = new ApexCharts(apiChart.value, apiChartOptions.value)
    chart.render()
  }
})
</script>

<template>
  <div class="software-agency-dashboard pa-6">
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold text-high-emphasis">Dashboard Overview</h1>
      <p class="text-subtitle-1 text-medium-emphasis mt-1">Key metrics and project status at a glance</p>
    </div>

    <!-- Stats Cards -->
    <VRow class="mb-6" g="4">
      <VCol v-for="(metric, i) in statCards" :key="i" cols="12" sm="6" md="4">
        <VCard class="pa-4 h-100" :style="`background: ${global.current.value.dark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.03)'}`">
          <div class="d-flex flex-column h-100 justify-space-between">
            <div class="d-flex justify-space-between align-start w-100 mb-2">
              <span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">
                {{ metric.title }}
              </span>
              <VIcon :icon="metric.icon" size="20" class="text-medium-emphasis" />
            </div>

            <div class="d-flex align-center justify-space-between mt-auto pt-1">
              <div class="flex-grow-1 pr-2">
                <h3 class="text-h5 font-weight-bold text-high-emphasis">{{ metric.value }}</h3>
                <div class="text-caption font-weight-medium text-medium-emphasis mt-1">
                  {{ metric.subtext }}
                </div>
              </div>

              <div class="sparkline-container">
                <svg class="sparkline-svg" viewBox="0 0 75 32" width="75" height="32">
                  <path
                    :d="generateSvgPath(metric.sparklineData, 75, 32)"
                    fill="none"
                    :stroke="`rgba(var(--v-theme-${metric.trendColor}), 1)`"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </div>
            </div>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Main Chart Row -->
    <VRow class="match-height">
      <VCol cols="12" lg="8">
        <VCard class="pa-5 h-100">
          <div class="d-flex justify-space-between align-center mb-4">
            <div>
              <VCardTitle class="text-h6 font-weight-bold pa-0">Development Velocity</VCardTitle>
              <VCardSubtitle class="text-caption text-medium-emphasis pa-0 mt-0">
                Weekly progress and deployment metrics
              </VCardSubtitle>
            </div>
            <div class="d-flex gap-2">
              <VChip size="small" variant="tonal" color="primary" class="font-weight-medium">Commits</VChip>
              <VChip size="small" variant="text" color="info" class="font-weight-medium">Deployments</VChip>
            </div>
          </div>

          <div class="chart-container">
            <div ref="velocityChart" style="height: 300px;"></div>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" lg="4" class="d-flex flex-column gap-4">
        <VCard class="pa-4 flex-grow-1">
          <div class="d-flex justify-space-between align-start mb-2">
            <div>
              <div class="text-caption text-uppercase text-medium-emphasis font-weight-medium">CI/CD Success Rate</div>
              <div class="text-h6 font-weight-bold mt-1 text-high-emphasis">99.94%</div>
            </div>
            <VChip size="x-small" color="success" class="font-weight-bold" variant="tonal">Stable</VChip>
          </div>
          <div class="chart-container">
            <div ref="cicdChart" style="height: 100px;"></div>
          </div>
        </VCard>

        <VCard class="pa-4 flex-grow-1 api-response-card">
          <div class="d-flex justify-space-between align-start mb-1">
            <div>
              <div class="text-caption text-uppercase text-medium-emphasis font-weight-medium">API Response Time</div>
              <div class="text-h6 font-weight-bold mt-1 text-info">42 ms avg</div>
            </div>
            <span class="text-caption font-weight-bold text-success">Optimal</span>
          </div>
          <div class="chart-container">
            <div ref="apiChart" style="height: 80px;"></div>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Projects Table -->
    <VRow class="mt-4">
      <VCol cols="12">
        <VCard>
          <VCardItem class="py-4">
            <VCardTitle class="text-h6 font-weight-bold">Active Projects</VCardTitle>
            <VCardSubtitle class="text-caption text-medium-emphasis">
              Current project status and progress
            </VCardSubtitle>
          </VCardItem>

          <div class="responsive-table-container">
            <VTable class="custom-dashboard-table">
              <thead>
                <tr>
                  <th class="text-uppercase text-xs font-weight-medium">Project Name</th>
                  <th class="text-uppercase text-xs font-weight-medium">Client</th>
                  <th class="text-uppercase text-xs font-weight-medium">Progress</th>
                  <th class="text-uppercase text-xs font-weight-medium">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(project, index) in productionProjects" :key="index">
                  <td class="font-weight-medium text-high-emphasis">{{ project.name }}</td>
                  <td class="text-medium-emphasis">{{ project.client }}</td>
                  <td style="width: 35%;">
                    <div class="d-flex align-center gap-3">
                      <VProgressLinear
                        :model-value="project.progress"
                        :color="project.color"
                        height="6"
                        rounded
                        class="flex-grow-1"
                      />
                      <span class="text-caption font-weight-medium">{{ project.progress }}%</span>
                    </div>
                  </td>
                  <td>
                    <VChip
                      :color="project.color"
                      size="small"
                      variant="tonal"
                      class="font-weight-medium"
                    >
                      {{ project.status }}
                    </VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
/* Stats Cards */
.sparkline-container {
  width: 75px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.sparkline-svg {
  overflow: visible;
}

/* Chart Container */
.chart-container {
  position: relative;
  width: 100%;
}

/* API Response Card with modern blue shadow */
.api-response-card {
  position: relative;
  overflow: visible;
}

.api-response-card::before {
  content: '';
  position: absolute;
  bottom: -8px;
  left: 0;
  right: 0;
  height: 12px;
  background: linear-gradient(135deg,
    rgba(var(--v-theme-info), 0.2) 0%,
    rgba(var(--v-theme-info), 0.1) 50%,
    rgba(var(--v-theme-info), 0) 100%);
  border-radius: 0 0 12px 12px;
  filter: blur(4px);
  z-index: 0;
}

.api-response-card > * {
  position: relative;
  z-index: 1;
}

/* Table Styling */
.responsive-table-container {
  overflow-x: auto;
  width: 100%;
  scrollbar-width: none;
}

.responsive-table-container::-webkit-scrollbar {
  display: none;
}

.custom-dashboard-table :deep(th) {
  color: rgba(var(--v-theme-on-surface), 0.6) !important;
  background-color: rgba(var(--v-theme-surface), 0.5) !important;
  height: 48px !important;
  border-bottom: 1px solid rgba(var(--v-theme-border), 0.1) !important;
}

.custom-dashboard-table :deep(td) {
  height: 60px !important;
  border-bottom: 1px solid rgba(var(--v-theme-border), 0.05) !important;
}

.custom-dashboard-table :deep(tr:hover td) {
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}
</style>
