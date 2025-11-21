<template>
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M1 0.75H13C13.0663 0.75 13.1299 0.776358 13.1768 0.823242C13.2236 0.870125 13.25 0.933694 13.25 1V13C13.25 13.0663 13.2236 13.1299 13.1768 13.1768C13.1299 13.2236 13.0663 13.25 13 13.25H1C0.933694 13.25 0.870125 13.2236 0.823242 13.1768C0.776358 13.1299 0.75 13.0663 0.75 13V1C0.75 0.933696 0.776358 0.870126 0.823242 0.823242C0.870126 0.776358 0.933696 0.75 1 0.75ZM1.25 12.75H9V5H1.25V12.75ZM9.5 12.75H12.75V5H9.5V12.75ZM1.25 4.5H12.75V1.25H1.25V4.5Z" stroke="#525866"/>
    </svg>
</template>

<script>
export default {
name: 'FeedIcon',
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