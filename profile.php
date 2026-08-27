<?php

/**
 * Plugin Name: ArabZawaj Profile & Matches Shortcode
 * Description: Renders the member Profile / Matches page (tabs, no page reload) pulled from
 *              wp_matchmaking_pool, wp_matches, and wp_usermeta. "Edit Profile" opens the
 *              questionnaire form; "Events" tab redirects to the events page.
 * Version: 1.1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* -------------------------------------------------------------------------
 * CONFIG — change these two links any time
 * ---------------------------------------------------------------------- */
define( 'AZ_EVENTS_URL', 'https://arabzawaj.org/events-2/' );
define( 'AZ_FORM_URL',   'https://arabzawaj.org/personal-matchmaking-questionnaire/' ); // used by "Edit Profile"

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

function az_calc_age( $birth_date ) {
	if ( empty( $birth_date ) || $birth_date === '0000-00-00' ) return '—';
	try {
		$dob = new DateTime( $birth_date );
		$now = new DateTime( 'now' );
		return $dob->diff( $now )->y;
	} catch ( Exception $e ) {
		return '—';
	}
}

function az_cm_to_feet( $cm ) {
	if ( empty( $cm ) ) return '—';
	$total_inches = $cm / 2.54;
	$feet   = floor( $total_inches / 12 );
	$inches = round( $total_inches - ( $feet * 12 ) );
	if ( $inches == 12 ) { $feet++; $inches = 0; }
	return $feet . ' foot ' . $inches;
}

function az_get_pool_row( $user_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'matchmaking_pool';
	return $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ),
		ARRAY_A
	);
}

function az_get_meta_block( $user_id ) {
	$keys = array(
		'user_citizenship','pref_citizenship','user_social_links','pref_social_links',
		'user_marital_status','pref_marital_status','user_children','pref_children',
		'user_prayer','pref_prayer','user_education','pref_education','user_income',
		'pref_income','pref_additional_info','user_photo1','user_photo2','user_photo3',
		'cycle_matches_count','mm_last_match_run',
	);
	$out = array();
	foreach ( $keys as $k ) {
		$out[ $k ] = get_user_meta( $user_id, $k, true );
	}
	return $out;
}

/**
 * "Days remaining to respond" — based on the most recent match still waiting
 * on this user's response, using a 7-day response window from created_at.
 * (No explicit deadline field exists in the schema, so this is the working
 * assumption — adjust AZ_RESPONSE_WINDOW_DAYS below if your real rule differs.)
 */
define( 'AZ_RESPONSE_WINDOW_DAYS', 7 );

function az_get_match_stats( $user_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'matches';

	// Matches received this term = created since the 1st of the current month.
	$month_start = date( 'Y-m-01 00:00:00' );
	$received_this_term = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table}
		 WHERE (user_one_id = %d OR user_two_id = %d)
		 AND created_at >= %s",
		$user_id, $user_id, $month_start
	) );

	// Days remaining to respond — oldest pending match awaiting this user's response.
	$pending_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT id, user_one_id, user_two_id, user_one_response, user_two_response, created_at
		 FROM {$table}
		 WHERE (user_one_id = %d OR user_two_id = %d)
		 AND status IN ('pending_review','approved')
		 ORDER BY created_at ASC LIMIT 1",
		$user_id, $user_id
	), ARRAY_A );

	$days_remaining = 0;
	if ( $pending_row ) {
		$is_user_one = ( (int) $pending_row['user_one_id'] === (int) $user_id );
		$my_response = $is_user_one ? $pending_row['user_one_response'] : $pending_row['user_two_response'];
		if ( $my_response === 'pending' ) {
			$deadline = strtotime( $pending_row['created_at'] . " +" . AZ_RESPONSE_WINDOW_DAYS . " days" );
			$diff_days = ceil( ( $deadline - current_time( 'timestamp' ) ) / DAY_IN_SECONDS );
			$days_remaining = max( 0, (int) $diff_days );
		}
	}

	// Total matches accepted — from usermeta cycle_matches_count if present,
	// otherwise fall back to counting status = 'matched'.
	$cycle_count = get_user_meta( $user_id, 'cycle_matches_count', true );
	if ( $cycle_count === '' || $cycle_count === false ) {
		$cycle_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table}
			 WHERE (user_one_id = %d OR user_two_id = %d) AND status = 'matched'",
			$user_id, $user_id
		) );
	}

	return array(
		'received_this_term' => $received_this_term,
		'days_remaining'     => $days_remaining,
		'total_accepted'     => (int) $cycle_count,
	);
}

