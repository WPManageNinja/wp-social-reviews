<template>
  <div>
    <el-input
        :id="id"
        :type="type"
        v-model="inputValue"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        :class="className"
        class="wpsr-input-primary"
        :disabled="disabled"
    >
      <template #prepend>
          <el-icon size="18" class="wpsr-icon-key"><Key /></el-icon>
      </template>

      <template #append>
        <el-button size="small" @click="toggle" :disabled="disabled">
          <el-icon v-if="this.type === 'text'"><View /></el-icon>
          <el-icon v-else><Hide /></el-icon>
        </el-button>
      </template>
    </el-input>
  </div>
</template>

<script>
import { View, Hide, Key } from '@element-plus/icons-vue'

export default {
  name: 'InputPassword',
  components: {
    View,
    Hide,
    Key
  },
  props: ['value', 'id', 'placeholder', 'disabled', 'autocomplete', 'className'],
  emits: ['update:modelValue'],
  data() {
    return {
      type: 'password',
      inputValue: this.modelValue
    };
  },
  watch: {
    modelValue(newVal) {
      this.inputValue = newVal;
    },
    inputValue(newVal) {
      this.$emit('update:modelValue', newVal);
    }
  },
  methods: {
    toggle() {
      this.type = this.type === 'text' ? 'password' : 'text';
    }
  }
};
</script>