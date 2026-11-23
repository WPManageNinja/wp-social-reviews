<template>
  <SystemToolsPage
    :title="$t('Connected Platforms')"
    :loading="loading"
    :loaded="!!platforms"
    :refreshing="refreshing"
    @refresh="fetchPlatforms(true)"
  >
    <div class="wpsr-settings-container">
      <div class="wpsr-setting-section wpsr-mb-24">
        <div class="wpsr-settings-header wpsr-d-flex wpsr-jc-between wpsr-flex-align-center">
          <h3 class="wpsr-feed-settings-title">{{ $t('Connected Platforms') }}</h3>
          <el-button
            v-if="hasPlatformLog"
            class="wpsr_default_btn"
            :loading="clearingLog"
            @click="clearLogVisible = true"
          >
            {{ $t('Clear Error Log') }}
          </el-button>
        </div>
        <div class="wpsr-feed-settings-content">
          <p v-if="!platforms.length" class="wpsr-system-tools-empty wpsr-paragraph-small">
            {{ $t('No platform is connected yet.') }}
          </p>
          <div v-else class="wpsr-system-tools-table">
            <el-table :data="platforms">
              <el-table-column :label="$t('Platform')" min-width="160">
                <template #default="{ row }">
                  <div class="wpsr-heading-text">{{ row.name }}</div>
                  <div class="wpsr-paragraph-extra-small wpsr-disabled-text-color">
                    {{ row.type === 'feed' ? $t('Feed') : $t('Reviews') }}
                  </div>
                </template>
              </el-table-column>
              <el-table-column :label="$t('Status')" min-width="160">
                <template #default="{ row }">
                  <span class="wpsr-system-tools-value">
                    <el-icon :class="statusClass(row.status)"><component :is="statusIcon(row.status)" /></el-icon>
                    {{ platformStatusText(row) }}
                  </span>
                  <div v-if="row.revoked" class="wpsr-paragraph-extra-small wpsr-disabled-text-color">
                    {{ $t('Revoked accounts: %s', row.revoked) }}
                  </div>
                </template>
              </el-table-column>
              <el-table-column :label="$t('Last Log Entry')" min-width="280">
                <template #default="{ row }">
                  <span class="wpsr-paragraph-extra-small">{{ row.last_error || '—' }}</span>
                </template>
              </el-table-column>
            </el-table>
          </div>
        </div>
      </div>
    </div>

    <el-dialog
      v-model="clearLogVisible"
      class="wpsr-confirmation-modal"
      width="40%"
      :close-on-click-modal="!clearingLog"
      :show-close="!clearingLog"
    >
      <div class="wpsr-delete-content">
        <h4>{{ $t('Are you sure you want to clear the error log?') }}</h4>
        <p>{{ $t('Connections, feeds and reviews are not changed.') }}</p>
        <div class="wpsr-delete-details">
          <p><strong>{{ $t('Platforms with log entries:') }}</strong> {{ loggedPlatformNames }}</p>
        </div>
      </div>
      <template #footer>
        <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
          <el-button class="wpsr_default_btn" :disabled="clearingLog" @click="clearLogVisible = false">{{ $t('Cancel') }}</el-button>
          <el-button type="primary" class="wpsr_primary_btn" :loading="clearingLog" @click="clearErrorLog">{{ $t('Confirm') }}</el-button>
        </div>
      </template>
    </el-dialog>
  </SystemToolsPage>
</template>

<script>
import SystemToolsPage from './SystemToolsPage';
import { SystemToolsMixin } from './SystemToolsMixin';

export default {
  name: 'ConnectedPlatforms',
  mixins: [SystemToolsMixin],
  components: {
    SystemToolsPage
  },
  data() {
    return {
      loading: true,
      refreshing: false,
      clearingLog: false,
      clearLogVisible: false,
      platforms: null
    };
  },
  computed: {
    loggedPlatforms() {
      return this.platforms.filter(platform => platform.last_error || platform.connection_errors);
    },
    hasPlatformLog() {
      return this.loggedPlatforms.length > 0;
    },
    loggedPlatformNames() {
      return this.loggedPlatforms.map(platform => platform.name).join(', ');
    }
  },
  methods: {
    fetchPlatforms(isRefresh = false) {
      if (isRefresh) {
        this.refreshing = true;
      }

      this.$get('system-tools/platforms')
        .then(response => {
          this.platforms = response.platforms;
        })
        .catch(error => this.handleError(error))
        .always(() => {
          this.loading = false;
          this.refreshing = false;
        });
    },
    clearErrorLog() {
      this.clearingLog = true;

      this.$del('settings/reset-error-log')
        .then(response => {
          this.clearLogVisible = false;
          this.handleSuccess(response.message);
          this.fetchPlatforms(true);
        })
        .catch(error => this.handleError(error))
        .always(() => {
          this.clearingLog = false;
        });
    }
  },
  mounted() {
    this.fetchPlatforms();
  }
};
</script>
