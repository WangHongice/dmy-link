<div class="dmylink-moxing-box">
  <div class="dmylink-moxing-logo">
   <img src="<?php echo $logourl; ?>" alt="<?php echo get_bloginfo('name'); ?>logo">
  </div>

  <p class="dmylink-moxing-title">
    <span class="dmylink-moxing-title-icon">🔗</span>
    <span class="dmylink-moxing-title-text">
      <?php echo esc_url($link); ?>
    </span>
  </p>

  <div class="dmylink-moxing-link-a">
    <a href="<?php echo esc_url($link); ?>" class="dmylink-moxing-link-background-pink">
      继续前往
    </a>
    <a href="<?php echo home_url(); ?>" class="dmylink-moxing-link-background-blue">
      回到主页
    </a>
  </div>
  <div class="dmylink-countdown">
    <span id="dmylink-countdown-text"><?php echo esc_html($countdown_seconds); ?></span>
  </div>
</div>
<script>
  var countdown = <?php echo intval($countdown_seconds); ?>;
  var countdownElement = document.getElementById('dmylink-countdown-text');
  var targetUrl = '<?php echo esc_js(esc_url($link)); ?>';
  var requireClick = <?php echo $require_click ? 'true' : 'false'; ?>;
  var continueButton = document.querySelector('.dmylink-moxing-link-background-pink');
  
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
        countdownElement.textContent = '请点击"继续前往"跳转';
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