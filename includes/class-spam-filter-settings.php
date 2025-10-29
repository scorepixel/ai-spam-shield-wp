<?php

/** Settings Class */
if (!defined('ABSPATH')) {
    exit;
}

class Spam_Filter_Settings
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('AI Spam Shield', 'ai-spam-shield'),
            __('AI Spam Shield', 'ai-spam-shield'),
            'manage_options',
            'ai-spam-shield',
            array($this, 'render_settings_page'),
            'dashicons-shield',  // Icon
            80  // Position in sidebar
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        // API Settings
        register_setting('spam_filter_api_settings', 'spam_filter_api_url');
        register_setting('spam_filter_api_settings', 'spam_filter_api_key');
        register_setting('spam_filter_api_settings', 'spam_filter_api_license');
        register_setting('spam_filter_api_settings', 'spam_filter_api_threshold');
        register_setting('spam_filter_api_settings', 'spam_filter_api_timeout');
        register_setting('spam_filter_api_settings', 'spam_filter_api_enabled');

        // Feature Settings
        register_setting('spam_filter_api_settings', 'spam_filter_api_check_comments');
        register_setting('spam_filter_api_settings', 'spam_filter_api_check_contact_forms');
        register_setting('spam_filter_api_settings', 'spam_filter_api_log_enabled');

        // API Configuration Section
        add_settings_section(
            'spam_filter_api_section',
            __('API Configuration', 'ai-spam-shield'),
            array($this, 'render_api_section'),
            'ai-spam-shield'
        );

        add_settings_field(
            'spam_filter_api_enabled',
            __('Enable Spam Filter', 'ai-spam-shield'),
            array($this, 'render_enabled_field'),
            'ai-spam-shield',
            'spam_filter_api_section'
        );

        add_settings_field(
            'spam_filter_api_license',
            __('License Key', 'ai-spam-shield'),
            array($this, 'render_license_field'),
            'ai-spam-shield',
            'spam_filter_api_section'
        );

        add_settings_field(
            'spam_filter_api_threshold',
            __('Spam Threshold', 'ai-spam-shield'),
            array($this, 'render_threshold_field'),
            'ai-spam-shield',
            'spam_filter_api_section'
        );

        // Features Section
        add_settings_section(
            'spam_filter_features_section',
            __('Features', 'ai-spam-shield'),
            array($this, 'render_features_section'),
            'ai-spam-shield'
        );

        add_settings_field(
            'spam_filter_api_check_comments',
            __('Check Comments', 'ai-spam-shield'),
            array($this, 'render_check_comments_field'),
            'ai-spam-shield',
            'spam_filter_features_section'
        );

        add_settings_field(
            'spam_filter_api_check_contact_forms',
            __('Check Contact Forms', 'ai-spam-shield'),
            array($this, 'render_check_contact_forms_field'),
            'ai-spam-shield',
            'spam_filter_features_section'
        );

        add_settings_field(
            'spam_filter_api_log_enabled',
            __('Enable Logging', 'ai-spam-shield'),
            array($this, 'render_log_enabled_field'),
            'ai-spam-shield',
            'spam_filter_features_section'
        );
    }

    public static function checked($checked, $current = true, $echo = true)
    {
        $result = '';

        // Compare the values (use loose comparison)
        if ((string) $checked === (string) $current) {
            $result = " checked='checked'";
        }

        if ($echo) {
            echo $result;
        }

        return $result;
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // If settings were just saved, attempt license activation if a license is present
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            Spam_Filter_API::maybe_activate_license();
        }
        ?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors('spam_filter_api_messages'); ?>

    <form action="options.php" method="post">
        <?php
        settings_fields('spam_filter_api_settings');
        do_settings_sections('ai-spam-shield');
        submit_button(__('Save Settings', 'ai-spam-shield'));
        ?>
    </form>
