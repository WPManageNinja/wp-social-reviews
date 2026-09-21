export const wpsrChatMessageContextMixin = {
  methods: {
    wpsrGetMessageContext(channel) {
      if (channel.name !== 'whatsapp') {
        return '';
      }

      const context = this.configs.chat_button.message_context || '';

      return context === 'none' ? '' : context;
    },
    wpsrGetContextMessage(channel) {
      const context = this.wpsrGetMessageContext(channel);
      let format = this.configs.chat_button.page_message_format;

      if (context === 'custom') {
        format = this.configs.chat_button.custom_message_format;
      } else if (context === 'woocommerce_product') {
        format = this.configs.chat_button.woocommerce_message_format;
      } else if (context === 'fluentcart_product') {
        format = this.configs.chat_button.fluentcart_message_format;
      }

      return this.wpsrFormatContextMessage(format);
    },
    wpsrFormatContextMessage(format) {
      const replacements = {
        '{page_title}': document.title,
        '{page_url}': window.location.href,
        '{page_link}': window.location.href,
        '{product_title}': '',
        '{product_price}': '',
        '{product_sku}': '',
        '{product_stock}': '',
        '{product_url}': '',
        '{product_link}': '',
        '{woocommerce_product_title}': '',
        '{woocommerce_product_price}': '',
        '{woocommerce_product_sku}': '',
        '{woocommerce_product_stock}': '',
        '{woocommerce_product_url}': '',
        '{woocommerce_product_link}': '',
        '{fluentcart_product_title}': '',
        '{fluentcart_product_price}': '',
        '{fluentcart_product_sku}': '',
        '{fluentcart_product_stock}': '',
        '{fluentcart_product_url}': '',
        '{fluentcart_product_link}': ''
      };
      let contextMessage = format || '';

      Object.keys(replacements).forEach((placeholder) => {
        contextMessage = contextMessage.split(placeholder).join(replacements[placeholder]);
      });

      return contextMessage.trim();
    },
    wpsrGetMessageWithContext(message, channel) {
      if (!this.wpsrGetMessageContext(channel)) {
        return message;
      }

      const contextMessage = this.wpsrGetContextMessage(channel);

      return message ? message + '\n\n' + contextMessage : contextMessage;
    },
    wpsrAppendContextToChatUrl(url, channel) {
      if (!this.wpsrGetMessageContext(channel)) {
        return url;
      }

      try {
        const parsedUrl = new URL(url, window.location.href);
        const message = parsedUrl.searchParams.get('text') || '';
        parsedUrl.searchParams.set('text', this.wpsrGetMessageWithContext(message, channel));

        return parsedUrl.toString();
      } catch (error) {
        return url;
      }
    },
  }
};
