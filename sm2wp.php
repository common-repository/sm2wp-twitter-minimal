<?php
/**
 * Plugin Name:     Social Media 2 WordPress for Twitter / Minimal
 * Plugin URI:      http://sm2wp.com
 * Description:     Import your Twitter Posts to your WordPress Blog
 * Version:         1.0.1
 * Author:       	Daniel Treadwell
 * Author URI:      http://minimali.se
 * License:         GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined( 'WPINC' )) {
	die;
}

@define('WP_POST_REVISIONS', 3);

if (get_option('timezone_string')) {
    @date_default_timezone_set(get_option('timezone_string'));
}

if (!function_exists('sm2wp_log_debug')) {
    function sm2wp_log_debug($prefix, $message) {
        $log = get_option($prefix.'_debug', array());
        if (!is_array($log)) $log = array();
        $d = get_option('timezone_string') ? date('d/m/Y @ H:i:s') : date('d/m/Y @ H:i:s', current_time('timestamp'));
        array_unshift($log, '<b>['.$d.']</b> '.$message);
        if (count($log) > 20) array_pop($log);
        update_option($prefix.'_debug', $log);
    }

    function sm2wp_log_running($prefix, $message) {
        $log = get_option($prefix.'_running', array());
        if (!is_array($log)) $log = array();
        $d = get_option('timezone_string') ? date('d/m/Y @ H:i:s') : date('d/m/Y @ H:i:s', current_time('timestamp'));
        array_unshift($log, '<b>['.$d.']</b> '.$message);
        if (count($log) > 10) array_pop($log);
        update_option($prefix.'_running', $log);
    }

    function sm2wp_log_error($prefix, $message) {
        $log = get_option($prefix.'_errors', array());
        if (!is_array($log)) $log = array();
        array_unshift($log, $message);
        update_option($prefix.'_errors', $log);
    }

    function sm2wp_log_info($prefix, $message) {
        $log = get_option($prefix.'_info', array());
        if (!is_array($log)) $log = array();
        array_unshift($log, $message);
        update_option($prefix.'_info', $log);
    }
}

class SM2WP_Twitter {
    public static $slug = 'sm2wp-twitter'; # EXT
    public static $prefix = 'tfw';
    public static $network = 'Twitter';

    protected static $instance = null;

    // Init

    private function __construct() {
        add_action('wpmu_new_blog', array($this, 'activate_new_site'));
        $this->_ext_init();
        @include_once('updater.php');
    }

    public static function get_instance() {
        if (null == self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public static function activate($network_wide) {
        if (function_exists('is_multisite') && is_multisite()) {
            if ($network_wide) {
                $blog_ids = self::get_blog_ids();

                foreach ($blog_ids as $blog_id) {
                    switch_to_blog($blog_id);
                    self::single_activate();
                }
                restore_current_blog();

            } else {
                self::single_activate();
            }

        } else {
            self::single_activate();
        }

    }

    public static function deactivate( $network_wide ) {
        if (function_exists('is_multisite') && is_multisite()) {
            if ($network_wide) {
                $blog_ids = self::get_blog_ids();
                foreach ($blog_ids as $blog_id) {
                    switch_to_blog( $blog_id );
                    self::single_deactivate();
                }
                restore_current_blog();
            } else {
                self::single_deactivate();
            }
        } else {
            self::single_deactivate();
        }

    }

    public function activate_new_site($blog_id) {
        if (1 !== did_action('wpmu_new_blog')) {
            return;
        }
        switch_to_blog($blog_id);
        self::single_activate();
        restore_current_blog();
    }

    private static function get_blog_ids() {
        global $wpdb;
        $sql = "SELECT blog_id FROM $wpdb->blogs
			    WHERE archived = '0' AND spam = '0'
			    AND deleted = '0'";
        return $wpdb->get_col($sql);
    }

    private static function single_activate() {
        $prefix = 'tfw';
        wp_clear_scheduled_hook($prefix.'_import');
        $schedule = get_option($prefix.'_schedule');
        if (!in_array($schedule, array('hourly', 'daily', 'twicedaily'))) {
            $schedule = 'hourly';
            update_option($prefix.'_schedule', 'hourly');
        }
        wp_schedule_event(time()+30, $schedule, $prefix.'_import');
    }

    private static function single_deactivate() {
        $prefix = 'tfw';
        wp_clear_scheduled_hook($prefix.'_import');
    }

    // Actions & Filters

    public function canonical_add() {
        global $post;

        if (is_single()) {
            $url = get_post_meta($post->ID, '_'.$this->prefix.'_url', true);
            if ($url)
                echo "<link rel='canonical' href='$url' />\n";
            else
                rel_canonical();
        }
    }

    public function avatar_replace($avatar, $comment, $size, $default, $alt) {
        if (is_object($comment)) {
            if ($comment->comment_author_IP == $this->network && $comment->comment_author_email)
                $avatar = "<img alt='$comment->comment_author' src='$comment->comment_author_email' class='avatar avatar-{$size} photo avatar-default' height='{$size}' width='{$size}' />";
            return $avatar;
        }
    }

    // Execution

    public static function run() {
        $c = $u = $i = 0;
        update_option($this->prefix.'_imported_comments', 0);
        foreach (get_option($this->prefix.'_profiles', array()) as $id => $profile) {
            if ($profile['author'] && $profile['author'] != '-1') {
                $a = SM2WP_Twitter_Library::create_from_array($profile);
                $r = $a->get_posts();
                $c += $r[0];
                $u += $r[1];
                $i += $r[2];
            }
        }
        update_option($this->prefix.'_imported_new', $c);
        update_option($this->prefix.'_imported_updated', $u);
        update_option($this->prefix.'_imported_ignored', $i);
    }

    private function _ext_init() { # EXT
        if (!get_option('tfw_ignore_canonical', false)) {
            add_action('wp_head', array($this, 'canonical_add'));
            remove_action('wp_head', 'rel_canonical');
        }

        add_action('tfw_import', array('self', 'run'));
    } # Extended

}

class SM2WP_Twitter_Admin {
    const SM2WP_AUTH_URL = 'http://auth.sm2wp.com/';

    public $slug = 'sm2wp-twitter'; # EXT
    public $prefix = 'tfw';
    public static $network = 'Twitter';
    public $library = 'SM2WP_Twitter_Library';

    protected static $instance = null;

    private function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_init', array($this, 'exec_on_admin'));
        add_action('admin_notices', array($this, 'notice_for_admin'));
    }

    public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function add_plugin_admin_menu() {
        add_options_page(__('SM2WP / Twitter Settings', $this->slug),
                         __('SM2WP / Twitter', $this->slug),
                         'manage_options',
                         $this->slug,
                         array($this, 'show_admin')
                        );
    }

    public function show_admin() {
        require_once(__DIR__.'/admin.php');
    }

    public function notice_for_admin() {
        foreach (get_option($this->prefix.'_info', array()) as $info) {
            ?>
                <div class="updated">
                <p><b style='margin-right:20px;'>SM2WP / Twitter</b><?php _e( $info ); ?></p>
                </div>
                <?php
        }

        foreach (get_option($this->prefix.'_errors', array()) as $error) {
            ?>
                <div class="error">
                <p><b style='margin-right:20px;'>SM2WP / Twitter</b><?php _e($error); ?></p>
                </div>
                <?php
        }
        update_option($this->prefix.'_info', array());
        update_option($this->prefix.'_errors', array());
    }

    public function exec_on_admin() {
        if (@$_GET['page'] == $this->slug) {
            if (key_exists('access_token', $_GET)) {
                $p = new $this->library($_GET['id'], $_GET['access_token'], $_GET['network_id']);
                if ($p->update_profile())
                {
                    if ($p->save_profile()) {
                        sm2wp_log_info($this->prefix, 'Successfully added new profile for "'.$p->name.'"');
                    }
                    else {
                        sm2wp_log_info($this->prefix, 'Unable to add profile as it already exists.');
                    }
                } else {
                    sm2wp_log_info($this->prefix, 'Unable to add profile as it could not be retrieved.');
                }
                wp_redirect( admin_url( "options-general.php?page=$_GET[page]" ) );
                exit();

            } else if (key_exists('del', $_GET)) {
                if (call_user_func_array(array($this->library, 'find_profile_by_id'), array($_GET['del']))) {
                    call_user_func_array(array($this->library, 'delete_profile_by_id'), array($_GET['del']));
                    sm2wp_log_info($this->prefix, 'Profile was deleted as requested.');
                    wp_redirect( admin_url( "options-general.php?page=$_GET[page]" ) );
                    exit();
                }
            } else if (key_exists('run', $_GET)) {
                $this->run();
                wp_redirect( admin_url( "options-general.php?page=$_GET[page]" ) );
                exit();
            }

        }
            register_setting($this->prefix.'_profiles', $this->prefix.'_profiles');
            register_setting($this->prefix.'_import_settings', $this->prefix.'_history');
            register_setting($this->prefix.'_import_settings', $this->prefix.'_comments');
            register_setting($this->prefix.'_import_settings', $this->prefix.'_overwrite');
            register_setting($this->prefix.'_import_settings', $this->prefix.'_import_trashed');
            register_setting($this->prefix.'_import_settings', $this->prefix.'_remove_hashtags');
            register_setting($this->prefix.'_import_settings', $this->prefix.'_featured_images');
            register_setting($this->prefix.'_import_settings', $this->prefix.'_import_tags');
            register_setting($this->prefix.'_import_settings', $this->prefix.'_ignore_tags');
            register_setting($this->prefix.'_import_settings', $this->prefix.'_ignore_canonical');
            register_setting($this->prefix.'_import_settings', $this->prefix.'_schedule', array($this, 'schedule_changed'));

            register_setting($this->prefix.'_defaults', $this->prefix.'_post_status');
            register_setting($this->prefix.'_defaults', $this->prefix.'_post_categories', array($this, 'post_categories_changed'));
            register_setting($this->prefix.'_defaults', $this->prefix.'_post_tags');
            register_setting($this->prefix.'_template', $this->prefix.'_template');

            $this->_ext_settings();

    }


    public function post_categories_changed($categories) {
        if (!is_array($categories)) $categories = array($categories);
        return array_filter($categories);
    }

    public function schedule_changed($schedule) {
        if ($schedule && ($schedule != get_option($this->prefix.'_schedule'))) {
            wp_clear_scheduled_hook($this->prefix.'_import');
            wp_schedule_event(time()+30, $schedule, $this->prefix.'_import');
        }
        return $schedule;
    }

    public static function run() {
        $c = $u = $i = 0;
        $prefix = 'tfw';
        update_option($prefix.'_imported_comments', 0);
        foreach (get_option($prefix.'_profiles', array()) as $id => $profile) {
            if ($profile['author'] && $profile['author'] != '-1') {
                $a = SM2WP_Twitter_Library::create_from_array($profile);
                $r = $a->get_posts();
                $c += $r[0];
                $u += $r[1];
                $i += $r[2];
            }
        }
        update_option($prefix.'_imported_new', $c);
        update_option($prefix.'_imported_updated', $u);
        update_option($prefix.'_imported_ignored', $i);
    }

    protected function _ext_settings() { # EXT
        register_setting($this->prefix.'_import_settings', $this->prefix.'_include_replies');
        register_setting($this->prefix.'_import_settings', $this->prefix.'_include_retweets');
        register_setting($this->prefix.'_import_settings', $this->prefix.'_remove_hashes');
    } # EXT


}

class SM2WP_Twitter_Post {
    public $id = null;
    public $post_id = null;
    public $url = null;

    protected $_parent = null;
    protected $_type = null;
    protected $_content = null;
    protected $_date = null;
    protected $_dategmt = null;
    protected $_images = array();
    protected $_video = null;

    public $reshares = 0;
    public $likes = 0;

    public function __construct($parent, $id, $url, $type, $content, $date) {
        $this->id = $id;
        $this->url = $url;
        $this->_type = $type;
        $this->_parent = $parent;
        $this->_content = str_replace('\n', '<br />', $content);
        if (get_option('timezone_string')) {
            $this->_date = date('Y-m-d H:i:s', strtotime($date));
            $this->_dategmt = gmdate('Y-m-d H:i:s', strtotime($date));
        }
        else {
            $this->_date = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($date)), 'Y-m-d H:i:s');
            $this->_dategmt = date('Y-m-d H:i:s', strtotime($date));
        }
    }

    public function add_content($content) {
        $this->_content = $content;
    }

    public function add_image($image, $thumbnail, $url, $link=null) {
        $this->_images[] = array('thumb' => $thumbnail, 'full' => $image, 'url' => $url);
    }

    public function add_video($video) {
        $this->_video = $video;
    }

    protected function _has_first_line_title() {
        if (strpos($this->_content, '<br />') >= 0 && (strpos($this->_content, 'http://') < 0 || (strpos($this->_content, 'http://') > strpos($this->_content, '<br />')))) {
            return strpos($this->_content, '<br />');
        }
        return false;
    }

    public function get_title() {
        $t = '';
        if ($this->_has_first_line_title()) { // Title First Line
            $t = rtrim(strip_tags(substr($this->_content, 0, strpos($this->_content, '<br />'))), '.');
        } else {
            $c = $this->_content;
            if (strpos($c, 'http') !== FALSE) $c = substr($c, 0, strpos($c, 'http'));
            if (strpos($c, '. ') !== FALSE) $t = substr($c, 0, strpos($c, '. '));
            if (!$t) {
                $t = strip_tags($c);
            }
        }

        if (get_option('tfw_remove_hashes', false)) {
            $t = str_replace('#', '', $t);
        }

        return trim($t) ? rtrim(trim($t),':') : 'Untitled';
    }

    public function get_content() {
        $images = $thumbs = $video = $article = $credit = '';
        $content = get_option('tfw_template', SM2WP_Twitter_Library::get_template());

        if ($this->_has_first_line_title()) { // Title First Line
            $c = trim(substr($this->_content, strpos($this->_content, '<br />')));
        } else {
            if (strpos($this->_content, '. ') !== FALSE) {
                $c = trim(substr($this->_content, (strpos($this->_content, 'http') !== FALSE && (strpos($this->_content, 'http') < strpos($this->_content, '. ') && strpos($this->_content, '. ') !== FALSE)) ? strpos($this->_content, 'http') : strpos($this->_content, '. ')+1));
            } else if (strpos($this->_content, 'http') !== FALSE) {
                $c = trim(substr($this->_content, strpos($this->_content, 'http')));
            } else {
                $c = '';
            }
        }
        $c = preg_replace('/^(?:<br\s*\/?>\s*)+/', '', trim($c));
        $c = preg_replace('/(?:<br\s*\/?>\s*)+$/', '', trim($c));

        $c = preg_replace('/(http|ftp|https):\/\/([\w\-_]+(?:(?:\.[\w\-_]+)+))([\w\-\.,@?^=%&amp;:\/\+#]*[\w\-\@?^=%&amp;\/\+#])?/', '<a href="$0">$0</a>', $c);

        $c = preg_replace('/@(\w+)/', '<a href="http://twitter.com/$0">$0</a>', $c);

        if (get_option('tfw_remove_hashtags', false)) $c = $this->_remove_hashtags($c);
        if (get_option('tfw_remove_hashes', false)) $c = str_replace('#', '', $c);

        $content = str_replace(array('{{title}}',
                                     '{{photo}}',
                                     '{{photo-url}}',
                                     '{{video}}',
                                     '{{content}}',
                                     '{{twitter-url}}',
                                     '{{twitter-url-encoded}}',
                                     '{{favourites}}',
                                     '{{retweets}}'),
                               array($this->get_title(),
                                     $this->_images ? $this->_images[0]['full'] : '',
                                     $this->_images ? $this->_images[0]['url'] : '',
                                     $this->_video,
                                     $c,
                                     $this->url,
                                     urlencode($this->url),
                                     $this->favourites,
                                     $this->retweets),
                               $content);

        if (!$this->_images) $content = preg_replace('/{{if-photo-start}}(.*){{if-photo-end}}/msU', '', $content);
        if (!$this->_video) $content = preg_replace('/{{if-video-start}}(.*){{if-video-end}}/msU', '', $content);

        $content = preg_replace('/{{(.*)}}/msU', '', $content);

        return $content;
    }

    public function get_hashtags() {
        $tags = '';
        preg_match_all("/(?:#)([\w\+\-]+)(?=\s|\.|<|$)/", $this->_content, $matches);
        if (@count($matches))
        {
            foreach ($matches[0] as $match)
                $tags .= ', '. str_replace('#','', trim($match));
        }
        return $tags;
    }

    protected function _remove_hashtags($content) {
        return preg_replace('/(?:#)([\w\+\-]+)(?=\s|\.|<|$)/U', '', $content);
    }

    protected function _add_featured_image($post_id) {
        $extension_lookup = array('image/jpeg' => '.jpg',
                                  'image/png' => '.png',
                                  'image/gif' => '.gif');
        list($s, $image) = SM2WP_Twitter_Library::get($this->_images[0]['full']); #@file_get_contents($this->_images[0]['full']);
        $f = substr($this->_images[0]['full'], 0, strpos($this->_images[0]['full'], '?') ? strpos($this->_images[0]['full'], '?') : strlen($this->_images[0]['full']));
        $filename   = urldecode(basename($f));
        $upload_dir = wp_upload_dir();

        if( wp_mkdir_p($upload_dir['path'])) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }
        file_put_contents($file, $image);
        $wp_filetype = wp_check_filetype_and_ext($file, $filename);
        $file_info = getimagesize($file);
        if (@key_exists($file_info['mime'], $extension_lookup)) {
            rename($file, $file.$extension_lookup[$file_info['mime']]);
            $file = $file.$extension_lookup[$file_info['mime']];
            $attachment = array(
                'post_mime_type' => $file_info['mime'],
                'post_title'     => sanitize_file_name( $filename ),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );
            $attach_id = wp_insert_attachment($attachment, $file, $post_id);
            set_post_thumbnail($post_id, $attach_id);

            $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
            wp_update_attachment_metadata( $attach_id, $attach_data );
        }

    }

    public function save_to_wordpress() {
        global $wpdb;
        $wp_post = array(
            'post_content'  => $this->get_content(),
            'post_status'   => strtolower(get_option('tfw_post_status', 'Publish')),
            'post_title'    => $this->get_title(),
            'post_author'   => $this->_parent->author,
            'post_date'     => $this->_date,
            'post_date_gmt' => $this->_dategmt,
            'post_category' => get_option('tfw_post_categories', array()),
            'tags_input'    => get_option('tfw_post_tags', '').$this->get_hashtags()
        );

        $is_trashed = get_option('tfw_import_trashed', true) ? false : (get_posts(array('meta_key' => '_tfw_id', 'meta_value' => $this->id, 'post_status' => 'trash', 'numberposts' => 1)) ? true : false);
        if ($is_trashed) return -1;

        $existing_posts = get_posts(array('meta_key' => '_tfw_id', 'meta_value' => $this->id, 'post_status' => 'publish,pending,future,private,draft', 'numberposts' => 1));
        if ($existing_posts) {
            $this->post_id = $existing_posts[0]->ID;
            $wp_post['ID'] = $this->post_id;
            $wp_post['edit_date'] = true;
        }

        remove_filter('content_save_pre', 'wp_filter_post_kses');
        remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');

        if (!$existing_posts) {
            if ($this->post_id = wp_insert_post($wp_post)) {
                add_post_meta($this->post_id, '_tfw_id', $this->id, true);
                add_post_meta($this->post_id, '_tfw_url', $this->url, true);

                if (get_option('tfw_featured_images', false) && $this->_images) {
                    $this->_add_featured_image($this->post_id);
                }
            }
        } else if (get_option('tfw_overwrite', true)) {
            wp_update_post($wp_post);
            if (!has_post_thumbnail($this->post_id) && get_option('tfw_featured_images', false) && $this->_images) {
                $this->_add_featured_image($this->post_id);
            }

        }

        add_filter('content_save_pre', 'wp_filter_post_kses');
        add_filter('content_filtered_save_pre', 'wp_filter_post_kses');

        return $existing_posts ? (get_option('tfw_overwrite', true) ? 0 : -1) : 1;
    }
}

class SM2WP_Twitter_Library {
    const API_URL = 'http://auth.sm2wp.com/';

    public static $slug = 'sm2wp-twitter'; # EXT
    public static $prefix = 'tfw';
    public static $network = 'Twitter';

    public static function get($url) {
        $r = null;
        $status_code = null;
        mb_internal_encoding('UTF-8');

        if (ini_get('allow_url_fopen') && in_array('https', stream_get_wrappers()))
        {
            $r = file_get_contents($url);
        }
        else
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            $r = curl_exec($ch);
            curl_close($ch);
        }
        $status_code = ($status_code ? $status_code : ($r ? '200' : null));
        sm2wp_log_debug('tfw', '['.$status_code.']'.$url);
        if (!$status_code) return array(null, null);

        return array($status_code, $r);
    }

    public $access_token = null;
    public $profile_id = null;
    public $id = null;

    public $name = null;
    public $avatar = null;
    public $author = null;

    public function __construct($id, $access_token, $profile_id, $name=null, $avatar=null, $author=null) {
        $this->id = $id;
        $this->access_token = $access_token;
        $this->profile_id = str_replace('@', '', $profile_id);
        $this->name = $name;
        $this->avatar = $avatar;
        $this->author = $author;
    }

    public function to_array() {
        return array('id' => $this->id,
                     'name' => $this->name,
                     'network_id' => $this->profile_id,
                     'avatar' => $this->avatar,
                     'access_token' => $this->access_token,
                     'author' => $this->author);
    }

    public static function create_from_array($details) {
        return new SM2WP_Twitter_Library($details['id'],
                                         $details['access_token'],
                                         $details['network_id'],
                                         $details['name'],
                                         $details['avatar'],
                                         $details['author']);
    }

    public static function find_profile_by_id($id, $profiles=null) {
        if (!$profiles) $profiles = get_option('tfw_profiles', array());
        if (@key_exists($id, $profiles)) return SM2WP_Twitter_Library::create_from_array($profiles[$id]);
    }

    public static function delete_profile_by_id($id, $profiles=null) {
        if (!$profiles) $profiles = get_option('tfw_profiles', array());
        if (@key_exists($id, $profiles)) unset($profiles[$id]);
        update_option('tfw_profiles', $profiles);
    }

    public function save_profile() {
        $profiles = get_option('tfw_profiles', array());
        if (@key_exists($this->id.'-'.$this->profile_id, $profiles) && trim($profiles[$this->id.'-'.$this->profile_id]['name'])) return false;
        $profiles[$this->id.'-'.$this->profile_id] = $this->to_array();
        update_option('tfw_profiles', $profiles);
        return true;
    }

    public function update_profile() {
        $url = SM2WP_Twitter_Library::API_URL."r/".$this->id."/".$this->profile_id."/user/";
        list($s, $r) = SM2WP_Twitter_Library::get($url);
        if (!$r) return false;
        $r = json_decode($r);
        $this->name = $r->name;
        $this->avatar = $r->avatar;
        return true;
    }

    public function get_posts() {
        sm2wp_log_running('tfw', 'Starting import of posts for '.$this->name.'.');
        $maxResults = get_option('tfw_history', 10) > 100 ? 100 : get_option('tfw_history', 10);
        $posts = 0;
        $u = $c = $i = 0;
        do {
            $url = SM2WP_Twitter_Library::API_URL."r/".$this->id."/".$this->profile_id."/tweets/";
            list($s, $r) = SM2WP_Twitter_Library::get($url);
            if (!$r || $s != 200) {
                sm2wp_log_running('tfw', "Unable to fetch posts for ".$this->name.'. ('.$s.')');
                return;
            }

            $r = json_decode($r);

            foreach (@$r as $post) {
                if ($post->in_reply_to_screen_name && !get_option('tfw_include_replies', false)) continue;
                if (@$post->retweeted_status && !get_option('tfw_include_retweets', true)) continue;
                if ($posts >= get_option('tfw_history', 10)) break;
                $posts++;

                if (@$post->retweeted_status) {
                  $post->text = $post->retweeted_status->text;
                  $post->text = 'RT @'.$post->retweeted_status->user->screen_name.' '.$post->text;
                }


                $p = new SM2WP_Twitter_Post($this, $post->id_str, 'http://www.twitter.com/'.$this->profile_id.'/status/'.$post->id_str, 'tweet', nl2br(@$post->text, true), $post->created_at);
                $content = $post->text;

                foreach (@$post->entities->urls as $url_entity) {
                  if (substr($url_entity->display_url, 0, 8) == 'youtu.be' ||
                      substr($url_entity->display_url, 0, 11) == 'youtube.com' ||
                      strpos($url_entity->display_url, 'youtube.com') !== FALSE) {
                    $video = str_replace('/v/', '/embed/', str_replace('&autoplay=1','', $url_entity->expanded_url));
                    $video = str_replace('.be/', 'be.com/embed/', str_replace('&autoplay=1','', $video));
                    $video = str_replace('/watch?v=', '/embed/', str_replace('&autoplay=1','', $video));
                    if (strpos($video, '&') !== FALSE) $video = substr($video, 0, strpos($video, '&'));
                    $p->add_video($video);
                  } else {
                    $content = str_replace($url_entity->url, $url_entity->expanded_url, $content);
                  }
                }

                $post->entities->media = @$post->entities->media ? $post->entities->media : array();
                foreach (@$post->entities->media as $media_entity) {
                  $p->add_image($media_entity->media_url.':large', $media_entity->media_url.':thumb', $media_entity->expanded_url);
                  $content = str_replace($media_entity->url, '', $content);
                }

                $p->add_content(nl2br($content, true));

                $p->favourites = @$post->favorite_count;
                $p->retweets = @$post->retweet_count;

                $ignore_tags = @array_filter(explode(',', trim(get_option('tfw_ignore_tags', ''))));
                $import_tags = @array_filter(explode(',', trim(get_option('tfw_import_tags', ''))));
                $tags = explode(', ', $p->get_hashtags());

                if ($ignore_tags && array_intersect($tags, $ignore_tags)) {
                    $i++;
                } else if (!$import_tags || array_intersect($tags, $import_tags)) {
                    $pr = $p->save_to_wordpress();
                    $pr == 1 ? $c++ : ($pr == 0 ? $u++ : $i++);
                } else {
                    $i++;
                }

            }
        } while ($pageToken != '' && ($posts <= get_option('tfw_history', 10)));

        sm2wp_log_running('tfw', "Created $c posts, Updated $u posts and Ignored $i posts for ".$this->name.'.');
        return array($c, $u, $i);
    }

    public static function get_template() {
        return <<<EOT
<p>{{content}}</p>

{{if-photo-start}}
<div><a href='{{photo-url}}'><img src='{{photo}}' /></a></div>
{{if-photo-end}}

{{if-video-start}}
<div><iframe type='text/html' width='100%' height='385' src='{{video}}' frameborder='0'></iframe></div>
{{if-video-end}}

<p><a href='{{twitter-url}}'>Check this out on Twitter</a></p>
EOT;
   }

}

// Init Hooks and Actions

register_activation_hook(__FILE__, array('SM2WP_Twitter', 'activate'));
register_deactivation_hook(__FILE__, array('SM2WP_Twitter', 'deactivate'));
add_action('plugins_loaded', array('SM2WP_Twitter', 'get_instance'));
if (is_admin()) add_action('plugins_loaded', array('SM2WP_Twitter_Admin', 'get_instance'));
