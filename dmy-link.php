<?php
/*
Plugin Name: 大绵羊外链跳转插件
Description: 大绵羊外链跳转插件是一个非常实用的WordPress插件，它可以对文章中的外链进行过滤，有效地防止追踪和提醒用户。
Version: 1.3.6
Author: 大绵羊&天无神话
Author URI: https://dmyblog.cn
*/

if (!defined('DMY_LINK_URL')) {
    define('DMY_LINK_URL', plugin_dir_url(__FILE__));
}

if (!defined('DMY_LINK_VERSION')) {
    define('DMY_LINK_VERSION', '1.3.7');
}

if (!defined('DMY_LINK_MAX_URL_LENGTH')) {
    define('DMY_LINK_MAX_URL_LENGTH', 2048);
}

if (!defined('DMY_LINK_RATE_LIMIT')) {
    define('DMY_LINK_RATE_LIMIT', 100);
}

if (!defined('DMY_LINK_RATE_LIMIT_PERIOD')) {
    define('DMY_LINK_RATE_LIMIT_PERIOD', 3600);
}

/**
 * 获取插件设置（带缓存）
 * 
 * @return array 插件设置
 */
function dmy_link_get_settings() {
    static $settings = null;
    if ($settings === null) {
        $settings = get_option('dmy_link_settings');
    }
    return $settings;
}

/**
 * 日志记录函数
 * 
 * @param string $message 日志消息
 * @param string $level 日志级别 (info, warning, error)
 * @return void
 */
function dmylink_log($message, $level = 'info') {
    if (WP_DEBUG && WP_DEBUG_LOG) {
        $log_message = sprintf('[DMYLink %s] %s', strtoupper($level), $message);
        error_log($log_message);
    }
}

/**
 * 验证URL格式和安全性
 * 
 * @param string $url 待验证的URL
 * @return bool 是否有效
 */
function dmylink_validate_url($url) {
    if (empty($url)) {
        return false;
    }
    
    if (strlen($url) > DMY_LINK_MAX_URL_LENGTH) {
        dmylink_log('URL长度超过限制: ' . strlen($url), 'warning');
        return false;
    }
    
    $parsed = parse_url($url);
    if ($parsed === false) {
        dmylink_log('URL解析失败: ' . $url, 'warning');
        return false;
    }
    
    $allowed_schemes = array('http', 'https');
    if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), $allowed_schemes)) {
        dmylink_log('不允许的URL协议: ' . (isset($parsed['scheme']) ? $parsed['scheme'] : 'none'), 'warning');
        return false;
    }
    
    if (isset($parsed['host']) && (strpos($parsed['host'], 'localhost') !== false || strpos($parsed['host'], '127.0.0.1') !== false)) {
        dmylink_log('本地地址不允许: ' . $parsed['host'], 'warning');
        return false;
    }
    
    return true;
}

/**
 * 检查速率限制
 * 
 * @param string $identifier 标识符（IP地址或用户ID）
 * @return bool 是否超过限制
 */
function dmylink_check_rate_limit($identifier) {
    $cache_key = 'dmylink_rate_limit_' . md5($identifier);
    $current_count = get_transient($cache_key);
    
    if ($current_count === false) {
        set_transient($cache_key, 1, DMY_LINK_RATE_LIMIT_PERIOD);
        return false;
    }
    
    if ($current_count >= DMY_LINK_RATE_LIMIT) {
        dmylink_log('速率限制触发: ' . $identifier, 'warning');
        return true;
    }
    
    set_transient($cache_key, $current_count + 1, DMY_LINK_RATE_LIMIT_PERIOD);
    return false;
}

/**
 * 获取客户端IP地址
 * 
 * @return string IP地址
 */
function dmylink_get_client_ip() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return sanitize_text_field($ip);
}


// 引入 Codestar Framework 进行插件设置
// 检查Codestar Framework是否已加载
// if ( ! function_exists( 'cs_framework_init' ) ) {
//     require_once plugin_dir_path(__FILE__) . 'codestar-framework/codestar-framework.php';
// }

