</main>
<footer class="site-footer">
  <div class="container">
    <p>© <?= date('Y') ?> <?= e(SITE_NAME) ?> · همه حقوق محفوظ است. · <a href="<?= BASE_URL ?>/privacy.php" style="color:#eb7c2a">حریم خصوصی</a></p>
  </div>
</footer>

<!-- Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js')
            .then(function(registration) {
                console.log('[PWA] Service Worker ثبت شد:', registration.scope);
            })
            .catch(function(error) {
                console.log('[PWA] خطا در ثبت Service Worker:', error);
            });
    });
}
</script>
</body>
</html>
