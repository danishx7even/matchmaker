<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Matchmaker\Frontend\EventRegistrationController;

final class EventRegistrationShortcodeTest extends TestCase
{
    private EventRegistrationController $controller;

    protected function setUp(): void
    {
        $GLOBALS['__mm_options'] = [];
        $GLOBALS['__mm_postmeta'] = [];
        $GLOBALS['__mm_posts'] = [];
        $GLOBALS['wpdb'] = new \Fakewpdb();
        $_GET = [];

        $this->controller = EventRegistrationController::instance();
    }

    public function test_extract_form_id(): void
    {
        $this->assertSame(1234, EventRegistrationController::extract_form_id('[forminator_form id="1234"]'));
        $this->assertSame(5678, EventRegistrationController::extract_form_id("[forminator_form id='5678']"));
        $this->assertSame(999, EventRegistrationController::extract_form_id('[forminator_form id=999]'));
        $this->assertSame(555, EventRegistrationController::extract_form_id('[forminator_form id="555" title="Registration"]'));
        $this->assertSame(777, EventRegistrationController::extract_form_id('777'));
        $this->assertSame(0, EventRegistrationController::extract_form_id(''));
        $this->assertSame(0, EventRegistrationController::extract_form_id('no numbers in here'));
    }

    public function test_generate_hash_consistency_and_format(): void
    {
        $hash1 = EventRegistrationController::generate_hash(501);
        $hash2 = EventRegistrationController::generate_hash(501);
        $hash3 = EventRegistrationController::generate_hash(502);

        $this->assertNotEmpty($hash1);
        $this->assertSame(32, strlen($hash1));
        $this->assertSame($hash1, $hash2, 'Same form ID must generate identical deterministic hash');
        $this->assertNotSame($hash1, $hash3, 'Different form IDs must generate distinct hashes');
        $this->assertSame('', EventRegistrationController::generate_hash(0));
        $this->assertSame('', EventRegistrationController::generate_hash(-10));
    }

    public function test_find_form_id_by_hash_from_posts_and_meta(): void
    {
        global $wpdb;

        // Mock forminator_forms posts
        $query_posts = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'forminator_forms' AND post_status != 'trash'";
        $wpdb->mock_cols[$query_posts] = [101, 102];

        // Mock meta query
        $query_meta = "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'forminator_form_shortcode'";
        $wpdb->mock_cols[$query_meta] = ['[forminator_form id="205"]'];

        $hash101 = EventRegistrationController::generate_hash(101);
        $hash102 = EventRegistrationController::generate_hash(102);
        $hash205 = EventRegistrationController::generate_hash(205);
        $hashUnknown = 'nonexistenthash12345678901234567';

        $this->assertSame(101, EventRegistrationController::find_form_id_by_hash($hash101));
        $this->assertSame(102, EventRegistrationController::find_form_id_by_hash($hash102));
        $this->assertSame(205, EventRegistrationController::find_form_id_by_hash($hash205));
        $this->assertSame(0, EventRegistrationController::find_form_id_by_hash($hashUnknown));
        $this->assertSame(0, EventRegistrationController::find_form_id_by_hash(''));
    }

    public function test_render_rsvp_button_missing_meta_returns_empty(): void
    {
        // Event with no ACF field
        $output = $this->controller->render_rsvp_button(['id' => 999]);
        $this->assertSame('', $output);

        // Event with empty meta
        $GLOBALS['__mm_postmeta'][999]['forminator_form_shortcode'] = '';
        $output = $this->controller->render_rsvp_button(['id' => 999]);
        $this->assertSame('', $output);
    }

    public function test_render_rsvp_button_valid_output_and_attributes(): void
    {
        $event_id = 450;
        $form_id = 880;
        $GLOBALS['__mm_postmeta'][$event_id]['forminator_form_shortcode'] = '[forminator_form id="' . $form_id . '"]';

        $expected_hash = EventRegistrationController::generate_hash($form_id);

        // 1. Default render
        $html = $this->controller->render_rsvp_button(['id' => $event_id]);
        $this->assertStringContainsString('class="rsvp-btn"', $html);
        $this->assertStringContainsString('?e=' . $expected_hash, $html);
        $this->assertStringContainsString('>RSVP</a>', $html);
        $this->assertStringContainsString('/event-reg/', $html);

        // 2. Custom text, extra class, and target
        $html_custom = $this->controller->render_rsvp_button([
            'id'     => $event_id,
            'text'   => 'Attend Now',
            'class'  => 'theme-pulse-btn',
            'target' => '_blank',
        ]);
        $this->assertStringContainsString('class="rsvp-btn theme-pulse-btn"', $html_custom);
        $this->assertStringContainsString('>Attend Now</a>', $html_custom);
        $this->assertStringContainsString('target="_blank"', $html_custom);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html_custom);

        // 3. Current loop context post ID fallback
        $post = new \stdClass();
        $post->ID = $event_id;
        $GLOBALS['post'] = $post;

        $html_loop = $this->controller->render_rsvp_button([]);
        $this->assertStringContainsString('?e=' . $expected_hash, $html_loop);
        $this->assertStringContainsString('class="rsvp-btn"', $html_loop);
    }

    public function test_render_reg_form_responses(): void
    {
        global $wpdb;
        $form_id = 910;
        $hash = EventRegistrationController::generate_hash($form_id);

        // Mock database posts
        $query_posts = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'forminator_forms' AND post_status != 'trash'";
        $wpdb->mock_cols[$query_posts] = [$form_id];

        // 1. Missing hash
        $_GET = [];
        $html_empty = $this->controller->render_reg_form([]);
        $this->assertStringContainsString('az-event-reg-empty', $html_empty);
        $this->assertStringContainsString('No event specified for registration', $html_empty);

        // 2. Invalid hash
        $_GET['e'] = 'invalidhash00000000000000000000';
        $html_invalid = $this->controller->render_reg_form([]);
        $this->assertStringContainsString('az-event-reg-error', $html_invalid);
        $this->assertStringContainsString('Registration form not found or link has expired', $html_invalid);

        // 3. Valid hash matching form 910
        $_GET['e'] = $hash;
        $html_valid = $this->controller->render_reg_form([]);
        $this->assertStringContainsString('[forminator_form id="910"]', $html_valid);
    }

    public function test_shortcodes_are_registered(): void
    {
        $this->assertTrue(shortcode_exists('event_rsvp_button'));
        $this->assertTrue(shortcode_exists('rsvp_button'));
        $this->assertTrue(shortcode_exists('az_event_rsvp_button'));

        $this->assertTrue(shortcode_exists('event_reg_form'));
        $this->assertTrue(shortcode_exists('event_registration_form'));
        $this->assertTrue(shortcode_exists('az_event_reg_form'));
    }
}
