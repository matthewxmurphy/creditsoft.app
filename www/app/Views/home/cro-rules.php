<?= $this->extend('layout/storefront') ?>

<?= $this->section('content') ?>

<style>
.alert{background:#fef3c7;border:1px solid #fcd34d;border-radius:12px;padding:16px;margin-bottom:24px}
.alert-title{font-weight:600;color:#92400e;margin-bottom:4px}
.alert p{font-size:14px;color:#92400e}
.alpha-index{position:sticky;top:100px;height:fit-content;background:white;padding:16px 12px;border-radius:12px;min-width:50px}
.alpha-index a{display:block;width:36px;height:28px;line-height:28px;text-align:center;background:var(--light);border-radius:4px;font-size:13px;font-weight:600;color:var(--dark);text-decoration:none;margin-bottom:2px}
.alpha-index a:hover{background:var(--primary);color:white}
.state-section{margin-bottom:40px}
.state-letter{font-size:28px;font-weight:700;color:var(--primary);margin-bottom:16px;padding-bottom:8px;border-bottom:2px solid var(--border)}
.state-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.state-card{background:white;padding:20px;border-radius:12px;border-left:4px solid var(--primary)}
.state-card.licensed{border-left-color:#ef4444}
.state-card.strict{border-left-color:#7c3aed}
.state-card h4{font-size:18px;margin-bottom:8px}
.state-card .status{display:inline-block;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:12px}
.state-card .status.licensed{background:#fee2e2;color:#ef4444}
.state-card .status.strict{background:#ede9fe;color:#7c3aed}
.state-card .status.none{background:#d1fae5;color:#10b981}
.state-card ul{list-style:none;padding:0;margin:0}
.state-card li{font-size:13px;color:var(--gray);padding:4px 0;display:flex;justify-content:space-between}
.state-card li span{color:var(--dark);font-weight:500}
.main-content{flex:1}
.page-content{display:flex;gap:30px;max-width:1200px;margin:0 auto;padding:30px 20px}
@media(max-width:900px){.page-content{flex-direction:column}.alpha-index{position:static;display:flex;flex-wrap:wrap;gap:8px;width:fit-content}}
</style>

<div class="page-content">
    <div class="main-content">
        <div class="alert">
            <div class="alert-title">⚠️ Important Compliance Notice</div>
            <p>Credit repair laws vary by state. You MUST be compliant in EACH state where you take clients. This page is for reference only - verify requirements with your attorney. Non-compliance can result in fines, lawsuits, and criminal charges.</p>
        </div>
        
        <div class="state-section">
            <div class="state-letter">A</div>
            <div class="state-grid">
                <div class="state-card"><h4>Alabama</h4><span class="status none">No State License</span><ul><li>Fees: <span>Permitted</span></li><li>Bond: <span>None required</span></li></ul></div>
                <div class="state-card"><h4>Alaska</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Arizona</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Arkansas</h4><span class="status none">No State License</span></div>
            </div>
        </div>
        
        <div class="state-section">
            <div class="state-letter">C</div>
            <div class="state-grid">
                <div class="state-card strict"><h4>California</h4><span class="status strict">STRICT</span><ul><li>Bond: <span>$100,000</span></li><li>Fees: <span>$10 setup, $25/mo</span></li></ul></div>
                <div class="state-card"><h4>Colorado</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Connecticut</h4><span class="status none">No State License</span></div>
            </div>
        </div>
        
        <div class="state-section">
            <div class="state-letter">D-F</div>
            <div class="state-grid">
                <div class="state-card"><h4>Delaware</h4><span class="status none">No State License</span></div>
                <div class="state-card strict"><h4>Florida</h4><span class="status strict">CRO License</span><ul><li>Bond: <span>$50,000</span></li></ul></div>
                <div class="state-card strict"><h4>Georgia</h4><span class="status strict">Strict</span><ul><li>Bond: <span>$25,000</span></li></ul></div>
            </div>
        </div>
        
        <div class="state-section">
            <div class="state-letter">H-I</div>
            <div class="state-grid">
                <div class="state-card"><h4>Hawaii</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Idaho</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Illinois</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Indiana</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Iowa</h4><span class="status none">No State License</span></div>
            </div>
        </div>
        
        <div class="state-section">
            <div class="state-letter">K-L</div>
            <div class="state-grid">
                <div class="state-card"><h4>Kansas</h4><span class="status none">No State License</span></div>
                <div class="state-card strict"><h4>Kentucky</h4><span class="status strict">Registration</span></div>
                <div class="state-card strict"><h4>Louisiana</h4><span class="status strict">Strict</span><ul><li>Bond: <span>$10,000</span></li></ul></div>
            </div>
        </div>
        
        <div class="state-section">
            <div class="state-letter">M</div>
            <div class="state-grid">
                <div class="state-card strict"><h4>Maine</h4><span class="status strict">Registration</span></div>
                <div class="state-card strict"><h4>Maryland</h4><span class="status strict">CRO License</span><ul><li>Bond: <span>$20,000</span></li></ul></div>
                <div class="state-card strict"><h4>Massachusetts</h4><span class="status strict">Strict</span></div>
                <div class="state-card"><h4>Michigan</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Minnesota</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Mississippi</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Missouri</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Montana</h4><span class="status none">No State License</span></div>
            </div>
        </div>
        
        <div class="state-section">
            <div class="state-letter">N</div>
            <div class="state-grid">
                <div class="state-card"><h4>Nebraska</h4><span class="status none">No State License</span></div>
                <div class="state-card strict"><h4>Nevada</h4><span class="status strict">CRO Registration</span><ul><li>Bond: <span>$15,000</span></li></ul></div>
                <div class="state-card strict"><h4>New Hampshire</h4><span class="status strict">Registration</span></div>
                <div class="state-card strict"><h4>New Jersey</h4><span class="status strict">Strict</span></div>
                <div class="state-card strict"><h4>New Mexico</h4><span class="status strict">Registration</span></div>
                <div class="state-card strict"><h4>New York</h4><span class="status strict">STRICT</span><ul><li>Bond: <span>$50,000</span></li></ul></div>
                <div class="state-card strict"><h4>North Carolina</h4><span class="status strict">Registration</span></div>
                <div class="state-card"><h4>North Dakota</h4><span class="status none">No State License</span></div>
            </div>
        </div>
        
        <div class="state-section">
            <div class="state-letter">O-P</div>
            <div class="state-grid">
                <div class="state-card"><h4>Ohio</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Oklahoma</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Oregon</h4><span class="status none">No State License</span></div>
                <div class="state-card strict"><h4>Pennsylvania</h4><span class="status strict">Strict</span></div>
            </div>
        </div>
        
        <div class="state-section">
            <div class="state-letter">R-S</div>
            <div class="state-grid">
                <div class="state-card strict"><h4>Rhode Island</h4><span class="status strict">Registration</span></div>
                <div class="state-card strict"><h4>South Carolina</h4><span class="status strict">Registration</span></div>
                <div class="state-card"><h4>South Dakota</h4><span class="status none">No State License</span></div>
            </div>
        </div>
        
        <div class="state-section">
            <div class="state-letter">T-W</div>
            <div class="state-grid">
                <div class="state-card strict"><h4>Tennessee</h4><span class="status strict">Registration</span><ul><li>Bond: <span>$10,000</span></li></ul></div>
                <div class="state-card strict"><h4>Texas</h4><span class="status strict">STRICT</span><ul><li>Bond: <span>$50,000</span></li></ul></div>
                <div class="state-card"><h4>Utah</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Vermont</h4><span class="status none">No State License</span></div>
                <div class="state-card strict"><h4>Virginia</h4><span class="status strict">Registration</span></div>
                <div class="state-card"><h4>Washington</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>West Virginia</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Wisconsin</h4><span class="status none">No State License</span></div>
                <div class="state-card"><h4>Wyoming</h4><span class="status none">No State License</span></div>
            </div>
        </div>
    </div>
    
    <div class="alpha-index">
        <a href="#Alabama">A</a>
        <a href="#California">C</a>
        <a href="#Delaware">D</a>
        <a href="#Florida">F</a>
        <a href="#Georgia">G</a>
        <a href="#Hawaii">H</a>
        <a href="#Idaho">I</a>
        <a href="#Kansas">K</a>
        <a href="#Louisiana">L</a>
        <a href="#Maine">M</a>
        <a href="#Nebraska">N</a>
        <a href="#Ohio">O</a>
        <a href="#Pennsylvania">P</a>
        <a href="#RhodeIsland">R</a>
        <a href="#SouthCarolina">S</a>
        <a href="#Tennessee">T</a>
        <a href="#Utah">U</a>
        <a href="#Vermont">V</a>
        <a href="#Washington">W</a>
    </div>
</div>

<?= $this->endSection() ?>
