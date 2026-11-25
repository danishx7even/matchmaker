<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Matchmaker\Core\PMProSync;
use Matchmaker\Frontend\FeaturedProfileController;
use Matchmaker\Repository\MatchRepository;
use Matchmaker\Service\NotificationService;

final class FeaturedProfileTest extends TestCase
{
    private FeaturedProfileController $controller;

    protected function setUp(): void
    {
        $GLOBALS['__mm_options']    = [];
        $GLOBALS['__mm_postmeta']   = [];
        $GLOBALS['__mm_posts']      = [];
        $GLOBALS['__mm_usermeta']   = [];
        $GLOBALS['__mm_users']      = [];
        $GLOBALS['__mm_pool_users'] = [];
        $GLOBALS['__mm_matches']    = [];
        $GLOBALS['__mm_sent_mails'] = [];
        $GLOBALS['wpdb']            = new \Fakewpdb();
        $_POST                      = [];
        $_GET                       = [];

        $this->controller = FeaturedProfileController::instance();
    }

    public function test_featured_profile_helpers_in_pmpro_sync(): void
    {
        $pmpro = PMProSync::instance();

        // 1. Configured option takes precedence
        update_option('mm_featured_profile_level_id', 42);
        $this->assertSame(42, $pmpro->get_featured_profile_level_id());

        // 2. Auto-detection fallback when option is 0
        update_option('mm_featured_profile_level_id', 0);
        // Default without levels returns 0
        $this->assertSame(0, $pmpro->get_featured_profile_level_id());

        // 3. User entitlement check: returns false for invalid ID
        $this->assertFalse($pmpro->has_active_featured_profile_service(0));
        $this->assertFalse($pmpro->has_active_featured_profile_service(-5));

        // 4. Returns true when user meta flag is active
        update_user_meta(101, 'mm_has_featured_profile', 1);
        $this->assertTrue($pmpro->has_active_featured_profile_service(101));
    }

    public function test_get_connected_user_id_resolves_from_acf_and_meta(): void
    {
        $post_id = 550;

        // Returns 0 for empty or invalid post ID
        $this->assertSame(0, $this->controller->get_connected_user_id(0));

        // Returns user ID when stored in meta 'connected-member'
        update_post_meta($post_id, 'connected-member', 205);
        $this->assertSame(205, $this->controller->get_connected_user_id($post_id));

        // Returns user ID when stored with underscore 'connected_member'
        delete_post_meta($post_id, 'connected-member');
        update_post_meta($post_id, 'connected_member', 308);
        $this->assertSame(308, $this->controller->get_connected_user_id($post_id));
    }

    public function test_sync_featured_post_creates_and_updates_post_and_acf_meta(): void
    {
        $user_id = 450;
        $user = new \WP_User((object) [
            'ID'           => $user_id,
            'display_name' => 'Layla Hassan',
            'user_email'   => 'layla@example.com',
        ]);
        $GLOBALS['__mm_users'][$user_id] = $user;

        // 1. Initial creation: Anonymous & Published with show_featured_profile = false
        $post_id = $this->controller->sync_featured_post($user_id, true, 'anonymous');
        $this->assertIsInt($post_id);
        $this->assertGreaterThan(0, $post_id);

        $post = $GLOBALS['__mm_posts'][$post_id] ?? null;
        $this->assertNotNull($post);
        $this->assertSame('publish', $post->post_status);
        $this->assertSame('featured-profile', $post->post_type);
        $this->assertStringContainsString('Candidate #450', $post->post_title);

        // Check meta & ACF defaults
        $this->assertSame($user_id, (int) get_post_meta($post_id, 'connected-member', true));
        $this->assertSame('anonymous', get_post_meta($post_id, 'featured_profile_mode', true));
        $this->assertSame('false', get_post_meta($post_id, 'show_featured_profile', true));
        $this->assertFalse($this->controller->is_profile_approved($post_id));
        $this->assertSame('anonymous', $this->controller->get_profile_mode($post_id, $user_id));
        $this->assertSame(1, (int) get_user_meta($user_id, 'mm_has_featured_profile', true));
        $this->assertSame('anonymous', get_user_meta($user_id, 'mm_featured_visibility', true));

        // 2. Update to Public Mode: post_status remains publish, mode becomes public, show_featured_profile preserved as false
        $updated_post_id = $this->controller->sync_featured_post($user_id, true, 'public');
        $this->assertSame($post_id, $updated_post_id, 'Should update same post ID');

        $updated_post = $GLOBALS['__mm_posts'][$post_id];
        $this->assertSame('publish', $updated_post->post_status);
        $this->assertStringContainsString('Layla Hassan', $updated_post->post_title);
        $this->assertSame('public', get_post_meta($post_id, 'featured_profile_mode', true));
        $this->assertSame('public', $this->controller->get_profile_mode($post_id, $user_id));
        $this->assertSame('false', get_post_meta($post_id, 'show_featured_profile', true));

        // 3. Admin approves profile
        update_post_meta($post_id, 'show_featured_profile', 'true');
        $this->assertTrue($this->controller->is_profile_approved($post_id));
    }

