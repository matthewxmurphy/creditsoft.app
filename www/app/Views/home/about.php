<?= $this->extend('layout/storefront') ?>

<?= $this->section('content') ?>

<div class="container" style="padding: 60px 20px;">
    <div style="background: white; padding: 40px; border-radius: 16px; margin-bottom: 24px;">
        <h2 style="font-size: 28px; margin-bottom: 16px;">Our Story</h2>
        <p style="color: var(--gray); margin-bottom: 16px;">CreditSoft was born from frustration. We watched credit repair professionals struggle with software that prioritized bells and whistles over what actually matters: getting negative items removed.</p>
        <p style="color: var(--gray); margin-bottom: 16px;">Most "AI-powered" credit repair software just generates templated letters with different words. Real results come from understanding the underlying data - Metro2 compliance, error codes, and proper dispute workflows.</p>
        <p style="color: var(--gray);">We built CreditSoft to be different. Software that actually understands credit repair.</p>
    </div>
    
    <div style="background: white; padding: 40px; border-radius: 16px; margin-bottom: 24px;">
        <h2 style="font-size: 28px; margin-bottom: 16px;">Why Metro2 Matters</h2>
        <p style="color: var(--gray); margin-bottom: 16px;">Metro2 is the data reporting standard used by all major credit bureaus. When you understand Metro2, you can identify errors that others miss:</p>
        <ul style="color: var(--gray); padding-left: 24px;">
            <li style="margin-bottom: 8px;">Account number mismatches</li>
            <li style="margin-bottom: 8px;">Late payments that don't belong to you</li>
            <li style="margin-bottom: 8px;">Fake or zombie debts</li>
            <li style="margin-bottom: 8px;">Future dates (clear violation)</li>
            <li>SSN mismatches (identity theft indicator)</li>
        </ul>
    </div>
    
    <div style="background: white; padding: 40px; border-radius: 16px; text-align: center;">
        <h2 style="font-size: 28px; margin-bottom: 24px;">Meet the Founder</h2>
        <img src="/assets/images/Matthew.jpg" alt="Matthew Murphy" style="width:150px;height:150px;border-radius:50%;object-fit:cover;margin-bottom:12px;">
        <div style="font-weight: 600; font-size: 18px;">Matthew Murphy</div>
        <div style="color: var(--gray);">Founder & Developer</div>
        <p style="margin-top:24px;color:var(--gray);">CreditSoft is independently owned and operated. We answer to our users, not venture capital.</p>
    </div>
</div>

<?= $this->endSection() ?>