function az_get_matches_list( $user_id ) {
	global $wpdb;
	$table    = $wpdb->prefix . 'matches';
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table}
		 WHERE user_one_id = %d OR user_two_id = %d
		 ORDER BY created_at DESC",
		$user_id, $user_id
	), ARRAY_A );

	$list = array();
	foreach ( (array) $rows as $row ) {
		$other_id = ( (int) $row['user_one_id'] === (int) $user_id ) ? $row['user_two_id'] : $row['user_one_id'];
		$pool     = az_get_pool_row( $other_id );
		$user_obj = get_userdata( $other_id );
		$photo    = get_user_meta( $other_id, 'user_photo1', true );

		$list[] = array(
			'match_id'  => $row['id'],
			'name'      => $user_obj ? $user_obj->display_name : 'Member #' . $other_id,
			'photo'     => $photo ? $photo : '',
			'age'       => $pool ? az_calc_age( $pool['birth_date'] ) : '—',
			'location'  => $pool ? $pool['location'] : '—',
			'status'    => $row['status'],
			'score'     => $row['score'],
			'contact_revealed' => (int) $row['contact_revealed'],
			'is_user_one'      => ( (int) $row['user_one_id'] === (int) $user_id ),
			'my_response'      => ( (int) $row['user_one_id'] === (int) $user_id ) ? $row['user_one_response'] : $row['user_two_response'],
		);
	}
	return $list;
}

