# bbh-redirection

- Contributors: jahidshah
- Donate link: https://www.buymeacoffee.com/jahidshah
- Tags: redirection, 301 redirect, redirect manager, url redirect, seo redirects
- Requires at least: 5.2
- Requires PHP: 7.2
- Tested up to: 7.0
- Stable tag: 1.1.0
- License: GPL v2 or later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight WordPress redirect manager for creating and managing 301 redirects, tracking 404 errors, and maintaining SEO during URL changes and site migrations.

## Description

BBH Redirection is a lightweight WordPress redirect manager plugin that helps you create and manage 301 redirects easily. Perfect for fixing broken links, migrating URLs, changing permalinks, and improving SEO without editing .htaccess files or server configurations.

Unlike heavy redirect plugins, BBH Redirection focuses on simplicity, speed, and minimal resource usage.

## Features

* Create unlimited 301 redirects
* Monitor 404 and track missing pages
* Lightweight and performance-focused
* Clean and beginner-friendly admin UI
* Custom database table with indexed lookups
* Bulk delete redirects quickly
* Input validation and sanitization for security
* Uses WordPress nonce verification and capability checks
* No .htaccess editing required
* No external libraries or dependencies

## Installation

1. Upload the `bbh-redirection` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to the "Redirections" menu in your WordPress admin to add redirects

## Frequently Asked Questions

### Does this plugin support temporary redirects (302)?

Currently, BBH Redirection supports permanent 301 redirects only.

### Does the plugin prevent redirect loops?

Yes. BBH Redirection includes basic redirect loop detection to help prevent accidental self-redirects.

### How do I add a redirect?

1. Go to Redirections in your WordPress admin
2. Enter the source URL (e.g., /old-page/)
3. Enter the destination URL (e.g., https://example.com/new-page/)
4. Click "Add Redirect"

### Can I redirect to external URLs?

Yes, you can redirect to any valid URL including external sites.

### Will this plugin slow down my site?

No. The plugin uses a single optimized database query and only runs on non-admin requests, keeping performance impact minimal.

## 🤝 Support This Project

This plugin is maintained by **MdJahidShah** as part of the **BusinessBridgeHub** open-source WordPress ecosystem.

**BBH Redirection** is actively developed to help WordPress website owners manage 301 redirects, monitor 404 errors, and maintain SEO during URL changes, content restructuring, and website migrations.

If this plugin helps improve your workflow or maintain your website's SEO, consider supporting ongoing development:

☕ [![Buy Me A Coffee](https://img.shields.io/badge/Support-Buy%20Me%20a%20Coffee-yellow)](https://www.buymeacoffee.com/jahidshah/)


**Your support helps fund:**

* WordPress compatibility testing
* New feature development
* Bug fixes and maintenance
* Documentation and user support
* Future improvements across the BusinessBridgeHub ecosystem


## 🔗 Other Ecosystem Tools

Part of the **Business Bridge Hub** ecosystem:

- BBH Lite Theme  
  https://github.com/businessbridgehub/bbh-lite/

- BBH Custom Schema  
  https://github.com/MdJahidShah/bbh-custom-schema/
  
- BBH Security Insight
  https://github.com/businessbridgehub/bbh-security-insight/

- Additional tools and frameworks available at:
  https://businessbridgehub.com/products/

---

## 📩 Support

Need help or want to report an issue? Visit our support page or open a support ticket on the WordPress plugin repository.

* Website: https://businessbridgehub.com/contact/
* Support: https://wordpress.org/support/plugin/bbh-redirection/

---

**BusinessBridgeHub** — Engineering the security layer of WordPress ecosystems.