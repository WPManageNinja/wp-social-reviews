<template>
  <div v-if="hasPermission(['wpsn_full_access'])" class="wpsr-system-tools">
    <div v-if="loading" class="wpsr-general-settings-wrapper">
      <div class="wpsr-settings-container">
        <WpsrSkeleton :rows="15" />
      </div>
    </div>

    <div v-else-if="loaded">
      <div class="wpsr-gs-header wpsr-d-flex wpsr-jc-between wpsr-flex-align-center">
        <h2 class="wpsr-gs-header-title">{{ title }}</h2>
        <div class="wpsr-d-flex wpsr-gap-8">
          <el-button class="wpsr_default_btn" :loading="refreshing" @click="$emit('refresh')">
            {{ $t('Refresh') }}
          </el-button>
          <el-button type="primary" class="wpsr_primary_btn" @click="openReport">
            {{ $t('Copy System Report') }}
          </el-button>
        </div>
      </div>

      <div class="wpsr-general-settings-wrapper">
        <slot />
      </div>
    </div>

    <el-dialog
      v-model="reportVisible"
      class="wpsr-connection-modal"
      width="50%"
      :close-on-click-modal="!reportLoading"
      :show-close="!reportLoading"
    >
      <template #header>
        <div class="wpsr-connection-modal-header">
          <h4 class="wpsr-dialog-title">{{ $t('System Report') }}</h4>
          <p class="wpsr-connection-modal-subheading">{{ $t('Paste this report into your support ticket.') }}</p>
        </div>
      </template>
      <div class="wpsr-connection-modal-body">
        <WpsrSkeleton v-if="reportLoading" :rows="8" />
        <el-input
          v-else
          v-model="reportText"
          type="textarea"
          class="wpsr-text-area"
          :rows="14"
          :aria-label="$t('System Report')"
          readonly
        />
      </div>
      <template #footer>
        <div class="wpsr-d-flex wpsr-jc-end wpsr-gap-8">
          <el-button class="wpsr_default_btn" :disabled="reportLoading" @click="reportVisible = false">{{ $t('Cancel') }}</el-button>
          <el-button
            type="primary"
            class="wpsr_primary_btn copy"
            :disabled="reportLoading || !reportText"
            :data-clipboard-text="reportText"
          >
            {{ $t('Copy System Report') }}
          </el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import { SystemToolsMixin } from './SystemToolsMixin';

/**
 * The shell every System Admin Tools page shares: header, loading state and the system report dialog.
 */
export default {
  name: 'SystemToolsPage',
  mixins: [SystemToolsMixin],
  props: {
    title: {
      type: String,
      required: true
    },
    loading: {
      type: Boolean,
      default: false
    },
    loaded: {
      type: Boolean,
      default: false
    },
    refreshing: {
      type: Boolean,
      default: false
    }
  },
  emits: ['refresh'],
  data() {
    return {
      reportVisible: false,
      reportLoading: false,
      reportText: '',
      reportChecks: this.newRestChecks()
    };
  },
  methods: {
    openReport() {
      this.reportVisible = true;
      this.reportLoading = true;
      this.reportText = '';

      const report = new Promise((resolve, reject) => {
        this.$get('system-tools').then(response => resolve(response.report)).catch(reject);
      });

      Promise.all([report, this.runRestChecks(this.reportChecks)])
        .then(([fullReport]) => {
          this.reportText = this.buildReportText(fullReport);
        })
        .catch(error => {
          this.reportVisible = false;
          this.handleError(error);
        })
        .finally(() => {
          this.reportLoading = false;
        });
    },
    buildReportText(report) {
      const lines = ['### WP Social Ninja System Report ###', '', '-- Environment --'];
      report.environment.forEach(item => lines.push(`${item.label}: ${item.value}`));

      lines.push('', '-- REST API --');
      this.reportChecks.forEach(check => lines.push(`${check.method}: ${check.label}${check.detail ? ` (${check.detail})` : ''}`));

      lines.push('', '-- Scheduled Tasks --');
      lines.push(`Server cron: ${report.cron.has_server_cron ? 'Yes' : 'No (WP-Cron)'}`);
      report.cron.events.forEach(event => {
        lines.push(`${event.hook}: ${event.schedule || '-'} / ${this.cronStatusText(event)}`);
      });

      lines.push('', '-- Platforms --');
      if (!report.platforms.length) {
        lines.push('None');
      }
      report.platforms.forEach(platform => {
        lines.push(`${platform.name} (${platform.type}): ${this.platformStatusText(platform)}${platform.revoked ? ', revoked: ' + platform.revoked : ''}`);
      });

      lines.push('', '-- Database --');
      report.database.tables.forEach(table => {
        lines.push(`${table.name}: ${table.exists ? table.rows + ' rows' : 'MISSING'}`);
      });
      lines.push(`Caches: ${report.database.caches.total} total, ${report.database.caches.expired} expired`);

      return lines.join('\n');
    }
  },
  mounted() {
    this.clipboard();
  }
};
</script>
