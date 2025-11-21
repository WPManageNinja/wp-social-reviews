<template>
    <div v-if="errors.has(field)" class="el-form-item__error">
        <p>{{ errors.first(field) }}</p>
    </div>
</template>

<script type="text/babel">
    export default {
        name: 'errorView',
        props: ['field', 'errors']
    }
</script>
