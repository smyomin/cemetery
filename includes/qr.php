<?php
function qr_block(int $id): void {
    $url = 'https://' . $_SERVER['HTTP_HOST'] . '/g/' . $id;
    ?>
<div class="card" style="margin:14px 20px 0;padding:16px;text-align:center">
  <h2 class="loc-h"><?= t('qr_title') ?></h2>
  <div id="qr" style="display:inline-block;padding:10px;background:#fff"></div>
  <p class="sm" style="word-break:break-all"><?= htmlspecialchars($url) ?></p>
  <p class="sm"><?= t('qr_hint') ?></p>
  <button class="btn" id="qr-share" type="button" style="margin-top:8px"><?= t('qr_share') ?></button>
  <button class="btn" id="qr-dl" type="button" style="background:#fff;color:#0F4C3A;border:1.5px solid #0F4C3A"><?= t('qr_download') ?></button>
</div>
<script src="/assets/js/qrcode.min.js"></script>
<script>
(function () {
  var url = <?= json_encode($url) ?>;
  var box = document.getElementById('qr');
  new QRCode(box, { text: url, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M });
  function download() {
    var a = document.createElement('a');
    a.href = box.querySelector('canvas').toDataURL('image/png');
    a.download = 'grave-<?= $id ?>-qr.png';
    a.click();
  }
  document.getElementById('qr-dl').addEventListener('click', download);
  document.getElementById('qr-share').addEventListener('click', function () {
    box.querySelector('canvas').toBlob(function (blob) {
      var f = new File([blob], 'grave-<?= $id ?>-qr.png', { type: 'image/png' });
      if (navigator.canShare && navigator.canShare({ files: [f] })) {
        navigator.share({ files: [f], text: url }).catch(function () {});
      } else if (navigator.share) {
        navigator.share({ url: url }).catch(function () {});
      } else {
        download();
      }
    }, 'image/png');
  });
})();
</script>
<?php
}