require_once plugin_dir_path(__FILE__) . 'codestar-framework/admin-settings/dmylink-settings.php';
// require_once plugin_dir_path(__FILE__) . 'codestar-framework/codestar-framework.php';
// require_once plugin_dir_path(__FILE__) . 'codestar-framework/admin-settings/dmylink-settings.php';

function dmy_link_enqueue_styles() {
    $settings = dmy_link_get_settings();
    if (empty($settings['dmy_link_enable'])) {
        return;
    }

    wp_enqueue_style('dmylink-csf-css', plugin_dir_url(__FILE__) . 'css/dmylink.css', array(), '1.0', 'all');
    
    $selected_style = isset($settings['dmy_link_style']) ? $settings['dmy_link_style'] : 'dmylink-default';

    if ($selected_style) {
        $css_file_path = plugin_dir_path(__FILE__) . 'css/' . $selected_style . '.css';
        if (file_exists($css_file_path)) {
            wp_enqueue_style('dmylink-custom-style', plugin_dir_url(__FILE__) . 'css/' . $selected_style . '.css', array(), filemtime($css_file_path), 'all');
        }
    }

    $style_file = plugin_dir_path(__FILE__) . 'styles/' . $selected_style . '.php';
    if (file_exists($style_file)) {
        include_once $style_file;
        $style_function = 'dmylink_' . str_replace('-', '_', $selected_style) . '_style';
        if (function_exists($style_function)) {
            call_user_func($style_function);
        }
    }
}
add_action('wp_enqueue_scripts', 'dmy_link_enqueue_styles');

/**
 * 统一URL加密函数
 * 
 * @param string $url 原始URL
 * @return string 加密后的密钥
 * 
 * 支持两种加密方式：
 * 1. random_string: 生成随机字符串+时间戳
 * 2. aes_encryption: 使用AES-256-CBC加密
 */
function dmy_link_encrypt_url($url) {
    $settings = dmy_link_get_settings();
    $method = isset($settings['dmy_link_verification_method']) ? $settings['dmy_link_verification_method'] : 'random_string';
    
    if ($method === 'aes_encryption') {
        $key = isset($settings['dmy_link_aes_key']) ? $settings['dmy_link_aes_key'] : '';
        if (empty($key)) {
            return generate_random_string(16);
        }
        
        $iv = substr(hash('sha256', $key, true), 0, 16);
        $encrypted = openssl_encrypt($url, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $encrypted_key = base64_encode($encrypted);
        
        set_transient('dmy_link_' . $encrypted_key, $url, 0);
        
        return $encrypted_key;
    } else {
        return generate_random_string(16);
    }
}

/**
 * 生成随机字符串（用于随机字符串方式）
 * 
 * @param int $length 字符串长度
 * @return string 随机字符串
 */
function generate_random_string($length = 16) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $random_string . '_' . time();
}

/**
 * 拦截所有外部链接并生成跳转Key
 * 
 * @param string $content 文章内容
 * @return string 处理后的内容
 * 
 * 功能说明：
 * - 自动识别文章中的所有外部链接
 * - 根据验证方式生成跳转密钥（随机字符串或AES加密）
 * - 支持白名单功能，白名单链接不跳转
 * - 内部链接保持不变
 * - 所有外部链接添加target="_blank"属性
 */
