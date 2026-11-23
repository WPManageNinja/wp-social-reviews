<template>
  <SystemToolsPage
    :title="$t('Cron Job Monitor')"
    :loading="loading"
    :loaded="!!cron"
    :refreshing="refreshing"
    @refresh="fetchCron(true)"
  >
    <div class="wpsr-settings-container wpsr-feed-global-settings-wrapper">
      <div class="wpsr-settings-header">
        <h3 class="wpsr-feed-settings-title">{{ $t('REST API Status') }}</h3>
      </div>
      <div class="wpsr-system-tools-rest-grid">
        <div v-for="check in restChecks" :key="check.method" class="wpsr-system-tools-tile">
          <p class="wpsr-system-tools-tile-label">{{ $t('%s Request Status', check.method) }}</p>
          <span class="wpsr-system-tools-badge">
            <el-icon :class="statusClass(check.status)">
              <component :is="statusIcon(check.status)" />
            </el-icon>
            {{ check.label }}
          </span>
        </div>
      </div>
      <p v-if="hasBlockedRest" class="wpsr-system-tools-rest-hint wpsr-paragraph-extra-small">
        {{ $t('The plugin admin cannot save changes while a request type is blocked. A security plugin or your host may block it.') }}
      </p>
    </div>

    <div v-if="!cron.has_server_cron" class="wpsr-system-tools-notice wpsr-mb-24">
      <el-icon class="wpsr-system-tools-icon wpsr-system-tools-icon-info"><InfoFilled /></el-icon>
      <p>{{ $t('Your site uses WP-Cron. We recommend setting up a server cron job.') }}</p>
      <a
        class="wpsr-system-tools-alert-link"
        href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/"
        target="_blank"
        rel="noopener noreferrer"
      >{{ $t('Read More') }}</a>
    </div>

    <div class="wpsr-settings-container">
      <div class="wpsr-settings-header wpsr-d-flex wpsr-jc-between wpsr-flex-align-center">
        <h3 class="wpsr-feed-settings-title">{{ $t('Cron Job Status') }}</h3>
        <el-button
          v-if="canManage && hasMissingCoreCron"
          class="wpsr_primary_light_btn"
          :loading="rescheduling"
          @click="rescheduleCrons"
        >
          {{ $t('Schedule Missing Tasks') }}
        </el-button>
      </div>
      <div class="wpsr-system-tools-monitor-body">
        <div class="wpsr-system-tools-stats">
          <div v-for="stat in cronStats" :key="stat.label" class="wpsr-system-tools-stat">
            <p class="wpsr-system-tools-tile-label">{{ stat.label }}</p>
            <span class="wpsr-system-tools-value">{{ stat.value }}</span>
          </div>
        </div>

        <div class="wpsr-system-tools-task-list">
          <div v-for="row in cron.events" :key="row.hook" class="wpsr-system-tools-task">
            <div class="wpsr-system-tools-task-info">
              <p class="wpsr-system-tools-task-title" :title="row.hook">{{ row.label }}</p>
              <span class="wpsr-system-tools-task-meta">
                <el-icon :class="statusClass(row.status)"><component :is="statusIcon(row.status)" /></el-icon>
                {{ cronStatusText(row) }}
                <template v-if="row.schedule">&middot; {{ row.schedule }}</template>
              </span>
            </div>
            <el-button
              v-if="canManage && row.can_run"
              class="wpsr_default_btn"
              :loading="runningHook === row.hook"
              :disabled="!!runningHook && runningHook !== row.hook"
              @click="confirmRow = row"
            >
              <el-icon v-if="runningHook !== row.hook" class="wpsr-mr-5"><VideoPlay /></el-icon>
              {{ $t('Run Manually') }}
            </el-button>
          </div>
        </div>
      </div>
    </div>

    <el-dialog
      :model-value="!!confirmRow"
      class="wpsr-confirmation-modal"
      width="40%"
      :close-on-click-modal="!runningHook"
      :show-close="!runningHook"
      @close="confirmRow = null"
    >
      <div v-if="confirmRow" class="wpsr-delete-content">
        <h4>{{ $t('Are you sure you want to run "%s" now?', confirmRow.label) }}</h4>
        <p>{{ $t('It runs right away instead of waiting for its schedule.') }}</p>
      </div>
      <template #footer>
        <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
          <el-button class="wpsr_default_btn" :disabled="!!runningHook" @click="confirmRow = null">{{ $t('Cancel') }}</el-button>
          <el-button type="primary" class="wpsr_primary_btn" :loading="!!runningHook" @click="runCron(confirmRow)">{{ $t('Confirm') }}</el-button>
        </div>
      </template>
    </el-dialog>
  </SystemToolsPage>
</template>

<script>
import { VideoPlay } from '@element-plus/icons-vue';
import SystemToolsPage from './SystemToolsPage';
import { SystemToolsMixin } from './SystemToolsMixin';

export default {
  name: 'CronMonitor',
  mixins: [SystemToolsMixin],
  components: {
    SystemToolsPage,
    VideoPlay
  },
  data() {
    return {
      loading: true,
      refreshing: false,
      rescheduling: false,
      runningHook: '',
      confirmRow: null,
      cron: null,
      server: null,
      canManage: false,
      restChecks: this.newRestChecks()
    };
  },
  computed: {
    hasBlockedRest() {
      return this.restChecks.some(check => check.status === 'error');
    },
    cronStats() {
      return [
        { label: this.$t('Server Memory Limit'), value: (this.server && this.server.memory_limit) || '—' },
        { label: this.$t('Max Execution Time'), value: (this.server && this.server.max_execution_time) || '—' },
        { label: this.$t('Cron Runner'), value: this.cron.has_server_cron ? this.$t('Server cron') : this.$t('WP-Cron') }
      ];
    },
    hasMissingCoreCron() {
      return this.cron.events.some(event => event.is_core && !event.is_scheduled);
    }
  },
  methods: {
    fetchCron(isRefresh = false) {
      if (isRefresh) {
        this.refreshing = true;
      }
      this.runRestChecks(this.restChecks);

      this.$get('system-tools/cron')
        .then(response => {
          this.cron = response.cron;
          this.server = response.server;
          this.canManage = !!response.can_manage;
        })
        .catch(error => this.handleError(error))
        .always(() => {
          this.loading = false;
          this.refreshing = false;
        });
    },
    runCron(row) {
      this.runningHook = row.hook;

      this.$post('system-tools/cron/run', { hook: row.hook })
        .then(response => {
          this.cron = response.cron;
          this.handleSuccess(response.message);
        })
        .catch(error => this.handleError(error))
        .always(() => {
          this.runningHook = '';
          this.confirmRow = null;
        });
    },
    rescheduleCrons() {
      this.rescheduling = true;

      this.$post('system-tools/cron/reschedule')
        .then(response => {
          this.cron = response.cron;
          this.handleSuccess(response.message);
        })
        .catch(error => this.handleError(error))
        .always(() => {
          this.rescheduling = false;
        });
    }
  },
  mounted() {
    this.fetchCron();
  }
};
</script>
