<template>
  <el-dialog
      class="wpsr-confirmation-modal wpsr-manage-categories-modal"
      :model-value="modelValue"
      @update:model-value="$emit('update:modelValue', $event)"
      @open="editingId = null"
      width="min(90vw, 560px)">
    <template #header>
      <h4 class="wpsr-dialog-title">{{ $t('Manage Categories') }}</h4>
      <p class="wpsr-modal-subtitle">{{ $t('Rename or delete the categories used across your reviews.') }}</p>
    </template>

    <div v-if="loading" class="wpsr-manage-categories-empty">
      <p>{{ $t('Loading categories…') }}</p>
    </div>

    <div v-else-if="loadError" class="wpsr-manage-categories-empty">
      <p>{{ $t('Could not load categories. Please try again.') }}</p>
      <el-button class="wpsr_default_btn" size="small" @click="$emit('retry')">{{ $t('Retry') }}</el-button>
    </div>

    <!-- Empty only once a load has actually succeeded, so a failed request never
         reads as "no categories" and invites a duplicate. -->
    <div v-else-if="loaded && !categories.length" class="wpsr-manage-categories-empty">
      <p>{{ $t('No categories yet. Assign one to a review to create your first.') }}</p>
    </div>

    <ul v-else class="wpsr-manage-categories-list">
      <li v-for="category in categories" :key="category.id" class="wpsr-manage-categories-row">
        <template v-if="editingId === category.id">
          <el-input
              class="wpsr-input-default wpsr-border-all-around"
              v-model="editingName"
              size="small"
              ref="renameInput"
              @keyup.enter="saveRename(category)"
              @keyup.esc="editingId = null"
          />
          <div class="wpsr-manage-categories-actions">
            <el-button class="wpsr_default_btn" size="small" @click="editingId = null">{{ $t('Cancel') }}</el-button>
            <el-button
                class="wpsr_primary_btn"
                type="primary"
                size="small"
                :loading="savingId === category.id"
                :disabled="savingId === category.id"
                @click="saveRename(category)"
            >
              {{ $t('Save') }}
            </el-button>
          </div>
        </template>

        <template v-else>
          <span class="wpsr-manage-categories-name">{{ category.name }}</span>
          <span class="wpsr-manage-categories-count">{{ countLabel(category) }}</span>
          <div class="wpsr-manage-categories-actions">
            <el-button
                class="wpsr_default_btn"
                size="small"
                :title="$t('Rename')"
                @click="startRename(category)"
            >
              <el-icon><Edit /></el-icon>
            </el-button>
            <el-button
                class="wpsr_default_btn"
                size="small"
                :title="$t('Delete')"
                :loading="deletingId === category.id"
                :disabled="deletingId === category.id"
                @click="confirmDelete(category)"
            >
              <el-icon><Delete /></el-icon>
            </el-button>
          </div>
        </template>
      </li>
    </ul>

    <template #footer>
      <div class="wpsr-d-flex wpsr-jc-end">
        <el-button class="wpsr_default_btn" @click="$emit('update:modelValue', false)">{{ $t('Done') }}</el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script>
import { Edit, Delete } from '@element-plus/icons-vue';

/**
 * Browse, rename and delete review categories — the only place in the admin that
 * can remove one.
 *
 * Emits `categories-changed` after any write: a rename or delete changes the
 * chips already rendered in the table behind this modal.
 */
export default {
  name: 'ManageCategoriesModal',
  components: { Edit, Delete },
  props: {
    modelValue: {
      type: Boolean,
      default: false
    },
    categories: {
      type: Array,
      default: () => []
    },
    loading: {
      type: Boolean,
      default: false
    },
    loaded: {
      type: Boolean,
      default: false
    },
    loadError: {
      type: Boolean,
      default: false
    },
    // Writes must go to the prefix the host screen is authorised for.
    routePrefix: {
      type: String,
      default: 'reviews'
    }
  },
  emits: ['update:modelValue', 'categories-changed', 'retry'],
  data() {
    return {
      editingId: null,
      editingName: '',
      savingId: null,
      deletingId: null
    }
  },
  methods: {
    countLabel(category) {
      const count = Number(category.reviews_count || 0);
      return count === 1
          ? this.$t('1 review')
          : this.$t('%d reviews').replace('%d', count);
    },
    startRename(category) {
      this.editingId = category.id;
      this.editingName = category.name;
    },
    saveRename(category) {
      const name = this.editingName.trim();

      if (!name) {
        this.$notify.warning(this.$t('Category name is required'));
        return;
      }

      if (name === category.name) {
        this.editingId = null;
        return;
      }

      this.savingId = category.id;
      this.$put(`${this.routePrefix}/categories/${category.id}`, { name: name }).then(response => {
        this.handleSuccess(response.message);
        this.editingId = null;
        this.$emit('categories-changed');
      }).catch(errors => {
        this.handleError(errors);
      }).always(() => {
        this.savingId = null;
      });
    },
    confirmDelete(category) {
      const count = Number(category.reviews_count || 0);
      const warning = count
          ? this.$t('It will be removed from %d review(s).').replace('%d', count)
          : this.$t('No reviews are using it.');

      this.$confirm(
          this.$t('Permanently delete "%s"? %w')
              .replace('%s', category.name)
              .replace('%w', warning),
          this.$t('Delete Category'),
          {
            confirmButtonText: this.$t('Delete'),
            cancelButtonText: this.$t('Cancel'),
            type: 'warning'
          }
      ).then(() => {
        this.deletingId = category.id;
        this.$del(`${this.routePrefix}/categories/${category.id}`).then(response => {
          this.handleSuccess(response.message);
          this.$emit('categories-changed');
        }).catch(errors => {
          this.handleError(errors);
        }).always(() => {
          this.deletingId = null;
        });
      }).catch(() => {});
    }
  }
}
</script>