function dmy_link_intercept_links($content) {
    $settings = dmy_link_get_settings();
    if (empty($settings['dmy_link_enable'])) {
        return $content;
    }

    return preg_replace_callback(
        '/<a\s+([^>]*?)href="([^"]*)"([^>]*?)>/i', 
        function($matches) {
            $url = $matches[2];
            $beforeHref = $matches[1];
            $afterHref = $matches[3];
            
            // 检查是否为内部链接或白名单链接
            if (!is_internal_link($url) && !is_whitelisted_link($url, 'dmy_link_settings')) {
                $encrypted_key = dmy_link_encrypt_url($url);
                $settings = dmy_link_get_settings();
                
                // 根据验证方式处理链接存储
                $method = isset($settings['dmy_link_verification_method']) ? $settings['dmy_link_verification_method'] : 'random_string';
                
                if ($method === 'random_string') {
                    // 随机字符串模式：设置过期时间
                    $expiration = isset($settings['dmy_link_expiration']) ? intval($settings['dmy_link_expiration']) : 5;
                    $expiration_time = $expiration * 60;
                    set_transient('dmy_link_' . $encrypted_key, $url, $expiration_time);
                } else {
                    // AES加密模式：永久有效
                    set_transient('dmy_link_' . $encrypted_key, $url, 0);
                }
                
                $newHref = esc_url(home_url('/dinterception?a=' . $encrypted_key));
                
                // 确保所有链接都有target="_blank"属性
                if (!preg_match('/target\s*=\s*[\'"][^"\']*_blank[^"\']*[\'"]/i', $afterHref)) {
                    $afterHref .= ' target="_blank"';
                }
                
                return '<a ' . $beforeHref . 'href="' . $newHref . '"' . $afterHref . '>';
            }
            
            // 内部链接和白名单链接保持不变
            return '<a ' . $beforeHref . 'href="' . $url . '"' . $afterHref . '>';
        }, 
        $content
    );
}
add_filter('the_content', 'dmy_link_intercept_links');

/**
 * 判断是否是内部链接
 * 
 * @param string $url 待检查的URL
 * @return bool 是否为内部链接
 */
function is_internal_link($url) {
    $parsed_url = parse_url($url);
    $home_url = parse_url(home_url());
    return isset($parsed_url['host']) && strcasecmp($parsed_url['host'], $home_url['host']) === 0;
}

/**
 * 检查链接是否在白名单
 * 
 * @param string $url 待检查的URL
 * @param string $option_name 设置选项名称
 * @return bool 是否在白名单中
 */
function is_whitelisted_link($url, $option_name) {
    $options = get_option($option_name);
    if (!isset($options['dmy_link_whitelist']) || !is_string($options['dmy_link_whitelist'])) {
        return false;
    }
    $whitelist = explode("\n", trim($options['dmy_link_whitelist']));

    $parsed_url = parse_url($url);
    $host_and_path = isset($parsed_url['host']) ? $parsed_url['host'] : '';
    $host_and_path .= isset($parsed_url['path']) ? $parsed_url['path'] : '';

    foreach ($whitelist as $whitelisted) {
        $whitelisted = trim($whitelisted);
        if (empty($whitelisted)) {
            continue;
        }

        $whitelisted_parsed = parse_url($whitelisted);
        $whitelisted_host_and_path = isset($whitelisted_parsed['host']) ? $whitelisted_parsed['host'] : '';
        $whitelisted_host_and_path .= isset($whitelisted_parsed['path']) ? $whitelisted_parsed['path'] : '';

        if ($whitelisted_host_and_path === '/') {
            if ($host_and_path === '/') {
                return true;
            }
        } else {
            if (strpos($host_and_path, $whitelisted_host_and_path) === 0) {
                return true;
            }
        }
    }

    return false;
}
/**
 * 处理重定向逻辑
 * 
 * @return void
 * 
 * 功能说明：
 * - 处理跳转请求，验证加密密钥
 * - 支持两种验证方式（随机字符串和AES加密）
 * - 检查速率限制，防止滥用
 * - 从transient中读取链接或进行AES解密
 * - 添加安全头部，防止XSS和点击劫持
 */
