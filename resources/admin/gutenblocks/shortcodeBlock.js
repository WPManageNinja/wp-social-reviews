import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { SelectControl } from '@wordpress/components';
import { createElement } from '@wordpress/element';

const socialNinjaLogo = createElement('svg',
  {
    width: 20,
    height: 20
  },
  createElement("path", {
    d: `M9,0C4.3,0,0.4,3.8,0.4,8.6v0c0,4.8,3.8,8.6,8.6,8.6c1.8,0,3.5-0.5,5-1.6c0.2-0.1,0.5-0.2,0.7-0.2c0.6,0.1,1.1,0.5,1.6,1
    c0.5,0.5,0.9,1.1,1.3,1.5V8.6C17.6,3.8,13.8,0,9,0z M13,7.9l-1.5,1.6c0,0,0,0,0,0l0.4,2.2c0.1,0.3,0,0.6-0.1,0.7
    c-0.1,0.1-0.2,0.2-0.3,0.2c-0.1,0-0.3-0.1-0.5-0.1l-1.9-1h0l-1.9,1.1c-0.2,0.1-0.3,0.1-0.5,0.1c-0.1,0-0.3-0.1-0.4-0.2
    c-0.1-0.1-0.2-0.4-0.1-0.7l0.4-2.2c0,0,0,0,0,0L5,7.9c0,0,0,0,0,0C4.8,7.7,4.7,7.5,4.7,7.3c0,0,0-0.1,0-0.1C4.8,7,5,6.8,5.3,6.7
    l2.1-0.3c0,0,0,0,0,0l0.9-2C8.6,4.1,8.8,3.9,9,3.9c0.2,0,0.4,0.2,0.6,0.5l0.9,2c0,0,0,0,0,0l2.1,0.3c0.3,0.1,0.6,0.2,0.6,0.4
    C13.4,7.4,13.3,7.6,13,7.9z`
  })
);

registerBlockType("social-ninja/guten-block", {
  title: __("Social Ninja Templates"),
  icon: socialNinjaLogo,
  category: "formatting",
  keywords: [
    __("Social Ninja Templates"),
    __("social review"),
    __("wpsr"),
    __("wpsr-gutenberg-block")
  ],
  attributes: {
    templateId: {
      type: "string"
    },
    platformName: {
      type: "string",
      default: "reviews"
    }
  },
  edit({ attributes, setAttributes }) {
    const config = window.wpsr_block_vars;
    const changeSelect = function(templateId) {
      var platformName = config.templates.filter(function(item) {
        return parseInt(item.id) === parseInt(templateId);
      })[0].platform;
      setAttributes({ templateId });
      setAttributes({ platformName });
    };

    return createElement(
      'div',
      { className: 'wpsr-guten-wrapper' },
      createElement(
        'div',
        { className: 'wpsr-logo' },
        createElement('img', { src: config.logo, alt: 'WP Social Ninja' })
      ),
      createElement(SelectControl, {
        label: __("Please Select a template"),
        value: attributes.templateId,
        options: config.templates.map(template => ({
          value: template.id,
          label: template.title
        })),
        onChange: templateId => changeSelect(templateId)
      })
    );
  },
  save({ attributes }) {
    return `[wp_social_ninja id=${attributes.templateId} platform='${attributes.platformName}']`;
  }
});