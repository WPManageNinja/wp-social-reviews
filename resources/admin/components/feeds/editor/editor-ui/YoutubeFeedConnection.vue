<template>
    <div v-if="feed_config" class="wpsr-connection-platform wpsr-feed-connection-platform">
        <span class="wpsr-platform-tab-content">
        {{$t('Configure your feed platform first. Click on the card below to start the process.')}}
        </span>
        <div class="wpsr-platforms-group">
            <div class="wpsr-platforms-list-group wpsr-connection-wrapper">
                <div class="wpsr-platform wpsr-platform--connection" :style="connectionCardStyle">
                    <div class="wpsr-platform-connection-row" @click="youtubeSettingModal = true">
                        <img :src="assets_url+'/images/icon/icon-youtube.png'" class="image wpsr-platform-connection-icon">
                        <span class="wpsr-platform-connection-title">
                            YouTube
                            <span v-if="isPlatformConnected" class="wpsr-platform-connection-enabled">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M8 14C4.6862 14 2 11.3138 2 8C2 4.6862 4.6862 2 8 2C11.3138 2 14 4.6862 14 8C14 11.3138 11.3138 14 8 14ZM7.4018 10.4L11.6438 6.1574L10.7954 5.309L7.4018 8.7032L5.7044 7.0058L4.856 7.8542L7.4018 10.4Z" fill="#23A682"/>
                                </svg>
                            </span>
                        </span>
                        <span class="wpsr-platform-connection-gear" @click.stop="youtubeSettingModal = true">
                            <i class="el-icon el-icon-setting">
                                <svg class="wpsr-setting-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
                                    <path fill="currentColor" d="M600.704 64a32 32 0 0 1 30.464 22.208l35.2 109.376c14.784 7.232 28.928 15.36 42.432 24.512l112.384-24.192a32 32 0 0 1 34.432 15.36L944.32 364.8a32 32 0 0 1-4.032 37.504l-77.12 85.12a357.12 357.12 0 0 1 0 49.024l77.12 85.248a32 32 0 0 1 4.032 37.504l-88.704 153.6a32 32 0 0 1-34.432 15.296L708.8 803.904c-13.44 9.088-27.648 17.28-42.368 24.512l-35.264 109.376A32 32 0 0 1 600.704 960H423.296a32 32 0 0 1-30.464-22.208L357.696 828.48a351.616 351.616 0 0 1-42.56-24.64l-112.32 24.256a32 32 0 0 1-34.432-15.36L79.68 659.2a32 32 0 0 1 4.032-37.504l77.12-85.248a357.12 357.12 0 0 1 0-48.896l-77.12-85.248A32 32 0 0 1 79.68 364.8l88.704-153.6a32 32 0 0 1 34.432-15.296l112.32 24.256c13.568-9.152 27.776-17.408 42.56-24.64l35.2-109.312A32 32 0 0 1 423.232 64H600.64zm-23.424 64H446.72l-36.352 113.088-24.512 11.968a294.113 294.113 0 0 0-34.816 20.096l-22.656 15.36-116.224-25.088-65.28 113.152 79.68 88.192-1.92 27.136a293.12 293.12 0 0 0 0 40.192l1.92 27.136-79.808 88.192 65.344 113.152 116.224-25.024 22.656 15.296a294.113 294.113 0 0 0 34.816 20.096l24.512 11.968L446.72 896h130.688l36.48-113.152 24.448-11.904a288.282 288.282 0 0 0 34.752-20.096l22.592-15.296 116.288 25.024 65.28-113.152-79.744-88.192 1.92-27.136a293.12 293.12 0 0 0 0-40.256l-1.92-27.136 79.808-88.128-65.344-113.152-116.288 24.96-22.592-15.232a287.616 287.616 0 0 0-34.752-20.096l-24.448-11.904L577.344 128zM512 320a192 192 0 1 1 0 384 192 192 0 0 1 0-384m0 64a128 128 0 1 0 0 256 128 128 0 0 0 0-256">
                                    </path>
                                </svg>
                            </i>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <youtube-config
            v-if="youtubeSettingModal"
            :youtubeModal="youtubeSettingModal"
            @youtube_settings="youtubeSettingModal = $event"
            @findEnabledPlatforms="handleFindEnabledPlatforms"
        >
        </youtube-config>
    </div>
</template>

<script type="text/babel">
import debounced from 'lodash/debounce';
import FeedEditorGroup from './../../../core-ui/editor/EditorGroup';
import UpgradeToProButton from '../../../views/advertise/UpgradeToProButton';
import Youtube from '../../../views/platforms/feeds/Youtube.vue';
import YoutubeConfig from '../../../views/platforms/feeds/Youtube';

export default {
    name: "YoutubeFeedConnection",
    components: {
        FeedEditorGroup,
        UpgradeToProButton,
        Youtube,
        YoutubeConfig,
    },
    props: {
        feed_config: {
            type: Object,
            required: true
        },
        image_settings: {
            type: Object,
            default: () => ({})
        },
        connected_ids: {
            type: [Array, Object], // Accept both,
            default: () => []
        },
        header_connected_ids: {
            type: [Array, Object], // Accept both,
            default: () => []
        }
    },
    emits: ['update:feed_config', 'fetchFeed'],

    data() {
        return {
            youtubeSettingModal: false,
            localFeedConfig: null,
            isUpdating: false
        }
    },

    computed: {
        isPlatformConnected() {
            // Check if YouTube is connected based on connected_ids or other connection indicators
            return this.connected_ids && this.connected_ids.length > 0;
        },
        connectionCardStyle() {
            return {
                'border-radius': '16px',
                'border': '1px solid #F0F0F0',
                'background': 'var(--wpsr-primary-bg, #FFF)',
                'display': 'flex',
                'align-items': 'center',
                'cursor': 'pointer',
                'transition': 'background 0.2s',
                'padding': '0',
                'margin-bottom': '0'
            };
        }
    },

    created() {
        this.initializeFeedConfig();
    },

    mounted() {
        let configuration = null;
        const queryString = window.location.search;
        const urlParams = new URLSearchParams(queryString);
        configuration = urlParams.get('configuration');

        if (!configuration) {
            const hash = window.location.hash;
            const hashQuery = hash.split('?')[1];
            if (hashQuery) {
                const hashParams = new URLSearchParams(hashQuery);
                configuration = hashParams.get('configuration');
            }
        }
        
        if(configuration){
            this.youtubeSettingModal = true;
            const hash = window.location.hash;
            if (hash.includes('?')) {
                const [base, query] = hash.split('?');
                const params = new URLSearchParams(query);
                params.delete('configuration');
                const newHash = params.toString() ? `${base}?${params.toString()}` : base;
                window.location.hash = newHash;
            }
            const url = new URL(window.location.href);
            if (url.searchParams.has('configuration')) {
                url.searchParams.delete('configuration');
                window.history.replaceState({}, '', url.toString());
            }
        }
    },

    methods: {
        initializeFeedConfig() {
            if (this.feed_config) {
                this.localFeedConfig = JSON.parse(JSON.stringify(this.feed_config));
            }
        },

        fetchFeeds() {
            this.$emit('fetchFeed');
        },

        fetch: debounced(function () {
            this.$emit('fetchFeed', 'fetching');
        }, 500),

        handleFindEnabledPlatforms() {
            // Handle the findEnabledPlatforms event from YoutubeConfig
            // This can be used to refresh connection status or update UI
            console.log('YouTube enabled platforms updated');
        },
    },
}
</script>