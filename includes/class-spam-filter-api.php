<?php
/** Main Spam Filter API Class */
if (!defined('ABSPATH')) {
    exit;
}

class Spam_Filter_API
{
    private $api_url;
    private $api_key;
    private $threshold;
    private $enabled;
    private $timeout;

    public function __construct()
    {
        // $this->api_url = 'https://ai-spam-shield.scorepixel.com';
        $this->api_url = 'http://host.docker.internal:5000';
        $this->api_key = get_option('spam_filter_api_license');
        $this->threshold = (float) get_option('spam_filter_api_threshold', 0.6);
        $this->enabled = get_option('spam_filter_api_enabled', true);
        $this->timeout = 60;
    }

    public function init()
    {
        error_log('AI Spam Filter: Initializing...');

        if (!$this->enabled) {
            return;
        }

        // Hook into WordPress comments
        if (get_option('spam_filter_api_check_comments', true)) {
            add_filter('preprocess_comment', array($this, 'check_comment'), 10, 1);
        }

        // Hook into contact forms
        if (get_option('spam_filter_api_check_contact_forms', true)) {
            error_log('AI Spam Filter: Checking contact forms...');

            // Hook into Contact Form 7
            if (function_exists('wpcf7')) {
                add_filter('wpcf7_spam', array($this, 'check_contact_form_7'), 10, 2);
            }

            // Hook into WPForms
            if (class_exists('WPForms')) {
                add_filter('wpforms_process_filter', array($this, 'check_wpforms'), 10, 3);
            }

            // Hook into Gravity Forms
            if (class_exists('GFAPI')) {
                add_filter('gform_entry_is_spam', array($this, 'check_gravity_forms'), 10, 3);
            }

            // Hook Bricks forms
            add_filter('bricks/form/validate', [$this, 'validate_bricks_form'], 10, 2);
        }

        // Create logs table if logging is enabled
        if (get_option('spam_filter_api_log_enabled', true)) {
            $this->create_logs_table();
        }
    }

