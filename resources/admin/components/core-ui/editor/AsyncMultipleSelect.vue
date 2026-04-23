<template>
    <el-select
        ref="selectRef"
        v-model="localValue"
        placeholder="Type to search pages..."
        size="large"
        :disabled="disabled"
        :multiple="multiple"
        :collapse-tags="multiple"
        :collapse-tags-tooltip="multiple"
        :clearable="!multiple"
        filterable
        remote
        :remote-method="searchPages"
        :loading="loading"
        class="wpsr-text-input"
        @change="onSelectionChange"
    >
        <el-option
            v-for="item in options"
            :key="item.id"
            :label="item.title"
            :value="item.id"
        />
    </el-select>
</template>

<script>
export default {
    name: 'AsyncMultipleSelect',

    props: {
        modelValue: {
            type: [Array, String, Number],
            default: () => []
        },
        searchRoute: {
            type: String,
            required: true
        },
        disabled: {
            type: Boolean,
            default: false
        },
        multiple: {
            type: Boolean,
            default: true
        },
        includeEverywhere: {
            type: Boolean,
            default: true
        },
        extraParams: {
            type: Object,
            default: () => ({})
        }
    },

    emits: ['update:modelValue'],

    data() {
        return {
            options: [],
            loading: false,
            searchTimeout: null,
            selectedResolved: false
        };
    },

    computed: {
        localValue: {
            get() {
                if (this.multiple) {
                    return this.modelValue || [];
                }
                return this.modelValue || '';
            },
            set(val) {
                this.$emit('update:modelValue', val);
            }
        }
    },

    created() {
        this.resolveSelectedLabels();
    },

    beforeDestroy() {
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = null;
        }
    },

    methods: {
        resolveSelectedLabels() {
            let ids = [];

            if (this.multiple) {
                const values = this.modelValue || [];
                if (!values.length) return;

                const hasEverywhere = values.some(v => String(v) === '-1');
                ids = values.filter(v => v && String(v) !== '-1');

                if (hasEverywhere && this.includeEverywhere) {
                    this.options.push({ id: '-1', title: this.$t('Everywhere') });
                }
            } else {
                const val = this.modelValue;
                if (!val && val !== 0) return;

                if (String(val) === '-1') {
                    if (this.includeEverywhere) {
                        this.options.push({ id: '-1', title: this.$t('Everywhere') });
                    }
                    this.selectedResolved = true;
                    return;
                }
                ids = [val];
            }

            if (!ids.length) {
                this.selectedResolved = true;
                return;
            }

            this.$get(this.searchRoute, { include_ids: ids, ...this.extraParams })
                .then(response => {
                    const data = response.data || response;
                    const resolved = data.results || [];
                    const existingIds = new Set(this.options.map(o => o.id));
                    resolved.forEach(item => {
                        if (!existingIds.has(item.id)) {
                            this.options.push(item);
                        }
                    });
                })
                .catch(() => {})
                .always(() => {
                    this.selectedResolved = true;
                });
        },

        filterResults(results) {
            if (!this.includeEverywhere) {
                return results.filter(item => String(item.id) !== '-1');
            }
            return results;
        },

        loadPages(query) {
            if (this.loading) return;

            this.loading = true;
            const params = { per_page: 20, ...this.extraParams };
            if (query && query.trim()) {
                params.search = query.trim();
            }

            this.$get(this.searchRoute, params)
                .then(response => {
                    const data = response.data || response;
                    const newResults = this.filterResults(data.results || []);

                    if (this.multiple) {
                        const selectedSet = new Set((this.localValue || []).map(String));
                        const newIds = new Set(newResults.map(o => o.id));
                        const preserved = this.options.filter(o => selectedSet.has(String(o.id)) && !newIds.has(o.id));
                        this.options = [...preserved, ...newResults];
                    } else {
                        const currentVal = this.localValue;
                        const newIds = new Set(newResults.map(o => o.id));
                        const preserved = this.options.filter(o => String(o.id) === String(currentVal) && !newIds.has(o.id));
                        this.options = [...preserved, ...newResults];
                    }
                })
                .catch(() => {})
                .always(() => {
                    this.loading = false;
                });
        },

        onSelectionChange() {
            const select = this.$refs.selectRef;
            if (select) {
                select.blur();
                this.$nextTick(() => {
                    select.focus();
                    this.loadPages('');
                });
            }
        },

        searchPages(query) {
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = null;
            }

            if (!query || !query.trim()) {
                this.loadPages('');
                return;
            }

            const trimmedQuery = query.trim();
            if (trimmedQuery.length < 2) {
                return;
            }

            this.searchTimeout = setTimeout(() => {
                this.loadPages(trimmedQuery);
            }, 300);
        }
    }
};
</script>
