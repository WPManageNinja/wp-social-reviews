<template>
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M13.25 11.25V13.25H11.25V12.75H12.75V11.25H13.25ZM8.75 12V13.25H8.25V12H8.75ZM5.75 8.25V13.25H0.75V8.25H5.75ZM1.25 12.75H5.25V8.75H1.25V12.75ZM3.5 10.5V11H3V10.5H3.5ZM11 9.75V10.25H10.5V9.75H11ZM9.5 8.25V9.5H8.25V8.25H9.5ZM13.25 8.25V8.75H12V8.25H13.25ZM13.25 0.75V5.75H8.25V0.75H13.25ZM5.75 0.75V5.75H0.75V0.75H5.75ZM1.25 5.25H5.25V1.25H1.25V5.25ZM8.75 5.25H12.75V1.25H8.75V5.25ZM11 3V3.5H10.5V3H11ZM3.5 3V3.5H3V3H3.5ZM9.75 11.25H10.25V11.75H9.75V11.25Z"/>
    </svg>
</template>

<script>
export default {
name: 'QrCodeIcon',
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