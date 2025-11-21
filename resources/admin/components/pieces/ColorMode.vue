<template>
  <div class="wpsr_color_mode">
    <button class="wpsr_color_mode_core wpsr_menu_button" @click="toggleTheme" :aria-label="isDarkMode ? 'Switch to light mode' : 'Switch to dark mode'">
                <span class="wpsr_color_mode_action wpsr_mode_switch el-icon">
                  <svg v-show="isDarkMode" class="show_on_dark" width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M17.9163 11.7317C16.9166 12.2654 15.7748 12.5681 14.5623 12.5681C10.6239 12.5681 7.43128 9.37543 7.43128 5.43705C7.43128 4.22456 7.73388 3.08274 8.2677 2.08301C4.72272 2.91382 2.08301 6.09562 2.08301 9.89393C2.08301 14.3246 5.67476 17.9163 10.1054 17.9163C13.9038 17.9163 17.0855 15.2767 17.9163 11.7317Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  <svg v-show="!isDarkMode" class="show_on_light" width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <g clip-path="url(#clip0_2906_20675)">
                      <path d="M14.1663 10.0007C14.1663 12.3018 12.3009 14.1673 9.99967 14.1673C7.69849 14.1673 5.83301 12.3018 5.83301 10.0007C5.83301 7.69946 7.69849 5.83398 9.99967 5.83398C12.3009 5.83398 14.1663 7.69946 14.1663 10.0007Z" stroke="currentColor" stroke-width="1.5"/>
                      <path d="M9.99984 1.66699V2.91699M9.99984 17.0837V18.3337M15.8922 15.8931L15.0083 15.0092M4.99089 4.99137L4.107 4.10749M18.3332 10.0003H17.0832M2.9165 10.0003H1.6665M15.8926 4.10758L15.0087 4.99147M4.99129 15.0093L4.10741 15.8932" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </g>
                    <defs>
                      <clipPath id="clip0_2906_20675">
                        <rect width="20" height="20" fill="currentColor"/>
                      </clipPath>
                    </defs>
                  </svg>
                </span>
    </button>
  </div>
</template>
<script>
export default {
  name: 'ColorMode',
  data() {
    return {
      isDarkMode: false
    }
  },
  methods: {
    toggleTheme() {
      this.isDarkMode = !this.isDarkMode;
      if (this.isDarkMode) {
        document.body.classList.add('wpsr-dark-mode');
        localStorage.setItem('wpsr-theme', 'dark');
      } else {
        document.body.classList.remove('wpsr-dark-mode');
        localStorage.setItem('wpsr-theme', 'light');
      }
    },
  },
  mounted() {
    const theme = localStorage.getItem('wpsr-theme');
    if (theme === 'dark') {
      this.isDarkMode = true;
      document.body.classList.add('wpsr-dark-mode');
    }
  }
}
</script>