    public function test_handle_service_revoked_unpublishes_post(): void
    {
        $user_id = 451;
        $user = new \WP_User((object) ['ID' => $user_id, 'display_name' => 'Tariq M.']);
        $GLOBALS['__mm_users'][$user_id] = $user;

        $post_id = $this->controller->sync_featured_post($user_id, true, 'public');
        $this->assertSame('publish', $GLOBALS['__mm_posts'][$post_id]->post_status);

        // Revoke service
        $this->controller->handle_service_revoked($user_id);

        $this->assertSame('draft', $GLOBALS['__mm_posts'][$post_id]->post_status);
        $this->assertSame(0, (int) get_user_meta($user_id, 'mm_featured_enabled', true));
    }

    public function test_handle_service_revoked_skips_when_never_enabled(): void
    {
        $user_id = 999;
        // User has no meta for featured enabled or post_id
        $this->controller->handle_service_revoked($user_id);
        $this->assertSame(0, (int) get_user_meta($user_id, 'mm_featured_enabled', true));
    }

    public function test_elementor_query_filter_sets_post_type_and_publish_status(): void
    {
        $query = new \WP_Query();
        $this->controller->filter_elementor_featured_profiles($query);

        $this->assertSame('featured-profile', $query->get('post_type'));
        $this->assertSame('publish', $query->get('post_status'));

        $meta_query = $query->get('meta_query');
        $this->assertIsArray($meta_query);
        $this->assertNotEmpty($meta_query);
        $clause = $meta_query[0] ?? [];
        $this->assertSame('OR', $clause['relation'] ?? '');
        $this->assertSame('show_featured_profile', $clause[0]['key'] ?? '');
        $this->assertSame('true', $clause[0]['value'] ?? '');
    }

    public function test_render_featured_profile_details_anonymous_masks_name_and_photo(): void
    {
        $user_id = 452;
        $user = new \WP_User((object) [
            'ID'           => $user_id,
            'display_name' => 'Secret Real Name',
            'user_email'   => 'secret@example.com',
        ]);
        $GLOBALS['__mm_users'][$user_id] = $user;

        MatchRepository::instance()->upsert_pool([
            'user_id'    => $user_id,
            'birth_date' => '1995-05-15',
            'country'    => 'United Kingdom',
            'city'       => 'London',
            'origin'     => 'Egyptian',
            'job'        => 'Architect',
            'religion'   => 'Muslim Sunni',
            'user_type'  => 'monthly',
        ]);
        update_user_meta($user_id, 'user_photo1', 'https://example.com/real_photo.jpg');

        $post_id = $this->controller->sync_featured_post($user_id, true, 'anonymous');

        $html = $this->controller->render_featured_profile_details(['post_id' => $post_id]);

        $this->assertStringContainsString('Candidate #452', $html);
        $this->assertStringNotContainsString('Secret Real Name', $html);
        $this->assertStringNotContainsString('https://example.com/real_photo.jpg', $html);
        $this->assertStringContainsString('mm-anonymous-avatar', $html);
        $this->assertStringContainsString('London, United Kingdom', $html);
        $this->assertStringContainsString('Architect', $html);
    }

    public function test_render_featured_profile_details_public_shows_name_and_photo(): void
    {
        $user_id = 453;
        $user = new \WP_User((object) [
            'ID'           => $user_id,
            'display_name' => 'Kareem Public',
            'user_email'   => 'kareem@example.com',
        ]);
        $GLOBALS['__mm_users'][$user_id] = $user;

        MatchRepository::instance()->upsert_pool([
            'user_id'             => $user_id,
            'birth_date'          => '1990-01-01',
            'gender'              => 'male',
            'country'             => 'Canada',
            'city'                => 'Toronto',
            'origin'              => 'Lebanese',
            'religion'            => 'Muslim Sunni',
            'modesty'             => 'Practicing',
            'height_cm'           => 180,
            'languages'           => 'Arabic, English',
            'job'                 => 'Software Engineer',
            'smoking'             => 'Non-smoker',
            'drinking'            => 'Never',
            'user_type'           => 'monthly',
            'pref_age_min'        => 22,
            'pref_age_max'        => 30,
            'pref_country'        => 'Lebanon',
        ]);
        update_user_meta($user_id, 'user_photo1', 'https://example.com/kareem1.jpg');
        update_user_meta($user_id, 'user_photo2', 'https://example.com/kareem2.jpg');
        update_user_meta($user_id, 'user_citizenship', 'Canadian');
        update_user_meta($user_id, 'user_marital_status', 'Never Married');
        update_user_meta($user_id, 'user_children', 'No');
        update_user_meta($user_id, 'user_education', 'Master Degree');
        update_user_meta($user_id, 'user_income', '$100k - $150k');
        update_user_meta($user_id, 'user_about_me', 'I am passionate about engineering and family.');
        update_user_meta($user_id, 'phone_number', '+15551234567');

        $post_id = $this->controller->sync_featured_post($user_id, true, 'public');

        $html = $this->controller->render_featured_profile_details(['post_id' => $post_id]);

        // 1. Identity & Gallery
        $this->assertStringContainsString('Kareem Public', $html);
        $this->assertStringContainsString('https://example.com/kareem1.jpg', $html);
        $this->assertStringContainsString('https://example.com/kareem2.jpg', $html);
        $this->assertStringContainsString('data-mm-lightbox="featured-gallery-' . $post_id . '"', $html);
        $this->assertStringContainsString('data-mm-photos=', $html);

        // 2. Candidate self-profile details
        $this->assertStringContainsString('Male', $html);
        $this->assertStringContainsString('Toronto, Canada', $html);
        $this->assertStringContainsString('Lebanese', $html);
        $this->assertStringContainsString('Canadian', $html);
        $this->assertStringContainsString('Muslim Sunni / Practicing', $html);
        $this->assertStringContainsString('Arabic, English', $html);
        $this->assertStringContainsString('Software Engineer', $html);
        $this->assertStringContainsString('Non-smoker / Never', $html);
        $this->assertStringContainsString('Never Married', $html);
        $this->assertStringContainsString('Master Degree', $html);
        $this->assertStringContainsString('$100k - $150k', $html);
        $this->assertStringContainsString('I am passionate about engineering and family.', $html);

        // 3. Strictly exclude private contact details and partner preferences
        $this->assertStringNotContainsString('kareem@example.com', $html);
        $this->assertStringNotContainsString('+15551234567', $html);
        $this->assertStringNotContainsString('Preferred Maximum Age', $html);
        $this->assertStringNotContainsString('Lebanon', $html); // Partner preference country
    }

