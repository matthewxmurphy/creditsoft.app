<?php
$page_title = 'CRO Rules';
$page_description = 'Credit Repair Organization requirements by state.';
$page_hero = true;
$hero_title = '50-State CRO Rules';
$hero_subtitle = 'State-by-state compliance guide';
?>
<?php include 'header.php'; ?>

<style>
.alert{background:#fef3c7;border:1px solid #fcd34d;border-radius:12px;padding:16px;margin-bottom:24px}
.alert-title{font-weight:600;color:#92400e}
.alpha-index{position:sticky;top:100px;background:white;padding:12px;border-radius:12px}
.alpha-index a{display:block;padding:4px 8px;color:var(--dark);font-weight:600}
.alpha-index a:hover{background:var(--primary);color:white;border-radius:4px}
.state-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.state-card{background:white;padding:20px;border-radius:12px;border-left:4px solid var(--primary)}
.state-card.strict{border-left-color:#7c3aed}
.state-card h4{font-size:18px;margin-bottom:8px}
.status{display:inline-block;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:12px}
.status.none{background:#d1fae5;color:#10b981}
.status.strict{background:#ede9fe;color:#7c3aed}
.state-card ul{list-style:none;padding:0;margin:0;font-size:13px;color:var(--gray)}
.state-card li{padding:4px 0}
.page-content{display:flex;gap:30px;max-width:1200px;margin:0 auto;padding:30px 20px}
@media(max-width:900px){.page-content{flex-direction:column}.alpha-index{position:static}}
</style>

<div class="page-content">
    <div style="flex:1">
        <div class="alert">
            <div class="alert-title">⚠️ Important Compliance Notice</div>
            <p style="font-size:14px;">Credit repair laws vary by state. Verify requirements with your attorney.</p>
        </div>
        
        <h3 style="margin-bottom:16px;">A</h3>
        <div class="state-grid">
            <div class="state-card"><h4>Alabama</h4><span class="status none">No License</span><ul><li>Fees: Permitted</li><li>Bond: None</li></ul></div>
            <div class="state-card"><h4>California</h4><span class="status strict">STRICT</span><ul><li>Bond: $100,000</li><li>Fees: $10/mo max</li></ul></div>
        </div>
        
        <h3 style="margin:24px 0 16px;">F-G</h3>
        <div class="state-grid">
            <div class="state-card strict"><h4>Florida</h4><span class="status strict">License Required</span><ul><li>Bond: $50,000</li></ul></div>
            <div class="state-card strict"><h4>Georgia</h4><span class="status strict">Strict</span><ul><li>Bond: $25,000</li></ul></div>
        </div>
        
        <h3 style="margin:24px 0 16px;">N</h3>
        <div class="state-grid">
            <div class="state-card strict"><h4>New York</h4><span class="status strict">STRICT</span><ul><li>Bond: $50,000</li></ul></div>
        </div>
        
        <h3 style="margin:24px 0 16px;">T</h3>
        <div class="state-grid">
            <div class="state-card strict"><h4>Texas</h4><span class="status strict">STRICT</span><ul><li>Bond: $50,000</li></ul></div>
        </div>
    </div>
    
    <div class="alpha-index">
        <a href="#Alabama">A</a>
        <a href="#California">C</a>
        <a href="#Florida">F</a>
        <a href="#Georgia">G</a>
        <a href="#NewYork">N</a>
        <a href="#Texas">T</a>
    </div>
</div>

<?php include 'footer.php'; ?>
