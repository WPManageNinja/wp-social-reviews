<template>
    <div class="wpsr_editor_group_items">
        <div class="wpsr_editor_group_item">
            <span @click="showWrapper = true">{{ 'Wrapper' }}</span>
            <div class="item-reorder">
                <i class="el-icon-arrow-right"></i>
            </div>
        </div>

        <transition name="fade">
            <div v-if="showWrapper" class="wpsr_editor_overflow">
                <div class="wpsr_editor_header wpsr_pointer" @click="showWrapper = false">
                    <i class="el-icon-back"></i>
                    <span>{{ 'Wrapper' }}</span>
                    <!-- <span v-if="header_title">{{ header_title }}</span> -->
                    <!-- <span v-else>{{ title }}</span> -->
                </div>
                <div class="editor_overflow_content">
                   <div class="wpsr-settings-input">
                        <h4>WIDTH ( px, %, initial ) </h4>
                        <el-input type="text"  placeholder="" v-model="templateW" @input="templateWidth" size="small"></el-input>
                    </div>

                    <div class="wpsr-settings-input">
                        <h4>HEIGHT ( px, %, auto ) </h4>
                        <el-input type="text"  placeholder="" v-model="templateH" @input="templateHeight" size="small"></el-input>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script>
export default {
    props:['templateWidthSync','templateHeightSync'],

    data(){
        return{
            showWrapper:false,
            templateW:this.templateWidthSync,
            templateH:this.templateHeightSync,
        }
    },

    methods:{
        templateWidth(val){
            this.$emit('update:templateWidthSync', this.templateW);
        },
        templateHeight(val){
            this.$emit('update:templateHeightSync', this.templateH);
        }
    },

    watch:{
        templateWidthSync(val){
            this.templateW = val;
            this.$emit('update:templateWidthSync', val);
        },
        templateHeightSync(val){
            this.templateH = val;
            this.$emit('update:templateHeightSync', val);
        }
    }
}
</script>