function dmy_link_redirect() {
    try {
        $settings = dmy_link_get_settings();
        if (empty($settings['dmy_link_enable'])) {
            return;
        }

        if (isset($_GET['a'])) {
            $encrypted_key = $_GET['a'];
            
            // 获取客户端IP并检查速率限制
            $client_ip = dmylink_get_client_ip();
            if (dmylink_check_rate_limit($client_ip)) {
                wp_die(__('请求过于频繁，请稍后再试。', 'dmylink'), __('请求限制', 'dmylink'), ['response' => 429]);
            }
            
            // 从transient中读取链接
            $link = get_transient('dmy_link_' . $encrypted_key);
            
            dmylink_log('Encrypted Key: ' . $encrypted_key, 'info');
            dmylink_log('Link from transient: ' . ($link ? $link : 'false'), 'info');
            
            // 如果transient中没有链接，尝试AES解密
            if (!$link) {
                $settings = dmy_link_get_settings();
                if (isset($settings['dmy_link_verification_method']) && 
                    $settings['dmy_link_verification_method'] === 'aes_encryption' &&
                    !empty($settings['dmy_link_aes_key'])) {
                    
                    // AES解密链接
                    $key = $settings['dmy_link_aes_key'];
                    $iv = substr(hash('sha256', $key, true), 0, 16);
                    $encrypted = base64_decode(str_replace(' ', '+', $encrypted_key));
                    $link = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                    
                    dmylink_log('AES Decrypt attempt with key: ' . substr($key, 0, 8) . '...', 'info');
                    dmylink_log('Decrypted link: ' . ($link ? $link : 'false'), 'info');
                }
            }

            if (!$link) {
                $home_url = home_url('/'); 
                $back_to_home_button = sprintf(
                    '<br><br><a href="%s" style="padding: 10px 20px; background-color: #0073aa; color: #fff; text-decoration: none; border-radius: 5px;">返回首页</a>',
                    esc_url($home_url)
                );
                
                wp_die(
                    '<div style="max-width: 600px; margin: 50px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">' .
                    '<h2 style="color: #d72c2cbd; margin-top: 0;">跳转链接已过期</h2>' .
                    '<p style="color: #555; line-height: 1.6;">您要访问的外部链接跳转令牌已过期或不存在。这可能是因为：</p>' .
                    '<ul style="color: #666; margin: 15px 0; padding-left: 20px;">' .
                    '<li>令牌已超过有效期</li>' .
                    '<li>页面已刷新导致令牌失效</li>' .
                    '<li>链接地址不正确</li>' .
                    '</ul>' .
                    '<p style="color: #666; margin-bottom: 20px;">您可以尝试以下操作：</p>' .
                    '<ul style="color: #666; margin: 15px 0; padding-left: 20px;">' .
                    '<li>刷新页面重新获取跳转令牌</li>' .
                    '<li>如果问题持续存在，请联系网站管理员</li>' .
                    '</ul>' .
                    $back_to_home_button .
                    '</div>',
                    __('跳转链接过期', 'dmylink'), 
                    ['response' => 404, 'back_link' => false]
                );
            }
            
            // 添加安全响应头
            if (!headers_sent()) {
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: SAMEORIGIN');
                header('X-XSS-Protection: 1; mode=block');
                header('Referrer-Policy: strict-origin-when-cross-origin');
            }
            
            // 加载跳转页面模板
            include_once(plugin_dir_path(__FILE__) . 'dmylink-template.php');
            exit;
        }
    } catch (Exception $e) {
        dmylink_log('DMY Link Error: ' . $e->getMessage(), 'error');
        wp_die(__('跳转页面加载失败，请稍后再试。', 'dmylink'));
    }
}
add_action('init', 'dmy_link_redirect');


// 添加重定向规则
function dmy_link_rewrite_rules() {
    add_rewrite_rule('^dinterception/?$', 'index.php?dinterception=1', 'top');
}
add_action('init', 'dmy_link_rewrite_rules');

// 添加查询变量
function dmy_link_query_vars($vars) {
    $vars[] = 'dinterception';
    return $vars;
}
add_filter('query_vars', 'dmy_link_query_vars');

// 处理重定向逻辑
function dmy_link_template_redirect() {
    if (get_query_var('dinterception') == 1) {
        dmy_link_redirect();
    }
}
add_action('template_redirect', 'dmy_link_template_redirect');
// 注册WordPress原生AJAX处理
add_action('wp_ajax_dmylink_convert', 'dmylink_ajax_convert');
add_action('wp_ajax_nopriv_dmylink_convert', 'dmylink_ajax_convert');

/**
 * AJAX转换链接处理函数
 * 
 * @return void
 */