    /**
     * Check content against spam API
     */
    public function check_spam($content, $type = 'email')
    {
        error_log('AI Spam Filter: Checking content for spam...');

        if (empty($content)) {
            return array(
                'is_spam' => false,
                'confidence' => 0,
                'error' => 'Empty content'
            );
        }

        $headers = array(
            'Content-Type' => 'application/json',
            'X-Origin-Domain' => home_url()
        );

        // Add API key if configured
        if (!empty($this->api_key)) {
            $headers['Authorization'] = 'Bearer ' . $this->api_key;
        }

        $args = array(
            'body' => json_encode(array('content' => $content)),
            'headers' => $headers,
            'timeout' => $this->timeout,
            'method' => 'POST',
        );

        // Make API request
        $response = wp_remote_post($this->api_url . '/check-spam', $args);

        // Check for errors
        if (is_wp_error($response)) {
            return array(
                'error' => $response->get_error_message()
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return array(
                'error' => json_decode(wp_remote_retrieve_body($response))
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['is_spam'])) {
            return array(
                'error' => 'Invalid response'
            );
        }

        $is_spam = $data['is_spam'] && $data['confidence'] >= $this->threshold;

        // Log the check
        $this->log_check(
            $content,
            $is_spam,
            $data['confidence'],
            $type,
            $data['flags'] ?? null
        );

        return array(
            'is_spam' => $is_spam,
            'confidence' => $data['confidence']
        );
    }

    /**
     * Check WordPress comment
     */
    public function check_comment($commentdata)
    {
        // Don't check if user is logged in and trusted
        if (is_user_logged_in() && current_user_can('moderate_comments')) {
            return $commentdata;
        }

        // Combine comment content and author info
        $content = $commentdata['comment_content'];
        if (!empty($commentdata['comment_author_email'])) {
            $content .= "\n\nEmail: " . $commentdata['comment_author_email'];
        }
        if (!empty($commentdata['comment_author_url'])) {
            $content .= "\nWebsite: " . $commentdata['comment_author_url'];
        }

        $result = $this->check_spam($content, 'comment');

        if ($result['is_spam']) {
            // Mark as spam
            add_filter('pre_comment_approved', function () {
                return 'spam';
            });

            // Add note to comment
            $commentdata['comment_approved'] = 'spam';

            // Log spam detection
            error_log(sprintf(
                'AI Spam Filter: Comment marked as spam (confidence: %.2f, method: %s)',
                $result['confidence'],
                $result['method']
            ));
        }

        return $commentdata;
    }

    /**
     * Check Contact Form 7
     */
    public function check_contact_form_7($spam, $submission)
    {
        if ($spam) {
            return $spam;
        }

        $data = $submission->get_posted_data();

        // Combine all form fields
        $content = '';
        foreach ($data as $key => $value) {
            if (is_string($value) && !empty($value)) {
                $content .= $value . "\n";
            }
        }

        $result = $this->check_spam($content);

        error_log('Contact Form 7 spam check result: ' . print_r($result, true));
        return $result['is_spam'];
    }

    /**
     * Check WPForms
     */
    public function check_wpforms($fields, $entry)
    {
        // Combine all field values
        $content = '';
        foreach ($fields as $field) {
            if (!empty($field['value'])) {
                $content .= $field['value'] . "\n";
            }
        }

        $result = $this->check_spam($content);

        if ($result['is_spam']) {
            wpforms()->process->errors[$entry['id']]['header'] = esc_html__('Your submission has been flagged as spam.', 'ai-spam-shield');
        }

        return $fields;
    }

    /**
     * Check Gravity Forms
     */
    public function check_gravity_forms($is_spam, $form, $entry)
    {
        if ($is_spam) {
            return $is_spam;
        }

        // Combine all form field values
        $content = '';
        foreach ($entry as $key => $value) {
            if (is_numeric($key) && !empty($value)) {
                $content .= $value . "\n";
            }
        }

        $result = $this->check_spam($content);

        return $result['is_spam'];
    }

    /**
     * Check Bricks form
     */
    public function validate_bricks_form($errors, $form)
    {
        try {
            // Access protected property 'form_fields'
            $ref = new ReflectionClass($form);
            $prop = $ref->getProperty('form_fields');
            $prop->setAccessible(true);
            $fields = $prop->getValue($form);
        } catch (Exception $e) {
            return $errors;
        }

        if (empty($fields)) {
            return $errors;
        }

        // Combine submitted values
        $content = '';
        foreach ($fields as $key => $value) {
            if (strpos($key, 'form-field-') === 0 && is_string($value) && !empty($value)) {
                $content .= $value . "\n";
            }
        }

        $result = $this->check_spam($content);

        if (!empty($result['is_spam']) && $result['is_spam']) {
            $errors[] = esc_html__('Your submission was flagged as spam and not sent.', 'ai-spam-shield');
        }

        return $errors;
    }

    /**
     * Create logs table
     */
    private function create_logs_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'spam_filter_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            content text NOT NULL,
            is_spam tinyint(1) NOT NULL,
            confidence decimal(4,3) NOT NULL,
            type varchar(50) DEFAULT 'email',
            flags text DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY is_spam (is_spam),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Log spam check
     */
    private function log_check(
        $content,
        $is_spam,
        $confidence,
        $type = 'email',
        $flags = null
    ) {
        if (!get_option('spam_filter_api_log_enabled', true)) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'spam_filter_logs';

        $wpdb->insert(
            $table_name,
            array(
                'content' => substr($content, 0, 1000),  // Store first 1000 chars
                'is_spam' => $is_spam ? 1 : 0,
                'confidence' => $confidence,
                'type' => $type,
                'flags' => $flags ? maybe_serialize($flags) : null,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            ),
            array('%s', '%d', '%f', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Get client IP address
     */
    private function get_client_ip()
    {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    /**
     * Validate license key
     */
    private function validate_license($license_key)
    {
        if (empty($license_key)) {
            return array(
                'valid' => false,
                'error' => __('License key is required', 'ai-spam-shield')
            );
        }

        // Make API request to YOUR server
        $response = wp_remote_post($this->api_url . '/license/validate', array(
            'body' => json_encode(array(
                'license_key' => $license_key,
                'domain' => home_url()
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
        ));

        // Check for errors
        if (is_wp_error($response)) {
            return array(
                'valid' => false,
                'error' => $response->get_error_message()
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Handle API response
        if ($status_code !== 200) {
            $error_message = isset($data['error']) ? $data['error'] : __('License validation failed', 'ai-spam-shield');
            return array(
                'valid' => false,
                'error' => $error_message
            );
        }

        // Check if license is valid based on your API response
        if (!isset($data['valid']) || !$data['valid']) {
            $error_message = isset($data['message']) ? $data['message'] : __('Invalid license key', 'ai-spam-shield');
            return array(
                'valid' => false,
                'error' => $error_message
            );
        }

        // License is valid - return data from your API
        return array(
            'valid' => true,
            'data' => $data,
            'expires' => isset($data['expires']) ? $data['expires'] : null,
            'plan' => isset($data['plan']) ? $data['plan'] : null
        );
    }

    public static function get_api_url()
    {
        $instance = new self();  // create a temporary instance
        return $instance->api_url;
    }

    /**
     * Attempt to activate license by calling {api_url}/license/activate
     */
    public static function maybe_activate_license()
    {
        $license = trim((string) get_option('spam_filter_api_license', ''));

        // If no license, remove saved data
        if (empty($license)) {
            delete_option('ai_spam_shield_license_active');
            return;
        }

        // Skip if already active (check if we have valid license data)
        $existing_license = get_option('ai_spam_shield_license_active');
        if (!empty($existing_license) && $existing_license === $license) {
            return;
        }

        // Build endpoint
        $endpoint = Spam_Filter_API::get_api_url() . '/license/activate';

        // Domain to bind
        $domain = parse_url(home_url(), PHP_URL_HOST);
        if (!$domain) {
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        }

        $args = array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Origin-Domain' => $domain,
            ),
            'body' => wp_json_encode(array(
                'license_key' => $license
            )),
        );

        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            add_settings_error(
                'spam_filter_api_messages',
                'license_activation_error',
                sprintf(__('License activation failed: %s', 'ai-spam-shield'), $response->get_error_message()),
                'error'
            );
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300 && !empty($body['success'])) {
            // Save license key
            add_option('ai_spam_shield_license_active', $license);

            // Show success message
            add_settings_error(
                'spam_filter_api_messages',
                'license_activation_success',
                __('License activated successfully.', 'ai-spam-shield'),
                'updated'
            );
        } else {
            // Remove saved data if activation failed
            delete_option('ai_spam_shield_license_active');

            // Show error message
            $msg = $body['error'] ?? ($body['message'] ?? __('Unknown error', 'ai-spam-shield'));
            if ($msg == 'not_found') {
                $msg = __('License not found', 'ai-spam-shield');
            }
            add_settings_error(
                'spam_filter_api_messages',
                'license_activation_error',
                sprintf(__('License activation failed: %s', 'ai-spam-shield'), esc_html($msg)),
                'error'
            );
        }
    }

    /*
     * Check license usage
     */
    public static function get_license_usage()
    {
        $license = trim((string) get_option('spam_filter_api_license', ''));

        /*
         * if (empty($license)) {
         *     return false;
         * }
         */

        // Build endpoint
        $endpoint = Spam_Filter_API::get_api_url() . '/license/usage';

        // Domain to bind
        $domain = home_url();

        if (!$domain) {
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        }

        $args = array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Origin-Domain' => $domain,
                'Authorization' => 'Bearer ' . $license,
            ),
        );

        $response = wp_remote_get($endpoint, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body['data'] ?? false;
    }
}