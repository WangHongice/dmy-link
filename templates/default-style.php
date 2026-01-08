<!-- 默认风格 -->
<div class="dmylink-default">
    <div class="dmylink-default-box">
        <!-- logo -->
        <div class="dmylink-default-logo">
            <img src="<?php echo $logourl; ?>" alt="<?php echo get_bloginfo('name'); ?>logo">
        </div>
        <!-- 内容 -->
        <div class="dmylink-default-title">
            <div class="dmylink-default-title-div">
                <div class="dmylink-default-title-icon">
                    <img class="loading-img"
                        src="<?php echo DMY_LINK_URL . 'assets/img/dmylink-default.png'; ?>"
                        alt="<?php echo get_bloginfo('name'); ?>-提示警告">
                    <div class="dmylink-default-title-text">请注意您的账号和财产安全</div>
                </div>
                <div class="dmylink-default-titlelink">
                    <span>
                        您即将离开
                        <?php echo get_bloginfo('name'); ?>，去往:
                        <?php echo esc_url($link); ?> 请注意您的帐号和财产安全
                    </span>
                </div>
            </div>
            <div class="dmylink-default-link-a">
                <a href="<?php echo esc_url($link); ?>" target="_self">继续访问</a>
                <a href="<?php echo home_url(); ?>">返回文章</a>
            </div>
            <div class="dmylink-countdown">
                <span id="dmylink-countdown-text"><?php echo esc_html($countdown_seconds); ?></span>
            </div>
        </div>
    </div>
    <script>
        var countdown = <?php echo intval($countdown_seconds); ?>;
        var countdownElement = document.getElementById('dmylink-countdown-text');
        var targetUrl = '<?php echo esc_js(esc_url($link)); ?>';
        var requireClick = <?php echo $require_click ? 'true' : 'false'; ?>;
        var continueButton = document.querySelector('.dmylink-default-link-a[href="<?php echo esc_js(esc_url($link)); ?>"]');
        
        function updateCountdown() {
            if (countdown > 0) {
                countdownElement.textContent = countdown;
                countdown--;
                setTimeout(updateCountdown, 1000);
            } else {
                if (!requireClick) {
                    setTimeout(function() {
                        window.location.href = targetUrl;
                    }, 500);
                } else {
                    countdownElement.textContent = '请点击"继续访问"跳转';
                }
            }
        }
        
        updateCountdown();
        
        if (continueButton && requireClick) {
            continueButton.addEventListener('click', function() {
                window.location.href = targetUrl;
            });
        }
    </script>
</div>