function az_status_label( $status ) {
	$map = array(
		'pending_review' => 'Pending Review',
		'approved'       => 'Awaiting Response',
		'admin_rejected' => 'Not Approved',
		'matched'        => 'Matched',
		'rejected'       => 'Declined',
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : ucfirst( $status );
}

/* -------------------------------------------------------------------------
 * Shortcode
 * ---------------------------------------------------------------------- */
function az_profile_shortcode() {

	if ( ! is_user_logged_in() ) {
		return '<div class="az-wrap"><div class="az-card"><p>Please log in to view your profile.</p></div></div>';
	}

	$user_id  = get_current_user_id();
	$user_obj = wp_get_current_user();
	$pool     = az_get_pool_row( $user_id );
	$meta     = az_get_meta_block( $user_id );
	$stats    = az_get_match_stats( $user_id );
	$matches  = az_get_matches_list( $user_id );

	if ( ! $pool ) {
		return '<div class="az-wrap"><div class="az-card"><p>Your matchmaking profile has not been set up yet.</p></div></div>';
	}

	$photo   = ! empty( $meta['user_photo1'] ) ? $meta['user_photo1'] : get_avatar_url( $user_id, array( 'size' => 300 ) );
	$name    = $user_obj->display_name;
	$age     = az_calc_age( $pool['birth_date'] );
	$height  = az_cm_to_feet( $pool['height_cm'] );
	$smoking = ! empty( $pool['smoking'] ) ? $pool['smoking'] : '—';
	$drinking= ! empty( $pool['drinking'] ) ? $pool['drinking'] : '—';
	$lifestyle = $smoking . ', ' . $drinking;

	ob_start();
	?>
	<div class="az-wrap">

		<div class="az-nav">
			<button class="az-nav-btn az-active" data-tab="profile">Profile</button>
			<button class="az-nav-btn" data-tab="matches">Matches</button>
			<a class="az-nav-btn az-nav-link" href="<?php echo esc_url( AZ_EVENTS_URL ); ?>">Events</a>
			<div class="az-nav-right">
				<?php echo get_avatar( $user_id, 34 ); ?>
			</div>
		</div>

		<div class="az-tab-panel" id="az-tab-profile">

			<div class="az-card az-about-card">
				<div class="az-about-photo">
					<img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $name ); ?>">
				</div>
				<div class="az-about-info">
					<div class="az-about-header">
						<h2>About Me</h2>
						<a href="<?php echo esc_url( AZ_FORM_URL ); ?>" class="az-edit-btn">Edit Profile</a>
					</div>
					<p class="az-user-name"><?php echo esc_html( $name ); ?></p>

					<div class="az-rows">
						<div class="az-row"><span class="az-label">Location</span><span class="az-value"><?php echo esc_html( $pool['location'] ); ?></span></div>
						<div class="az-row"><span class="az-label">Age</span><span class="az-value"><?php echo esc_html( $age ); ?> Years Old</span></div>
						<div class="az-row"><span class="az-label">Origin</span><span class="az-value"><?php echo esc_html( $pool['origin'] ); ?></span></div>
						<div class="az-row"><span class="az-label">Languages</span><span class="az-value"><?php echo esc_html( $pool['languages'] ); ?></span></div>
						<div class="az-row"><span class="az-label">Religion</span><span class="az-value"><?php echo esc_html( $pool['religion'] ); ?></span></div>
						<div class="az-row"><span class="az-label">Marital Status</span><span class="az-value"><?php echo esc_html( $meta['user_marital_status'] ); ?></span></div>
					</div>
				</div>
			</div>

			<div class="az-card az-mm-card">
				<div class="az-mm-header">
					<h3>Your Matchmaking</h3>
					<span class="az-badge">● You are an active member</span>
				</div>
				<p class="az-mm-sub">Your profile is currently active and you are eligible to receive a curated match each month.</p>

				<div class="az-stats-grid">
					<div class="az-stat-box">
						<div class="az-stat-num"><?php echo esc_html( sprintf( '%02d', $stats['received_this_term'] ) ); ?></div>
						<div class="az-stat-caption">Match received this term</div>
					</div>
					<div class="az-stat-box">
						<div class="az-stat-num"><?php echo esc_html( sprintf( '%02d', $stats['days_remaining'] ) ); ?></div>
						<div class="az-stat-caption">Days remaining to respond</div>
					</div>
					<div class="az-stat-box">
						<div class="az-stat-num"><?php echo esc_html( sprintf( '%02d', $stats['total_accepted'] ) ); ?></div>
						<div class="az-stat-caption">Total match accepted</div>
					</div>
				</div>
			</div>

			<div class="az-card">
				<h3 class="az-card-title">A Little More About Me</h3>
				<div class="az-rows">
					<div class="az-row"><span class="az-label">Height</span><span class="az-value"><?php echo esc_html( $height ); ?></span></div>
					<div class="az-row"><span class="az-label">Education</span><span class="az-value"><?php echo esc_html( $meta['user_education'] ); ?></span></div>
					<div class="az-row"><span class="az-label">Career</span><span class="az-value"><?php echo esc_html( $pool['job'] ); ?></span></div>
					<div class="az-row"><span class="az-label">Lifestyle</span><span class="az-value"><?php echo esc_html( $lifestyle ); ?></span></div>
					<div class="az-row"><span class="az-label">Prayer Habits</span><span class="az-value"><?php echo esc_html( $meta['user_prayer'] ); ?></span></div>
				</div>
			</div>

			<div class="az-card">
				<h3 class="az-card-title">Who I Am Looking For</h3>
				<?php if ( ! empty( $meta['pref_additional_info'] ) ) : ?>
					<p class="az-looking-text"><?php echo esc_html( $meta['pref_additional_info'] ); ?></p>
				<?php endif; ?>
				<div class="az-rows">
					<div class="az-row"><span class="az-label">Preferred Location</span><span class="az-value"><?php echo esc_html( $pool['pref_location'] ); ?></span></div>
					<div class="az-row"><span class="az-label">Preferred Background</span><span class="az-value"><?php echo esc_html( $pool['pref_origin'] ); ?></span></div>
					<div class="az-row"><span class="az-label">Age Preference</span><span class="az-value"><?php echo esc_html( $pool['preferred_age_min'] . ' to ' . $pool['preferred_age_max'] ); ?></span></div>
					<div class="az-row"><span class="az-label">Relationship Goal</span><span class="az-value">Marriage</span></div>
				</div>
			</div>

		</div><!-- /profile tab -->

		<div class="az-tab-panel" id="az-tab-matches" style="display:none;">
			<div class="az-card">
				<h3 class="az-card-title">Your Matches</h3>

				<?php if ( empty( $matches ) ) : ?>
					<p class="az-empty">No matches yet. Check back after your next matching cycle.</p>
				<?php else : ?>
					<div class="az-match-list">
						<?php foreach ( $matches as $m ) : ?>
							<div class="az-match-item">
								<div class="az-match-photo">
									<?php if ( $m['photo'] ) : ?>
										<img src="<?php echo esc_url( $m['photo'] ); ?>" alt="">
									<?php else : ?>
										<div class="az-match-photo-placeholder">?</div>
									<?php endif; ?>
								</div>
								<div class="az-match-info">
									<div class="az-match-name"><?php echo esc_html( $m['name'] ); ?></div>
									<div class="az-match-meta"><?php echo esc_html( $m['age'] ); ?> Years Old · <?php echo esc_html( $m['location'] ); ?></div>
								</div>
								<div class="az-match-status">
									<span class="az-status-pill az-status-<?php echo esc_attr( $m['status'] ); ?>">
										<?php echo esc_html( az_status_label( $m['status'] ) ); ?>
									</span>
									<?php if ( $m['contact_revealed'] ) : ?>
										<span class="az-contact-tag">Contact Revealed</span>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div><!-- /matches tab -->

	</div><!-- /az-wrap -->

	<style>
	.az-wrap{max-width:900px;margin:0 auto;padding:40px 40px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;color:#2b2b2b;background:#ffffff;border-radius:15px;box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);}

	.az-nav{display:flex;align-items:center;gap:28px;padding:0 4px 22px 4px;border-bottom:1px solid #eee;margin-bottom:26px;font-family:Georgia,'Times New Roman',serif;}
	.az-nav-btn{background:none;border:none;font-size:14px;letter-spacing:.5px;color:#8a8a8a;cursor:pointer;padding:6px 0 8px 0;font-family:inherit;text-decoration:none;border-bottom:2px solid transparent;transition:color .15s ease;outline:none;box-shadow:none;}
	.az-nav-btn:hover,
	.az-nav-btn:focus{color:#1a1a1a;background:none;box-shadow:none;text-decoration:none;}
	.az-nav-btn.az-active{color:#1a1a1a;border-bottom:2px solid #1a1a1a;}
	.az-nav-btn.az-active:hover{color:#1a1a1a;}
	.az-nav-right{margin-left:auto;display:flex;align-items:center;}
	.az-nav-right img{border-radius:50%;}

	.az-card{background:#fff;border:1px solid #ece7de;border-radius:18px;padding:28px 30px;margin-bottom:22px;}
	.az-card-title{font-family:Georgia,'Times New Roman',serif;font-size:15px;letter-spacing:.5px;text-transform:uppercase;margin:0 0 18px 0;color:#1a1a1a;}

	.az-about-card{display:flex;gap:28px;align-items:flex-start;}
	.az-about-photo img{width:320px;height:320px;object-fit:cover;object-position:center center;border-radius:14px;display:block;}
	.az-about-info{flex:1;}
	.az-about-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;}
	.az-about-header h2{font-family: "Playfair Display", serif;font-size:16px;letter-spacing:.5px;text-transform:uppercase;margin:0;color:#1a1a1a;}
	.az-edit-btn{border:1px solid #ddd;border-radius:8px;padding:6px 14px;font-size:13px;color:#333;text-decoration:none;transition:color .15s ease;}
	.az-edit-btn:hover{color:#c1502e;}
	.az-user-name{font-size:15px;color:#444;margin:6px 0 18px 0;line-height:1.5;font-weight:600;}

	.az-rows{display:flex;flex-direction:column;}
	.az-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f0ede6;font-size:13.5px;}
	.az-row:last-child{border-bottom:none;}
	.az-label{color:#9a9a9a;}
	.az-value{font-family: "Playfair Display", serif;text-transform:uppercase;letter-spacing:.4px;font-weight:500;color:#1a1a1a;text-align:right;}

	.az-mm-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:10px;}
	.az-mm-header h3{font-family: "Playfair Display", serif;font-size:15px;letter-spacing:.5px;text-transform:uppercase;margin:0;}
	.az-badge{background:#CC723F;color:#fff;font-size:11.5px;letter-spacing:.3px;padding:6px 14px;border-radius:20px;}
	.az-mm-sub{color:#8a8a8a;font-size:13.5px;margin:6px 0 22px 0;line-height:1.5;}

	.az-stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
	.az-stat-box{background:#f6f4ef;border-radius:12px;padding:20px 18px;text-align:left;}
	.az-stat-num{font-family: "Playfair Display", serif; font-size:30px;color:#1a1a1a;}
	.az-stat-caption{font-size:12px;color:#9a9a9a;margin-top:6px;}

	.az-looking-text{font-size:13.5px;color:#555;line-height:1.6;margin-bottom:18px;}

	.az-match-list{display:flex;flex-direction:column;gap:14px;}
	.az-match-item{display:flex;align-items:center;gap:16px;border:1px solid #f0ede6;border-radius:12px;padding:14px 16px;}
	.az-match-photo img,.az-match-photo-placeholder{width:56px;height:56px;border-radius:10px;object-fit:cover;object-position:center center;background:#eee;display:flex;align-items:center;justify-content:center;color:#999;}
	.az-match-info{flex:1;}
	.az-match-name{font-weight:600;font-size:14px;}
	.az-match-meta{font-size:12.5px;color:#9a9a9a;margin-top:3px;}
	.az-match-status{text-align:right;}
	.az-status-pill{display:inline-block;font-size:11.5px;padding:5px 12px;border-radius:16px;background:#f0ede6;color:#555;}
	.az-status-matched{background:#e4f3e7;color:#2e7d32;}
	.az-status-approved{background:#fdf0e3;color:#c1502e;}
	.az-status-rejected,.az-status-admin_rejected{background:#fbe9e7;color:#c0392b;}
	.az-contact-tag{display:block;font-size:11px;color:#9a9a9a;margin-top:4px;}
	.az-empty{color:#9a9a9a;font-size:13.5px;}
			  .e-con.e-flex>.e-con-inner {
				  padding:1rem;
			  }

	@media(max-width:640px){
		.az-about-card{flex-direction:column;}
		.az-about-photo img{width:100%;height:220px;}
		.az-stats-grid{grid-template-columns:1fr;}
		.az-nav{flex-wrap:wrap;gap:16px;}
	}
    @media (max-width: 640px) {
		.az-about-header{
			gap:30px;
		}
  .az-row{
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }
  .az-value{
    text-align: left;
    white-space: normal;
    word-break: break-word;
    width: 100%;
  }
}
	</style>

	<script>
	(function(){
		var buttons = document.querySelectorAll('.az-nav-btn[data-tab]');
		buttons.forEach(function(btn){
			btn.addEventListener('click', function(e){
				e.preventDefault();
				var tab = btn.getAttribute('data-tab');
				buttons.forEach(function(b){ b.classList.remove('az-active'); });
				btn.classList.add('az-active');
				document.querySelectorAll('.az-tab-panel').forEach(function(p){ p.style.display = 'none'; });
				var panel = document.getElementById('az-tab-' + tab);
				if (panel) panel.style.display = 'block';
			});
		});
	})();
	</script>
	<?php
	return ob_get_clean();
}
add_shortcode( 'az_profile', 'az_profile_shortcode' );