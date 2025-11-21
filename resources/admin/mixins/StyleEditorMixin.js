export const StyleEditorMixin = {
  methods: {
    hasVisibleElements(option, optionsKey) {
      if (!this.feed_config?.styles?.[option.key] || !this.show_section(optionsKey, option)) {
        return false;
      }

      // Check if any of the child elements will be visible
      const hasColorElement = option.styles && option.styles.length && 
                              this.feed_config.styles[option.key].color && 
                              this.show_section(optionsKey, option);

      const hasTypographyElement = option.typography && 
                                   this.feed_config.styles[option.key] && 
                                   this.show_section(optionsKey, option);

      const hasSpacingElement = option.padding && 
                                this.feed_config.styles[option.key] && 
                                this.show_section(optionsKey, option);

      const hasBorderElement = option.border && 
                               this.feed_config.styles[option.key] && 
                               this.show_section(optionsKey, option);

      const hasSliderElement = option.slider && 
                               this.feed_config.styles[option.key] && 
                               this.show_section(optionsKey, option);

      const hasDivider = option.divider;

      return hasColorElement || hasTypographyElement || hasSpacingElement || hasBorderElement || hasSliderElement || hasDivider;
    },
  }
};