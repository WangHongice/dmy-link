<!-- 腾讯风格 -->
<div class="dmylink-tencent">
    <div class="dmylink-tencent-box">
        <!-- logo -->
        <div class="dmylink-tencent-logo">
            <img src="<?php echo $logourl; ?>" alt="<?php echo get_bloginfo('name'); ?>logo">
        </div>
        <!-- 内容 -->
        <div class="dmylink-tencent-title">
            <div class="dmylink-tencent-title-div">
                <div class="dmylink-tencent-title-icon">
                    您即将离开
                    <?php echo get_bloginfo('name'); ?>，请注意您的账号财产安全
                </div>
                <div class="dmylink-tencent-titlelink">
                    <a>
                        <?php echo esc_url($link); ?>
                    </a>
                </div>
            </div>
            <div class="dmylink-tencent-link-a">
                <a href="<?php echo esc_url($link); ?>" target="_self">继续访问</a>
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
        var continueButton = document.querySelector('.dmylink-tencent-link-a[href="<?php echo esc_js(esc_url($link)); ?>"]');
        
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
