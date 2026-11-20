<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>跳转提示</title>
    <style>
        * { padding: 0; margin: 0; }
        body { background: #f4f8f7; color: #1f2d2c; font: 16px/1.6 "Microsoft YaHei", "Helvetica Neue", Arial, sans-serif; }
        .system-message { width: min(520px, calc(100% - 32px)); margin: 12vh auto 0; padding: 32px 36px; background: #fff; border: 1px solid #e6efed; border-radius: 10px; text-align: center; box-shadow: 0 10px 30px rgba(31, 45, 44, .06); }
        .system-message h1 { margin-bottom: 10px; color: #16baaa; font-size: 48px; font-weight: 500; line-height: 1.2; }
        .system-message .error h1 { color: #ff5722; }
        .system-message .success { color: #16baaa; font-size: 18px; }
        .system-message .error { color: #ff5722; font-size: 18px; }
        .system-message .jump { margin-top: 22px; padding-top: 18px; border-top: 1px solid #e6efed; color: #7a9291; font-size: 14px; }
        .system-message .jump a { color: #16baaa; }
    </style>
</head>
<body>
<div class="system-message">
    <?php if ((int) $code === 1): ?>
        <h1>:)</h1>
        <p class="success"><?php echo htmlspecialchars(strip_tags((string) $msg), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php else: ?>
        <h1>:(</h1>
        <p class="error"><?php echo htmlspecialchars(strip_tags((string) $msg), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <p class="jump">
        页面自动 <a id="href" href="<?php echo htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8'); ?>">跳转</a>
        等待时间：<b id="wait"><?php echo (int) $wait; ?></b>
    </p>
</div>
<script>
    (function () {
        var wait = document.getElementById('wait');
        var href = document.getElementById('href').href;
        var timer = setInterval(function () {
            var time = --wait.innerHTML;
            if (time <= 0) {
                location.href = href;
                clearInterval(timer);
            }
        }, 1000);
    })();
</script>
</body>
</html>