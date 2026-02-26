<div class="content-area">
    <h1><?= isset($client) ? 'Edit Client' : 'Add Client' ?></h1>
    <form method="post" action="/clients/save">
        <input type="text" name="first_name" placeholder="First Name" required>
        <input type="text" name="last_name" placeholder="Last Name" required>
        <input type="email" name="email" placeholder="Email">
        <button type="submit">Save</button>
    </form>
</div>