function dmylink_ajax_convert() {
    try {
        $settings = dmy_link_get_settings();
        if (empty($settings['dmy_link_enable'])) {
            wp_send_json_error(array('message' => '插件已关闭'));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dmylink_convert')) {
            wp_send_json_error(array('message' => '安全验证失败'));
        }

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        if (!dmylink_validate_url($url)) {
            wp_send_json_error(array('message' => 'URL格式无效或不安全'));
        }

        $client_ip = dmylink_get_client_ip();
        if (dmylink_check_rate_limit($client_ip)) {
            wp_send_json_error(array('message' => '请求过于频繁，请稍后再试'));
        }

        if (is_internal_link($url) || is_whitelisted_link($url, 'dmy_link_settings')) {
            wp_send_json_success(array('url' => $url));
        }

        $encrypted_key = dmy_link_encrypt_url($url);
        $settings = dmy_link_get_settings();
        
        $method = isset($settings['dmy_link_verification_method']) ? $settings['dmy_link_verification_method'] : 'random_string';
        
        if ($method === 'random_string') {
            $ttl = isset($settings['dmy_link_expiration']) ? (int)$settings['dmy_link_expiration'] : 5;
            set_transient('dmy_link_' . $encrypted_key, $url, $ttl * 60);
        } else {
            set_transient('dmy_link_' . $encrypted_key, $url, 0);
        }

        wp_send_json_success(array('url' => home_url("/dinterception?a=" . urlencode($encrypted_key))));
    } catch (Exception $e) {
        dmylink_log('DMY Link AJAX Error: ' . $e->getMessage(), 'error');
        wp_send_json_error(array('message' => '处理失败，请稍后再试'));
    }
}


/**
 * 根据设置条件加载圈子或社区功能脚本
 * 
 * @return void
 */
add_action('wp_enqueue_scripts', function () {
    $settings = dmy_link_get_settings();
    if (empty($settings['dmy_link_enable'])) {
        return;
    }

    $enabled_type = '';
    $selector = '';
    
    if (isset($settings['dmy_link_function_type'])) {
        $enabled_type = $settings['dmy_link_function_type'];
        
        if ($enabled_type === 'circle') {
            $selector = isset($settings['dmy_link_circle_selector']) && !empty($settings['dmy_link_circle_selector']) 
                ? $settings['dmy_link_circle_selector'] 
                : '.topic-content';
        } elseif ($enabled_type === 'forums') {
            $selector = isset($settings['dmy_link_forums_selector']) && !empty($settings['dmy_link_forums_selector']) 
                ? $settings['dmy_link_forums_selector'] 
                : '.forum-article';
        }
    }
    
    if (!empty($enabled_type) && !empty($selector)) {
        wp_enqueue_script(
            'dmylink-circle',
            plugin_dir_url(__FILE__) . 'js/dmylink-circle.js',
            array(),            
            '1.0.2',
            true                
        );
        
        wp_localize_script('dmylink-circle', 'dmylink_circle_config', array(
            'selector' => $selector,
            'ajax_url' => admin_url('admin-ajax.php'),
            'function_type' => $enabled_type,
            'nonce' => wp_create_nonce('dmylink_convert')
        ));
    }
});

/**
 * 插件卸载时清理数据
 * 
 * @return void
 */
function dmy_link_uninstall() {
    delete_option('dmy_link_settings');
    
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_dmy_link_%',
            '_transient_timeout_dmy_link_%'
        )
    );
}
register_uninstall_hook(__FILE__, 'dmy_link_uninstall');

/**
 * 插件健康检查
 * 
 * @return array 健康状态
 */
