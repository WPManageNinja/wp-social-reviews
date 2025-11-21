<template>
    <svg xmlns="http://www.w3.org/2000/svg" :width="width" :height="height" viewBox="0 0 16 16" fill="none">
      <path d="M3.33333 10.6665L2 3.33317L5.66667 6.6665L8 2.6665L10.3333 6.6665L14 3.33317L12.6667 10.6665H3.33333ZM12.6667 12.6665C12.6667 13.0665 12.4 13.3332 12 13.3332H4C3.6 13.3332 3.33333 13.0665 3.33333 12.6665V11.9998H12.6667V12.6665Z" fill="#F7A539"/>
    </svg>
</template>
<script>
export default {
name: 'ProCrownIcon',
props: {
  width: {
    type: [String, Number],
    default: 16
  },
  height: {
    type: [String, Number],
    default: 16
  }
}
}
</script>