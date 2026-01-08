<?php
/**
 * 大绵羊外链跳转模板
 * 
 * 安全加载跳转页面模板，包含错误处理和缓存机制
 * 
 * 功能说明：
 * - 支持多种跳转页面风格（默认、Bilibili、腾讯、CSDN、知乎、Jump、TikTok、模型）
 * - 自动加载对应的CSS样式文件
 * - 支持自定义Logo显示
 * - 提供头部模板fallback机制
 * - 包含错误处理和日志记录
 * 
 * @package DMY_LINK_URL
 */

// 缓存设置数据，避免重复查询数据库
static $settings = null;
if ($settings === null) {
    $settings = get_option('dmy_link_settings');
}

// 获取风格标识，默认为default风格
static $style = null;
if ($style === null) {
    $style = isset($settings['dmy_link_style']) ? 
             sanitize_text_field($settings['dmy_link_style']) : 
             'dmylink-default';
}

// 定义风格模板映射，关联风格名称到对应的模板文件
define('DMYLINK_TEMPLATES', [
    'dmylink-bilibili' => 'bilibili-style.php',
    'dmylink-tencent'  => 'tencent-style.php',
    'dmylink-csdn'     => 'csdn-style.php',
    'dmylink-zhihu'    => 'zhihu-style.php',
    'dmylink-jump'     => 'jump-style.php',
    'dmylink-default'  => 'default-style.php',
    'dmylink-moxing'   => 'moxing-style.php',
    'dmylink-tiktok'   => 'tiktok-style.php'
]);

// 获取倒计时秒数
$countdown_seconds = isset($settings['dmy_link_countdown_seconds']) ? 
                     intval($settings['dmy_link_countdown_seconds']) : 5;

// 获取显示目标URL设置
$show_target_url = isset($settings['dmy_link_show_target_url']) ? 
                     (bool)$settings['dmy_link_show_target_url'] : false;

// 获取要求手动点击设置
$require_click = isset($settings['dmy_link_require_click']) ? 
                   (bool)$settings['dmy_link_require_click'] : false;

// 确保样式表加载
$css_file = plugin_dir_path(__FILE__) . 'css/' . $style . '.css';
$css_url = plugin_dir_url(__FILE__) . 'css/' . $style . '.css';

// 检查文件是否存在，不存在则使用默认样式
if (!file_exists($css_file)) {
    $style = 'dmylink-default';
    $css_file = plugin_dir_path(__FILE__) . '/css/' . $style . '.css';
    $css_url = plugin_dir_url(__FILE__) . '/css/' . $style . '.css';
}

// 仅当文件存在时才加载样式
if (file_exists($css_file)) {
    wp_enqueue_style('dmylink-template-style', $css_url, array(), filemtime($css_file));
}

// 安全加载头部模板
$header_file = plugin_dir_path(__FILE__) . 'templates/header.php';
if (file_exists($header_file)) {
    include_once $header_file;
} else {
    // 头部模板缺失的fallback
    get_header();
    echo '<div class="container">';
}

// 确定要加载的模板文件
$template_file = isset(DMYLINK_TEMPLATES[$style]) ? 
                DMYLINK_TEMPLATES[$style] : 
                DMYLINK_TEMPLATES['dmylink-default'];

// 安全加载内容模板
$template_path = plugin_dir_path(__FILE__) . 'templates/' . $template_file;
if (file_exists($template_path)) {
    if (WP_DEBUG && WP_DEBUG_LOG) {
        error_log('Loading template: ' . $template_path);
    }
    include_once $template_path;
} else {
    if (WP_DEBUG && WP_DEBUG_LOG) {
        error_log('Template not found: ' . $template_path);
    }
    echo '<div class="alert alert-warning">';
    echo '<p>'.__('跳转页面加载失败，请稍后再试。', 'dmylink').'</p>';
    echo '<p>'.__('当前样式: ', 'dmylink') . esc_html($style) . '</p>';
    echo '</div>';
}

// 加载页面底部
//wp_footer();
?>
</div>
</body>
</html>