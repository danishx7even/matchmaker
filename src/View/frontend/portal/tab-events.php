<?php
/**
 * View: Member Portal – Events Tab (In-Canvas CPT Loop with Elementor Loop Template)
 *
 * Available variables:
 *   @var int    $user_id
 *   @var string $user_type
 *   @var bool   $is_premium
 *   @var int    $paged
 *
 * @package Matchmaker\View
 */

if (!defined('ABSPATH')) {
    exit;
}

$cpt_slug     = (string) get_option('mm_events_cpt_slug', 'event');
$template_id  = (int) get_option('mm_events_template_id', 395);
$per_page     = (int) get_option('mm_events_per_page', 6);
$current_page = max(1, (int) ($paged ?? 1));

$query_args = [
    'post_type'      => $cpt_slug,
    'post_status'    => 'publish',
    'posts_per_page' => $per_page,
    'paged'          => $current_page,
    'orderby'        => 'date',
    'order'          => 'DESC',
];

$events_query = new \WP_Query($query_args);
$total_pages  = (int) $events_query->max_num_pages;
?>
<div class="mm-events-container">
    <div class="mm-events-header">
        <h2 class="mm-events-title font-cormorant"><?php esc_html_e('Upcoming Events & Mixers', 'matchmaker'); ?></h2>
        <p class="mm-events-subtitle"><?php esc_html_e('Explore our curated matrimonial mixers, webinars, and exclusive community gatherings.', 'matchmaker'); ?></p>
    </div>

    <?php if ($events_query->have_posts()) : ?>
        <div class="mm-events-grid">
            <?php 
            $template_css_output = '';
            $has_captured_css    = false;

            while ($events_query->have_posts()) : $events_query->the_post();
                $event_id  = get_the_ID();
                $rendered  = false;

                // Resolve event featured image / background thumbnail URL
                $thumb_url = '';
                if (function_exists('get_the_post_thumbnail_url')) {
                    $thumb_url = (string) (get_the_post_thumbnail_url($event_id, 'full') ?: get_the_post_thumbnail_url($event_id, 'large') ?: '');
                }
                if (empty($thumb_url)) {
                    $meta_img = get_post_meta($event_id, 'event_image', true) 
                        ?: get_post_meta($event_id, 'image', true) 
                        ?: get_post_meta($event_id, '_thumbnail_id', true);
                    if (!empty($meta_img)) {
                        $thumb_url = (is_numeric($meta_img) && function_exists('wp_get_attachment_image_url'))
                            ? (string) (wp_get_attachment_image_url((int) $meta_img, 'full') ?: '')
                            : (string) $meta_img;
                    }
                }

                // Render via Elementor Loop Template if available
                if (class_exists('\Elementor\Plugin') && $template_id > 0) {
                    global $post;
                    $post = get_post($event_id);
                    if ($post) {
                        setup_postdata($post);
                        $builder = \Elementor\Plugin::instance()->frontend;
                        if (method_exists($builder, 'get_builder_content_for_display')) {
                            $loop_content = $builder->get_builder_content_for_display($template_id, false);
                            if (!empty($loop_content)) {
                                // Extract and deduplicate <style> tags so they are only printed once, never repeated per card
                                if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $loop_content, $matches)) {
                                    foreach ($matches[0] as $style_tag) {
                                        if (!$has_captured_css) {
                                            $template_css_output .= $style_tag . "\n";
                                        }
                                        $loop_content = str_replace($style_tag, '', $loop_content);
                                    }
                                    $has_captured_css = true;
                                }

                                $loop_content = trim($loop_content);
                                echo '<div class="mm-event-loop-item" data-event-id="' . esc_attr((string) $event_id) . '">' . $loop_content . '</div>';
                                $rendered = true;
                            }
                        }
                    }
                }

                // Fallback native event card matching design system
                if (!$rendered) : ?>
                    <div class="mm-event-card az-card" data-event-id="<?php echo esc_attr((string) $event_id); ?>">
                        <?php if (!empty($thumb_url)) : ?>
                            <div class="mm-event-thumb-wrap">
                                <a href="<?php the_permalink(); ?>">
                                    <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php the_title_attribute(); ?>" class="mm-event-thumb">
                                </a>
                            </div>
                        <?php elseif (has_post_thumbnail()) : ?>
                            <div class="mm-event-thumb-wrap">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail('medium_large', ['class' => 'mm-event-thumb']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="mm-event-card-body">
                            <span class="mm-event-date-pill">
                                📅 <?php echo esc_html(get_the_date('M j, Y')); ?>
                            </span>
                            <h3 class="mm-event-card-title font-cormorant">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h3>
                            <div class="mm-event-card-excerpt">
                                <?php echo esc_html(wp_trim_words(get_the_excerpt(), 18, '...')); ?>
                            </div>
                            <div class="mm-event-card-footer">
                                <a href="<?php the_permalink(); ?>" class="btn btn-primary mm-event-action-btn">
                                    <?php esc_html_e('View Event Details →', 'matchmaker'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endwhile;
            wp_reset_postdata(); ?>
        </div>

        <?php if (!empty($template_css_output)) : ?>
            <div class="mm-elementor-template-styles" style="display:none;" aria-hidden="true">
                <?php echo $template_css_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>

        <?php if ($total_pages > 1) : ?>
            <!-- Events AJAX Pagination -->
            <nav class="mm-pagination" aria-label="<?php esc_attr_e('Events Pagination', 'matchmaker'); ?>">
                <?php if ($current_page > 1) : ?>
                    <button type="button" class="mm-page-btn mm-page-prev" data-mm-action="paginate-events" data-page="<?php echo ($current_page - 1); ?>">
                        &larr; <?php esc_html_e('Previous', 'matchmaker'); ?>
                    </button>
                <?php endif; ?>

                <div class="mm-page-numbers">
                    <?php for ($p = 1; $p <= $total_pages; $p++) : ?>
                        <button type="button" 
                            class="mm-page-number <?php echo ($p === $current_page) ? 'active' : ''; ?>" 
                            data-mm-action="paginate-events" 
                            data-page="<?php echo $p; ?>">
                            <?php echo $p; ?>
                        </button>
                    <?php endfor; ?>
                </div>

                <?php if ($current_page < $total_pages) : ?>
                    <button type="button" class="mm-page-btn mm-page-next" data-mm-action="paginate-events" data-page="<?php echo ($current_page + 1); ?>">
                        <?php esc_html_e('Next', 'matchmaker'); ?> &rarr;
                    </button>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    <?php else : ?>
        <div class="az-card mm-empty-card" style="text-align:center; padding:48px 24px; margin-top:20px;">
            <div style="font-size:42px; margin-bottom:12px;">🎟️</div>
            <h2 style="font-family:'Cormorant SC', serif; font-size:24px; font-weight:700; color:#1e293b; margin-bottom:10px;">
                <?php esc_html_e('No Events Currently Scheduled', 'matchmaker'); ?>
            </h2>
            <p style="max-width:540px; margin:0 auto; color:#64748b; font-size:15px; line-height:1.6;">
                <?php esc_html_e('We are continuously arranging matrimonial gatherings and community webinars. Please check back soon for upcoming dates!', 'matchmaker'); ?>
            </p>
        </div>
    <?php endif; ?>
</div>
