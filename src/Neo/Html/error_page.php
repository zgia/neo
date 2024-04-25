<!DOCTYPE html>
<html lang="<?php echo \Neo\Neo::language(); ?>">
<head>
  <meta charset="<?php echo \Neo\Neo::charset(); ?>">
  <title><?php echo $title ?: __('Neo &rsaquo; Error'); ?></title>
  <style type="text/css">
    html{font-family:sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}body{margin:0}a{background-color:transparent}a:active,a:hover{outline:0}strong{font-weight:700}small{font-size:80%}*{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}:after,:before{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}html{font-size:10px;-webkit-tap-highlight-color:rgba(0,0,0,0)}body{font-family:"Helvetica Neue",Helvetica,Arial,sans-serif;font-size:14px;line-height:1.42857143;color:#333;background-color:#fff}h3{font-family:inherit;font-weight:500;line-height:1.1;color:inherit;margin-top:20px;margin-bottom:10px;font-size:24px}p{margin:0 0 10px}.lead{margin-bottom:20px;font-size:16px;font-weight:600;line-height:1.4}@media(min-width:768px){.lead{font-size:21px}}dl{margin-top:0;margin-bottom:20px}dd,dt{line-height:1.42857143}dt{font-weight:700}dd{margin-left:0}@media(min-width:768px){.dl-horizontal dt{float:left;width:160px;overflow:hidden;clear:left;text-align:right;text-overflow:ellipsis;white-space:nowrap}.dl-horizontal dd{margin-left:180px}}.container{padding-right:15px;padding-left:15px;margin-right:auto;margin-left:auto}@media(min-width:768px){.container{width:750px}}@media(min-width:992px){.container{width:970px}}@media(min-width:1200px){.container{width:1170px}}.row{margin-right:-15px;margin-left:-15px}.col-md-12{position:relative;min-height:1px;padding-right:15px;padding-left:15px}@media(min-width:992px){.col-md-12{float:left}.col-md-12{width:100%}}.panel{margin-bottom:20px;background-color:#fff;border:1px solid transparent;border-radius:4px;-webkit-box-shadow:0 1px 1px rgba(0,0,0,.05);box-shadow:0 1px 1px rgba(0,0,0,.05)}.panel-body{padding:15px}.panel-heading{padding:10px 15px;border-bottom:1px solid transparent;border-top-left-radius:3px;border-top-right-radius:3px}.panel-title{margin-top:0;margin-bottom:0;font-size:16px;color:inherit}.panel-title>.small>a,.panel-title>a,.panel-title>small,.panel-title>small>a{color:inherit}.panel-danger{border-color:#ebccd1}.panel-danger>.panel-heading{color:#a94442;background-color:#f2dede;border-color:#ebccd1}.panel-default{border-color:#ddd}.panel-default>.panel-heading{color:#333;background-color:#f5f5f5;border-color:#ddd}.panel-success{border-color:#d6e9c6}.panel-success>.panel-heading{color:#3c763d;background-color:#dff0d8;border-color:#d6e9c6}.text-muted{color:#777}.margin-top-20{margin-top:20px}hr{margin:20px 0;border:0;border-top:1px solid #eee;border-bottom:0}
  </style>
</head>
<body>

<div class="container">
  <div class="row margin-top-20">
    <div class="col-md-12">
      <div class="panel panel-<?php echo $extension['isError'] ? 'danger' : ($extension['isError'] === 0 ? 'success' : 'default'); ?>">
        <div class="panel-heading">
          <h3 class="panel-title"><?php echo $title ?: __('Neo &rsaquo; Error'); ?></h3>
        </div>
        <div class="panel-body">
          <p class="lead"><?php echo $message; ?></p>

          <?php if ($extension['more'] && is_array($extension['more'])) { ?>
          <hr>
          <dl class="dl-horizontal">
            <?php foreach ($extension['more'] as $k => $v) { ?>
            <dt><?php echo $k; ?></dt>
            <dd<?php echo strtolower($k) == 'error' ? ' class="text-danger"' : ''; ?>><?php echo $v; ?></dd>
            <?php } ?>
          </dl>
          <?php } ?>

          <?php if ($extension['redirectTime'] || $extension['back']) { ?>
          <p class="text-muted">
            <small>
            <?php if ($extension['redirectTime']) {
              echo sprintf('<span id="redirectTime">%s</span>', $extension['redirectTime']);
              _f('Seconds after the auto-jump, does not support frames, please <a href="%s">click here</a> to jump manually or click <a href="javascript:history.back(1)">Back</a> to retry.', $extension['url']);
            } elseif ($extension['back']) {
              _e('Please click <a href="javascript:history.back(1)">Back</a> to retry.');
            } ?>
            </small>
          </p>
          <?php } ?>

        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($extension['redirectTime']) { ?>
<script type="text/javascript">
  let i = <?php echo $extension['redirectTime']; ?>;
  let timer = setInterval(function () {
    i--;
    document.getElementById('redirectTime').innerText = i;
    if (i === 0) {
      location.href = "<?php echo $extension['url']; ?>";
      clearInterval(timer);
    }
  }, 1000);
</script>
<?php } ?>

</body>
</html>