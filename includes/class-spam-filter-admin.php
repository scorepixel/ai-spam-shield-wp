<?php
/** Admin Features Class */
if (!defined('ABSPATH')) {
    exit;
}

class Spam_Filter_Admin
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_logs_menu'));
        add_action('wp_ajax_spam_filter_test_api', array($this, 'test_api'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add logs menu
     */
    public function add_logs_menu()
    {
        add_submenu_page(
            'ai-spam-shield',
            __('Spam Filter Logs', 'ai-spam-shield'),
            __('Spam Logs', 'ai-spam-shield'),
            'manage_options',
            'spam-filter-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        if ($hook !== 'settings_page_ai-spam-shield' && $hook !== 'settings_page_spam-filter-logs') {
            return;
        }

        wp_enqueue_style('spam-filter-admin', SPAM_FILTER_API_PLUGIN_URL . 'assets/admin.css', array(), SPAM_FILTER_API_VERSION);
    }

    /**
     * Render logs page
     */
    public function render_logs_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'spam_filter_logs';

        // Handle actions
        if (isset($_GET['action']) && $_GET['action'] === 'clear_logs' && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'clear_spam_logs')) {
                $wpdb->query("TRUNCATE TABLE $table_name");
                echo '<div class="notice notice-success"><p>' . __('Logs cleared successfully.', 'ai-spam-shield') . '</p></div>';
            }
        }

        // Pagination
        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;

        // Filter
        $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
        $where = '';
        if ($filter === 'spam') {
            $where = 'WHERE is_spam = 1';
        } elseif ($filter === 'legitimate') {
            $where = 'WHERE is_spam = 0';
        }

        // Get total count
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");
        $total_pages = ceil($total_items / $per_page);

        // Get logs
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        // Get statistics
        $total_checks = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $total_spam = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_spam = 1");
        $total_legitimate = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_spam = 0");
        $avg_confidence = $wpdb->get_var("SELECT AVG(confidence) FROM $table_name");

        ?>
<div class="wrap">
    <h1><?php _e('Spam Filter Logs', 'ai-spam-shield'); ?></h1>

    <div class="spam-filter-stats"
        style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
        <div class="card">
            <h3><?php _e('Total Checks', 'ai-spam-shield'); ?></h3>
            <p style="font-size: 32px; margin: 0;"><?php echo number_format($total_checks); ?></p>
        </div>
        <div class="card">
            <h3><?php _e('Spam Detected', 'ai-spam-shield'); ?></h3>
            <p style="font-size: 32px; margin: 0; color: #d63638;"><?php echo number_format($total_spam); ?></p>
        </div>
        <div class="card">
            <h3><?php _e('Legitimate', 'ai-spam-shield'); ?></h3>
            <p style="font-size: 32px; margin: 0; color: #00a32a;"><?php echo number_format($total_legitimate); ?></p>
        </div>
        <div class="card">
            <h3><?php _e('Avg Confidence', 'ai-spam-shield'); ?></h3>
            <p style="font-size: 32px; margin: 0;"><?php echo number_format($avg_confidence * 100, 1); ?>%</p>
        </div>
    </div>

    <div class="tablenav top">
        <div class="alignleft actions">
            <select name="filter" id="filter-select">
                <option value="all" <?php selected($filter, 'all'); ?>><?php _e('All', 'ai-spam-shield'); ?></option>
                <option value="spam" <?php selected($filter, 'spam'); ?>><?php _e('Spam Only', 'ai-spam-shield'); ?>
                </option>
                <option value="legitimate" <?php selected($filter, 'legitimate'); ?>>
                    <?php _e('Legitimate Only', 'ai-spam-shield'); ?></option>
            </select>
            <button type="button" class="button" id="filter-btn"><?php _e('Filter', 'ai-spam-shield'); ?></button>
        </div>
        <div class="alignright actions">
            <a href="<?php echo wp_nonce_url(add_query_arg('action', 'clear_logs'), 'clear_spam_logs'); ?>"
                class="button button-secondary"
                onclick="return confirm('<?php _e('Are you sure you want to clear all logs?', 'ai-spam-shield'); ?>');">
                <?php _e('Clear Logs', 'ai-spam-shield'); ?>
            </a>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 50px;"><?php _e('ID', 'ai-spam-shield'); ?></th>
                <th style="width: 100px;"><?php _e('Type', 'ai-spam-shield'); ?></th>
                <th><?php _e('Content', 'ai-spam-shield'); ?></th>
                <th style="width: 100px;"><?php _e('Status', 'ai-spam-shield'); ?></th>
                <th style="width: 100px;"><?php _e('Confidence', 'ai-spam-shield'); ?></th>
                <th style="width: 150px;"><?php _e('IP Address', 'ai-spam-shield'); ?></th>
                <th style="width: 150px;"><?php _e('Date', 'ai-spam-shield'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px;">
                    <?php _e('No logs found.', 'ai-spam-shield'); ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?php echo esc_html($log->id); ?></td>
                <td><?php echo $log->type === 'comment' ? '💬' : '✉️'; ?> <?php echo ucfirst(esc_html($log->type)); ?>
                </td>
                <td>
                    <details>
                        <summary style="cursor: pointer;">
                            <?php echo esc_html(wp_trim_words($log->content, 10)); ?>
                        </summary>
                        <div style="margin-top: 10px; padding: 10px; background: #f0f0f0; border-radius: 4px;">
                            <?php echo nl2br(esc_html($log->content)); ?>
                            <?php if (!empty($log->flags)): ?>
                            <div style="margin-top: 10px;">
                                <strong><?php _e('Matched Keywords:', 'ai-spam-shield'); ?></strong>
                                <?php
                                $keywords = maybe_unserialize($log->flags);
                                if (is_array($keywords) && !empty($keywords)) {
                                    echo '<div style="margin-top: 5px;">';
                                    foreach ($keywords as $keyword) {
                                        echo '<span style="display: inline-block; background: #d63638; color: white; padding: 2px 8px; border-radius: 3px; margin: 2px; font-size: 11px;">'
                                            . esc_html($keyword) . '</span>';
                                    }
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </details>
                </td>
                <td>
                    <?php if ($log->is_spam): ?>
                    <span style="color: #d63638; font-weight: bold;">
                        ⚠️ <?php _e('SPAM', 'ai-spam-shield'); ?>
                    </span>
                    <?php else: ?>
                    <span style="color: #00a32a; font-weight: bold;">
                        ✓ <?php _e('OK', 'ai-spam-shield'); ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?php echo number_format($log->confidence * 100, 1); ?>%</strong>
                    <div style="background: #e0e0e0; height: 4px; border-radius: 2px; margin-top: 4px;">
                        <div style="background: <?php echo $log->is_spam ? '#d63638' : '#00a32a'; ?>; 
                                                    width: <?php echo ($log->confidence * 100); ?>%; 
                                                    height: 100%; 
                                                    border-radius: 2px;">
                        </div>
                    </div>
                </td>
                <td><?php echo esc_html($log->ip_address); ?></td>
                <td><?php echo esc_html(mysql2date('Y-m-d H:i:s', $log->created_at)); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $total_pages,
                'current' => $page
            ));
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('#filter-btn').on('click', function() {
        var filter = $('#filter-select').val();
        window.location.href =
            '<?php echo admin_url('options-general.php?page=spam-filter-logs'); ?>&filter=' + filter;
    });
});
</script>
<?php
    }
}

// Initialize admin
new Spam_Filter_Admin();