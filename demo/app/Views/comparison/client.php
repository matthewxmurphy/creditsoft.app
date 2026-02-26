<div class="content-area">
    <h1>Select Client for Comparison</h1>
    <form method="post" action="/comparison/result">
        <select name="client_id">
            <option value="">Select a client</option>
            <?php if (isset($clients)): ?>
            <?php foreach ($clients as $c): ?>
            <option value="<?= $c['id'] ?>"><?= $c['first_name'] . ' ' . $c['last_name'] ?></option>
            <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <button type="submit">Compare</button>
    </form>
</div>