    public function test_render_featured_profile_field_shortcode(): void
    {
        $user_id = 454;
        $user = new \WP_User((object) [
            'ID'           => $user_id,
            'display_name' => 'Yasmin B.',
        ]);
        $GLOBALS['__mm_users'][$user_id] = $user;

        MatchRepository::instance()->upsert_pool([
            'user_id'    => $user_id,
            'birth_date' => '1998-03-20',
            'country'    => 'United States',
            'city'       => 'Chicago',
            'job'        => 'Data Scientist',
            'user_type'  => 'monthly',
        ]);

        $post_id = $this->controller->sync_featured_post($user_id, true, 'anonymous');

        $this->assertSame('Candidate #454', $this->controller->render_featured_profile_field(['key' => 'name', 'post_id' => $post_id]));
        $this->assertSame('Data Scientist', $this->controller->render_featured_profile_field(['key' => 'job', 'post_id' => $post_id]));
        $this->assertSame('United States', $this->controller->render_featured_profile_field(['key' => 'country', 'post_id' => $post_id]));
    }

    public function test_render_featured_profile_apply_button_states(): void
    {
        $featured_user_id = 455;
        $post_id          = 2500;
        update_post_meta($post_id, 'connected-member', $featured_user_id);

        // 1. Guest -> Login link
        wp_set_current_user(0);
        $btn_guest = $this->controller->render_featured_profile_apply_button(['post_id' => $post_id]);
        $this->assertStringContainsString('Log in to Apply for Match', $btn_guest);

        // 2. Member viewing own profile -> Disabled "Your Featured Profile"
        wp_set_current_user($featured_user_id);
        $btn_self = $this->controller->render_featured_profile_apply_button(['post_id' => $post_id]);
        $this->assertStringContainsString('Your Featured Profile', $btn_self);

        // 3. User with no base membership -> Membership Required
        $other_user_id = 456;
        $GLOBALS['__mm_users'][$other_user_id] = new \WP_User((object) ['ID' => $other_user_id]);
        update_user_meta($other_user_id, 'user_type', 'none');
        wp_set_current_user($other_user_id);
        $btn_no_membership = $this->controller->render_featured_profile_apply_button(['post_id' => $post_id]);
        $this->assertStringContainsString('Membership Required to Apply', $btn_no_membership);

        // 4. Eligible member -> Interactive Apply button
        update_user_meta($other_user_id, 'user_type', 'monthly');
        $btn_eligible = $this->controller->render_featured_profile_apply_button(['post_id' => $post_id]);
        $this->assertStringContainsString('Apply for a Match', $btn_eligible);
        $this->assertStringContainsString('mm-btn-apply-match', $btn_eligible);

        // 5. Application Pending Review
        $match_id = MatchRepository::instance()->create_match($other_user_id, $featured_user_id, $other_user_id, 'pending_review', 'featured', 0);
        $this->assertNotFalse($match_id);
        $btn_pending = $this->controller->render_featured_profile_apply_button(['post_id' => $post_id]);
        $this->assertStringContainsString('Application Pending Review', $btn_pending);

        // 6. Application Admin Rejected -> Shows "Application Rejected" instead of "Apply for a Match"
        global $wpdb;
        $matches_table = $wpdb->prefix . 'matches';
        $wpdb->update($matches_table, ['status' => 'admin_rejected'], ['id' => $match_id]);
        $btn_rejected = $this->controller->render_featured_profile_apply_button(['post_id' => $post_id]);
        $this->assertStringContainsString('Application Rejected', $btn_rejected);
        $this->assertStringNotContainsString('Apply for a Match', $btn_rejected);
    }