</div>
<?php
    }

    public function render_api_section()
    {
        echo '<p>' . __('Configure your spam detection API settings.', 'ai-spam-shield') . '</p>';
    }

    public function render_features_section()
    {
        echo '<p>' . __('Choose which features to enable.', 'ai-spam-shield') . '</p>';
    }

    public function render_enabled_field()
    {
        $enabled = get_option('spam_filter_api_enabled', true);
?>
<label>
    <input type="checkbox" name="spam_filter_api_enabled" value="1" <?php self::checked($enabled, 1); ?>>
    <?php _e('Enable spam filtering', 'ai-spam-shield'); ?>
</label>
<?php
    }

    public function render_api_key_field()
    {
        $value = get_option('spam_filter_api_key', '');
?>
<input type="password" name="spam_filter_api_key" value="<?php echo esc_attr($value); ?>" class="regular-text">
<p class="description"><?php _e('API key for authentication', 'ai-spam-shield'); ?></p>
<?php
    }

    public function render_license_field()
    {
        $value = get_option('spam_filter_api_license', '');
        $usage = Spam_Filter_API::get_license_usage();
?>
<input type="password" name="spam_filter_api_license" value="<?php echo esc_attr($value); ?>" class="regular-text"
    placeholder="<?php esc_attr_e('sk_XXXX...', 'ai-spam-shield'); ?>">
<p class="description">
    <?php _e('Enter the license key to activate this site.', 'ai-spam-shield'); ?>
</p>
<?php if (get_option('ai_spam_shield_license_active')): ?>
<p style="color: green; margin-top: 5px;">
    <span style="background: #008000; padding: 2px 6px; color: #fff; font-weight: 600;"><?= $usage['plan'] ?></span>
    <?php _e('Your license is active', 'ai-spam-shield'); ?>
</p>
<?php endif; ?>
<?php if ($usage): ?>
<p class="description">
    <?php _e('Content scanned:', 'ai-spam-shield'); ?>
    <strong><?php echo esc_html($usage['count'] . ' / ' . $usage['limit']); ?></strong>
</p>
<?php endif; ?>


<?php
    }

    public function render_threshold_field()
    {
        $value = get_option('spam_filter_api_threshold', 0.6);
?>
<input type="number" name="spam_filter_api_threshold" value="<?php echo esc_attr($value); ?>" min="0" max="1"
    step="0.05" class="small-text">
<p class="description">
    <?php _e('Confidence threshold (0-1). Content with confidence above this value will be marked as spam. Default: 0.6', 'ai-spam-shield'); ?>
</p>
<?php
    }

    public function render_check_comments_field()
    {
        $enabled = get_option('spam_filter_api_check_comments', true);
?>
<label>
    <input type="checkbox" name="spam_filter_api_check_comments" value="1" <?php self::checked($enabled, 1); ?>>
    <?php _e('Filter WordPress comments', 'ai-spam-shield'); ?>
</label>
<?php
    }

    public function render_check_contact_forms_field()
    {
        $enabled = get_option('spam_filter_api_check_contact_forms', true);
?>
<label>
    <input type="checkbox" name="spam_filter_api_check_contact_forms" value="1" <?php self::checked($enabled, 1); ?>>
    <?php _e('Filter contact form submissions', 'ai-spam-shield'); ?>
</label>
<p class="description"><?php _e('Contact Form 7, WPForms, Gravity Forms and Bricks Forms', 'ai-spam-shield'); ?></p>
<?php
    }

    public function render_log_enabled_field()
    {
        $enabled = get_option('spam_filter_api_log_enabled', true);
?>
<label>
    <input type="checkbox" name="spam_filter_api_log_enabled" value="1" <?php self::checked($enabled, 1); ?>>
    <?php _e('Log spam checks to database', 'ai-spam-shield'); ?>
</label>
<p class="description"><?php _e('Stores spam check results for analysis', 'ai-spam-shield'); ?></p>
<?php
    }
}

// Initialize settings
new Spam_Filter_Settings();