<template>
  <SystemToolsPage
    :title="$t('Database Health')"
    :loading="loading"
    :loaded="!!database"
    :refreshing="refreshing"
    @refresh="fetchDatabase(true)"
  >
    <div class="wpsr-settings-container">
      <div class="wpsr-settings-header">
        <h3 class="wpsr-feed-settings-title">{{ $t('Database Tables') }}</h3>
      </div>
      <div class="wpsr-system-tools-monitor-body">
        <p class="wpsr-system-tools-intro wpsr-paragraph-small">
          {{ $t('The plugin stores reviews, feed caches, review forms and categories in these tables. If an update was interrupted, a table may be missing. Deactivating and activating the plugin creates it again.') }}
        </p>

        <div :class="['wpsr-system-tools-banner', missingTables ? 'wpsr-system-tools-banner-error' : 'wpsr-system-tools-banner-ok']">
          <el-icon class="wpsr-system-tools-icon">
            <component :is="missingTables ? 'CircleCloseFilled' : 'CircleCheckFilled'" />
          </el-icon>
          <span v-if="missingTables">{{ $t('%1$s of %2$s tables are missing.', missingTables, database.tables.length) }}</span>
          <span v-else>{{ $t('All tables are present.') }}</span>
        </div>

        <div class="wpsr-system-tools-task-list">
          <div v-for="table in database.tables" :key="table.name" class="wpsr-system-tools-task">
            <div class="wpsr-system-tools-task-info">
              <p class="wpsr-system-tools-task-title">{{ table.label || table.name }}</p>
              <span class="wpsr-system-tools-task-meta">
                {{ $t('Table: %s', table.name) }}
                <template v-if="table.exists">&middot; {{ $t('%s rows', numberWithCommas(table.rows)) }}</template>
              </span>
            </div>
            <span :class="['wpsr-system-tools-pill', { 'wpsr-system-tools-pill-error': !table.exists }]">
              {{ table.exists ? $t('Healthy') : $t('Missing') }}
            </span>
          </div>
        </div>

        <div class="wpsr-system-tools-task-list">
          <div class="wpsr-system-tools-task">
            <div class="wpsr-system-tools-task-info">
              <p class="wpsr-system-tools-task-title">{{ $t('Cached feeds and reviews') }}</p>
              <span class="wpsr-system-tools-task-meta">
                {{ $t('An expired cache is refreshed on the next scheduled run. Many expired caches can mean that scheduled tasks do not run.') }}
              </span>
            </div>
            <span class="wpsr-system-tools-value">
              {{ $t('%1$s total, %2$s expired', database.caches.total, database.caches.expired) }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </SystemToolsPage>
</template>

<script>
import SystemToolsPage from './SystemToolsPage';
import { SystemToolsMixin } from './SystemToolsMixin';

export default {
  name: 'DatabaseHealth',
  mixins: [SystemToolsMixin],
  components: {
    SystemToolsPage
  },
  data() {
    return {
      loading: true,
      refreshing: false,
      database: null
    };
  },
  computed: {
    missingTables() {
      return this.database ? this.database.tables.filter(table => !table.exists).length : 0;
    }
  },
  methods: {
    fetchDatabase(isRefresh = false) {
      if (isRefresh) {
        this.refreshing = true;
      }

      this.$get('system-tools/database')
        .then(response => {
          this.database = response.database;
        })
        .catch(error => this.handleError(error))
        .always(() => {
          this.loading = false;
          this.refreshing = false;
        });
    }
  },
  mounted() {
    this.fetchDatabase();
  }
};
</script>