    public function test_ajax_apply_featured_match_success_and_admin_notification(): void
    {
        $applicant_id = 460;
        $featured_id  = 461;

        $GLOBALS['__mm_users'][$applicant_id] = new \WP_User((object) ['ID' => $applicant_id, 'display_name' => 'Applicant Adam']);
        $GLOBALS['__mm_users'][$featured_id]  = new \WP_User((object) ['ID' => $featured_id, 'display_name' => 'Featured Fatima']);

        update_user_meta($applicant_id, 'user_type', 'monthly');
        update_user_meta($featured_id, 'user_type', 'monthly');

        MatchRepository::instance()->upsert_pool(['user_id' => $applicant_id, 'user_type' => 'monthly', 'gender' => 'Male']);
        MatchRepository::instance()->upsert_pool(['user_id' => $featured_id, 'user_type' => 'monthly', 'gender' => 'Female']);

        wp_set_current_user($applicant_id);

        $_POST = [
            'nonce'          => wp_create_nonce('mm_featured_apply_nonce'),
            'target_user_id' => $featured_id,
        ];

        ob_start();
        $caught = false;
        try {
            $this->controller->handle_apply_featured_match();
        } catch (\RuntimeException $e) {
            $caught = ($e->getMessage() === 'wp_send_json_success');
        }
        $out = ob_get_clean();

        $this->assertTrue($caught, 'Should send JSON success on valid match application');
        $this->assertStringContainsString('application has been received', $out);

        // Check wp_matches table
        $matches = MatchRepository::instance()->get_all_matches();
        $this->assertCount(1, $matches);
        $match = $matches[0];
        $this->assertSame('pending_review', $match['status']);
        $this->assertSame('featured', $match['match_source']);
        $this->assertSame($applicant_id, (int) $match['initiator_user_id']);

        // Check admin email notification
        $this->assertNotEmpty($GLOBALS['__mm_sent_mails']);
        $last_email = end($GLOBALS['__mm_sent_mails']);
        $this->assertStringContainsString('New Featured Profile Match Application', $last_email['subject']);
        $this->assertStringContainsString('Applicant Adam', $last_email['message']);
    }

    public function test_ajax_toggle_featured_profile_in_portal(): void
    {
        $user_id = 470;
        $GLOBALS['__mm_users'][$user_id] = new \WP_User((object) ['ID' => $user_id, 'display_name' => 'Portal Member']);
        wp_set_current_user($user_id);

        // User does not have service -> error
        $_POST = [
            'nonce'           => wp_create_nonce('mm_featured_toggle_nonce'),
            'enabled'         => '1',
            'visibility_mode' => 'public',
        ];

        ob_start();
        $caught_err = false;
        try {
            $this->controller->handle_toggle_featured_profile();
        } catch (\RuntimeException $e) {
            $caught_err = ($e->getMessage() === 'wp_send_json_error');
        }
        $out_err = ob_get_clean();
        $this->assertTrue($caught_err);
        $this->assertStringContainsString('not purchased the Featured Profile service', $out_err);

        // Grant service
        update_user_meta($user_id, 'mm_has_featured_profile', 1);

        ob_start();
        $caught_succ = false;
        try {
            $this->controller->handle_toggle_featured_profile();
        } catch (\RuntimeException $e) {
            $caught_succ = ($e->getMessage() === 'wp_send_json_success');
        }
        $out_succ = ob_get_clean();

        $this->assertTrue($caught_succ);
        $this->assertStringContainsString('Your featured profile settings have been updated.', $out_succ);
        $this->assertStringContainsString('"is_approved":false', $out_succ);
        $this->assertSame(1, (int) get_user_meta($user_id, 'mm_has_featured_profile', true));
        $this->assertSame('public', get_user_meta($user_id, 'mm_featured_visibility', true));
    }

    public function test_anonymous_title_resolution_and_custom_alias(): void
    {
        $user_id = 512;

        // Default when no custom alias set
        $this->assertSame('Candidate #512', $this->controller->get_anonymous_title($user_id));
        $this->assertSame('Candidate #512 (Featured Profile)', $this->controller->get_anonymous_title($user_id, true));

        // When member sets custom alias on their featured profile card
        update_user_meta($user_id, 'mm_featured_anonymous_name', 'Doctor in Manchester');
        $this->assertSame('Doctor in Manchester', $this->controller->get_anonymous_title($user_id));
        $this->assertSame('Doctor in Manchester (Featured Profile)', $this->controller->get_anonymous_title($user_id, true));
    }

    public function test_default_anonymous_image_setting(): void
    {
        // Default without option returns SVG silhouette
        delete_option('mm_featured_profile_default_image');
        $svg_html = $this->controller->get_anonymous_avatar_html(160);
        $this->assertStringContainsString('<svg', $svg_html);
        $this->assertStringNotContainsString('<img', $svg_html);

        // When admin specifies custom anonymous avatar in Settings
        update_option('mm_featured_profile_default_image', 'https://example.com/silhouette.png');
        $img_html = $this->controller->get_anonymous_avatar_html(160);
        $this->assertStringContainsString('<img', $img_html);
        $this->assertStringContainsString('https://example.com/silhouette.png', $img_html);
    }

