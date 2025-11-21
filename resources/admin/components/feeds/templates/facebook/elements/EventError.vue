<template>
  <div class="wpsr-event-error-container">
    <div class="wpsr-event-error-message">
      <h2>Facebook Events API Limitation</h2>

      <span>
        <p>Facebook has limited event data access through the API. However you can still show event data from your page just follow the documentation</p>
        <a href="https://wpsocialninja.com/docs/facebook-events-access-token/" target="_blank" rel="noopener noreferrer">Social Ninja Facebook Events Feed.</a>
      </span>

      <span v-if="false">
        
        <p>
          We regret to inform you that there are limitations on accessing Facebook event data through the API. As a result, we are currently unable to provide event-related information.
        </p>
        <p>
          To learn more about these limitations, please visit the official Facebook Events API documentation:
        </p>
        <p>
          <a href="https://developers.facebook.com/products/official-events-api/" target="_blank" rel="noopener noreferrer">Official Facebook Events API Documentation</a>
        </p>
      </span>
    </div>
  </div>
</template>

<style scoped>
.wpsr-event-error-container {
  text-align: center;
  margin: 20px;
  padding: 20px;
  border: 1px solid var(--wpsr-primary-border, #E1E4EA);
  background-color: var(--wpsr-secondary-bg, #F5F7FA);
}

.wpsr-event-error-message {
  font-size: 18px;
  color: #333;
  h2{
    color:var(--wpsr-heading-text, #0E121B);
  }
  p{
    color: var(--wpsr-primary-text, #525866);
  }
}

a {
  color: #007bff;
  text-decoration: none;
  font-size: 13px;
}
</style>
