</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <span><?= e(SITE_NAME) ?> — учёт кошек и родословных</span>
        <span><?= date('Y') ?> · тестовый проект</span>
    </div>
</footer>

<dialog class="confirm-dialog" id="confirm-dialog" aria-labelledby="confirm-dialog-title">
    <h2 id="confirm-dialog-title">Подтвердите действие</h2>
    <p data-confirm-text></p>
    <div class="dialog-actions">
        <button class="btn btn-ghost" type="button" data-confirm-cancel>Отмена</button>
        <button class="btn btn-danger" type="button" data-confirm-ok>Удалить</button>
    </div>
</dialog>

<script src="assets/app.js"></script>
</body>
</html>
