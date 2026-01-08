<div class="dmy-tiktok-main-container">
  <div class="dmy-tiktok-external-link-image adpator_x_img">
    <img
      src="<?php echo DMY_LINK_URL . 'assets/img/dmylink-tiktok.png'; ?>"
      alt="" class="image">
  </div>

  <div class="dmy-tiktok-external-link-header">
    <p class="dmy-tiktok-external-link-title">您即将打开一个链接：</p>
    <p class="dmy-tiktok-external-link-url">
      <a class="dmy-tiktok-external-link-url-text"><?php echo esc_url($link); ?></a>
    </p>
  </div>

  <div class="dmy-tiktok-external-link-detail">
    <p class="dmy-tiktok-external-link-detail-text" id="prompt_detail_word_top">
      您即将打开外部网站。请谨慎操作，保护您的个人信息安全。
    </p>
  </div>

  <div class="dmy-tiktok-button-group dmy-tiktok-button-open-anyway-group">
    <div class="dmy-tiktok-button-open-link">
      <a href="<?php echo esc_url($link); ?>" target="_self"
        style="font-family:'TikTok Text'; font-size:18px; color:#161823; font-weight:600; line-height:25px;">
        点击跳转
      </a>
    </div>
    <div class="dmylink-countdown">
      <span id="dmylink-countdown-text"><?php echo esc_html($countdown_seconds); ?></span>秒后自动跳转
    </div>
  </div>
  <script>
    var countdown = <?php echo intval($countdown_seconds); ?>;
    var countdownElement = document.getElementById('dmylink-countdown-text');
    var targetUrl = '<?php echo esc_js(esc_url($link)); ?>';
    var requireClick = <?php echo $require_click ? 'true' : 'false'; ?>;
    var continueButton = document.querySelector('.dmy-tiktok-button-open-link');
    
    function updateCountdown() {
      if (countdown > 0) {
        countdownElement.textContent = countdown;
        countdown--;
        setTimeout(updateCountdown, 1000);
      } else {
        countdownElement.textContent = '正在跳转...';
        if (!requireClick) {
          setTimeout(function() {
            window.location.href = targetUrl;
          }, 500);
        } else {
          countdownElement.textContent = '请点击"点击跳转"';
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