    public function test_ajax_toggle_featured_profile_with_display_name(): void
    {
        $user_id = 620;
        $GLOBALS['__mm_users'][$user_id] = new \WP_User((object) ['ID' => $user_id, 'display_name' => 'Original Name']);
        wp_set_current_user($user_id);
        update_user_meta($user_id, 'mm_has_featured_profile', 1);

        $_POST = [
            'nonce'           => wp_create_nonce('mm_featured_toggle_nonce'),
            'enabled'         => '1',
            'visibility_mode' => 'anonymous',
            'display_name'    => 'Engineer in Dubai',
        ];

        ob_start();
        $caught_succ = false;
        try {
            $this->controller->handle_toggle_featured_profile();
        } catch (\RuntimeException $e) {
            $caught_succ = ($e->getMessage() === 'wp_send_json_success');
        }
        $out = ob_get_clean();

        $this->assertTrue($caught_succ);
        $this->assertSame('Engineer in Dubai', get_user_meta($user_id, 'mm_featured_anonymous_name', true));
        $post_id = (int) get_user_meta($user_id, '_mm_featured_post_id', true);
        $this->assertGreaterThan(0, $post_id);
        $post = get_post($post_id);
        $this->assertNotNull($post);
        $this->assertStringContainsString('Engineer in Dubai', $post->post_title);
    }

    public function test_featured_match_admin_email_custom_settings(): void
    {
        $applicant_id = 701;
        $featured_id  = 702;

        $GLOBALS['__mm_users'][$applicant_id] = new \WP_User((object) [
            'ID'           => $applicant_id,
            'display_name' => 'Kareem Applicant',
            'user_email'   => 'kareem@example.com',
        ]);
        $GLOBALS['__mm_users'][$featured_id] = new \WP_User((object) [
            'ID'           => $featured_id,
            'display_name' => 'Layla Candidate',
            'user_email'   => 'layla@example.com',
        ]);

        update_option('mm_email_admin_featured_match_recipient', 'manager1@example.com, manager2@example.com');
        update_option('mm_email_admin_featured_match_subject', 'Alert: {applicant_name} applied for {candidate_name} (#{match_id})');
        update_option('mm_email_admin_featured_match_template', '<p>Match #{match_id} with score {score}/6 from {applicant_email} to see {admin_match_url}</p>');

        $GLOBALS['__mm_sent_mails'] = [];

        $sent = NotificationService::instance()->send_admin_featured_match_notification(
            99,
            $applicant_id,
            $featured_id,
            5
        );

        $this->assertTrue($sent);
        $this->assertCount(2, $GLOBALS['__mm_sent_mails']);

        $to_addresses = array_column($GLOBALS['__mm_sent_mails'], 'to');
        $this->assertContains('manager1@example.com', $to_addresses);
        $this->assertContains('manager2@example.com', $to_addresses);

        $email = $GLOBALS['__mm_sent_mails'][0];
        $this->assertSame('Alert: Kareem Applicant applied for Layla Candidate (#99)', $email['subject']);
        $this->assertStringContainsString('Match #99 with score 5/6 from kareem@example.com', $email['message']);
        $this->assertStringContainsString('admin.php?page=matchmaking-matches&view_match=99', $email['message']);

        // Clean up
        delete_option('mm_email_admin_featured_match_recipient');
        delete_option('mm_email_admin_featured_match_subject');
        delete_option('mm_email_admin_featured_match_template');
    }

    public function test_featured_match_source_and_filter(): void
    {
        $u1 = 801;
        $u2 = 802;

        $GLOBALS['__mm_users'][$u1] = new \WP_User((object) ['ID' => $u1, 'display_name' => 'User One']);
        $GLOBALS['__mm_users'][$u2] = new \WP_User((object) ['ID' => $u2, 'display_name' => 'User Two']);

        $repo = MatchRepository::instance();
        $match_id = $repo->create_match($u1, $u2, $u1, 'pending_review', 'featured', 4);
        $this->assertNotFalse($match_id);

        $results = $repo->get_all_matches(['source' => 'featured']);
        $this->assertNotEmpty($results);

        $found = false;
        foreach ($results as $m) {
            if ((int) $m['id'] === (int) $match_id) {
                $found = true;
                $this->assertSame('featured', $m['match_source']);
                break;
            }
        }
        $this->assertTrue($found, 'Should find match with source=featured');
    }

