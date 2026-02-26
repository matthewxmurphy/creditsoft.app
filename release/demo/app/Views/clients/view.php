<div class="content-area">
    <h1>View Client</h1>
    <?php if (isset($client)): ?>
    <p>Client: <?= $client['first_name'] . ' ' . $client['last_name'] ?></p>
    <p>Email: <?= $client['email'] ?? 'N/A' ?></p>
    <?php else: ?>
    <p>Client not found.</p>
    <?php endif; ?>
</div>
