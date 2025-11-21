<template>
    <svg :width="width" :height="height" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M10.125 5.375V9.875H14.625V10.125H10.125V14.625H9.875V10.125H5.375V9.875H9.875V5.375H10.125Z" :fill="fill" :stroke="stroke" stroke-width="1.25"/>
    </svg>
</template>
<script>
export default {
name: 'PlusIcon',
    props: {
        width: {
            type: [String, Number],
            default: 20
        },
        height: {
            type: [String, Number],
            default: 20
        },
        fill:{
            type: String,
            default: '#525866'
        },
        stroke: {
            type: String,
            default: '#525866'
        }
    }
}
</script>