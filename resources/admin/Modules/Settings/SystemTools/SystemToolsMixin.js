import { CircleCheckFilled, WarningFilled, CircleCloseFilled, InfoFilled } from '@element-plus/icons-vue';

const REST_METHODS = ['GET', 'POST', 'PUT', 'DELETE'];

export const SystemToolsMixin = {
  components: {
    CircleCheckFilled,
    WarningFilled,
    CircleCloseFilled,
    InfoFilled
  },
  methods: {
    newRestChecks() {
      return REST_METHODS.map(method => ({ method, status: 'checking', label: this.$t('Checking...'), detail: '' }));
    },
    // Resolves once every method has answered, so the report never waits on a rejected request.
    runRestChecks(checks) {
      const helpers = { GET: this.$get, POST: this.$post, PUT: this.$put, DELETE: this.$del };

      return Promise.all(checks.map(check => {
        check.status = 'checking';
        check.label = this.$t('Checking...');
        check.detail = '';

        return new Promise(resolve => {
          helpers[check.method]('system-tools/rest-check')
            .then(response => {
              const ok = response && response.ok;
              check.status = ok ? 'ok' : 'error';
              check.label = ok ? this.$t('Operational') : this.$t('Down');
              check.detail = ok ? '' : 'unexpected response';
            })
            .catch(error => {
              check.status = 'error';
              check.label = this.$t('Down');
              check.detail = `HTTP ${error && error.status ? error.status : 0}`;
            })
            .always(() => resolve());
        });
      }));
    },
    cronStatusText(row) {
      if (!row.is_scheduled) {
        return this.$t('Not scheduled');
      }

      if (row.is_overdue) {
        return this.$t('Overdue (%s)', row.next_run_in);
      }
      // Past its time but inside the grace period: WP-Cron has not picked it up yet.
      if (row.is_past) {
        return this.$t('Due (%s)', row.next_run_in);
      }

      return this.$t('Next run %s', row.next_run_in);
    },
    platformStatusText(row) {
      if (row.critical) {
        return this.$t('Connection error');
      }
      if (row.connection_errors) {
        return this.$t('%s connection warnings', row.connection_errors);
      }

      return row.connected ? this.$t('Connected') : this.$t('Not connected');
    },
    statusIcon(status) {
      const icons = {
        ok: 'CircleCheckFilled',
        checking: 'WarningFilled',
        warning: 'WarningFilled',
        error: 'CircleCloseFilled'
      };

      return icons[status] || 'InfoFilled';
    },
    statusClass(status) {
      return 'wpsr-system-tools-icon wpsr-system-tools-icon-' + status;
    }
  }
};