    public function test_apply_button_multi_user_isolation_lifecycle(): void
    {
        $candidate_id = 901;
        $user_a_id    = 902;
        $user_b_id    = 903;
        $post_id      = 3500;

        $GLOBALS['__mm_users'][$candidate_id] = new \WP_User((object) ['ID' => $candidate_id, 'display_name' => 'Featured Layla']);
        $GLOBALS['__mm_users'][$user_a_id]    = new \WP_User((object) ['ID' => $user_a_id, 'display_name' => 'Applicant Adam']);
        $GLOBALS['__mm_users'][$user_b_id]    = new \WP_User((object) ['ID' => $user_b_id, 'display_name' => 'Applicant Bilal']);

        update_user_meta($candidate_id, 'user_type', 'monthly');
        update_user_meta($user_a_id, 'user_type', 'monthly');
        update_user_meta($user_b_id, 'user_type', 'free');

        MatchRepository::instance()->upsert_pool(['user_id' => $candidate_id, 'user_type' => 'monthly', 'gender' => 'Female']);
        MatchRepository::instance()->upsert_pool(['user_id' => $user_a_id, 'user_type' => 'monthly', 'gender' => 'Male']);
        MatchRepository::instance()->upsert_pool(['user_id' => $user_b_id, 'user_type' => 'free', 'gender' => 'Male']);

        update_post_meta($post_id, 'connected-member', $candidate_id);

        // 1. Featured candidate views own profile -> "Your Featured Profile"
        wp_set_current_user($candidate_id);
        $btn_candidate = $this->controller->render_featured_profile_apply_button(['post_id' => $post_id]);
        $this->assertStringContainsString('Your Featured Profile', $btn_candidate);

        // 2. User A views profile initially -> Eligible, sees "Apply for a Match"
        wp_set_current_user($user_a_id);
        $btn_user_a_initial = $this->controller->render_featured_profile_apply_button(['post_id' => $post_id]);
        $this->assertStringContainsString('Apply for a Match', $btn_user_a_initial);

        // 3. User A applies -> Match created
        $_POST = [
            'nonce'          => wp_create_nonce('mm_featured_apply_nonce'),
            'target_user_id' => $candidate_id,
        ];
        ob_start();
        try {
            $this->controller->handle_apply_featured_match();
        } catch (\RuntimeException $e) {
            // Caught wp_send_json_success
        }
        ob_end_clean();

        // 4. User A views profile now -> "Application Pending Review"
        $btn_user_a_pending = $this->controller->render_featured_profile_apply_button(['post_id' => $post_id]);
        $this->assertStringContainsString('Application Pending Review', $btn_user_a_pending);
        $this->assertStringNotContainsString('Apply for a Match', $btn_user_a_pending);

        // 5. Admin rejects User A's match
        global $wpdb;
        $matches_table = $wpdb->prefix . 'matches';
        $u1_a = min($user_a_id, $candidate_id);
        $u2_a = max($user_a_id, $candidate_id);
        $match_a = $wpdb->get_row("SELECT id FROM {$matches_table} WHERE user_one_id = {$u1_a} AND user_two_id = {$u2_a}", ARRAY_A);
        $this->assertNotEmpty($match_a);
        $wpdb->update($matches_table, ['status' => 'admin_rejected'], ['id' => $match_a['id']]);

        // 6. User A views profile after rejection -> "Application Rejected", CANNOT apply
        $btn_user_a_rejected = $this->controller->render_featured_profile_apply_button(['post_id' => $post_id]);
        $this->assertStringContainsString('Application Rejected', $btn_user_a_rejected);
        $this->assertStringNotContainsString('Apply for a Match', $btn_user_a_rejected);

        // 7. User A attempts to re-apply via AJAX -> Rejected with specific message
        ob_start();
        $caught_error = false;
        try {
            $this->controller->handle_apply_featured_match();
        } catch (\RuntimeException $e) {
            $caught_error = ($e->getMessage() === 'wp_send_json_error');
        }
        $err_json = ob_get_clean();
        $this->assertTrue($caught_error);
        $this->assertStringContainsString('previous application for this featured candidate was rejected', $err_json);

        // 8. User B (different member) views the SAME candidate -> CAN apply!
        wp_set_current_user($user_b_id);
        $btn_user_b_initial = $this->controller->render_featured_profile_apply_button(['post_id' => $post_id]);
        $this->assertStringContainsString('Apply for a Match', $btn_user_b_initial);
        $this->assertStringNotContainsString('Application Rejected', $btn_user_b_initial);

        // 9. User B applies for the candidate -> Succeeds!
        $_POST = [
            'nonce'          => wp_create_nonce('mm_featured_apply_nonce'),
            'target_user_id' => $candidate_id,
        ];
        ob_start();
        $caught_succ_b = false;
        try {
            $this->controller->handle_apply_featured_match();
        } catch (\RuntimeException $e) {
            $caught_succ_b = ($e->getMessage() === 'wp_send_json_success');
        }
        $succ_json_b = ob_get_clean();
        $this->assertTrue($caught_succ_b);
        $this->assertStringContainsString('application has been received', $succ_json_b);

        // 10. User B now views profile -> "Application Pending Review"
        $btn_user_b_pending = $this->controller->render_featured_profile_apply_button(['post_id' => $post_id]);
        $this->assertStringContainsString('Application Pending Review', $btn_user_b_pending);

        // 11. User A STILL sees "Application Rejected"
        wp_set_current_user($user_a_id);
        $btn_user_a_still_rejected = $this->controller->render_featured_profile_apply_button(['post_id' => $post_id]);
        $this->assertStringContainsString('Application Rejected', $btn_user_a_still_rejected);

        // 12. Candidate STILL sees "Your Featured Profile"
        wp_set_current_user($candidate_id);
        $btn_candidate_still = $this->controller->render_featured_profile_apply_button(['post_id' => $post_id]);
        $this->assertStringContainsString('Your Featured Profile', $btn_candidate_still);
    }