function dmylink_health_check() {
    $health_status = array(
        'status' => 'good',
        'issues' => array(),
        'recommendations' => array()
    );
    
    $settings = dmy_link_get_settings();
    
    if (empty($settings)) {
        $health_status['status'] = 'warning';
        $health_status['issues'][] = '插件未配置';
        $health_status['recommendations'][] = '请前往插件设置页面进行配置';
    }
    
    if (isset($settings['dmy_link_enable']) && empty($settings['dmy_link_enable'])) {
        $health_status['status'] = 'warning';
        $health_status['issues'][] = '插件已关闭';
        $health_status['recommendations'][] = '如需使用外链跳转功能，请启用插件';
    }
    
    if (isset($settings['dmy_link_verification_method']) && $settings['dmy_link_verification_method'] === 'aes_encryption') {
        if (empty($settings['dmy_link_aes_key'])) {
            $health_status['status'] = 'warning';
            $health_status['issues'][] = 'AES加密密钥未设置';
            $health_status['recommendations'][] = '请设置AES加密密钥或切换到随机字符串模式';
        }
    }
    
    if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
        $health_status['status'] = 'error';
        $health_status['issues'][] = 'OpenSSL扩展未安装';
        $health_status['recommendations'][] = '请安装PHP OpenSSL扩展以使用AES加密功能';
    }
    
    $transient_count = wp_count_posts('dmy_link_transient');
    if ($transient_count > 1000) {
        $health_status['status'] = 'warning';
        $health_status['issues'][] = 'Transient数据过多';
        $health_status['recommendations'][] = '建议清理过期的transient数据';
    }
    
    return $health_status;
}

/**
 * 添加站点健康检查
 */
add_filter('site_status_tests', function($tests) {
    $tests['dmylink_health'] = array(
        'label' => __('大绵羊外链跳转插件', 'dmylink'),
        'test' => 'dmylink_health_check',
        'callback' => 'dmylink_health_check',
    );
    return $tests;
});

/**
 * 判断子比主题是否存在
 * 
 * @return bool 是否为子比主题
 */
function is_zibll_themes() {
    $style_file_path = WP_CONTENT_DIR . '/themes/zibll/style.css';
    return file_exists($style_file_path) && is_file($style_file_path);
}

/**
 * 适配子比主题：接管评论链接和用户中心重定向
 * 
 * @return void
 */
if (is_zibll_themes()) {
    if (function_exists('remove_filter')) {
        remove_filter('get_comment_author_link', 'add_redirect_comment_link', 5);
        remove_filter('comment_text', 'add_redirect_comment_link', 99);
    }
    if (function_exists('remove_action')) {
        remove_action('wp_ajax_user_details_data_modal', 'zib_ajax_user_details_data_modal');
        remove_action('wp_ajax_nopriv_user_details_data_modal', 'zib_ajax_user_details_data_modal');
    }
    add_filter('get_comment_author_link', 'dmy_add_redirect_comment_link', 6);
    add_filter('comment_text', 'dmy_add_redirect_comment_link', 100);
    add_action('wp_ajax_user_details_data_modal', 'dmy_zib_ajax_user_details_data_modal');
    add_action('wp_ajax_nopriv_user_details_data_modal', 'dmy_zib_ajax_user_details_data_modal');
}


/**
 * 插件的评论链接处理函数（替换主题的add_redirect_comment_link）
 * 
 * @param string $text 评论文本
 * @return string 处理后的文本
 */
function dmy_add_redirect_comment_link($text = '') {
    $settings = dmy_link_get_settings();
    if (empty($settings['dmy_link_enable'])) {
        return $text;
    }
    return dmy_go_link($text);
}

/**
 * 插件的链接处理逻辑（替代主题的go_link）
 * 
 * @param string $text 文本内容
 * @return string 处理后的文本
 */
function dmy_go_link($text = '') {
    $settings = dmy_link_get_settings();
    if (empty($settings['dmy_link_enable'])) {
        return $text;
    }

    if (preg_match('/^https?:\/\//', $text) && !preg_match('/<a.*?>/', $text)) {
        if (!is_internal_link($text) && !is_whitelisted_link($text, 'dmy_link_settings')) {
            return dmy_get_redirect_url($text);
        }
        return $text;
    }

    preg_match_all("/<a(.*?)href=['\"](.*?)['\"](.*?)>/", $text, $matches);
    if ($matches) {
        foreach ($matches[2] as $val) {
            if (!is_internal_link($val) && !is_whitelisted_link($val, 'dmy_link_settings')) {
                $redirect_url = dmy_get_redirect_url($val);
                $text = str_replace(
                    array("href=\"$val\"", "href='$val'"),
                    "href=\"$redirect_url\"",
                    $text
                );
            }
        }
        foreach ($matches[0] as $a_tag) {
            if (!preg_match('/target=["\']_blank["\']/', $a_tag)) {
                $text = str_replace($a_tag, str_replace('<a', '<a target="_blank"', $a_tag), $text);
            }
        }
    }
    return $text;
}

