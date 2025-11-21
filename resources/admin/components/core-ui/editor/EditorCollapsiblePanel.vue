<template>
    <span class="wpsr-editor-title-row">
        <ChatEditorAccordionsIconPack :iconKey="iconKey" class="wpsr-editor-icon" width="20" height="20"/>
        <span class="wpsr-editor-title-text wpsr-heading-text">{{ label }}</span>
        <ProCrownIcon v-if="isPro"/>
    </span>
    <span class="wpsr-custom-collapse-arrow">
        <PlusIcon v-if="!isActive" />
        <MinusIcon v-else/>
    </span>
</template>

<script type="text/babel">
import ChatEditorAccordionsIconPack from '../../pieces/icons/ChatEditorAccordionsIconPack';
import PlusIcon from '../../pieces/icons/PlusIcon';
import MinusIcon from '../../pieces/icons/MinusIcon';
import ProCrownIcon from '../../pieces/icons/ProCrownIcon';

export default {
  name: 'EditorCollapsiblePanel',
  components: {
    ChatEditorAccordionsIconPack,
    ProCrownIcon,
    PlusIcon,
    MinusIcon
  },
  props: {
    isActive: {
      type: Boolean,
      default: false
    },
    iconKey: {
      type: String,
      default: 'platform'
    },
    label: {
        type: String,
        default: ''
    },
    isPro:{
      type: Boolean,
      default:false
    }
  }
};
</script>