    public function test_parse_image_dimensions_utility(): void
    {
        // 1. Shorthand "200x300"
        $d1 = FeaturedProfileController::parse_image_dimensions(['dimensions' => '200x300']);
        $this->assertSame('200px', $d1['width']);
        $this->assertSame('300px', $d1['height']);
        $this->assertSame(200, $d1['size_num']);
        $this->assertSame('12px', $d1['radius']);
        $this->assertSame('cover', $d1['fit']);

        // 2. Square number "150"
        $d2 = FeaturedProfileController::parse_image_dimensions(['size' => '150', 'radius' => 'circle', 'fit' => 'contain']);
        $this->assertSame('150px', $d2['width']);
        $this->assertSame('150px', $d2['height']);
        $this->assertSame('50%', $d2['radius']);
        $this->assertSame('contain', $d2['fit']);

        // 3. Named WordPress size "thumbnail"
        $d3 = FeaturedProfileController::parse_image_dimensions(['size' => 'thumbnail', 'border_radius' => '8']);
        $this->assertSame('150px', $d3['width']);
        $this->assertSame('150px', $d3['height']);
        $this->assertSame('8px', $d3['radius']);

        // 4. Explicit width and height overriding
        $d4 = FeaturedProfileController::parse_image_dimensions([
            'width'  => '100%',
            'height' => '250px',
            'radius' => '16px',
            'fit'    => 'fill',
        ]);
        $this->assertSame('100%', $d4['width']);
        $this->assertSame('250px', $d4['height']);
        $this->assertSame('16px', $d4['radius']);
        $this->assertSame('fill', $d4['fit']);
    }

    public function test_render_featured_profile_field_photo_size_and_dimensions(): void
    {
        $user_id = 458;
        $user = new \WP_User((object) [
            'ID'           => $user_id,
            'display_name' => 'Laila Test',
        ]);
        $GLOBALS['__mm_users'][$user_id] = $user;

        MatchRepository::instance()->upsert_pool([
            'user_id'    => $user_id,
            'birth_date' => '1996-04-10',
            'country'    => 'Morocco',
            'city'       => 'Casablanca',
            'user_type'  => 'monthly',
        ]);
        update_user_meta($user_id, 'user_photo1', 'https://example.com/laila1.jpg');
        update_user_meta($user_id, 'user_photo2', 'https://example.com/laila2.jpg');

        // Public post
        $post_id_pub = $this->controller->sync_featured_post($user_id, true, 'public');

        // 1. Photo with custom width, height, and circular radius
        $html_pub_round = $this->controller->render_featured_profile_field([
            'key'     => 'photo',
            'post_id' => $post_id_pub,
            'width'   => '140',
            'height'  => '140',
            'radius'  => '50%',
        ]);
        $this->assertStringContainsString('https://example.com/laila1.jpg', $html_pub_round);
        $this->assertStringContainsString('width:140px;', $html_pub_round);
        $this->assertStringContainsString('height:140px;', $html_pub_round);
        $this->assertStringContainsString('border-radius:50%;', $html_pub_round);
        $this->assertStringContainsString('object-fit:cover;', $html_pub_round);

        // 2. Photo with dimensions attribute and fit="contain"
        $html_pub_dims = $this->controller->render_featured_profile_field([
            'key'        => 'photo',
            'post_id'    => $post_id_pub,
            'dimensions' => '200x260',
            'fit'        => 'contain',
        ]);
        $this->assertStringContainsString('width:200px;', $html_pub_dims);
        $this->assertStringContainsString('height:260px;', $html_pub_dims);
        $this->assertStringContainsString('object-fit:contain;', $html_pub_dims);

        // 3. Photo 2 slot
        $html_pub_p2 = $this->controller->render_featured_profile_field([
            'key'     => 'photo_2',
            'post_id' => $post_id_pub,
            'size'    => '180',
        ]);
        $this->assertStringContainsString('https://example.com/laila2.jpg', $html_pub_p2);
        $this->assertStringContainsString('width:180px;', $html_pub_p2);
        $this->assertStringContainsString('height:180px;', $html_pub_p2);

        // 4. Anonymous candidate scaling
        $post_id_anon = $this->controller->sync_featured_post($user_id, true, 'anonymous');
        $html_anon = $this->controller->render_featured_profile_field([
            'key'     => 'photo',
            'post_id' => $post_id_anon,
            'size'    => '100x100',
            'radius'  => 'circle',
        ]);
        $this->assertStringContainsString('mm-anonymous-avatar', $html_anon);
        $this->assertStringContainsString('width:100px;', $html_anon);
        $this->assertStringContainsString('height:100px;', $html_anon);
        $this->assertStringContainsString('border-radius:50%;', $html_anon);
    }

    public function test_render_featured_profile_details_custom_photo_dimensions(): void
    {
        $user_id = 459;
        $user = new \WP_User((object) [
            'ID'           => $user_id,
            'display_name' => 'Samir Test',
        ]);
        $GLOBALS['__mm_users'][$user_id] = $user;

        MatchRepository::instance()->upsert_pool([
            'user_id'    => $user_id,
            'birth_date' => '1992-08-15',
            'country'    => 'Jordan',
            'city'       => 'Amman',
            'user_type'  => 'monthly',
        ]);
        update_user_meta($user_id, 'user_photo1', 'https://example.com/samir1.jpg');

        $post_id = $this->controller->sync_featured_post($user_id, true, 'public');

        $html_details = $this->controller->render_featured_profile_details([
            'post_id'    => $post_id,
            'photo_size' => '400x360',
        ]);

        $this->assertStringContainsString('max-width:400px;', $html_details);
        $this->assertStringContainsString('height:360px;', $html_details);
    }

