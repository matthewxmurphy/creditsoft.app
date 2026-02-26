<div class="content-area">
    <h1>Clients</h1>
    <p>Welcome to the clients management page.</p>
    <?php if (isset($clients) && count($clients) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clients as $client): ?>
            <tr>
                <td><?= $client['id'] ?></td>
                <td><?= $client['first_name'] . ' ' . $client['last_name'] ?></td>
                <td><?= $client['email'] ?? 'N/A' ?></td>
                <td><a href="/clients/<?= $client['id'] ?>">View</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>No clients found. <a href="/clients/add">Add your first client</a></p>
    <?php endif; ?>
</div>
