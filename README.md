# WP Social Reviews (WP Social Ninja)

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/wp-social-reviews.svg)](https://wordpress.org/plugins/wp-social-reviews/)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/wp-social-reviews.svg)](https://wordpress.org/plugins/wp-social-reviews/)
[![WordPress Plugin Rating](https://img.shields.io/wordpress/plugin/r/wp-social-reviews.svg)](https://wordpress.org/plugins/wp-social-reviews/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**The Ultimate Social Media & Review Management Plugin for WordPress**

WP Social Reviews (WP Social Ninja) is a powerful, all-in-one WordPress plugin that helps you seamlessly embed social feeds, display customer reviews, and integrate chat widgets on your website. Boost engagement, build trust, and grow your online presence by showcasing authentic social proof from multiple platforms.

---

## 🚀 Key Features

### 📱 Social Media Feed Integration
- **Facebook Feed** - Display your Facebook page posts, photos, videos, and albums
- **Instagram Feed** - Show Instagram photos and videos in beautiful layouts
- **YouTube Feed** - Embed YouTube channel videos and playlists
- **Twitter Feed** - Display Twitter timeline and tweets
- **TikTok Feed** - Showcase TikTok videos on your website

### ⭐ Review Management
- **Google Reviews** - Display Google Business reviews with ratings
- **Facebook Reviews** - Show Facebook page reviews and recommendations
- **Yelp Reviews** - Embed Yelp business reviews
- **Trustpilot Reviews** - Display Trustpilot reviews and ratings
- **WordPress.org Reviews** - Showcase plugin/theme reviews
- **Custom Reviews** - Create and manage your own review testimonials

### 💬 Chat Widgets
- **Facebook Messenger** - Add Facebook Messenger chat to your site
- **WhatsApp Chat** - Enable WhatsApp chat button
- **Live Chat Integration** - Multiple chat widget options

### 🎨 Display Features
- **Multiple Layouts** - Grid, masonry, list, carousel, and slider layouts
- **Responsive Design** - Mobile-friendly and fully responsive
- **Custom Styling** - Customize colors, fonts, and spacing
- **Filtering & Sorting** - Filter by platform, date, or rating
- **Lazy Loading** - Optimize performance with lazy load
- **Schema Markup** - Built-in SEO-friendly schema support

---

## 📋 Requirements

- **WordPress Version:** 5.0 or higher
- **PHP Version:** 7.4 or higher
- **MySQL Version:** 5.6 or higher

---

## 🔧 Installation

### Automatic Installation

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins → Add New**
3. Search for "WP Social Reviews" or "WP Social Ninja"
4. Click **Install Now** on the WP Social Reviews plugin
5. After installation, click **Activate**

### Manual Installation

1. Download the plugin ZIP file from [WordPress.org](https://wordpress.org/plugins/wp-social-reviews/)
2. Log in to your WordPress admin dashboard
3. Navigate to **Plugins → Add New → Upload Plugin**
4. Choose the downloaded ZIP file and click **Install Now**
5. After installation, click **Activate Plugin**

### Via FTP

1. Download and extract the plugin ZIP file
2. Upload the `wp-social-reviews` folder to `/wp-content/plugins/` directory via FTP
3. Log in to WordPress admin dashboard
4. Navigate to **Plugins** and activate **WP Social Reviews**

---

## 🎯 Quick Start Guide

### Step 1: Connect Your Platforms

After activation, navigate to **WP Social Ninja** in your WordPress admin menu:

1. Click on **Settings** or **Platforms**
2. Choose the platform you want to connect (Facebook, Instagram, Google, etc.)
3. Follow the platform-specific authentication process
4. Grant necessary permissions and save your connection

### Step 2: Create a Feed or Review Template

1. Go to **WP Social Ninja → Add New Template**
2. Select the type: Social Feed, Reviews, or Chat Widget
3. Choose your connected platform
4. Configure display settings:
   - Layout style (grid, carousel, list, etc.)
   - Number of items to display
   - Colors and styling
   - Filtering options
5. Save your template

### Step 3: Display on Your Site

**Using Shortcode:**
```
[wp_social_ninja id="123" platform="facebook"]
```

**Using Gutenberg Block:**
1. Edit any page or post
2. Add a new block and search for "WP Social Ninja"
3. Select your template from the dropdown
4. Publish or update your page

**Using Widget:**
1. Navigate to **Appearance → Widgets**
2. Drag "WP Social Ninja" widget to your sidebar
3. Select your template and configure settings
4. Save the widget

**Using PHP Code:**
```php
<?php 
if (function_exists('wp_social_ninja_render')) {
    echo wp_social_ninja_render(123); // Replace 123 with your template ID
}
?>
```

---

## 🎨 Customization Options

### Styling & Design
- Custom color schemes to match your brand
- Typography controls (font family, size, weight)
- Spacing adjustments (padding, margins)
- Border and shadow effects
- Custom CSS support

### Layout Options
- **Grid Layout** - Classic grid display
- **Masonry Layout** - Pinterest-style layout
- **List Layout** - Vertical list view
- **Carousel** - Auto-rotating slider
- **Slider** - Manual slide navigation

### Advanced Features
- Content filtering and moderation
- Cache management for optimal performance
- API rate limiting controls
- GDPR compliance settings
- Multi-language support (WPML, Polylang compatible)

---

## 🔐 Privacy & Security

WP Social Reviews takes privacy and security seriously:

- **Data Storage** - Reviews and feeds are cached locally for performance
- **GDPR Compliant** - Built-in privacy controls and data management
- **Secure Connections** - All API connections use HTTPS/SSL
- **No Data Sharing** - Your data is never shared with third parties
- **Regular Updates** - Security patches and updates released regularly

---

## 📚 Documentation & Support

### Documentation
Visit our comprehensive documentation for detailed guides, tutorials, and troubleshooting:
- [Official Documentation](https://wpsocialninja.com/docs/)
- [Video Tutorials](https://wpsocialninja.com/tutorials/)
- [Knowledge Base](https://wpsocialninja.com/kb/)

### Getting Help
- **Free Support** - [WordPress.org Support Forum](https://wordpress.org/support/plugin/wp-social-reviews/)
- **Premium Support** - Available for Pro users via [support portal](https://wpsocialninja.com/support/)
- **Community** - Join our [Facebook Community Group](https://facebook.com/groups/wpsocialninja/)

### Common Issues
- **API Connection Errors** - Check your API credentials and permissions
- **Feed Not Displaying** - Clear cache and verify shortcode placement
- **Rate Limits** - Adjust cache duration in settings
- **Styling Issues** - Check for theme conflicts and CSS priorities

---

## 🌟 Pro Version

Upgrade to **WP Social Ninja Pro** for advanced features:

### Pro Features Include:
- ✅ Unlimited templates and feeds
- ✅ Advanced filtering and moderation
- ✅ Analytics and insights dashboard
- ✅ Priority support with faster response times
- ✅ White-label options
- ✅ Advanced customization options
- ✅ Additional platform integrations
- ✅ Campaign and promotion tools
- ✅ Team collaboration features
- ✅ Regular feature updates

[**Upgrade to Pro →**](https://wpsocialninja.com/pricing/)

---

## 🤝 Contributing

We welcome contributions from the community! Here's how you can help:

### Reporting Bugs
- Check existing [GitHub Issues](https://github.com/WPManageNinja/wp-social-reviews/issues) first
- Provide detailed reproduction steps
- Include WordPress version, PHP version, and plugin version
- Add screenshots or error logs if possible

### Suggesting Features
- Open a [feature request](https://github.com/WPManageNinja/wp-social-reviews/issues/new) on GitHub
- Explain the use case and benefits
- Check if similar requests already exist

### Development
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Translation
Help translate WP Social Reviews into your language:
- Visit [WordPress.org Translation](https://translate.wordpress.org/projects/wp-plugins/wp-social-reviews/)
- Or submit translations via [GitHub](https://github.com/WPManageNinja/wp-social-reviews)

---

## 📝 Frequently Asked Questions

### Is WP Social Reviews free?
Yes! The core plugin is completely free with many powerful features. A Pro version is available with additional advanced features.

### Which social platforms are supported?
Facebook, Instagram, YouTube, Twitter, TikTok, Google Reviews, Yelp, Trustpilot, and more. Check the features list for complete details.

### Do I need coding knowledge?
No coding knowledge required! The plugin features an intuitive interface with drag-and-drop functionality and visual editors.

### Will it slow down my website?
No. The plugin is optimized for performance with caching, lazy loading, and efficient code. It won't slow down your website.

### Is it mobile responsive?
Yes! All feeds, reviews, and widgets are fully responsive and look great on all devices.

### Can I customize the design?
Absolutely! You can customize colors, layouts, fonts, spacing, and add custom CSS for complete control.

### Is it GDPR compliant?
Yes. The plugin includes GDPR-compliant features and privacy controls to help you meet data protection requirements.

### Can I display multiple feeds on one page?
Yes! You can add unlimited feeds and templates on any page using shortcodes, blocks, or widgets.

### Does it work with page builders?
Yes! WP Social Reviews works with Elementor, Divi, Beaver Builder, WPBakery, and other popular page builders.

### How often are feeds updated?
Feeds are cached for performance. You can configure cache duration in settings (default is every few hours).

---

## 🔄 Changelog

### Version History
For detailed changelog, visit: [https://wordpress.org/plugins/wp-social-reviews/#developers](https://wordpress.org/plugins/wp-social-reviews/#developers)

Latest updates include:
- Regular security enhancements
- Performance optimizations
- New platform integrations
- Bug fixes and improvements
- WordPress compatibility updates

---

## 📄 License

WP Social Reviews is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

---

## 👥 Credits

### Development Team
**WP Social Reviews** is developed and maintained by [WPManageNinja](https://wpmanageninja.com/)

### Acknowledgments
- Thanks to all our contributors and community members
- Special thanks to the WordPress community
- Icons and assets from various open-source projects

---

## 🔗 Useful Links

- **Plugin Homepage:** [https://wpsocialninja.com/](https://wpsocialninja.com/)
- **WordPress.org Page:** [https://wordpress.org/plugins/wp-social-reviews/](https://wordpress.org/plugins/wp-social-reviews/)
- **Documentation:** [https://wpsocialninja.com/docs/](https://wpsocialninja.com/docs/)
- **Support Forum:** [https://wordpress.org/support/plugin/wp-social-reviews/](https://wordpress.org/support/plugin/wp-social-reviews/)
- **GitHub Repository:** [https://github.com/WPManageNinja/wp-social-reviews](https://github.com/WPManageNinja/wp-social-reviews)
- **Facebook Community:** [Join our Facebook Group](https://facebook.com/groups/wpsocialninja/)

---

## ⭐ Show Your Support

If you find WP Social Reviews helpful, please:
- ⭐ Star this repository on GitHub
- 📝 Leave a review on [WordPress.org](https://wordpress.org/support/plugin/wp-social-reviews/reviews/)
- 🐦 Follow us on [Twitter](https://twitter.com/wpmanageninja)
- 👍 Like our [Facebook Page](https://facebook.com/wpmanageninja)
- 📧 Subscribe to our [newsletter](https://wpsocialninja.com/newsletter/)

---

**Made with ❤️ by WPManageNinja**

*Boost engagement, build trust, and showcase social proof on your WordPress website!*
