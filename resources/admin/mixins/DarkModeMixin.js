export const DarkModeMixin = {
    data() {
        return {
            isDarkMode: false,
            observer: null
        }
    },
    methods: {
        checkDarkMode() {
            this.isDarkMode = document.body.classList.contains('wpsr-dark-mode');
        },
        initDarkModeObserver() {
            this.checkDarkMode();
            
            // Watch for changes in dark mode class
            this.observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        const newDarkMode = document.body.classList.contains('wpsr-dark-mode');
                        if (newDarkMode !== this.isDarkMode) {
                            this.isDarkMode = newDarkMode;
                            // Call component-specific dark mode handler if it exists
                            if (this.onDarkModeChange && typeof this.onDarkModeChange === 'function') {
                                this.onDarkModeChange(newDarkMode);
                            }
                        }
                    }
                });
            });
            
            this.observer.observe(document.body, {
                attributes: true,
                attributeFilter: ['class']
            });
        },
        disconnectDarkModeObserver() {
            if (this.observer) {
                this.observer.disconnect();
                this.observer = null;
            }
        }
    },
    created() {
        if (typeof document !== 'undefined' && document.body) {
            const theme = localStorage.getItem('wpsr-theme');
            if (theme === 'dark' && !document.body.classList.contains('wpsr-dark-mode')) {
                document.body.classList.add('wpsr-dark-mode');
            }
            this.checkDarkMode();
        }
    },
    mounted() {
        this.initDarkModeObserver();
    },
    beforeUnmount() {
        this.disconnectDarkModeObserver();
    }
}