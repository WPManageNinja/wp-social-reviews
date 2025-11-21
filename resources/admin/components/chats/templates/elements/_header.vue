<template>
    <div class="wpsr-fm-chat-header" :class="[showStatus ? 'wpsr-online' : !showStatus && has_pro && showOfflineMessage ? 'wpsr-offline' : '' ]" :style="{backgroundColor: configs.styles.header_color}">
        <div v-if="templateConfigs.chat_header.picture" class="wpsr-chat-user-img">
            <img :src="templateConfigs.chat_header.picture" width="172" height="172" alt="">
        </div>
        <div class="wpsr-fm-group-details">
            <h3 v-if="templateConfigs.chat_header.name" :style="{color:configs.styles.header_title_color}">{{templateConfigs.chat_header.name}}</h3>
            <p class="wpsr-fm-caption" v-if="templateConfigs.chat_header.caption"
               :style="{color:configs.styles.header_caption_color}">
                {{templateConfigs.chat_header.caption}}
            </p>
            <p class="wpsr-fm-offline-caption" v-if="configs.settings.caption_when_offline && showOfflineMessage"
               :style="{color:configs.styles.header_caption_color}">
                {{configs.settings.caption_when_offline}}
            </p>
        </div>
    </div>
</template>

<script type="text/babel">
    export default {
        name: 'wpsr_chat_header',
        props: ['templateConfigs', 'configs'],
        data(){
            return{
                showStatus: false,
                showOfflineMessage: false,
            }
        },
        methods:{
            statusMessageFounder(){
                if( this.configs.settings.day_list ){
                    //if day and time both are on then do this
                    let daytimeOn = this.configs.settings.day_time_schedule;
                    if( daytimeOn === 'true'){
                        //time setup
                        let startTime   = '01/01/2020 '+this.configs.settings.start_time;
                        let endTime     = '01/01/2020 '+this.configs.settings.end_time;
                        let currentTime = '01/01/2020 '+new Date().toLocaleTimeString();

                        //day setup
                        let weekday  = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday','Saturday'];
                        let day      = weekday[new Date().getDay()];
                        let dayLists = this.configs.settings.day_list;

                        let checktodayExists = dayLists.indexOf(day);
                        if(checktodayExists !== -1){
                            //day is found , now we have to check time
                            this.showStatus = true;
                            this.showOfflineMessage = false;

                            // time schedule is on
                            let timeOn =  this.configs.settings.time_schedule;

                            if(timeOn === 'true'){
                                //start time is smaller than current time and end time greater than current time
                                if(  Date.parse(startTime) < Date.parse(currentTime)
                                    && Date.parse(endTime) > Date.parse(currentTime)){
                                    this.showStatus = true;
                                    this.showOfflineMessage = false;
                                }else{
                                    this.showStatus = false;
                                    this.showOfflineMessage = true;
                                }
                            }
                        }else{
                            this.showStatus = false;
                            this.showOfflineMessage = true;
                        }
                    }else{
                        this.showStatus = false;
                        this.showOfflineMessage = false;
                    }
                }

                let self = this;
                let t = setTimeout( self.statusMessageFounder,500 );
            }
        },

        watch:{
            showStatus(val){
                this.showStatus = val;
            }
        },
        mounted(){
            this.statusMessageFounder();
        }
    }
</script>