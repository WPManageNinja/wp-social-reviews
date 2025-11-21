export const helperStyle = {
    methods: {
         validateTypography : function ( typography ) {
            let validatedTypography = '';
            let unit = 'px';
            if(typography.font_size.desktop > 0){
                validatedTypography += 'font-size: ' + typography.font_size.desktop + unit + ';';
            }
            if(typography.letter_spacing.desktop > 0) {
                validatedTypography += 'letter-spacing: ' + typography.letter_spacing.desktop +  unit + ';';
            }
            if(typography.line_height.desktop > 0) {
                validatedTypography += 'line-height: ' + typography.line_height.desktop +  unit + ';';
            }
            if(typography.font_style) {
                validatedTypography += 'font-style: ' + typography.font_style + ';' ;
            }
            if(typography.font_weight){
                validatedTypography += 'font-weight: ' + typography.font_weight + ';' ;
            }
            if(typography.text_transform) {
                validatedTypography += 'text-transform: ' + typography.text_transform + ';';
            }
            if(typography.text_decoration) {
                validatedTypography += 'text-decoration: ' + typography.text_decoration + ';';
            }
            return validatedTypography;
        },

        validateSpacing: function ( spacing, spacing_unit, device = 'desktop', unit = 'px' ) {
            let validatedSpacing = '';
            spacing.top && spacing.top[device] > 0 ? validatedSpacing += `${spacing_unit}-top: ` + spacing.top[device] + unit + ';' : '';
            spacing.right && spacing.right[device] > 0 ? validatedSpacing += `${spacing_unit}-right: ` + spacing.right[device] + unit + ';' : '';
            spacing.bottom && spacing.bottom[device] > 0 ? validatedSpacing += `${spacing_unit}-bottom: ` + spacing.bottom[device] + unit + ';' : '';
            spacing.left && spacing.left[device] > 0 ? validatedSpacing += `${spacing_unit}-left: ` + spacing.left[device] + unit + ';' : '';
            return validatedSpacing;
        },

        validateColor: function ( color  ) {
            let validatedColor = color.text_color ? 'color: ' + color.text_color + ';' : '' ;
            validatedColor += color.background_color ? 'background-color: ' + color.background_color + ';' : '' ;
            validatedColor += color.fill_color ? 'fill: ' + color.fill_color + ';' : '' ;
            validatedColor += color.border_color ? 'border-color: ' + color.border_color + ';' : '' ;
            return validatedColor;
        },

        validateBorder: function (border, device = 'desktop', unit = 'px') {
            let validatedBorder = '';
            if(border.border_style) {
                if (border.top[device] > 0) {
                    validatedBorder += 'border-top: ' + border.top[device] + unit + ' '+ border.border_style + ' '+ border.border_color +  ';';
                }
                if (border.right[device] > 0) {
                    validatedBorder += 'border-right: ' + border.right[device] + unit + ' '+ border.border_style + ' '+ border.border_color +  ';';
                }
                if (border.bottom[device] > 0) {
                    validatedBorder += 'border-bottom: ' + border.bottom[device] + unit + ' '+ border.border_style + ' '+ border.border_color +  ';';
                }
                if (border.left[device] > 0) {
                    validatedBorder += 'border-left: ' + border.left[device] + unit + ' '+ border.border_style + ' '+ border.border_color +  ';';
                }
            }
            return validatedBorder;
        },

        validateResponsiveTypography: function (typography, selector, device = 'tablet', unit = 'px') {
            // Check if typography is defined and has the required properties
            if (!typography || !selector) {
                return '';
            }

            // Ensure font_size, letter_spacing, and line_height objects exist
            const fontSize = typography.font_size && typography.font_size[device];
            const letterSpacing = typography.letter_spacing && typography.letter_spacing[device];
            const lineHeight = typography.line_height && typography.line_height[device];

            // Only proceed if at least one of the properties has a positive value
            if ((fontSize && fontSize > 0) ||
                (letterSpacing && letterSpacing > 0) ||
                (lineHeight && lineHeight > 0)) {

                // Set the appropriate width based on device
                const width = device === 'tablet' ? 960 : 480;

                // Build the CSS style string with proper validation
                let customStyleTablet = '';

                // Only add font-size if it's a positive number
                if (fontSize && fontSize > 0) {
                    customStyleTablet += 'font-size: ' + fontSize + unit + ';';
                }

                // Only add letter-spacing if it's a positive number
                if (letterSpacing && letterSpacing > 0) {
                    customStyleTablet += 'letter-spacing: ' + letterSpacing + unit + ';';
                }

                // Only add line-height if it's a positive number
                if (lineHeight && lineHeight > 0) {
                    customStyleTablet += 'line-height: ' + lineHeight + unit + ';';
                }

                // Only create media query if we have styles to apply
                if (customStyleTablet) {
                    return '@media screen and (max-width: ' + width + 'px){'
                        + selector + '{' + customStyleTablet + '}' + '}';
                }
            }

            // Return empty string if no valid styles were found
            return '';
        },

        validateResponsiveSpacing: function (spacing, selector, device = 'tablet' , unit = 'px' , spacing_unit = 'margin') {
             if (spacing !== undefined && (spacing.top && spacing.top[device] > 0 || spacing.right && spacing.right[device] > 0 || spacing.bottom && spacing.bottom[device] > 0 || spacing.left && spacing.left[device] > 0)) {
                let width =  device === 'tablet' ? 960 : 480;
                let customResponsiveStyleTablet = '';
                let customStyleTablet = this.validateSpacing(spacing, spacing_unit, device, unit);
                customResponsiveStyleTablet += '@media screen and (max-width: '+ width +'px){'
                    + selector + '{' + customStyleTablet + '}' + '}';
                return customResponsiveStyleTablet;
            }
        },

        validateResponsiveBorder: function (border, selector, device = 'tablet' , unit = 'px' ){
            if (border !== undefined && (border.top[device] > 0 || border.right[device] > 0 || border.bottom[device] > 0 || border.left[device] > 0)) {
                let width = device === 'tablet' ? 960 : 480;
                let customResponsiveStyleTablet = '';
                let customStyleTablet = this.validateBorder(border, device, unit);
                if (customStyleTablet) {
                    customResponsiveStyleTablet += '@media screen and (max-width: ' + width + 'px){'
                        + selector + '{' + customStyleTablet + '}' + '}';
                }
                return customResponsiveStyleTablet;
            }
        },

        validateResponsiveStyle: function ( styles, selector,  spacing_unit = '' ) {
            let customResponsiveStyleTablet = '';
            let customResponsiveStyleMobile = '';
            let typography = styles.typography;
            let margin = styles.margin;
            let padding = styles.padding;
            let border = styles.border;
            let slider = styles.slider;
            let unit = 'px';
            if(typography !== undefined && (typography.font_size.tablet > 0 || typography.letter_spacing.tablet > 0 || typography.line_height.tablet > 0)) {
                let validatedResponsiveTypographyTablet = this.validateResponsiveTypography(typography, selector, 'tablet', unit);
                customResponsiveStyleTablet += validatedResponsiveTypographyTablet;
            }
            if (margin !== undefined && (margin.top.tablet > 0 || margin.right.tablet > 0 || margin.bottom.tablet > 0 || margin.left.tablet > 0)) {
                let validatedResponsiveSpacingTablet = this.validateResponsiveSpacing(margin, selector, 'tablet', unit, 'margin');
                customResponsiveStyleTablet += validatedResponsiveSpacingTablet;
            }
            if (padding !== undefined && (padding.top.tablet > 0 || padding.right.tablet > 0 || padding.bottom.tablet > 0 || padding.left.tablet > 0)) {
                let validatedResponsiveSpacingTablet = this.validateResponsiveSpacing(padding, selector, 'tablet', unit, 'padding');
                customResponsiveStyleTablet += validatedResponsiveSpacingTablet;
            }
            if (border !== undefined && (border.top.tablet > 0 || border.right.tablet > 0 || border.bottom.tablet > 0 || border.left.tablet > 0)) {
                let validatedResponsiveBorderTablet = this.validateResponsiveBorder(border, selector, 'tablet', unit );
                customResponsiveStyleTablet += validatedResponsiveBorderTablet;
            }
            if (slider !== undefined && (slider.top && slider.top.tablet > 0 || slider.right && slider.right.tablet > 0 || slider.bottom && slider.bottom.tablet > 0 || slider.left && slider.left.tablet > 0)) {
                let validatedResponsiveSpacingTablet = this.validateResponsiveSpacing(slider, selector, 'tablet', unit, 'margin');
                customResponsiveStyleTablet += validatedResponsiveSpacingTablet;
            }
            if(typography !== undefined && (typography.font_size.mobile > 0 || typography.letter_spacing.mobile > 0 || typography.line_height.mobile > 0)) {
                let validatedResponsiveTypographyMobile = this.validateResponsiveTypography(typography, selector, 'mobile', unit);
                customResponsiveStyleMobile += validatedResponsiveTypographyMobile;
            }
            if (margin !== undefined && (margin.top.mobile > 0 || margin.right.mobile > 0 || margin.bottom.mobile > 0 || margin.left.mobile > 0)) {
                let validatedResponsiveSpacingMobile = this.validateResponsiveSpacing(margin, selector, 'mobile', unit, 'margin');
                customResponsiveStyleMobile += validatedResponsiveSpacingMobile;
            }
            if (padding !== undefined && (padding.top.mobile > 0 || padding.right.mobile > 0 || padding.bottom.mobile > 0 || padding.left.mobile > 0)) {
                let validatedResponsiveSpacingMobile = this.validateResponsiveSpacing(padding, selector, 'mobile', unit, 'padding');
                customResponsiveStyleMobile += validatedResponsiveSpacingMobile;
            }
            if (border !== undefined && (border.top.mobile > 0 || border.right.mobile > 0 || border.bottom.mobile > 0 || border.left.mobile > 0)) {
                let validatedResponsiveBorderMobile = this.validateResponsiveBorder(border, selector, 'mobile', unit);
                customResponsiveStyleMobile += validatedResponsiveBorderMobile;
            }
            if (slider !== undefined && (slider.top && slider.top.mobile > 0 || slider.right && slider.right.mobile > 0 || slider.bottom && slider.bottom.mobile > 0 || slider.left && slider.left.mobile > 0)) {
                let validatedResponsiveSpacingMobile = this.validateResponsiveSpacing(slider, selector, 'mobile', unit, 'margin');
                customResponsiveStyleMobile += validatedResponsiveSpacingMobile;
            }
            let customResponsiveStyle = '';
            customResponsiveStyle = customResponsiveStyleTablet + customResponsiveStyleMobile;
            return customResponsiveStyle;
        },

        resetVariableHandler(response, identifier ){
            // Check if typography exists for this identifier before trying to destructure
            if (!response.styles[identifier].typography) {
                // For identifiers without typography (like star_rating), set empty objects
                this[`temp_style_${identifier}`] = {};
                this[`temp_style_${identifier}_responsive`] = {
                    font_size: {},
                    letter_spacing: {},
                    line_height: {},
                };
                return;
            }

            let component_style = response.styles[identifier].typography;
            let {
                font_size: component_style_font_size,
                letter_spacing: component_style_letter_spacing,
                line_height: component_style_line_height,
            } = response.styles[identifier].typography;

            this[`temp_style_${identifier}`] = {
                ...component_style
            }
            this[`temp_style_${identifier}_responsive`] = {
                font_size: {...component_style_font_size},
                letter_spacing: {...component_style_letter_spacing},
                line_height: {...component_style_line_height},
            }
        },

        resetHandler (identifier, styles, responsiveStyle) {
            // Only set typography if it exists for this identifier
            if (this.feed_config.styles[identifier].typography) {
                this.feed_config.styles[identifier].typography = {...styles};
                this.feed_config.styles[identifier].typography.font_size = {...responsiveStyle.font_size};
                this.feed_config.styles[identifier].typography.letter_spacing = {...responsiveStyle.letter_spacing};
                this.feed_config.styles[identifier].typography.line_height = {...responsiveStyle.line_height};
            }
        },

        handleStyles(styles){
            let all_styles = [];
            let all_responsive_styles = [];

            styles.forEach((style) =>{
                let style_selector = style.selector;
                let typography_style = style.typography;
                let color_style = style.color;
                let margin_style = style.margin;
                let padding_style = style.padding;
                let border_style = style.border;
                let spacing_style = style.slider;

                let validated_typography = typography_style ? this.validateTypography(typography_style) : '';
                let validated_color = color_style ? this.validateColor(color_style) : '';
                let validated_margin = margin_style ? this.validateSpacing(margin_style , 'margin', 'desktop', 'px') : '';
                let validated_padding = padding_style ? this.validateSpacing(padding_style , 'padding', 'desktop', 'px') : '';
                let validated_border = border_style ? this.validateBorder(border_style, 'desktop', 'px') : '';
                let validated_spacing = spacing_style ? this.validateSpacing(spacing_style , 'margin', 'desktop', 'px') : '';
                
                // Standard processing for all styles
                let combined_style =  validated_typography + validated_color + validated_margin + validated_padding + validated_border + validated_spacing;
                if(combined_style) {
                    let css_style = style_selector + '{' + combined_style + '}';
                    if(css_style){
                        all_styles.push(css_style.trim());
                    }
                }

                let validated_responsive_style =  this.validateResponsiveStyle(style , style_selector);
                if(validated_responsive_style){
                    all_responsive_styles.push(validated_responsive_style.trim());
                }
            });

            this.styleTagHandler(all_styles, all_responsive_styles);
        },

        styleTagHandler ( all_styles , all_responsive_styles ) {
            if(this.styleTag){
                this.styleTag.remove()
            }

            this.styleTag = document.createElement('style');
            this.styleTag.type = 'text/css';
            this.styleTag.id = 'wp-social-reviews';

            this.$emit('templateCss', all_styles || [], all_responsive_styles || []);

            if (Array.isArray(all_styles)) {
                all_styles.forEach((style) => {
                    if (style && typeof style === 'string') {
                        this.styleTag.appendChild(document.createTextNode(style));
                    }
                });
            }

            if (Array.isArray(all_responsive_styles)) {
                all_responsive_styles.forEach((style) => {
                    if (style && typeof style === 'string') {
                        this.styleTag.appendChild(document.createTextNode(style));
                    }
                });
            }

            document.head.appendChild(this.styleTag);
        },

        templateCss(val, val2) {
            this.feedConfig['styles'] = val || [];
            this.feedConfig['responsive_styles'] = val2 || [];

            var style = '';
            var tabletStyle;

            if (Array.isArray(val2)) {
                val2.forEach((styleVal) => {
                    if(styleVal && typeof styleVal === 'string'){
                        tabletStyle = styleVal.replace('@media screen and (max-width: 960px){', '.is-tablet-active ');
                        style += tabletStyle.replace('@media screen and (max-width: 480px){', '.is-mobile-active ');
                    }
                });
            }

            if (style) {
                style = style.replace('}}', '}');
                style = style.replace('}}', '}');
                this.style = style;
            }
        },

        validateResponsiveCss(){
            let css = this.style;
            if (!css || typeof css !== 'string') {
                return;
            }

            if(css.includes('@media screen and (max-width: 960px){')){
                this.style = css.replace('@media screen and (max-width: 960px){', '.is-tablet-active ');
            } else if (css.includes('@media screen and (max-width: 480px){')){
                this.style = css.replace('@media screen and (max-width: 480px){', '.is-mobile-active ');
            }
        },

        getValue(object, path) {
            const parts = path.split(".");
            return parts.reduce((value, key) => value ? value[key] : value, object)
        },

        show_section(section_name, options) {
            // First check if options is defined
            if (!options) {
                return true;
            }

            if (options.condition) {
                let compare_operator = options.condition.operator ? options.condition.operator : '==';
                if (compare_operator === '==') {
                    let section_value = this.getValue(this.feed_settings, options.condition.key);
                    return section_value === options.condition.selector;
                } else if (compare_operator === '!=') {
                    let section_value = this.getValue(this.feed_settings, options.condition.key);
                    return section_value !== options.condition.selector;
                } else if (compare_operator === 'multiple') {
                    let terms = options.condition.terms, ok = 1;
                    terms.forEach((term) => {
                        let compare_operator = term.operator ? term.operator : '==';
                        let section_value = this.getValue(this.feed_settings, term.key);
                        if (compare_operator === '==') {
                            ok &= section_value === term.selector;
                        } else if (compare_operator === 'includes') {
                            ok &= (term.selector.includes(section_value));
                        } else {
                            ok &= section_value !== term.selector;
                        }
                    });
                    return ok;
                } else if (compare_operator === 'includes') {
                    let section_value = this.getValue(this.feed_settings, options.condition.key);
                    return (options.condition.selector.includes(section_value));
                }
            }

            return true;
        }
    },
    computed:{
        css(){
            if(this.style){
                if(this.styleTag){
                    this.styleTag.remove()
                }
                this.styleTag = document.createElement('style');
                this.styleTag.type = 'text/css';
                this.styleTag.id = 'wp-social-reviews-editor-responsive-css';
                this.styleTag.appendChild(document.createTextNode(this.style));
                document.head.appendChild(this.styleTag);
            }
        }
    },
    watch: {
        'style': {
            handler(val) {
                this.validateResponsiveCss();
            },
            deep:true
        },
    }
}
