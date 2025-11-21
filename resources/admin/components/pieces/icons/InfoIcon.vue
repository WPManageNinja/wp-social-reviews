<template>
    <svg width="40" height="40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M10 17.5C5.85775 17.5 2.5 14.1423 2.5 10C2.5 5.85775 5.85775 2.5 10 2.5C14.1423 2.5 17.5 5.85775 17.5 10C17.5 14.1423 14.1423 17.5 10 17.5ZM9.25 9.25V13.75H10.75V9.25H9.25ZM9.25 6.25V7.75H10.75V6.25H9.25Z" :fill="color"/>
    </svg>
</template>
<script>
export default {
    name: 'InfoIcon',
    props: {
        color: {
            type: String,
            default: 'var(--wpsr-icon-info-color)'
        }
    }
}
</script>