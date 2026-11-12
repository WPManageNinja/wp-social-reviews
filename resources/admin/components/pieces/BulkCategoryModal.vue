<template>
  <el-dialog
      class="wpsr-confirmation-modal wpsr-bulk-category-modal"
      :model-value="modelValue"
      @update:model-value="onDialogVisibility"
      @open="resetForm"
      :show-close="!applying"
      :close-on-click-modal="!applying"
      :close-on-press-escape="!applying"
      width="min(90vw, 520px)">
    <template #header>
      <h4 class="wpsr-dialog-title">{{ $t('Assign Category') }}</h4>
      <p class="wpsr-modal-subtitle">{{ subtitle }}</p>
    </template>
    <div class="wpsr-bulk-category-body">
      <el-radio-group v-model="mode">
        <el-radio value="existing">{{ $t('Existing category') }}</el-radio>
        <el-radio value="new">{{ $t('New category') }}</el-radio>
      </el-radio-group>

      <div class="wpsr-modal-field-group">
        <label class="wpsr-field-label">{{ $t('Category') }}</label>
        <template v-if="mode === 'existing'">
          <el-select
              class="wpsr-select-input-field"
              v-model="categoryId"
              filterable
              :loading="loading"
              :placeholder="loadError ? $t('Could not load categories') : $t('Select a category')"
          >
            <el-option
                v-for="category in categories"
                :key="category.id"
                :label="category.name"
                :value="category.id">
            </el-option>
          </el-select>
          <!-- Say the load failed, or an empty list reads as "no categories exist". -->
          <p v-if="loadError" class="wpsr-field-hint">
            {{ $t('Could not load existing categories.') }} <el-button link type="primary" size="small" @click="$emit('retry')">{{ $t('Retry') }}</el-button>
          </p>
        </template>
        <el-input
            v-else
            class="wpsr-input-default wpsr-border-all-around"
            v-model="newName"
            size="small"
            :placeholder="$t('Enter new category name')">
        </el-input>
      </div>
    </div>
    <template #footer>
      <div class="wpsr-modal-footer wpsr-mt-20">
        <el-button class="wpsr_default_btn" :disabled="applying" @click="$emit('update:modelValue', false)">{{ $t('Cancel') }}</el-button>
        <div class="wpsr-modal-footer-actions">
          <!-- Removal needs an existing category. The server rejects it too. -->
          <el-button
              v-if="mode === 'existing'"
              class="wpsr_default_btn"
              :disabled="applying || !canSubmit"
              :loading="applying"
              @click="submit('remove')"
          >
            {{ $t('Remove Category') }}
          </el-button>
          <el-button
              class="wpsr_primary_btn"
              type="primary"
              :disabled="applying || !canSubmit"
              :loading="applying"
              @click="submit('add')"
          >
            {{ $t('Assign Category') }}
          </el-button>
        </div>
      </div>
    </template>
  </el-dialog>
</template>

<script>
/**
 * Bulk assign/remove a category on the selected reviews. reviewCategoryMixin supplies
 * `categories` and handles `submit`. Remove only unassigns; deleting lives in ManageCategoriesModal.
 */
export default {
  name: 'BulkCategoryModal',
  props: {
    modelValue: {
      type: Boolean,
      default: false
    },
    categories: {
      type: Array,
      default: () => []
    },
    selectedCount: {
      type: Number,
      default: 0
    },
    applying: {
      type: Boolean,
      default: false
    },
    loading: {
      type: Boolean,
      default: false
    },
    loadError: {
      type: Boolean,
      default: false
    }
  },
  emits: ['update:modelValue', 'submit', 'retry'],
  data() {
    return {
      mode: 'existing',
      categoryId: null,
      newName: ''
    }
  },
  computed: {
    canSubmit() {
      return this.mode === 'existing' ? !!this.categoryId : this.newName.trim() !== '';
    },
    subtitle() {
      return this.selectedCount === 1
          ? this.$t('1 review selected')
          : this.$t('%d reviews selected').replace('%d', this.selectedCount);
    }
  },
  methods: {
    // Don't close mid-request, or its success handler clears a selection the user has since changed.
    onDialogVisibility(visible) {
      if (!visible && this.applying) {
        return;
      }
      this.$emit('update:modelValue', visible);
    },
    resetForm() {
      this.mode = 'existing';
      this.categoryId = null;
      this.newName = '';
    },
    submit(action) {
      if (!this.canSubmit) {
        return;
      }

      this.$emit('submit', this.mode === 'existing'
          ? { action: action, category_id: this.categoryId }
          : { action: action, new_category_name: this.newName.trim() }
      );
    }
  }
}
</script>
