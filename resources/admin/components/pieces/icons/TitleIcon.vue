<template>
  <svg
    width="20"
    height="20"
    viewBox="0 0 20 20"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
  >
    <path
      d="M3.25 3.875H16.75C16.7832 3.875 16.8154 3.88769 16.8389 3.91113C16.8623 3.93457 16.875 3.96685 16.875 4V16C16.875 16.0332 16.8623 16.0654 16.8389 16.0889C16.8154 16.1123 16.7832 16.125 16.75 16.125H3.25C3.21685 16.125 3.18457 16.1123 3.16113 16.0889C3.13769 16.0654 3.125 16.0332 3.125 16V4C3.125 3.96685 3.13769 3.93457 3.16113 3.91113C3.18457 3.88769 3.21685 3.875 3.25 3.875ZM3.375 15.875H16.625V4.125H3.375V15.875ZM13.875 6.875V7.125H6.125V6.875H13.875Z"
      stroke="#525866"
      stroke-width="1.25"
    />
  </svg>
</template>
<script>
export default {
name: 'TitleIcon',
    props: {
        width: {
            type: [String, Number],
            default: 14
        },
        height: {
            type: [String, Number],
            default: 14
        }
    }
}
</script>