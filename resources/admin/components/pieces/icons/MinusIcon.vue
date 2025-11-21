<template>
    <svg :width="width" :height="height" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M4.75 9.25H15.25V10.75H4.75V9.25Z" fill="#525866"/>
    </svg>

</template>
<script>
export default {
name: 'MinusIcon',
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