    public function test_role_access_matrix_and_permission_checks(): void
    {
        $admin_portal = \Matchmaker\Admin\AdminPortal::instance();

        // 1. Matrix defaults
        $matrix = $admin_portal->get_role_access_matrix();
        $this->assertIsArray($matrix);
        $this->assertArrayHasKey('matchmaker_admin', $matrix);
        $this->assertArrayHasKey('events_organizer', $matrix);

        $this->assertSame(1, $matrix['matchmaker_admin']['pool']);
        $this->assertSame(1, $matrix['matchmaker_admin']['matches']);
        $this->assertSame(0, $matrix['matchmaker_admin']['events']);

        $this->assertSame(0, $matrix['events_organizer']['pool']);
        $this->assertSame(1, $matrix['events_organizer']['events']);
        $this->assertSame(1, $matrix['events_organizer']['forminator']);

        // 2. Administrator has access to everything
        $admin_user = new \WP_User((object) ['ID' => 1, 'roles' => ['administrator']]);
        $GLOBALS['__mm_users'][1] = $admin_user;
        $this->assertTrue($admin_portal->is_item_allowed_for_user('pool', $admin_user));
        $this->assertTrue($admin_portal->is_item_allowed_for_user('events', $admin_user));

        // 3. Matchmaker Admin user
        $mm_user = new \WP_User((object) ['ID' => 2, 'roles' => ['matchmaker_admin']]);
        $GLOBALS['__mm_users'][2] = $mm_user;
        $this->assertTrue($admin_portal->is_item_allowed_for_user('pool', $mm_user));
        $this->assertTrue($admin_portal->is_item_allowed_for_user('matches', $mm_user));
        $this->assertFalse($admin_portal->is_item_allowed_for_user('events', $mm_user));

        // 4. Events Organizer user
        $eo_user = new \WP_User((object) ['ID' => 3, 'roles' => ['events_organizer']]);
        $GLOBALS['__mm_users'][3] = $eo_user;
        $this->assertFalse($admin_portal->is_item_allowed_for_user('pool', $eo_user));
        $this->assertTrue($admin_portal->is_item_allowed_for_user('events', $eo_user));
        $this->assertTrue($admin_portal->is_item_allowed_for_user('forminator', $eo_user));

        // 5. Custom configured role in matrix
        $custom_matrix = $matrix;
        $custom_matrix['editor'] = [
            'pool'         => 1,
            'matches'      => 0,
            'settings'     => 0,
            'logs'         => 0,
            'event_clicks' => 0,
            'events'       => 0,
            'forminator'   => 0,
            'posts'        => 1,
            'pages'        => 1,
            'users'        => 0,
            'tools'        => 0,
        ];
        update_option('mm_role_access_matrix', $custom_matrix);

        $editor_user = new \WP_User((object) ['ID' => 4, 'roles' => ['editor']]);
        $GLOBALS['__mm_users'][4] = $editor_user;
        $this->assertTrue($admin_portal->is_item_allowed_for_user('pool', $editor_user));
        $this->assertFalse($admin_portal->is_item_allowed_for_user('matches', $editor_user));
        $this->assertTrue($admin_portal->is_item_allowed_for_user('posts', $editor_user));

        // Cleanup
        delete_option('mm_role_access_matrix');
    }

    public function test_admin_create_featured_profile_action(): void
    {
        $user_id = 490;
        $user = new \WP_User((object) ['ID' => $user_id, 'display_name' => 'Candidate 490']);
        $GLOBALS['__mm_users'][$user_id] = $user;

        $admin_user = new \WP_User((object) ['ID' => 1, 'roles' => ['administrator']]);
        $GLOBALS['__mm_users'][1] = $admin_user;
        wp_set_current_user(1);

        $_GET = [
            'page'     => 'matchmaking-pool',
            'action'   => 'create_featured_profile',
            'user_id'  => (string) $user_id,
            '_wpnonce' => wp_create_nonce('mm_create_featured_' . $user_id),
        ];

        // Should create post and trigger safe redirect
        $caught_redirect = false;
        try {
            \Matchmaker\Admin\AdminPortal::instance()->handle_admin_actions();
        } catch (\RuntimeException $e) {
            $caught_redirect = str_starts_with($e->getMessage(), 'wp_safe_redirect:');
        }

        $post_id = $this->controller->get_user_featured_post_id($user_id);
        $this->assertGreaterThan(0, $post_id);
        $this->assertSame('publish', $GLOBALS['__mm_posts'][$post_id]->post_status);
        $this->assertSame('false', get_post_meta($post_id, 'show_featured_profile', true));
        $this->assertSame('anonymous', get_post_meta($post_id, 'featured_profile_mode', true));
        $this->assertSame(1, (int) get_user_meta($user_id, 'mm_has_featured_profile', true));

        // Clear GET
        $_GET = [];
    }
}

