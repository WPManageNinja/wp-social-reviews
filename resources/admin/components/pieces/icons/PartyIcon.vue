<template>
  <img :src="assets_url+ '/images/icon/partyIcon.svg'" alt="party popper icon">
</template>
<script>
export default {
  name: "PartyIcon",
  props: {
    width: {
      type: [String, Number],
      default: 15,
    },
    height: {
      type: [String, Number],
      default: 15,
    },
  },
};
</script>
