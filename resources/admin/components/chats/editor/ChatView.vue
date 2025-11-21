<template>
    <div class="wpsr_editor" v-if="chatConfig">
        <editor-header
            :title="template_title"
            :platform_section="'chats'"
            :route_name="'chat-widgets'"
            :route_title="'All Chat Widgets'"
            @update-settings="saveUpdatedChatSettings"
        ></editor-header>
        <div class="wpsr_editor_wrapper">
            <el-container>
                <el-main>
                    <keep-alive>
                        <component :is="component" :configs="chatConfig"/>
                    </keep-alive>
                    <div class="wpsr-placeholder-wrapper">
                      <img class="wpsr-placeholder-image" :src="assets_url+ '/images/template/editor-placeholder.png'" alt="Placeholder Image">
                    </div>
                </el-main>

                <el-aside class="wpsr-editor-sidebar">
                    <UpgradeApp />
                    <el-tabs v-model="activeTab" class="wpsr-element-tab-wrapper">
                        <el-tab-pane :label="$t('General')" name="general" class="wpsr-element-tab-item">
                            <el-collapse accordion v-model="activeNames" v-if="chatConfig">
                                <ChatEditor
                                    :chat_config="chatConfig"
                                    :pages="pages"
                                    :languages="languages"
                                    :menu_order="menu_order"
                                    :post_types="post_types"
                                    @update:chat_config="updateChatConfig"
                                    @on-update="saveUpdatedChatSettings"
                                />
                            </el-collapse>
                        </el-tab-pane>

                        <el-tab-pane :label="$t('Channels')" name="channels" class="wpsr-element-tab-item">
                            <ChannelTab 
                                v-if="chatConfig && chatConfig.channels"
                                :channels="chatConfig.channels || []" 
                                :assets_url="assets_url"
                                :chat_config="chatConfig"
                                :pre_selected_channels="selectedChannelIdsFromUrl"
                                @update:chat_config="updateChatChannels"
                            />
                        </el-tab-pane>

                        <el-tab-pane name="Style" class="wpsr-element-tab-item">
                            <template #label>
                                <span class="wpsr-tab-label-with-icon">
                                    Style
                                </span>
                            </template>
                            <StyleTab
                                v-if="chatConfig"
                                :chat_config="chatConfig"
                                @update:chat_config="updateChatConfig"
                            />
                        </el-tab-pane>
                    </el-tabs>
                </el-aside>
            </el-container>
        </div>
    </div>
</template>

<script type="text/babel">
import Template1 from './../templates/Template1';
import ChatEditor from './editor-ui/ChatEditor';
import ChannelTab from './editor-ui/ChannelTab';
import StyleTab from './editor-ui/StyleTab.vue';
import {commonMixin} from "../../../mixins/commonMixin";
import EditorHeader from "../../pieces/EditorHeader";
import UpgradeApp from "../../pieces/UpgradeApp.vue";
import ProCrownIcon from "../../pieces/icons/ProCrownIcon";
export default {
    name: "ChatView",
    components: {
      EditorHeader,
        Template1,
        ChatEditor,
        ChannelTab,
        StyleTab,
        UpgradeApp,
        ProCrownIcon
    },
    mixins: [commonMixin],
    data() {
        return {
            template_id: '',
            activeNames: ['1'],
            chatConfig: false,
            pages: [],
            languages: [],
            post_types: [],
            component: Template1,
            saving: false,
            menu_order: 0,
            selectedChannelIdsFromUrl: null, // <-- add this
            activeTab: 'general',
            saveTimeout: null
        }
    },

    watch: {
        'chatConfig': {
            handler(newVal, oldVal) {
                if (oldVal) {
                    this.isEditing = true;
                }
            },
            deep: true
        }
    },

    methods: {
        handleConfigUpdate(newConfig, save = false) {
            if (newConfig) {
            this.chatConfig = newConfig;
            this.isEditing = true;
            }

            // Debounce the save operation if needed
            if (this.saveTimeout) {
            clearTimeout(this.saveTimeout);
            }

            if (save) {
            this.saveTimeout = setTimeout(() => {
                this.saveUpdatedChatSettings();
            }, 500); // Wait 500ms before saving
            }
        },
        updateChatChannels(newConfig) {
            this.handleConfigUpdate(newConfig, true);
        },
        updateChatConfig(newConfig) {
            this.handleConfigUpdate(newConfig, false);
        },
        getChatSettings() {
            let widget_id = this.$route.params.widget_id
            this.$get(`chat-widgets/meta/chats/${widget_id}`).then(response => {
              if (response.data && response.data.settings) {
                this.chatConfig = response.data.settings.chat_settings;
                this.pages = response.data.pages;
                this.languages = response.data.languages;
                this.post_types = response.data.post_types;

                let template_details = response.data.template_details;
                this.template_title = template_details.post_title;
                this.template_id = template_details.ID;
                this.chatConfig.menu_order = template_details.menu_order;
                
                this.setAppropriateTab();
              }
            }).catch(error => {
                this.handleError(error);
            }).always(() => {
              this.isEditing = false;
            });
        },

        //save all changed configs
        saveUpdatedChatSettings() {
            this.saving = true;
            let widget_id = this.$route.params.widget_id;
            // Create a deep copy of the config to ensure reactivity
            const configToSave = JSON.parse(JSON.stringify(this.chatConfig));
            
            this.$put(`chat-widgets/meta/chats/${widget_id}`, {
                args: JSON.stringify(configToSave)
            }).then(response => {
                if (response.data) {
                    // Update the local config with the saved data
                    this.chatConfig = configToSave;
                    this.$message({
                        message: response.data.message,
                        type: 'success',
                        offset: 50
                    });
                }
            }).catch(error => {
                this.handleError(error);
            }).always(() => {
                this.saving = false;
                this.isEditing = false;
            });
        },
        setAppropriateTab(){
            // Check if 'channels' query param exists
            if (this.selectedChannelIdsFromUrl?.length > 0 || this.chatConfig.channels.length === 0) {
                this.activeTab = 'channels';
            } else {
                this.activeTab = 'general';
            }
        },
    },

    beforeUnmount() {
        // Clean up timeout when component is destroyed
        if (this.saveTimeout) {
            clearTimeout(this.saveTimeout);
        }
    },

    mounted() {
        // Check for 'channels' query param and convert to array
        if (this.$route.query.channels) {
            this.selectedChannelIdsFromUrl = this.$route.query.channels.split(',');
            // Remove the 'channels' query parameter from the URL
            const { channels, ...rest } = this.$route.query;
            this.$router.replace({
                path: this.$route.path,
                query: rest
            });
        }
        this.getChatSettings();
    }
}
</script>