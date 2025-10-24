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
        /*
         * add_options_page(
         *     __('AI Spam Shield Settings', 'ai-spam-shield'),
         *     __('AI Spam Shield', 'ai-spam-shield'),
         *     'manage_options',
         *     'ai-spam-shield',
         *     array($this, 'render_settings_page')
         * );
         */
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
            'spam_filter_api_url',
            __('API URL', 'ai-spam-shield'),
            array($this, 'render_api_url_field'),
            'ai-spam-shield',
            'spam_filter_api_section'
        );

        add_settings_field(
            'spam_filter_api_key',
            __('API Key', 'ai-spam-shield'),
            array($this, 'render_api_key_field'),
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

        /*
         * add_settings_field(
         *     'spam_filter_api_timeout',
         *     __('Request Timeout', 'ai-spam-shield'),
         *     array($this, 'render_timeout_field'),
         *     'ai-spam-shield',
         *     'spam_filter_api_section'
         * );
         */

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

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
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

    <div class="card">
        <h2><?php _e('Test API Connection', 'ai-spam-shield'); ?></h2>
        <p><?php _e('Test your API configuration with sample spam content.', 'ai-spam-shield'); ?></p>
        <button type="button" class="button button-secondary" id="test-api-btn">
            <?php _e('Test API', 'ai-spam-shield'); ?>
        </button>
        <div id="test-api-result" style="margin-top: 10px;"></div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#test-api-btn').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('<?php _e('Testing...', 'ai-spam-shield'); ?>');

            $.post(ajaxurl, {
                action: 'spam_filter_test_api',
                nonce: '<?php echo wp_create_nonce('spam_filter_test_api'); ?>'
            }, function(response) {

                console.log(response);

                if (response.success && !response.error) {
                    $('#test-api-result').html(
                        '<div class="notice notice-success"><p>' +
                        '<strong><?php _e('API Test Successful!', 'ai-spam-shield'); ?></strong><br>' +
                        '<?php _e('Is Spam:', 'ai-spam-shield'); ?> ' + (response.data
                            .is_spam ? 'Yes' : 'No') + '<br>' +
                        '<?php _e('Confidence:', 'ai-spam-shield'); ?> ' + (response.data
                            .confidence * 100).toFixed(1) + '%<br>' +
                        '<?php _e('Method:', 'ai-spam-shield'); ?> ' + response.data
                        .method +
                        '</p></div>'
                    );
                } else {
                    $('#test-api-result').html(
                        '<div class="notice notice-error"><p>' +
                        '<strong><?php _e('API Test Failed!', 'ai-spam-shield'); ?></strong><br>' +
                        response.data.message.error +
                        '</p></div>'
                    );
                }
                btn.prop('disabled', false).text('<?php _e('Test API', 'ai-spam-shield'); ?>');
            });
        });
    });
    </script>
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
    <input type="checkbox" name="spam_filter_api_enabled" value="1" <?php checked($enabled, 1); ?>>
    <?php _e('Enable spam filtering', 'ai-spam-shield'); ?>
</label>
<?php
    }

    public function render_api_url_field()
    {
        $value = get_option('spam_filter_api_url', 'http://localhost:3000/check-spam');
?>
<input type="url" name="spam_filter_api_url" value="<?php echo esc_attr($value); ?>" class="regular-text" required>
<p class="description"><?php _e('Full URL to your spam detection API endpoint', 'ai-spam-shield'); ?></p>
<?php
    }

    public function render_api_key_field()
    {
        $value = get_option('spam_filter_api_key', '');
?>
<input type="password" name="spam_filter_api_key" value="<?php echo esc_attr($value); ?>" class="regular-text">
<p class="description"><?php _e('Optional: API key for authentication (Bearer token)', 'ai-spam-shield'); ?></p>
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

    public function render_timeout_field()
    {
        $value = get_option('spam_filter_api_timeout', 5);
?>
<input type="number" name="spam_filter_api_timeout" value="<?php echo esc_attr($value); ?>" min="1" max="30" step="1"
    class="small-text">
<p class="description"><?php _e('API request timeout in seconds. Default: 5', 'ai-spam-shield'); ?></p>
<?php
    }

    public function render_check_comments_field()
    {
        $enabled = get_option('spam_filter_api_check_comments', true);
?>
<label>
    <input type="checkbox" name="spam_filter_api_check_comments" value="1" <?php checked($enabled, 1); ?>>
    <?php _e('Filter WordPress comments', 'ai-spam-shield'); ?>
</label>
<?php
    }

    public function render_check_contact_forms_field()
    {
        $enabled = get_option('spam_filter_api_check_contact_forms', true);
?>
<label>
    <input type="checkbox" name="spam_filter_api_check_contact_forms" value="1" <?php checked($enabled, 1); ?>>
    <?php _e('Filter contact form submissions (Contact Form 7, WPForms, Gravity Forms)', 'ai-spam-shield'); ?>
</label>
<?php
    }

    public function render_log_enabled_field()
    {
        $enabled = get_option('spam_filter_api_log_enabled', true);
?>
<label>
    <input type="checkbox" name="spam_filter_api_log_enabled" value="1" <?php checked($enabled, 1); ?>>
    <?php _e('Log spam checks to database', 'ai-spam-shield'); ?>
</label>
<p class="description"><?php _e('Stores spam check results for analysis', 'ai-spam-shield'); ?></p>
<?php
    }
}

// Initialize settings
new Spam_Filter_Settings();