/**
 * 生成插件的跳转链接（替代主题的zib_get_gourl）
 * 
 * @param string $url 目标URL
 * @return string 跳转URL
 */
function dmy_get_redirect_url($url) {
    $encrypted_key = dmy_link_encrypt_url($url);
    $settings = dmy_link_get_settings();
    $method = isset($settings['dmy_link_verification_method']) ? $settings['dmy_link_verification_method'] : 'random_string';
    
    if ($method === 'random_string') {
        $expiration = isset($settings['dmy_link_expiration']) ? intval($settings['dmy_link_expiration']) : 5;
        set_transient('dmy_link_' . $encrypted_key, $url, $expiration * 60);
    }
    
    return esc_url(home_url('/dinterception?a=' . $encrypted_key));
}


/**
 * 查看用户全部详细资料的模态框
 * 
 * @return void
 */
function dmy_zib_ajax_user_details_data_modal() {
    try {
        $user_id = !empty($_REQUEST['id']) ? $_REQUEST['id'] : '';

        $user = get_userdata($user_id);
        if (!$user_id || empty($user->ID)) {
            if (function_exists('zib_ajax_notice_modal')) {
                zib_ajax_notice_modal('danger', '用户不存在或参数传入错误');
            } else {
                wp_die('用户不存在或参数传入错误');
            }
        }

        echo dmy_zib_get_user_details_data_modal($user_id);
        exit();
    } catch (Exception $e) {
        if (WP_DEBUG) {
            error_log('DMY Link User Modal Error: ' . $e->getMessage());
        }
        wp_die('加载用户信息失败');
    }
}


/**
 * 获取用户详细资料
 * 
 * @param int $user_id 用户ID
 * @param string $class CSS类名
 * @param string $t_class 标题CSS类名
 * @param string $v_class 值CSS类名
 * @return string HTML内容
 */
