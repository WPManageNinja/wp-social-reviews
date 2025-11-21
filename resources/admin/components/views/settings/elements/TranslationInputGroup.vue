<template>
    <div class="wpsr_editor_edit_item-group">
        <div v-for="(field, key) in fieldsMaps" :key="key">
            <h3>{{field.title}}</h3>
            
            <el-input v-if="field.type === 'text'"
                type="text"
                v-model="value[field.fieldKey]"
                size="small"
                :placeholder="field.placeholder ? field.placeholder : ''"
                :disabled="(field.disabled === false) ? true : false"
            >
            </el-input>
        </div>
    </div>
</template>
<script>

export default {
    name: 'wpsr_translation_editor_group',
    props: [ 'fieldsMaps', 'value'],
    data () {
        return {
            device: 'desktop',
            activeDevice: 'dashicons-desktop',
            isMobileValueChanged: false,
            userInteraction: false,
            hasUserGivenValue: false,
            totalPostsNumberInteraction: false
        }
    }
}
</script>