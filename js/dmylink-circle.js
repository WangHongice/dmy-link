/**
 * 实时转换动态加载的链接为跳转链接
 * 
 * 功能说明：
 * - 支持动态配置选择器，适配不同主题（如7b2主题圈子、子比主题社区帖子）
 * - 使用请求队列机制，批处理优化，减少重复请求
 * - 支持MutationObserver，监听DOM变化，实时处理动态加载的内容
 * - 支持缓存机制，避免重复处理同一链接
 * 
 * @return void
 */
(function () {
    // 从全局配置中获取选择器和AJAX URL
    const config = window.dmylink_circle_config || {
        selector: '.topic-content',
        ajax_url: window.location.origin + '/wp-admin/admin-ajax.php'
    };
    
    // 获取根域名和API配置
    const ROOT = window.location.origin;
    const API = config.ajax_url;
    const DOMAIN = window.location.host;
    const SELECTOR = config.selector;
    
    /**
     * 判断是否为外部链接
     * 
     * @param {string} href 待检查的链接
     * @return {boolean} 是否为外部链接
     */
    function isExternal(href) {
        // 检查链接是否为空或不是http/https协议
        if (!href || (!href.startsWith('http://') && !href.startsWith('https://'))) return false;
        
        // 尝试解析URL并比较域名
        try { return new URL(href).host !== DOMAIN; } catch (e) { return false; }
    }
    
    // 创建转换队列，用于批处理优化
    let convertQueue = new Map();
    let isProcessing = false;
    
    /**
     * 处理队列中的链接
     * 
     * @return {void}
     */
    function processQueue() {
        // 如果正在处理或队列为空，直接返回
        if (isProcessing || convertQueue.size === 0) return;
        
        // 标记为处理中
        isProcessing = true;
        
        // 批处理：每次最多处理5个链接
        const batch = Array.from(convertQueue).slice(0, 5);
        batch.forEach(([key, a]) => {
            convertQueue.delete(key);
            convert(a);
        });
    
        // 100ms后重置处理状态
        setTimeout(() => {
            isProcessing = false;
            // 如果队列中还有链接，继续处理
            if (convertQueue.size > 0) {
                processQueue();
            }
        }, 100);
    }
    
    /**
     * 转换单个链接
     * 
     * @param {HTMLAnchorElement} a 链接元素
     * @return {void}
     */
    function convert(a) {
        // 检查是否已处理过
        if (a.dataset.dmylinkDone) return;
        
        // 获取链接的href属性
        const href = a.getAttribute('href');
        
        // 如果不是外部链接，直接返回
        if (!isExternal(href)) return;
        
        // 生成缓存键
        const cacheKey = 'dmylink_' + href;
        
        // 如果队列中已有该链接，直接返回
        if (convertQueue.has(cacheKey)) return;
        
        // 将链接加入队列
        convertQueue.set(cacheKey, a);
        
        // 触发队列处理
        processQueue();
    }
    
    /**
     * 扫描指定根元素下的所有链接
     * 
     * @param {Element} root 根元素
     * @return {void}
     */
    function scan(root) {
        root.querySelectorAll('a[href]').forEach(convert);
    }
    
    // 扫描文档中的所有指定选择器元素
    document.querySelectorAll(SELECTOR).forEach(scan);
    
    /**
     * 使用MutationObserver监听DOM变化
     * 
     * 功能：监听DOM变化，实时处理动态加载的内容
     * 适用于：AJAX加载的内容、SPA应用等动态内容
     */
    const ob = new MutationObserver(list => {
        list.forEach(m => {
            // 遍历所有变化记录
            m.addedNodes.forEach(n => {
                // 跳过非元素节点
                if (n.nodeType !== 1) return;
                
                // 如果匹配选择器，扫描其中的链接
                if (n.matches(SELECTOR)) scan(n);
                // 递归扫描子元素中的链接
                n.querySelectorAll && n.querySelectorAll(SELECTOR).forEach(scan);
            });
        });
    });
    
    // 开始监听整个文档的子节点和属性变化
    ob.observe(document.body, { childList: true, subtree: true });
    
    // 调试模式下输出日志
    if (WP_DEBUG) {
        console.log('DMY Link Circle 已启用，监听选择器:', SELECTOR);
    }
})();
