<?php
/**
 * Plugin Name: Smart Internal Links
 * Plugin URI:  https://wordpress.org/plugins/smart-internal-links/
 * Description: Smart Internal Links automatically suggests and inserts relevant internal links in your posts to boost SEO and improve site navigation.
 * Version:     1.2
 * Author:      Daniyal Hassan
 * Author URI:  https://profiles.wordpress.org/daniyaldotdev/
 * Text Domain: smart-internal-links
 * Domain Path: /languages
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SmartInternalLinks {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'smartinlinks_suggestions';

        register_activation_hook( __FILE__, array( $this, 'create_tables' ) );
        add_action( 'init', array( $this, 'register_post_type' ) );

        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Meta box AJAX handlers
        add_action( 'wp_ajax_smartinlinks_analyze', array( $this, 'ajax_analyze' ) );
        add_action( 'wp_ajax_smartinlinks_add_link', array( $this, 'ajax_add_link' ) );

        // Dashboard AJAX handlers
        add_action( 'wp_ajax_smartinlinks_bulk_analyze', array( $this, 'ajax_bulk_analyze' ) );
        add_action( 'wp_ajax_smartinlinks_get_suggestions', array( $this, 'ajax_get_suggestions' ) );
        add_action( 'wp_ajax_smartinlinks_get_added_links', array( $this, 'ajax_get_added_links' ) );
        add_action( 'wp_ajax_smartinlinks_add_link_bulk', array( $this, 'ajax_add_link_bulk' ) );
        add_action( 'wp_ajax_smartinlinks_get_stats', array( $this, 'ajax_get_stats' ) );
    }

    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_post_id bigint(20) NOT NULL,
            anchor_text varchar(255) NOT NULL,
            target_post_id bigint(20) NOT NULL,
            target_url varchar(255) NOT NULL,
            strength varchar(20) NOT NULL,
            status varchar(20) DEFAULT 'available',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            added_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY source_post_id (source_post_id),
            KEY status (status)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function register_post_type() {
        register_post_type('sil_suggestion', array(
            'public' => false,
            'show_ui' => false,
            'supports' => array('title'),
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'manage_options',
                'edit_posts' => 'manage_options',
                'delete_posts' => 'manage_options'
            )
        ));
    }

    public function add_admin_menu() {
        add_submenu_page(
            'edit.php',
            'Smart Internal Links',
            'Internal Links',
            'manage_options',
            'smart-internal-links',
            array( $this, 'render_dashboard' )
        );
    }

    public function render_dashboard() {
        ?>
        <div class="wrap sil-dashboard">
            <div class="sil-dashboard-header">
                <div class="sil-header-left">
                    <h1><span class="dashicons dashicons-admin-links"></span> Smart Internal Links</h1>
                    <p class="sil-subtitle">Boost your SEO with intelligent internal linking</p>
                </div>
                <div class="sil-header-right">
                    <select id="sil-analyze-limit" class="sil-limit-select">
                        <option value="25" selected>Latest 25 Posts</option>
                        <option value="50">Latest 50 Posts</option>
                        <option value="100">Latest 100 Posts</option>
                        <option value="-1">All Posts</option>
                    </select>
                    <button type="button" id="sil-bulk-analyze" class="sil-analyze-button">
                        <span class="dashicons dashicons-update"></span> Analyze Website
                    </button>
                </div>
            </div>

            <div id="sil-progress-container" class="sil-progress-container" style="display:none;">
                <div class="sil-progress-bar">
                    <div class="sil-progress-fill" id="sil-progress-fill"></div>
                </div>
                <div class="sil-progress-text" id="sil-progress-text"></div>
            </div>

            <div class="sil-stats-container">
                <div class="sil-stat-card sil-stat-analyzed">
                    <div class="sil-stat-icon">
                        <span class="dashicons dashicons-chart-bar"></span>
                    </div>
                    <div class="sil-stat-content">
                        <div class="sil-stat-value" id="sil-stat-analyzed">0</div>
                        <div class="sil-stat-label">Posts Analyzed</div>
                    </div>
                </div>
                <div class="sil-stat-card sil-stat-found">
                    <div class="sil-stat-icon">
                        <span class="dashicons dashicons-search"></span>
                    </div>
                    <div class="sil-stat-content">
                        <div class="sil-stat-value" id="sil-stat-found">0</div>
                        <div class="sil-stat-label">Opportunities Found</div>
                    </div>
                </div>
                <div class="sil-stat-card sil-stat-available">
                    <div class="sil-stat-icon">
                        <span class="dashicons dashicons-paperclip"></span>
                    </div>
                    <div class="sil-stat-content">
                        <div class="sil-stat-value" id="sil-stat-available">0</div>
                        <div class="sil-stat-label">Links Available</div>
                    </div>
                </div>
                <div class="sil-stat-card sil-stat-linked">
                    <div class="sil-stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="sil-stat-content">
                        <div class="sil-stat-value" id="sil-stat-linked">0</div>
                        <div class="sil-stat-label">Links Added</div>
                    </div>
                </div>
            </div>

            <div class="sil-tabs-wrapper">
                <div class="sil-tabs">
                    <button class="sil-tab-button active" data-tab="available">
                        <span class="dashicons dashicons-admin-links"></span> Available Links
                    </button>
                    <button class="sil-tab-button" data-tab="added">
                        <span class="dashicons dashicons-yes"></span> Added Links
                    </button>
                </div>

                <div class="sil-tab-content" id="tab-available">
                    <div class="sil-table-controls">
                        <div class="sil-pagination-info">
                            Showing <span id="available-showing">0</span> of <span id="available-total">0</span> links
                        </div>
                        <div class="sil-per-page">
                            <label>Show:
                                <select id="available-per-page">
                                    <option value="10">10</option>
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="sil-table-wrapper">
                        <table class="sil-modern-table">
                            <thead>
                                <tr>
                                    <th class="column-source">Source Post</th>
                                    <th class="column-keyword">Keyword</th>
                                    <th class="column-strength">Strength</th>
                                    <th class="column-target">Target Post</th>
                                    <th class="column-action">Action</th>
                                </tr>
                            </thead>
                            <tbody id="available-tbody"></tbody>
                        </table>
                    </div>

                    <div class="sil-pagination" id="available-pagination"></div>
                </div>
            </div>

            <div class="sil-tab-content" id="tab-added" style="display:none;">
                <div class="sil-table-controls">
                    <div class="sil-pagination-info">
                        Showing <span id="added-showing">0</span> of <span id="added-total">0</span> links
                    </div>
                    <div class="sil-per-page">
                        <label>Show:
                            <select id="added-per-page">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </label>
                    </div>
                </div>

                <div class="sil-table-wrapper">
                    <table class="sil-modern-table">
                        <thead>
                            <tr>
                                <th class="column-source">Source Post</th>
                                <th class="column-keyword">Keyword</th>
                                <th class="column-strength">Strength</th>
                                <th class="column-target">Target Post</th>
                                <th class="column-date">Date Added</th>
                            </tr>
                        </thead>
                        <tbody id="added-tbody"></tbody>
                    </table>
                </div>

                <div class="sil-pagination" id="added-pagination"></div>
            </div>
        </div>
        <?php
    }

    public function add_meta_box() {
        $screen = get_current_screen();
        if ( $screen && $screen->post_type === 'post' ) {
            add_meta_box( 'smart_internal_links_box', 'Smart Internal Links', array( $this, 'render_meta_box' ), 'post', 'normal', 'low' );
        }
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'smartinlinks_meta_box', 'smartinlinks_nonce_field' );
        ?>
        <div id="sil-container" class="sil-metabox">
            <div class="sil-table-wrapper">
                <table id="sil-table" class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-anchor">Anchor Text</th>
                            <th class="column-strength">Match Strength</th>
                            <th class="column-target">Target Post</th>
                            <th class="column-action">Action</th>
                        </tr>
                    </thead>
                    <tbody id="sil-tbody"></tbody>
                </table>
            </div>

            <div class="sil-actions">
                <button type="button" id="sil-analyze" class="button button-primary">
                    <span class="dashicons dashicons-search"></span> Analyze Content
                </button>
            </div>
        </div>
        <input type="hidden" id="sil-post-id" value="<?php echo esc_attr( $post->ID ); ?>" />
        <?php
    }

    public function enqueue_scripts( $hook ) {
        $is_post_edit = ( $hook === 'post.php' || $hook === 'post-new.php' );
        $is_dashboard = ( strpos( $hook, 'page_smart-internal-links' ) !== false );

        if ( ! $is_post_edit && ! $is_dashboard ) {
            return;
        }

        wp_enqueue_style( 'smartinlinks-style', plugin_dir_url( __FILE__ ) . 'style.css', array(), '1.2' );
        wp_enqueue_script( 'smartinlinks-script', plugin_dir_url( __FILE__ ) . 'script.js', array( 'jquery' ), '1.2', true );
        wp_localize_script( 'smartinlinks-script', 'smartinlinksData', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'smartinlinks_nonce' ),
            'is_dashboard' => $is_dashboard
        ) );
    }

    public function ajax_bulk_analyze()
    {
        check_ajax_referer('smartinlinks_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        global $wpdb;

        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : -1;
        
        // Validate limit parameter
        if ($limit < -1 || $limit > 1000) {
            $limit = -1;
        }

        // Get post objects directly to avoid double query
        $post_objects = get_posts( array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => $limit
        ) );

        if ( empty( $post_objects ) ) {
            wp_send_json_error( 'No posts found to analyze' );
        }

        $posts = wp_list_pluck( $post_objects, 'ID' );

        // Clean up old suggestions for these posts
        if ( ! empty( $posts ) ) {
            $old_suggestions = get_posts(array(
                'post_type' => 'sil_suggestion',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids'
            ));
            
            foreach ($old_suggestions as $suggestion_id) {
                $source_id = get_post_meta($suggestion_id, '_sil_source_post_id', true);
                $status = get_post_meta($suggestion_id, '_sil_status', true);
                if (in_array($source_id, $posts) && $status === 'available') {
                    wp_delete_post($suggestion_id, true);
                }
            }
        }

        $total = count( $post_objects );
        $processed = 0;
        $suggestions_found = 0;

        foreach ( $post_objects as $post ) {
            $processed++;

            // Find best suggestion for this post
            $suggestion = $this->find_best_suggestion( $post );

            if ( $suggestion ) {
                $post_id = wp_insert_post(array(
                    'post_type' => 'sil_suggestion',
                    'post_status' => 'publish',
                    'post_title' => $suggestion['anchor'] . ' -> ' . get_the_title($suggestion['target_id'])
                ));
                
                if ($post_id) {
                    update_post_meta($post_id, '_sil_source_post_id', $post->ID);
                    update_post_meta($post_id, '_sil_anchor_text', $suggestion['anchor']);
                    update_post_meta($post_id, '_sil_target_post_id', $suggestion['target_id']);
                    update_post_meta($post_id, '_sil_target_url', $suggestion['target_url']);
                    update_post_meta($post_id, '_sil_strength', $suggestion['strength']);
                    update_post_meta($post_id, '_sil_status', 'available');
                    $suggestions_found++;
                }
            }

            // Send progress update every 10 posts or on last post
            // For now, we process all in one batch to ensure completion.
            // In a future update, we could implement proper batching with offsets.
            // if ($processed % 10 === 0 || $processed === $total) {
        }

        wp_send_json_success( array(
            'progress' => $processed,
            'total' => $total,
            'found' => $suggestions_found,
            'complete' => true
        ) );

    }

    private function find_best_suggestion( $post ) {
        $content = $this->normalize( $post->post_content );
        $phrases_3 = $this->get_phrases( $content, 3 );
        $phrases_2 = $this->get_phrases( $content, 2 );

        // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
        $all_posts = get_posts( array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ) );

        $posts = array_filter($all_posts, function($id) use ($post) {
            return $id !== $post->ID;
        });

        // Get post objects for title matching
        $target_posts = array();
        if ( ! empty( $posts ) ) {
            $target_posts = get_posts( array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'include' => $posts,
                'numberposts' => -1
            ) );
        }

        // Try 3-word matches first (Strong)
        foreach ( $phrases_3 as $phrase ) {
            if ( $this->is_already_linked( $post->post_content, $phrase ) ) {
                continue;
            }

            foreach ( $target_posts as $p ) {
                $title = $this->normalize( $p->post_title );
                if ( strpos( $title, $phrase ) !== false ) {
                    return array(
                        'anchor' => $phrase,
                        'strength' => 'Strong',
                        'target_id' => $p->ID,
                        'target_url' => get_permalink( $p->ID )
                    );
                }
            }
        }

        // Try 2-word matches (Normal)
        foreach ( $phrases_2 as $phrase ) {
            if ( $this->is_already_linked( $post->post_content, $phrase ) ) {
                continue;
            }

            foreach ( $target_posts as $p ) {
                $title = $this->normalize( $p->post_title );
                if ( strpos( $title, $phrase ) !== false ) {
                    return array(
                        'anchor' => $phrase,
                        'strength' => 'Normal',
                        'target_id' => $p->ID,
                        'target_url' => get_permalink( $p->ID )
                    );
                }
            }
        }

        return null;
    }

    public function ajax_get_suggestions()
    {
        check_ajax_referer('smartinlinks_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, min(100, intval($_POST['per_page']))) : 25;
        $offset = ($page - 1) * $per_page;

        $args = array(
            'post_type' => 'sil_suggestion',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $query = new WP_Query($args);
        $suggestions = array();

        foreach ($query->posts as $post) {
            $status = get_post_meta($post->ID, '_sil_status', true);
            if ($status !== 'available') continue;
            
            $source_id = get_post_meta($post->ID, '_sil_source_post_id', true);
            $target_id = get_post_meta($post->ID, '_sil_target_post_id', true);
            $source_post = get_post($source_id);
            $target_post = get_post($target_id);

            if ($source_post && $target_post) {
                $suggestions[] = [
                    'id' => $post->ID,
                    'source_title' => $source_post->post_title,
                    'source_id' => $source_id,
                    'source_url' => get_permalink($source_id),
                    'anchor' => get_post_meta($post->ID, '_sil_anchor_text', true),
                    'strength' => get_post_meta($post->ID, '_sil_strength', true),
                    'target_title' => $target_post->post_title,
                    'target_url' => get_post_meta($post->ID, '_sil_target_url', true)
                ];
            }
        }

        // Get total count for available suggestions
        $total_args = array(
            'post_type' => 'sil_suggestion',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        $total_query = new WP_Query($total_args);
        $available_count = 0;
        foreach ($total_query->posts as $post_id) {
            if (get_post_meta($post_id, '_sil_status', true) === 'available') {
                $available_count++;
            }
        }

        wp_send_json_success([
            'suggestions' => $suggestions,
            'total' => intval($available_count),
            'page' => $page,
            'per_page' => $per_page
        ]);
    }

    public function ajax_get_added_links()
    {
        check_ajax_referer('smartinlinks_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, min(100, intval($_POST['per_page']))) : 25;

        $args = array(
            'post_type' => 'sil_suggestion',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'modified',
            'order' => 'DESC'
        );

        $query = new WP_Query($args);
        $links = array();

        foreach ($query->posts as $post) {
            $status = get_post_meta($post->ID, '_sil_status', true);
            if ($status !== 'added') continue;
            
            $source_id = get_post_meta($post->ID, '_sil_source_post_id', true);
            $target_id = get_post_meta($post->ID, '_sil_target_post_id', true);
            $source_post = get_post($source_id);
            $target_post = get_post($target_id);

            if ($source_post && $target_post) {
                $links[] = [
                    'source_title' => $source_post->post_title,
                    'source_url' => get_permalink($source_id),
                    'anchor' => get_post_meta($post->ID, '_sil_anchor_text', true),
                    'strength' => get_post_meta($post->ID, '_sil_strength', true),
                    'target_title' => $target_post->post_title,
                    'target_url' => get_post_meta($post->ID, '_sil_target_url', true),
                    'added_at' => $post->post_modified
                ];
            }
        }

        // Get total count for added links
        $total_args = array(
            'post_type' => 'sil_suggestion',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        $total_query = new WP_Query($total_args);
        $added_count = 0;
        foreach ($total_query->posts as $post_id) {
            if (get_post_meta($post_id, '_sil_status', true) === 'added') {
                $added_count++;
            }
        }

        wp_send_json_success([
            'links' => $links,
            'total' => intval($added_count),
            'page' => $page,
            'per_page' => $per_page
        ]);
    }

    public function ajax_add_link_bulk()
    {
        check_ajax_referer('smartinlinks_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        if (!isset($_POST['suggestion_id']) || !is_numeric($_POST['suggestion_id'])) {
            wp_send_json_error('Invalid suggestion ID');
        }

        $suggestion_id = intval($_POST['suggestion_id']);
        $suggestion_post = get_post($suggestion_id);

        if (!$suggestion_post || $suggestion_post->post_type !== 'sil_suggestion') {
            wp_send_json_error('Suggestion not found');
        }

        $status = get_post_meta($suggestion_id, '_sil_status', true);
        if ($status !== 'available') {
            wp_send_json_error('Suggestion not available');
        }

        $source_post_id = get_post_meta($suggestion_id, '_sil_source_post_id', true);
        $anchor_text = get_post_meta($suggestion_id, '_sil_anchor_text', true);
        $target_url = get_post_meta($suggestion_id, '_sil_target_url', true);

        $post = get_post($source_post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }

        $content = $post->post_content;
        $updated = $this->insert_link($content, $anchor_text, $target_url);

        if ($updated === false) {
            wp_send_json_error('Could not insert link');
        }

        wp_update_post([
            'ID' => $source_post_id,
            'post_content' => $updated
        ]);

        update_post_meta($suggestion_id, '_sil_status', 'added');
        wp_update_post([
            'ID' => $suggestion_id,
            'post_modified' => current_time('mysql')
        ]);

        wp_send_json_success(['message' => 'Link added successfully']);
    }

    public function ajax_get_stats()
    {
        check_ajax_referer('smartinlinks_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $total_posts = wp_count_posts('post')->publish;

        $analyzed_query = new WP_Query(array(
            'post_type' => 'sil_suggestion',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        $source_posts = array();
        foreach ($analyzed_query->posts as $suggestion_id) {
            $source_id = get_post_meta($suggestion_id, '_sil_source_post_id', true);
            if ($source_id) {
                $source_posts[$source_id] = true;
            }
        }
        $analyzed = count($source_posts);

        $available_count = 0;
        $added_count = 0;
        
        foreach ($analyzed_query->posts as $suggestion_id) {
            $status = get_post_meta($suggestion_id, '_sil_status', true);
            if ($status === 'available') {
                $available_count++;
            } elseif ($status === 'added') {
                $added_count++;
            }
        }

        $found = $analyzed_query->found_posts;

        wp_send_json_success([
            'analyzed' => intval($analyzed),
            'found' => intval($found),
            'available' => intval($available_count),
            'linked' => intval($added_count)
        ]);
    }

    public function ajax_analyze()
    {
        check_ajax_referer('smartinlinks_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        if (!isset($_POST['post_id'])) {
            wp_send_json_error('Missing post ID');
        }

        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'post') {
            wp_send_json_error('Invalid post');
        }

        $content = $this->normalize($post->post_content);
        $phrases_3 = $this->get_phrases($content, 3);
        $phrases_2 = $this->get_phrases($content, 2);

        // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Necessary to exclude current post from suggestions
        $all_posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1
        ]);

        $posts = array_filter($all_posts, function($p) use ($post_id) {
            return $p->ID !== $post_id;
        });

        $suggestions = [];

        // 3-word matches first
        foreach ($phrases_3 as $phrase) {
            if (count($suggestions) >= 5)
                break;

            $is_linked = $this->is_already_linked($post->post_content, $phrase);

            foreach ($posts as $p) {
                $title = $this->normalize($p->post_title);
                if (strpos($title, $phrase) !== false) {
                    $suggestions[] = [
                        'anchor' => $phrase,
                        'strength' => 'Strong',
                        'target_id' => $p->ID,
                        'target_title' => $p->post_title,
                        'target_url' => get_permalink($p->ID),
                        'is_linked' => $is_linked
                    ];
                    break;
                }
            }
        }

        // 2-word matches if needed
        if (count($suggestions) < 5) {
            foreach ($phrases_2 as $phrase) {
                if (count($suggestions) >= 5)
                    break;

                $is_linked = $this->is_already_linked($post->post_content, $phrase);

                foreach ($posts as $p) {
                    $title = $this->normalize($p->post_title);
                    if (strpos($title, $phrase) !== false) {
                        $suggestions[] = [
                            'anchor' => $phrase,
                            'strength' => 'Normal',
                            'target_id' => $p->ID,
                            'target_title' => $p->post_title,
                            'target_url' => get_permalink($p->ID),
                            'is_linked' => $is_linked
                        ];
                        break;
                    }
                }
            }
        }

        wp_send_json_success($suggestions);
    }

    public function ajax_add_link()
    {
        check_ajax_referer('smartinlinks_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        if (!isset($_POST['post_id']) || !isset($_POST['anchor']) || !isset($_POST['target_url'])) {
            wp_send_json_error('Missing required parameters');
        }

        $post_id = intval($_POST['post_id']);
        $anchor = sanitize_text_field(wp_unslash($_POST['anchor']));
        $target_url = esc_url_raw(wp_unslash($_POST['target_url']));

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Invalid post');
        }

        $content = $post->post_content;
        $updated = $this->insert_link($content, $anchor, $target_url);

        if ($updated === false) {
            wp_send_json_error('Could not insert link');
        }

        wp_update_post([
            'ID' => $post_id,
            'post_content' => $updated
        ]);

        wp_send_json_success(['content' => $updated]);
    }

    private function normalize($text)
    {
        $text = wp_strip_all_tags($text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function get_phrases($text, $word_count)
    {
        $words = explode(' ', $text);
        $phrases = [];

        for ($i = 0; $i <= count($words) - $word_count; $i++) {
            $phrase_words = array_slice($words, $i, $word_count);
            
            // Skip phrases containing words shorter than 3 characters
            $valid_phrase = true;
            foreach ($phrase_words as $word) {
                if (strlen($word) < 3) {
                    $valid_phrase = false;
                    break;
                }
            }
            
            if ($valid_phrase) {
                $phrase = implode(' ', $phrase_words);
                $phrases[] = $phrase;
            }
        }

        return array_unique($phrases);
    }

    private function is_already_linked($content, $anchor)
    {
        $pattern = '/<a[^>]*>.*?' . preg_quote($anchor, '/') . '.*?<\/a>/i';
        return preg_match($pattern, $content) > 0;
    }

    private function insert_link($content, $anchor, $url)
    {
        $pos = stripos($content, $anchor);
        if ($pos === false) {
            return false;
        }

        $actual_anchor = substr($content, $pos, strlen($anchor));
        $link = '<a href="' . esc_url($url) . '"><strong>' . $actual_anchor . '</strong></a>';
        return substr_replace($content, $link, $pos, strlen($anchor));
    }
}

new SmartInternalLinks();