<!-- 知乎风格 -->
<div class="dmylink-zhihu">
    <div class="dmylink-zhihu-box">
        <!-- logo -->
        <div class="dmylink-zhihu-logo">
            <img src="<?php echo $logourl; ?>" alt="<?php echo get_bloginfo('name'); ?>logo">
        </div>
        <!-- 内容 -->
        <div class="dmylink-zhihu-title">
            <div class="dmylink-zhihu-title-div">
                <div class="dmylink-zhihu-title-icon">
                    <div class="dmylink-zhihu-title-text">即将离开
                        <?php echo get_bloginfo('name'); ?>
                    </div>
                    <p>您即将离开
                        <?php echo get_bloginfo('name'); ?>，请注意您的帐号和财产安全。
                    </p>
                    <p class="dmylink-zhihu-titlelink-p-no2">
                        <?php echo esc_url($link); ?>
                    </p>
                </div>
                <div class="dmylink-zhihu-link-a">
                    <a href="<?php echo esc_url($link); ?>" target="_self">继续访问</a>
                </div>
                <div class="dmylink-countdown">
                    <span id="dmylink-countdown-text"><?php echo esc_html($countdown_seconds); ?></span>秒后自动跳转
                </div>
            </div>
        </div>
    </div>
    <script>
        var countdown = <?php echo intval($countdown_seconds); ?>;
        var countdownElement = document.getElementById('dmylink-countdown-text');
        var targetUrl = '<?php echo esc_js(esc_url($link)); ?>';
        var requireClick = <?php echo $require_click ? 'true' : 'false'; ?>;
        var continueButton = document.querySelector('.dmylink-zhihu-link-a[href="<?php echo esc_js(esc_url($link)); ?>"]');
        
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
