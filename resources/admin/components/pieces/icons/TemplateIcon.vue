<template>
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M4 3.875H16C16.0332 3.875 16.0654 3.88769 16.0889 3.91113C16.1123 3.93457 16.125 3.96685 16.125 4V16C16.125 16.0332 16.1123 16.0654 16.0889 16.0889C16.0654 16.1123 16.0332 16.125 16 16.125H4C3.96685 16.125 3.93457 16.1123 3.91113 16.0889C3.88769 16.0654 3.875 16.0332 3.875 16V4C3.875 3.96685 3.88769 3.93457 3.91113 3.91113C3.93457 3.88769 3.96685 3.875 4 3.875ZM4.125 15.875H12.125V7.875H4.125V15.875ZM12.375 15.875H15.875V7.875H12.375V15.875ZM4.125 7.625H15.875V4.125H4.125V7.625Z" fill="#0E121B" stroke="#525866" stroke-width="1.25"/>
    </svg>
</template>
<script>
export default {
name: 'TemplateIcon',
    props: {
        width: {
            type: [String, Number],
            default: 20
        },
        height: {
            type: [String, Number],
            default: 20
        }
    }
}
</script>