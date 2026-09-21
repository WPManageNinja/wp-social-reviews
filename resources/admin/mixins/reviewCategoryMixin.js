/**
 * Shared review-category behaviour for the listings that show categories:
 * AllReviews.vue, AllTestimonials.vue and CustomSourceView.vue.
 *
 * Expects the host to provide (all do, via tableMixin): `selectedItems`,
 * `bulkAction` and `getItems()`.
 */
export const reviewCategoryMixin = {
    data() {
        return {
            // Reviews and testimonials share one vocabulary but different
            // capabilities, so the testimonials screen overrides this.
            categoryRoutePrefix: 'reviews',
            categories: [],
            categoriesLoading: false,
            categoriesLoaded: false,
            categoriesLoadError: false,
            show_category_modal: false,
            show_manage_categories_modal: false,
            applyingBulkCategory: false
        }
    },
    computed: {
        // Renaming/deleting a category is global (it touches rows on both screens),
        // so the server's CategoryManagementPolicy requires BOTH capabilities. Mirror
        // that here so a single-capability user is never shown a Manage Categories
        // control whose rename/delete would deterministically 403.
        canManageCategories() {
            return this.hasPermission('wpsn_full_access')
                || (this.hasPermission('wpsn_manage_reviews') && this.hasPermission('wpsn_manage_testimonials'));
        }
    },
    methods: {
        loadCategories() {
            this.categoriesLoading = true;
            this.categoriesLoadError = false;
            return this.$get(`${this.categoryRoutePrefix}/categories`).then(response => {
                this.categories = response.categories || [];
                this.categoriesLoaded = true;
            }).catch(errors => {
                // Surface it instead of swallowing: a silent empty list invites
                // duplicate categories and looks like lost data.
                this.categoriesLoadError = true;
                this.handleError(errors);
            }).always(() => {
                this.categoriesLoading = false;
            });
        },
        openBulkCategoryModal() {
            this.show_category_modal = true;
        },
        applyBulkCategory(payload) {
            this.applyingBulkCategory = true;
            // Snapshot the rows the request actually applied to, so the response can
            // clear the selection only if the user has not changed it meanwhile.
            const submittedIds = this.selectedItems.map(item => item.id || item.ID);
            this.$put(`${this.categoryRoutePrefix}/bulk-category`, {
                ids: submittedIds,
                ...payload
            }).then(response => {
                this.handleSuccess(response.message);
                this.show_category_modal = false;
                const currentIds = this.selectedItems.map(item => item.id || item.ID);
                if (this.isSameIdSet(submittedIds, currentIds)) {
                    this.selectedItems = [];
                    this.bulkAction = '';
                }
                this.loadCategories();
                this.getItems();
            }).catch(errors => {
                this.handleError(errors);
            }).always(() => {
                this.applyingBulkCategory = false;
            });
        },
        // True when both arrays hold the same review ids, order-independent.
        isSameIdSet(a, b) {
            if (a.length !== b.length) {
                return false;
            }
            const other = new Set(b);
            return a.every(id => other.has(id));
        },
        // The "x" on a chip: unassigns one category and patches the row in place.
        removeCategoryFromReview(row, category) {
            this.$put(`${this.categoryRoutePrefix}/bulk-category`, {
                ids: [row.id || row.ID],
                action: 'remove',
                category_id: category.id
            }).then(response => {
                this.handleSuccess(response.message);
                row.categories = (row.categories || []).filter(c => c.id !== category.id);
            }).catch(errors => {
                this.handleError(errors);
            });
        },
        // A rename or delete changes chips already rendered in the table.
        onCategoriesChanged() {
            this.loadCategories();
            this.getItems();
        }
    }
}
