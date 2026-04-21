<?php
$page_title = 'CRO Rules';
$page_description = 'Credit Repair Organization requirements by state';
$page_hero = true;
$hero_title = '50-State CRO Rules';
$hero_subtitle = 'State-by-state compliance requirements';
?>
<?php include 'header.php'; ?>

<style>
.alpha-index{position:sticky;top:100px;height:fit-content;background:white;padding:16px 12px;border-radius:12px;min-width:50px}
.alpha-index a{display:block;width:36px;height:28px;line-height:28px;text-align:center;background:var(--light);border-radius:4px;font-size:13px;font-weight:600;color:var(--dark);text-decoration:none;margin-bottom:2px}
.alpha-index a:hover{background:var(--primary);color:white}
.page-content{display:flex;gap:30px;max-width:1200px;margin:0 auto;padding:40px 24px}
@media(max-width:900px){.page-content{flex-direction:column}.alpha-index{position:static;display:flex;flex-wrap:wrap;gap:8px;width:fit-content}}
</style>

<div class="page-content">
    <div class="cro-content">
        <div class="alert alert-warning">
            <strong>⚠️ Important:</strong> Credit repair laws vary by state. Verify requirements with your attorney.
        </div>
        
        <h2 class="mb-3">High-Stakes States</h2>
        <table class="table mb-4">
            <thead><tr><th>State</th><th>Status</th><th>Bond</th><th>Required</th></tr></thead>
            <tbody>
            <tr><td>California</td><td><span class="badge badge-danger">High-Stakes</span></td><td>$100,000</td><td>Yes</td></tr>
            <tr><td>Texas</td><td><span class="badge badge-warning">Regulated</span></td><td>$100,000</td><td>Yes</td></tr>
            <tr><td>Nevada</td><td><span class="badge badge-danger">High-Stakes</span></td><td>$100,000</td><td>Yes</td></tr>
            <tr><td>Florida</td><td><span class="badge badge-warning">Regulated</span></td><td>$10,000</td><td>Yes</td></tr>
            <tr><td>Georgia</td><td><span class="badge badge-danger">Prohibited</span></td><td>N/A</td><td>No</td></tr>
            <tr><td>New York</td><td><span class="badge badge-warning">Strict</span></td><td>$0</td><td>No</td></tr>
            <tr><td>Virginia</td><td><span class="badge badge-warning">Regulated</span></td><td>$5,000</td><td>Yes</td></tr>
            </tbody>
        </table>
        
        <h2 class="mb-3">Other States</h2>
        <table class="table">
            <thead><tr><th>State</th><th>Status</th><th>Bond</th><th>Required</th></tr></thead>
            <tbody>
            <tr><td>Alabama</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Alaska</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Arizona</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Arkansas</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Colorado</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Connecticut</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Delaware</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Hawaii</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Idaho</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Illinois</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Indiana</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Iowa</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Kansas</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Kentucky</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Louisiana</td><td><span class="badge badge-warning">Regulated</span></td><td>$10,000</td><td>Yes</td></tr>
            <tr><td>Maine</td><td><span class="badge badge-warning">Regulated</span></td><td>None</td><td>Yes</td></tr>
            <tr><td>Maryland</td><td><span class="badge badge-warning">Regulated</span></td><td>$20,000</td><td>Yes</td></tr>
            <tr><td>Massachusetts</td><td><span class="badge badge-warning">Regulated</span></td><td>None</td><td>Yes</td></tr>
            <tr><td>Michigan</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Minnesota</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Mississippi</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Missouri</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Montana</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Nebraska</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>New Hampshire</td><td><span class="badge badge-warning">Regulated</span></td><td>None</td><td>Yes</td></tr>
            <tr><td>New Jersey</td><td><span class="badge badge-warning">Strict</span></td><td>None</td><td>Yes</td></tr>
            <tr><td>New Mexico</td><td><span class="badge badge-warning">Regulated</span></td><td>None</td><td>Yes</td></tr>
            <tr><td>North Carolina</td><td><span class="badge badge-warning">Regulated</span></td><td>None</td><td>Yes</td></tr>
            <tr><td>North Dakota</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Ohio</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Oklahoma</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Oregon</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Pennsylvania</td><td><span class="badge badge-warning">Strict</span></td><td>None</td><td>Yes</td></tr>
            <tr><td>Rhode Island</td><td><span class="badge badge-warning">Regulated</span></td><td>None</td><td>Yes</td></tr>
            <tr><td>South Carolina</td><td><span class="badge badge-warning">Regulated</span></td><td>None</td><td>Yes</td></tr>
            <tr><td>South Dakota</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Tennessee</td><td><span class="badge badge-warning">Regulated</span></td><td>$10,000</td><td>Yes</td></tr>
            <tr><td>Utah</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Vermont</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Washington</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>West Virginia</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Wisconsin</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            <tr><td>Wyoming</td><td><span class="badge badge-neutral">Open</span></td><td>None</td><td>No</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
