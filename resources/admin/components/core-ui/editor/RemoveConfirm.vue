<template>
    <span>
        <el-popover
            ref="popoverRef"
            placement="top"
            width="360"
            :visible="visible"
            @update:visible="visible = $event"
            trigger="click"
        >

          <template #default>
                <h4>{{message}}</h4>
                <div class="wpsr-d-flex wpsr-jc-end wpsr-mt-20">
                    <el-button class="wpsr_default_btn" type="default" size="small" @click="visible = false">{{$t('Cancel')}}</el-button>
                    <el-button class="wpsr_primary_btn" type="primary" size="small" @click="confirmAction">{{$t('Confirm')}}</el-button>
                </div>
            </template>
            <template #reference>
                <div v-if="submit_button_type === 'text'">
                    
                    <span class="wpsr-settings-clear-btn wpsr-d-flex wpsr-align-items-center" :class="displayRefreshLeft === 'trash' ? 'wpsr-gap-5' : ''">
                      <TrashcanIcon v-if="displayRefreshLeft == 'trash'"/> 
                      <el-icon v-if="displayRefreshLeft !== 'trash'"><RefreshLeft/></el-icon>
                      <span>{{button_text}}</span>
                    </span>
                </div>
                <el-button v-else class="wpsr-clear-btn wpsr_mini wpsr_light_edit wpsr_default_btn wpsr_icon_btn">
                    <TrashcanIcon />
                </el-button>
<!--                <el-icon v-else class="wpsr-clear-btn"><Close /></el-icon>-->
            </template>

        </el-popover>

    </span>
</template>

<script type="text/babel">

import {RefreshLeft} from '@element-plus/icons-vue';
import TrashcanIcon from '../../pieces/icons/TrashcanIcon.vue';
export default {
    name: 'RemoveConfirm',
    emits: ['confirm'],
    components: {
      RefreshLeft,
      TrashcanIcon
    },
    props: {
        displayRefreshLeft: {
          type: [Boolean, String],
          default: false
        },
        button_text: {
          type: String,
          default: ""
        },
        submit_button_type: {
          type: String,
          default: "icon"
        },
        message: {
          type: String,
          default: 'Are you sure you want to delete this account?'
        },
        plain: {
            type: Boolean,
            default: false
        },
        platform:{
            type: String,
            default: ''
        }
    },
    data() {
        return {
            visible: false
        }
    },
    methods: {
        confirmAction() {
            this.visible = false;
            this.$emit('confirm');
        }
    }
}
</script>