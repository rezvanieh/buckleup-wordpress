<?php
/**
 * SINGLE SOURCE OF TRUTH for the Hub 1 "Driving Lessons & Packages" cluster —
 * one entry per licence-class / lesson-type service page.
 *
 * Consumed by:
 *   - build-pages.php  → build_services(): renders the /services/ PILLAR cards
 *                        (short intro + link down to each cluster page).
 *   - build-service-clusters.php (next step) → builds the cluster pages themselves.
 *
 * Mirrors the pattern locations-content.php established for the location pages, so
 * copy lives in ONE place and the pillar can never drift from the cluster pages.
 *
 * Per the content plan (Documents/pillar-cluster-driving-school.pdf, Hub 1):
 *   - `/services/` is the pillar; each of these is a cluster that links back up.
 *   - Cluster pages are the "money pages" — the single biggest gap on the site.
 *   - Deliberately NO prices, pass rates or awards here: the plan adds none, and the
 *     live site already has conflicting trust claims that need a separate decision.
 *   - Nothing here names or implies knowledge of confidential ICBC test routes.
 *
 * `slug` is relative to /services/ (cluster pages are children of the pillar, so the
 * URL itself expresses the pillar→cluster relationship: /services/<slug>/).
 *
 * @package BuckleUp
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

return array(

	'class-7-driving-lessons' => array(
		'icon'       => 'fas fa-id-card',
		'nav_label'  => 'Class 7 Lessons',
		'card_title' => 'Class 7 Driving Lessons',
		'h1'         => 'Class 7 Driving Lessons <span class="gradient-text">for New Drivers</span>',
		'eyebrow'    => 'Beginner &amp; Novice Drivers',
		// Short = the pillar card. Long = the cluster page intro.
		'short'      => 'Just passed your knowledge test, or driving on an N? Patient one-on-one lessons that take you from your first time behind the wheel through to your Class 7 road test.',
		'long'       => 'Class 7 is where almost every BC driver starts. Whether you have just picked up your Class 7L learner\'s licence or you are already driving on a Class 7N, lessons are built around what you actually find difficult — not a fixed script. We start wherever you are: cockpit drill and mirrors if this is your first time behind the wheel, or intersections, lane changes and highway confidence if you have been driving for a while.',
		'features'   => array(
			'First-time drivers on a Class 7L learner\'s licence',
			'Novice drivers building confidence on a 7N',
			'Cockpit drill, mirrors, observation and steering',
			'Intersections, lane changes and merging',
			'Class 7 road test preparation',
		),
		'seo_title'  => 'Class 7 Driving Lessons in Coquitlam &amp; the Tri-Cities | BuckleUp',
		'seo_desc'   => 'ICBC-certified Class 7 driving lessons for new and novice BC drivers in Coquitlam, Port Coquitlam, Port Moody and North Vancouver. Patient one-on-one instruction.',
	),

	'class-5-driving-lessons' => array(
		'icon'       => 'fas fa-car',
		'nav_label'  => 'Class 5 Lessons',
		'card_title' => 'Class 5 Lessons &amp; Road Test Prep',
		'h1'         => 'Class 5 Driving Lessons &amp; <span class="gradient-text">Road Test Prep</span>',
		'eyebrow'    => 'Moving Off Your N',
		'short'      => 'Ready to move off your N and get your full licence? Focused lessons that sharpen the skills the Class 5 road test actually assesses.',
		'long'       => 'The Class 5 road test asks more of you than the Class 7 did — examiners are looking for consistent observation, smooth speed control and confident decision-making in heavier traffic. These lessons focus on the habits that separate a comfortable pass from a near miss: scanning and shoulder checks, lane positioning, parallel parking and hill starts, and staying calm in the situations that most often cost people points.',
		'features'   => array(
			'For novice drivers ready to leave the N behind',
			'Observation, scanning and shoulder checks',
			'Parallel parking, hill starts and reversing',
			'Speed management and lane positioning',
			'Mock road test with honest feedback',
		),
		'seo_title'  => 'Class 5 Driving Lessons &amp; ICBC Road Test Prep | BuckleUp Driving School',
		'seo_desc'   => 'Class 5 driving lessons and ICBC road test preparation in Coquitlam, Port Moody and North Vancouver. Move off your N with confidence.',
	),

	'class-4-driving-lessons' => array(
		'icon'       => 'fas fa-truck',
		'nav_label'  => 'Class 4 Lessons',
		'card_title' => 'Class 4 Commercial Lessons',
		'h1'         => 'Class 4 Commercial <span class="gradient-text">Driving Lessons</span>',
		'eyebrow'    => 'Taxi, Ride-Hailing &amp; Small Bus',
		'short'      => 'Driving for work? Class 4 training for taxi, ride-hailing and small-bus drivers — paired with our free ICBC Class 4 knowledge test practice exam.',
		'long'       => 'A Class 4 licence is what BC requires to drive a taxi, a ride-hailing vehicle, an ambulance or a small bus carrying up to 25 people. There are two versions: restricted (taxis, ride-hailing, small buses) and unrestricted (which adds larger passenger vehicles). Training covers the knowledge test first, then the practical skills an examiner expects from a professional driver — passenger safety, vehicle checks and defensive habits that matter when driving is your job.',
		'features'   => array(
			'Restricted and unrestricted Class 4',
			'Taxi, ride-hailing and small-bus drivers',
			'Free ICBC Class 4 knowledge test practice exam',
			'Pre-trip vehicle checks and passenger safety',
			'Defensive driving for professional drivers',
		),
		'seo_title'  => 'Class 4 Driving Lessons in BC | Taxi &amp; Ride-Hailing Training | BuckleUp',
		'seo_desc'   => 'Class 4 commercial driving lessons for taxi, ride-hailing and small-bus drivers in Metro Vancouver, plus a free ICBC Class 4 knowledge test practice exam.',
	),

	'highway-driving-lessons' => array(
		'icon'       => 'fas fa-road',
		'nav_label'  => 'Highway Lessons',
		'card_title' => 'Highway Driving Lessons',
		'h1'         => 'Highway Driving <span class="gradient-text">Lessons</span>',
		'eyebrow'    => 'Confidence at Speed',
		'short'      => 'Nervous about merging at 90 km/h? Dedicated highway sessions that make high-speed driving feel routine instead of daunting.',
		'long'       => 'Plenty of drivers are perfectly comfortable on city streets and still avoid the highway entirely. Highway lessons deal with that directly: judging gaps and merging at speed, keeping a safe following distance, changing lanes among trucks, and knowing what to do when traffic suddenly slows. We build up gradually — quieter stretches first, busier routes once the basics feel automatic.',
		'features'   => array(
			'Merging and exiting at highway speed',
			'Judging gaps and following distance',
			'Lane discipline around heavy vehicles',
			'Handling congestion and sudden slowdowns',
			'Suitable for new and returning drivers',
		),
		'seo_title'  => 'Highway Driving Lessons in Metro Vancouver | BuckleUp Driving School',
		'seo_desc'   => 'Highway driving lessons for nervous or inexperienced drivers in Coquitlam, Port Moody and North Vancouver. Learn merging, lane changes and speed management.',
	),

	'refresher-driving-lessons' => array(
		'icon'       => 'fas fa-heart',
		'nav_label'  => 'Refresher Lessons',
		'card_title' => 'Refresher &amp; Nervous-Driver Lessons',
		'h1'         => 'Refresher &amp; <span class="gradient-text">Nervous-Driver Lessons</span>',
		'eyebrow'    => 'No Pressure, No Judgement',
		'short'      => 'Licensed but out of practice, or anxious behind the wheel? Calm, unhurried lessons that rebuild confidence at whatever pace suits you.',
		'long'       => 'Not everyone learning with us is a teenager going for a first licence. Some drivers have held a licence for years but stopped driving after a move, a break or a collision. Others are new to Canada and adjusting to unfamiliar roads and winter conditions. And some have simply never felt comfortable behind the wheel. These lessons are deliberately unhurried — we go at your pace, revisit whatever feels shaky, and treat nerves as normal rather than something to push through.',
		'features'   => array(
			'Returning after a break from driving',
			'Anxious and nervous drivers welcome',
			'New to Canada and adjusting to BC roads',
			'Rebuilding specific skills at your own pace',
			'Lessons available in English and Farsi',
		),
		'seo_title'  => 'Refresher &amp; Nervous Driver Lessons | BuckleUp Driving School Vancouver',
		'seo_desc'   => 'Patient refresher and nervous-driver lessons in Coquitlam, Port Moody and North Vancouver — for drivers returning after a break or new to Canada.',
	),

);
