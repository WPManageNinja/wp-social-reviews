<template>
  <span class="wpsr-reviewer-position">
    {{displayAuthorPosition === 'true' && authorPosition ? authorPosition : ''}}
    {{displayAuthorCompanyName === 'true' && author_company_name ? '@'+author_company_name : ''}}
  </span>
</template>

<script type="text/babel">
export default {
  name: 'AuthorPosition',
  props: ['authorPosition', 'author_company_name', 'displayAuthorPosition', 'displayAuthorCompanyName'],
}
</script>