function dmy_zib_get_user_details_data_modal($user_id = '', $class = 'mb10 flex', $t_class = 'muted-2-color', $v_class = '') {
    if (!$user_id) {
        return;
    }

    $current_id = get_current_user_id();
    $udata = get_userdata($user_id);
    if (!$udata) {
        return;
    }

    $privacy = function_exists('zib_get_user_meta') ? zib_get_user_meta($user_id, 'privacy', true) : 'public';

    $datas = array(
        array(
            'title' => '签名',
            'value' => function_exists('get_user_desc') ? get_user_desc($user_id, false) : '',
            'spare' => '未知',
            'no_show' => false,
        ),
        array(
            'title' => '注册时间',
            'value' => get_date_from_gmt($udata->user_registered),
            'spare' => '未知',
            'no_show' => false,
        ),
        array(
            'title' => '最后登录',
            'value' => get_user_meta($user_id, 'last_login', true),
            'spare' => '未知',
            'no_show' => false,
        ),
        array(
            'title' => '邮箱',
            'value' => esc_attr($udata->user_email),
            'spare' => '未知',
            'no_show' => true,
        ),
        array(
            'title' => '性别',
            'value' => function_exists('zib_get_user_meta') ? esc_attr(zib_get_user_meta($user_id, 'gender', true)) : '',
            'spare' => '保密',
            'no_show' => true,
        ),
        array(
            'title' => '地址',
            'value' => function_exists('zib_get_user_meta') ? esc_textarea(zib_get_user_meta($user_id, 'address', true)) : '',
            'spare' => '未知',
            'no_show' => true,
        ),
        array(
            'title' => '个人网站',
            'value' => dmy_zib_get_url_link($user_id),
            'spare' => '未知',
            'no_show' => true,
        ),
        array(
            'title' => 'QQ',
            'value' => function_exists('zib_get_user_meta') ? esc_attr(zib_get_user_meta($user_id, 'qq', true)) : '',
            'spare' => '未知',
            'no_show' => true,
        ),
        array(
            'title' => '微信',
            'value' => function_exists('zib_get_user_meta') ? esc_attr(zib_get_user_meta($user_id, 'weixin', true)) : '',
            'spare' => '未知',
            'no_show' => true,
        ),
        array(
            'title' => '微博',
            'value' => function_exists('zib_get_user_meta') ? esc_url(zib_get_user_meta($user_id, 'weibo', true)) : '',
            'spare' => '未知',
            'no_show' => true,
        ),
        array(
            'title' => 'Github',
            'value' => function_exists('zib_get_user_meta') ? esc_url(zib_get_user_meta($user_id, 'github', true)) : '',
            'spare' => '未知',
            'no_show' => true,
        ),
    );

    $lists = '';

    if (function_exists('_pz') && _pz('user_auth_s', true)) {
        $auth_name = function_exists('zib_get_user_auth_info_link') ? zib_get_user_auth_info_link($user_id, 'c-blue') : '';
        $auth_name = $auth_name ? $auth_name : '未认证';
        $lists .= '<div class="' . $class . '" style="min-width: 50%;">';
        $lists .= '<div class="author-set-left ' . $t_class . '" style="min-width: 80px;">认证</div>';
        $lists .= '<div class="author-set-right mt6' . $v_class . '">' . $auth_name . '</div>';
        $lists .= '</div>';
    }

    if (function_exists('_pz') && _pz('user_medal_s', true)) {
        $user_medal = function_exists('zib_get_user_medal_show_link') ? zib_get_user_medal_show_link($user_id, '', 5) : '';
        $user_medal = $user_medal ? $user_medal : '暂无徽章';

        $lists .= '<div class="' . $class . '" style="min-width: 50%;">';
        $lists .= '<div class="author-set-left ' . $t_class . '" style="min-width: 80px;">徽章</div>';
        $lists .= '<div class="author-set-right mt6' . $v_class . '">' . $user_medal . '</div>';
        $lists .= '</div>';
    }

    foreach ($datas as $data) {
        if (!is_super_admin() && $data['no_show'] && 'public' != $privacy && $current_id != $user_id) {
            if (('just_logged' == $privacy && !$current_id) || 'just_logged' != $privacy) {
                $data['value'] = '用户未公开';
            }
        }
        $lists .= '<div class="' . $class . '" style="min-width: 50%;">';
        $lists .= '<div class="author-set-left ' . $t_class . '" style="min-width: 80px;">' . $data['title'] . '</div>';
        $lists .= '<div class="author-set-right mt6' . $v_class . '">' . ($data['value'] ? $data['value'] : $data['spare']) . '</div>';
        $lists .= '</div>';
    }

    $header = '<div class="mb10 border-bottom touch" style="padding-bottom: 12px;">';
    $header .= '<button class="close ml10" data-dismiss="modal">' . (function_exists('zib_get_svg') ? zib_get_svg('close', null, 'ic-close') : '&times;') . '</button>';
    $header .= '<div class="" style="">';
    $header .= function_exists('zib_get_post_user_box') ? zib_get_post_user_box($user_id) : '';
    $header .= '</div>';
    $header .= '</div>';

    $html = '<div class="mini-scrollbar scroll-y max-vh5 flex hh">' . $lists . '</div>';
    return $header . $html;
}


/**
 * 获取用户URL链接
 * 
 * @param int $user_id 用户ID
 * @param string $class CSS类名
 * @return string HTML链接
 */
function dmy_zib_get_url_link($user_id, $class = 'focus-color') {
    $user_url = get_userdata($user_id)->user_url;
    $url_name = function_exists('zib_get_user_meta') ? zib_get_user_meta($user_id, 'url_name', true) : $user_url;
    $url_name = $url_name ?: $user_url;
    $user_url = dmy_go_link($user_url, true);
    return $user_url ? '<a class="' . $class . '" href="' . esc_url($user_url) . '" target="_blank">' . esc_attr($url_name) . '</a>' : 0;
}