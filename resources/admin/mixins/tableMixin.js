import moment from 'moment';

export const tableMixin = {
    data() {
        return {
            per_page: this.$route.query.per_page ? parseInt(this.$route.query.per_page || null) : 10,
            page_number: this.$route.query.page ? parseInt(this.$route.query.page || null) : 1,
            total: 0,
            pageSizes: [5,10, 20, 30, 40, 50, 100, 200],
            search_string: '',
            loading: false,
            show_delete_modal: false,
            show_duplicate_modal: false,
            show_bulk_duplicate_modal: false,
            items: [],
            feed_items: [],
            deletingItem: {},
            duplicatingItem: false,
            feedPlatforms: ["twitter", "youtube", "instagram", "facebook_feed", "tiktok"],
            connectedPlatformSections: [],
            validPlatforms: [],
            filter_value: null,
            total_items: 0,
            order_by: '',
            endpoint: '',
        }
    },
    methods: {
         getValidPlatforms(platformNames) {
            if (platformNames) {
                let platforms = platformNames.split(',');
                return platforms;
            }
        },
        handleSizeChange(val) {
            this.per_page = val;
            this.page_number = 1;
            this.$router.push({ 
                path: this.endpoint, 
                query: { 
                    page: 1, 
                    per_page: val 
                }
            });
            this.getItems();
        },
        handleCurrentChange(val) {
            this.page_number = val;
            this.$router.push({
                path: this.endpoint, 
                query: { 
                    page: val, 
                    per_page: this.per_page 
                }
            }).catch(() => {});
            this.getItems();
        },
        handleDeleteClose() {
            this.show_delete_modal = false;
            this.deletingItem = {}
        },
        beforeDeleteHandler(template) {
            let platforms = ['reviews', 'testimonials'];
            if(!this.has_pro && platforms.includes(this.endpoint)) {
                this.handlePro();
                return;
            }
            this.deletingItem = template;
            this.show_delete_modal = true;
        },
        addNewItem(arg) {
            let platform, channels;
            if (typeof arg === 'object') {
                platform = arg.platform;
                channels = arg.channels;
            } else {
                platform = arg;
                channels = null;
            }
            if (!this.has_pro && this.endpoint === 'notifications') {
                this.handlePro();
                return;
            }
            let payload = { platform: platform };
            if (channels) {
                payload.channels = channels;
            }
            this.$post(this.endpoint, payload).then(response => {
                if ( response ) {
                    if (response.template_id) {
                        if (platform === 'chat') {
                            this.$router.push({
                                name: 'edit-chat-widget',
                                params: {
                                    widget_id: response.template_id
                                },
                                query: channels ? { channels } : {}
                            })
                        } else {
                            this.$router.push({
                                name: platform === 'reviews' || platform === 'testimonial' ? 'edit-template' : 'edit-' + platform.replace("_", "-") + '-template',
                                params: {
                                    template_id: response.template_id
                                }
                            })
                        }
                    }
                }
            }).catch(errors => {
                this.handleError(errors);
            }).always(() => {

            });
        },
        getItems(){
            this.loading = true;
            this.$get(this.endpoint, {
                per_page: this.per_page,
                page: this.page_number,
                search: this.search_string,
                filter: this.filter_value,
                type: this.type ? this.type :  this.endpoint.slice(0, -1), // slice for recommendations data controller
                source_id: this.sourceId ? this.sourceId : null,
                order_by: this.order_by
            })
                .then(response => {
                    if ( response ) {
                        this.items = response.items.data;
                        this.feed_items = response.feed_items;
                        this.total =  response.items.total;
                        this.total_items = response.total_items;

                        if (response.connected_platform_sections) {
                            this.connectedPlatformSections = response.connected_platform_sections;
                        }

                        if (response.all_valid_platforms) {
                            let platforms = response.all_valid_platforms;
                            this.validPlatforms = [];
                            if (Object.entries(platforms).length) {
                                this.validPlatforms.push({value: 'all', label: 'All'});
                                for (let [key, value] of Object.entries(platforms)) {
                                    this.validPlatforms.push({'value': key, 'label': value})
                                }
                            }
                        }
                    }
                }).catch( errors => {
                    this.handleError(errors);
                }).always(() => {
                    this.loading = false;
                });
        },

        deleteItems() {
            let ids = [];
            let isBulkDelete = false;
            
            if (this.selectedItems && this.selectedItems.length > 0) {
                ids = this.selectedItems.map(item => item.id || item.ID);
                isBulkDelete = true;
            } else if (this.deletingItem && (this.deletingItem.id || this.deletingItem.ID)) {
                ids = [this.deletingItem.id || this.deletingItem.ID];
                isBulkDelete = false;
            } else {
                this.$notify.warning('No items selected for deletion');
                return;
            }

            this.performDelete(ids, isBulkDelete);
        },
        performDelete(ids, isBulkDelete) {
            this.loading = true;
            
            this.$del(this.endpoint, { ids: ids })
                .then(response => {
                    if (response) {
                        this.handleSuccess(response.message || `Successfully deleted ${ids.length} item(s)`);
                        this.getItems();
                        
                        if (isBulkDelete) {
                            this.selectedItems = [];
                            this.bulkAction = '';
                            if (this.isBulkDelete !== undefined) {
                                this.isBulkDelete = false;
                            }
                        } else {
                            this.deletingItem = {};
                        }
                        
                        this.show_delete_modal = false;
                    }
                }).catch(errors => {
                    this.handleError(errors);
                }).always(() => {
                    this.loading = false;
                });
        },
        bulkDuplicateItems() {
            if (!this.selectedItems || !this.selectedItems.length) {
                this.$notify.warning('No items selected for duplication');
                return;
            }

            this.isBulkDuplicate = true;
            this.show_duplicate_modal = true;
        },
        performDuplicate() {
            const selectedIds = this.selectedItems.map(item => item.id || item.ID);
            this.loading = true;
            
            this.$post(`${this.endpoint}/duplicate`, {
                ids: selectedIds
            }).then(response => {
                if (response) {
                    this.handleSuccess(response.message || `Successfully duplicated ${selectedIds.length} item(s)`);
                    this.getItems();
                    this.selectedItems = [];
                    this.bulkAction = '';
                }
            }).catch(errors => {
                this.handleError(errors);
            }).always(() => {
                this.loading = false;
                this.show_duplicate_modal = false;
                this.show_bulk_duplicate_modal = false;
                this.isBulkDuplicate = false;
            });
        },
        bulkStatusItems() {
            if (!this.selectedItems || !this.selectedItems.length) {
                this.$notify.warning('No items selected for status update');
                return;
            }

            this.isBulkStatus = true;
            this.show_status_modal = true;
        },
        performStatusUpdate() {
            const selectedIds = this.selectedItems.map(item => item.id || item.ID);
            let status = this.bulkAction; // 'enable', 'disable', 'approve', 'disapprove'
            
            // Map approve/disapprove to enable/disable for API
            if (status === 'approve') {
                status = 'enable';
            } else if (status === 'disapprove') {
                status = 'disable';
            }
            
            this.loading = true;
            
            this.$put(`${this.endpoint}/status-update`, {
                ids: selectedIds,
                status: status,
                type: this.actionType
            }).then(response => {
                if (response) {
                    const actionText = status === 'enable' ? 'enabled' : 'disabled';
                    this.handleSuccess(response.message || `Successfully ${actionText} ${selectedIds.length} item(s)`);
                    this.getItems();
                    this.selectedItems = [];
                    this.bulkAction = '';
                }
            }).catch(errors => {
                this.handleError(errors);
            }).always(() => {
                this.loading = false;
                this.show_status_modal = false;
                this.isBulkStatus = false;
            });
        },
        updateItemStatus(item, status) {
            this.loading = true;
            
            this.$put(`${this.endpoint}/status-update`, {
                ids: [item.id || item.ID],
                status: status,
                type: this.actionType
            }).then(response => {
                if (response) {
                    const actionText = status === 'enable' ? 'enabled' : 'disabled';
                    this.handleSuccess(response.message || `Item has been successfully ${actionText}`);
                    this.getItems();
                }
            }).catch(errors => {
                this.handleError(errors);
            }).always(() => {
                this.loading = false;
            });
        },
        templateStatus(Itemstatus = null, ItemId = null) {
            let selectedIds = [];
            let status = '';
            if (Itemstatus && ItemId) {
                selectedIds = [parseInt(ItemId)];
                status = Itemstatus === 'publish' ? 'publish' : 'draft';
            } else {
                selectedIds = this.selectedItems.map(({ id, ID }) => id ?? ID);
                status = this.bulkAction;
            }
            
            this.loading = true;
            
            this.$put(`${this.endpoint}`, {
                ids: selectedIds,
                status: status,
                type: this.actionType
            }).then(response => {
                if (response) {
                    this.handleSuccess(response.message || `Successfully updated ${selectedIds.length} item(s)`);
                    this.getItems();
                    this.selectedItems = [];
                    this.bulkAction = '';
                }
            }).catch(errors => {
                this.handleError(errors);
            }).always(() => {
                this.loading = false;
                this.show_status_modal = false;
                this.show_bulk_status_modal = false;
                this.isBulkStatus = false;
            });
        },
        duplicateItem(templateDetails) {
            let ex_platforms = ['reviews', 'testimonials'];
            if(!this.has_pro && ex_platforms.includes(this.endpoint)) {
                this.handlePro();
                return;
            }

            this.duplicatingItem = true;
            let id = ex_platforms.includes(this.endpoint) ? templateDetails.id : templateDetails.ID;
            this.$post(`${this.endpoint}/duplicate`, { ids: [id] })
                .then(response => {
                    if ( response ) {
                        this.handleSuccess(response.message);
                        let platform = templateDetails.platform_name;

                        if (response.item) {
                            if (this.endpoint === 'chat-widgets') {
                                this.$router.push({
                                    name: 'edit-chat-widget',
                                    params: {
                                        widget_id: response.item.ID
                                    }
                                });
                            } else {
                                let platform_len = this.getValidPlatforms(templateDetails.platform_name);
                                if ((platform_len && platform_len.length > 1) || (!this.feedPlatforms.includes(platform))) {
                                    this.$router.push({
                                        name: 'edit-template',
                                        params: {
                                            template_id: response.item.ID
                                        }
                                    });
                                } else {
                                    this.$router.push({
                                        name: 'edit-' + platform.replace("_", "-") + '-template',
                                        params: {
                                            template_id: response.item.ID
                                        }
                                    })
                                }
                            }
                        } else {
                            this.getItems();
                        }
                    }
                }).catch((errors) => {
                    this.handleError(errors);
                }).always(() => {
                    this.duplicatingItem = false;
                });
        },
        getAll(endpoint, options = {}) {
             if(options !== null){
                 this.order_by = options.order_by || '';
                 this.type = options.type || '';
                 this.sourceId = options.sourceId || null;
             }

            this.endpoint = endpoint;
            this.getItems();
        },
        handleTableSort(column) {
            if (column.order) {
                if (column.prop === 'status') {
                    this.order_by = column.order === 'ascending' ? 'publish' : 'draft';
                } else {
                    this.order_by = (column.order === 'ascending') ? 'ASC' : 'DESC';
                }
                this.getItems();
            }
        },
        formatDate(date) {
            return date ? moment(date).format('MMM DD, YYYY') : '-';
        },
        handleClear() {
            this.search_string = '';
            this.getItems();
        },
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        applyBulkAction() {
            if (!this.selectedItems || !this.selectedItems.length) {
                this.$notify.warning('Please select items to perform bulk action');
                return;
            }

            if (!this.bulkAction) {
                this.$notify.warning('Please select a bulk action');
                return;
            }

            switch(this.bulkAction) {
                case 'delete':
                    this.beforeBulkDeleteHandler();
                    break;
                case 'duplicate':
                    this.beforeBulkDuplicateHandler();
                    break;
                case 'enable':
                case 'disable':
                case 'approve':
                case 'disapprove':
                    this.bulkStatusItems();
                    break;
                default:
                    this.$notify.warning('Selected action is not supported yet');
            }
        },
        beforeBulkDeleteHandler() {
            if (!this.has_pro) {
                this.handlePro();
                return;
            }
            
            this.isBulkDelete = true;
            this.show_delete_modal = true;
        },
        performBulkDelete() {
            const selectedIds = this.selectedItems.map(item => item.id || item.ID);
            this.loading = true;
            
            this.$del(this.endpoint, { ids: selectedIds })
                .then(response => {
                    if (response) {
                        this.handleSuccess(response.message || `Successfully deleted ${selectedIds.length} item(s)`);
                        this.getItems();
                        this.selectedItems = [];
                        this.show_delete_modal = false;
                        this.show_bulk_delete_modal = false;
                        this.bulkAction = '';
                    }
                }).catch(errors => {
                    this.handleError(errors);
                }).always(() => {
                    this.loading = false;
                    this.show_delete_modal = false;
                    this.isBulkDelete = false;
                });
        },
        beforeBulkDuplicateHandler() {
            if (!this.has_pro) {
                this.handlePro();
                return;
            }
            
            this.isBulkDuplicate = true;
            // Set both modal variables to support different components
            this.show_duplicate_modal = true;
            this.show_bulk_duplicate_modal = true;
        },
        performBulkDuplicate() {
            const selectedIds = this.selectedItems.map(item => item.id || item.ID);
            this.loading = true;
            
            this.$post(`${this.endpoint}/duplicate`, { ids: selectedIds })
                .then(response => {
                    if (response) {
                        this.handleSuccess(response.message || `Successfully duplicated ${selectedIds.length} item(s)`);
                        this.getItems();
                        this.selectedItems = [];
                        this.bulkAction = '';
                    }
                }).catch(errors => {
                    this.handleError(errors);
                }).always(() => {
                    this.loading = false;
                    this.show_duplicate_modal = false;
                    this.show_bulk_duplicate_modal = false;
                    this.isBulkDuplicate = false;
                });
        }
    }
}