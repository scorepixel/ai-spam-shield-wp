=== AI Spam Shield ===
Contributors: Scorepixel
Tags: spam, comments, contact-form, ai, machine-learning
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Advanced spam filtering for WordPress using AI-powered with support for comments and popular contact forms.

== Description ==

AI Spam Shield integrates your WordPress site with a custom spam detection powered by AI and machine learning. It provides sophisticated spam filtering for:

* WordPress Comments
* Contact Form 7
* WPForms
* Gravity Forms

**Features:**

* AI-powered spam detection
* Configurable spam confidence threshold
* Detailed logging and analytics

**How it Works:**

1. Install and activate the plugin
2. Configure your spam detection API endpoint
3. Set your desired spam threshold (0-1)
4. Enable filtering for comments and/or contact forms
5. The plugin automatically sends content to your API for analysis
6. Spam is blocked based on AI confidence scores

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-spam-shield/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > AI Spam Shield to configure
4. Enter your API URL and optional API key
5. Test the connection using the "Test API" button
6. Enable the features you want to use

== Frequently Asked Questions ==

= What API endpoint do I need? =

You need a running instance of the spam detection API server (included in the repository). The API should accept POST requests with JSON body containing a "content" field.

= Does this work without an API? =

No, this plugin requires a working spam detection API endpoint. You can host the API on your own server or use a cloud service.

= Which contact form plugins are supported? =

Currently supported:
* Contact Form 7
* WPForms
* Gravity Forms

= Can I adjust the spam sensitivity? =

Yes! Go to Settings > AI Spam Filter and adjust the "Spam Threshold" value. Lower values are more strict (0.5 = 50% confidence), higher values are more lenient (0.8 = 80% confidence).

= Where can I view spam logs? =

Go to Settings > Spam Logs to view detailed logs of all spam checks, including confidence scores, matched keywords, and detection methods.

= Will this slow down my site? =

The plugin includes configurable timeouts (default 5 seconds) and only checks user-submitted content. If the API is unavailable or times out, content is allowed through to prevent blocking legitimate users.

== Screenshots ==

1. Settings page with API configuration
2. Spam logs dashboard with statistics
3. Detailed spam detection results

== Changelog ==

= 1.0.0 =
* Initial release
* Support for WordPress comments
* Support for Contact Form 7, WPForms, Gravity Forms
* Configurable API endpoint and authentication
* Spam confidence threshold settings
* Detailed logging system
* API connection testing
* Statistics dashboard

== Upgrade Notice ==

= 1.0.0 =
Initial release of AI Spam Shield.

== API Response Format ==

Your API should return JSON in this format:

```json
{
  "is_spam": true,
  "confidence": 0.85,
  "method": "hybrid-llm",
  "matched_keywords": ["inheritance", "beneficiary"],
  "timestamp": "2025-10-24T00:00:00.000Z"
}
```

== Server Requirements ==

* PHP 7.4 or higher
* WordPress 5.0 or higher
* cURL extension enabled
* Access to external API endpoint

== Support ==

For support and bug reports, please visit: https://github.com/yourusername/ai-spam-shield