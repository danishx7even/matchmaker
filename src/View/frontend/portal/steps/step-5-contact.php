<?php
/**
 * View: Member Portal – Matches Step 5 (Mutual Match Celebration & Contact Details Reveal)
 *
 * Available variables:
 *   @var array<string, mixed> $active_match
 *
 * @package Matchmaker\View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- STATE 5: MUTUAL MATCH — REVEALED CONTACT INFO (STEP 5) -->
<div id="step-5" class="view-state <?php echo (($default_step ?? 1) === 5) ? 'active' : ''; ?>">
    <div class="centered-state-wrapper" style="max-width: 620px;">
        <div class="status-avatar-bubble orange" style="background: linear-gradient(135deg, #CC723F 0%, #E89158 100%); width: 76px; height: 76px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 10px 25px rgba(204, 114, 63, 0.35); margin: 0 auto 20px;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m2 22 5.8-10.7 8.12 8.12L2 22Z"/>
                <path d="M17.13 3.29a2 2 0 0 1 2.83 0l.75.75a2 2 0 0 1 0 2.83l-8.12 8.12-3.58-3.58 8.12-8.12Z"/>
                <path d="m15 5 2 2"/>
                <circle cx="18" cy="14" r="1.2" fill="#ffffff"/>
                <circle cx="12" cy="5" r="1.2" fill="#ffffff"/>
                <circle cx="21" cy="9" r="1.2" fill="#ffffff"/>
                <path d="M10 2 9 3.5"/>
                <path d="M15 1.5 16 3"/>
                <path d="M22 6 20.5 7"/>
            </svg>
        </div>

        <h1 class="state-main-title font-cormorant" style="color: #144D34;"><?php esc_html_e("It's a Match!", 'matchmaker'); ?></h1>
        <p class="state-main-desc"><?php esc_html_e('You both accepted the match. Now you can connect directly outside Arab Zawaj.', 'matchmaker'); ?></p>

        <div class="matched-profile-summary-box">
            <img src="<?php echo esc_url($active_match['photo'] ?: get_avatar_url(0, ['size' => 150])); ?>" alt="">
            <div>
                <h2 class="font-cormorant"><?php echo esc_html($active_match['name']); ?></h2>
                <div class="meta-sub"><?php echo esc_html($active_match['age']); ?> • <?php echo esc_html($active_match['location']); ?></div>
                <span class="premium-tag-gold">★ <?php esc_html_e('Mutual Match Accepted', 'matchmaker'); ?></span>
            </div>
        </div>

        <div style="text-align: left; font-family: 'Cormorant SC', serif; font-size: 18px; text-transform: uppercase; font-weight: 700; margin-bottom: 14px;">
            <?php esc_html_e('Direct Contact Information', 'matchmaker'); ?>
        </div>

        <div class="contacts-grid">
            <div class="contact-entry-field">
                <div class="contact-icon-bubble">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                </div>
                <div class="contact-data-text">
                    <div class="label"><?php esc_html_e('Phone', 'matchmaker'); ?></div>
                    <div class="val"><?php echo esc_html($active_match['phone_number'] ?: '—'); ?></div>
                </div>
            </div>

            <div class="contact-entry-field">
                <div class="contact-icon-bubble">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"></circle><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"></path></svg>
                </div>
                <div class="contact-data-text">
                    <div class="label"><?php esc_html_e('Email', 'matchmaker'); ?></div>
                    <div class="val"><?php echo esc_html($active_match['email'] ?: '—'); ?></div>
                </div>
            </div>

            <div class="contact-entry-field full">
                <div class="contact-icon-bubble">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path></svg>
                </div>
                <div class="contact-data-text">
                    <div class="label"><?php esc_html_e('Social / Handle', 'matchmaker'); ?></div>
                    <div class="val"><?php echo esc_html($active_match['social_links'] ?: '—'); ?></div>
                </div>
            </div>
        </div>

        <div class="notice-gray-box">
            <strong>ⓘ <?php esc_html_e('Important Note:', 'matchmaker'); ?></strong> <?php esc_html_e('Our platform does not provide internal chat messaging. You can now contact each other directly using the information above. Please communicate respectfully.', 'matchmaker'); ?>
        </div>

        <button type="button" class="btn btn-primary" style="width: 100%;" data-mm-action="switch-tab" data-tab="profile">
            <?php esc_html_e('Back to Profile Dashboard →', 'matchmaker'); ?>
        </button>
    </div>
</div>
