<template>
  <svg
    width="20"
    height="20"
    viewBox="0 0 20 20"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
  >
    <path
      d="M3.25 3.875H16.75C16.7832 3.875 16.8154 3.88769 16.8389 3.91113C16.8623 3.93457 16.875 3.96685 16.875 4V14.5C16.875 14.5332 16.8623 14.5654 16.8389 14.5889C16.8154 14.6123 16.7832 14.625 16.75 14.625H5.625L5.45508 14.7588L3.125 16.5889V4C3.125 3.96685 3.13769 3.93457 3.16113 3.91113C3.18457 3.88769 3.21685 3.875 3.25 3.875ZM3.375 16.0742L4.38574 15.2803L5.53809 14.375H16.625V4.125H3.375V16.0742ZM12.375 9.125V9.375H7.625V9.125H12.375Z"
      fill="#0E121B"
      stroke="#525866"
      stroke-width="1.25"
    />
  </svg>
</template>
<script>
export default {
name: 'ReviewerNameIcon',
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