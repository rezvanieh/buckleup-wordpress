<?php
/**
 * SINGLE SOURCE OF TRUTH for the Hub 1 "Driving Lessons & Packages" cluster —
 * one entry per licence-class / lesson-type service page.
 *
 * Consumed by:
 *   - build-pages.php            → build_services(): the /services/ PILLAR cards
 *   - build-service-clusters.php → the cluster pages themselves
 *   - build-lessons.php          → the home page's lesson cards
 *
 * Mirrors the pattern locations-content.php established for the location pages, so
 * copy lives in ONE place and the three surfaces can never drift apart.
 *
 * Per the content plan (Documents/pillar-cluster-driving-school.pdf, Hub 1):
 *   - `/services/` is the pillar; each of these is a cluster that links back up.
 *   - Cluster pages are the "money pages" — the single biggest gap on the site.
 *   - Deliberately NO prices, pass rates or awards here.
 *   - Nothing here names or implies knowledge of confidential ICBC test routes.
 *
 * HOUSE STYLE for this copy (client request):
 *   - No em or en dashes. Use a full stop, colon, semicolon, comma or brackets —
 *     whichever the sentence actually calls for — so the punctuation carries
 *     meaning instead of standing in for it.
 *   - Contractions ("you've", "we'll") because people speak that way; the earlier
 *     draft avoided them entirely and read like a brochure.
 *   - Second person, plain words, no stacked marketing adjectives.
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
		// Short = the pillar and home cards. Long = the cluster page intro.
		'short'      => 'Just passed your knowledge test, or driving on an N? We\'ll take you from your first time behind the wheel through to your Class 7 road test, at whatever pace suits you.',
		'long'       => 'Class 7 is where almost every BC driver starts. Whether you\'ve just picked up your Class 7L learner\'s licence or you\'re already driving on a 7N, we build the lessons around what you actually find hard instead of working through a fixed script. If it\'s your first time behind the wheel we\'ll start with the basics: where everything is, how the car responds, how to use your mirrors. If you\'ve been driving a while, we\'ll spend the time on intersections, lane changes and getting comfortable at highway speed.',
		'features'   => array(
			'First-time drivers on a Class 7L learner\'s licence',
			'Novice drivers building confidence on a 7N',
			'Cockpit drill, mirrors, observation and steering',
			'Intersections, lane changes and merging',
			'Class 7 road test preparation',
			'Use our insured dual-control car for your road test',
		),
		'faqs'       => array(
			array(
				'q' => 'Do I need my Class 7L before I can book a lesson?',
				'a' => 'Yes. You need a valid Class 7L learner\'s licence to drive with an instructor, which means passing the ICBC knowledge test first. If you haven\'t done that yet, our step-by-step guide to getting your Class 7L walks through the whole process.',
			),
			array(
				'q' => 'How many lessons will I need before my road test?',
				'a' => 'It genuinely varies. Some students are ready after a handful of sessions; others want more practice before test day. We\'ll give you an honest read after the first lesson rather than selling you a fixed package up front.',
			),
			array(
				'q' => 'Can I use your car for the Class 7 road test?',
				'a' => 'Yes. Our dual-control cars are insured for road tests, and we include a warm-up lesson beforehand so you arrive already settled.',
			),
		),
		'seo_title'  => 'Class 7 Driving Lessons in Coquitlam | BuckleUp',
		'seo_desc'   => 'ICBC-certified Class 7 driving lessons for new and novice BC drivers in Coquitlam, Port Coquitlam, Port Moody and North Vancouver. Patient one-on-one instruction.',
	),

	'class-5-driving-lessons' => array(
		'icon'       => 'fas fa-car',
		'nav_label'  => 'Class 5 Lessons',
		'card_title' => 'Class 5 Lessons &amp; Road Test Prep',
		'h1'         => 'Class 5 Driving Lessons &amp; <span class="gradient-text">Road Test Prep</span>',
		'eyebrow'    => 'Moving Off Your N',
		'short'      => 'Ready to move off your N and get your full licence? Focused lessons that sharpen the skills the Class 5 road test actually looks at.',
		'long'       => 'The Class 5 road test asks more of you than the Class 7 did. Examiners want to see consistent observation, smooth speed control and confident decisions in heavier traffic. So that\'s where we spend the time: scanning and shoulder checks until they stop being something you have to remember, lane positioning, parallel parking and hill starts, and staying calm in the situations that cost people the most points. If something still feels shaky the week before your test, tell us and we\'ll drill it.',
		'features'   => array(
			'For novice drivers ready to leave the N behind',
			'Observation, scanning and shoulder checks',
			'Parallel parking, hill starts and reversing',
			'Speed management and lane positioning',
			'Mock road test with honest feedback',
			'Use our insured dual-control car for your road test',
		),
		'faqs'       => array(
			array(
				'q' => 'When can I take my Class 5 road test?',
				'a' => 'Once you\'ve held your Class 7N for the required time, which is normally 24 months, or 18 if you completed an ICBC-approved driver training course. Our guide to the Graduated Licensing Program explains how the stages fit together.',
			),
			array(
				'q' => 'How is the Class 5 test different from the Class 7?',
				'a' => 'It\'s longer, it happens in heavier traffic, and examiners expect your observation habits to be automatic rather than deliberate. Most people who struggle aren\'t making new mistakes; they\'re making old ones under more pressure.',
			),
			array(
				'q' => 'Will we practise parallel parking?',
				'a' => 'Yes, along with hill starts and reversing. Parallel parking is the one students ask to repeat most often, so we keep at it until the reference points are second nature.',
			),
		),
		'seo_title'  => 'Class 5 Lessons &amp; ICBC Road Test Prep | BuckleUp',
		'seo_desc'   => 'Class 5 driving lessons and ICBC road test preparation in Coquitlam, Port Moody and North Vancouver. Move off your N with confidence.',
	),

	'class-4-driving-lessons' => array(
		'icon'       => 'fas fa-truck',
		'nav_label'  => 'Class 4 Lessons',
		'card_title' => 'Class 4 Commercial Lessons',
		'h1'         => 'Class 4 Commercial <span class="gradient-text">Driving Lessons</span>',
		'eyebrow'    => 'Taxi, Ride-Hailing &amp; Small Bus',
		'short'      => 'Driving for work? Class 4 training for taxi, ride-hailing and small-bus drivers, paired with our free ICBC Class 4 knowledge test practice exam.',
		'long'       => 'A Class 4 licence is what BC requires to drive professionally with passengers. The restricted version covers taxis, ride-hailing vehicles, limousines, ambulances and any vehicle seating up to 10 people including the driver. The unrestricted version adds buses seating up to 25 including the driver, plus school and special-activity buses. We start with the knowledge test, because that\'s the part people underestimate, then move on to what an examiner expects from a professional driver: passenger safety, proper vehicle checks and the defensive habits that matter when driving is your job rather than your commute.',
		'features'   => array(
			'Restricted and unrestricted Class 4',
			'Taxi, ride-hailing and small-bus drivers',
			'Free ICBC Class 4 knowledge test practice exam',
			'Pre-trip vehicle checks and passenger safety',
			'Defensive driving for professional drivers',
		),
		'faqs'       => array(
			array(
				'q' => 'Do I need restricted or unrestricted Class 4?',
				'a' => 'Restricted covers taxis, ride-hailing, limousines and vehicles seating up to 10 including the driver, which is what most drivers need. Choose unrestricted if you plan to drive a bus seating up to 25, a school bus or a special-activity bus.',
			),
			array(
				'q' => 'Is there a knowledge test for Class 4?',
				'a' => 'Yes, and it\'s the step people underestimate. Our free ICBC Class 4 practice test has 231 questions across all 12 official topics, so you can find your weak spots before you book the real thing.',
			),
			array(
				'q' => 'Do I need a Class 5 licence first?',
				'a' => 'You need a full Class 5, not an N, before you can hold a Class 4. If you\'re still on your novice licence, start with Class 5 road test preparation and come back to Class 4 afterwards.',
			),
		),
		'seo_title'  => 'Class 4 Driving Lessons in BC | BuckleUp',
		'seo_desc'   => 'Class 4 commercial driving lessons for taxi, ride-hailing and small-bus drivers in Metro Vancouver, plus a free ICBC Class 4 knowledge test practice exam.',
	),

	'highway-driving-lessons' => array(
		'icon'       => 'fas fa-road',
		'nav_label'  => 'Highway Lessons',
		'card_title' => 'Highway Driving Lessons',
		'h1'         => 'Highway Driving <span class="gradient-text">Lessons</span>',
		'eyebrow'    => 'Confidence at Speed',
		'short'      => 'Nervous about merging at 90 km/h? Dedicated highway sessions that make high-speed driving feel routine instead of daunting.',
		'long'       => 'Plenty of drivers are perfectly comfortable on city streets and still plan their whole week around avoiding the highway. These lessons deal with that head on: judging gaps and merging at speed, holding a safe following distance, changing lanes with trucks around you, and knowing what to do when traffic slows without warning. We build up gradually, starting on quieter stretches and moving to busier routes once the basics stop taking any thought.',
		'features'   => array(
			'Merging and exiting at highway speed',
			'Judging gaps and following distance',
			'Lane discipline around heavy vehicles',
			'Handling congestion and sudden slowdowns',
			'Suitable for new and returning drivers',
		),
		'faqs'       => array(
			array(
				'q' => 'I already have my licence but avoid highways. Is that normal?',
				'a' => 'Very. Plenty of licensed drivers stick to routes that keep them off the highway for years. Avoiding it is a habit more than a skill gap, and habits respond well to a few structured sessions.',
			),
			array(
				'q' => 'Where do we practise?',
				'a' => 'We start on quieter stretches at off-peak times and work up to busier routes as your confidence builds. You won\'t be dropped into heavy traffic before you\'re ready for it.',
			),
			array(
				'q' => 'How many sessions will I need?',
				'a' => 'Most drivers feel noticeably better after two or three focused sessions. We\'ll tell you honestly when more practice would help, and when you\'re simply ready to go and do it.',
			),
		),
		'seo_title'  => 'Highway Driving Lessons in Vancouver | BuckleUp',
		'seo_desc'   => 'Highway driving lessons for nervous or inexperienced drivers in Coquitlam, Port Moody and North Vancouver. Learn merging, lane changes and speed management.',
	),

	'refresher-driving-lessons' => array(
		'icon'       => 'fas fa-heart',
		'nav_label'  => 'Refresher Lessons',
		'card_title' => 'Refresher &amp; Nervous-Driver Lessons',
		'h1'         => 'Refresher &amp; <span class="gradient-text">Nervous-Driver Lessons</span>',
		'eyebrow'    => 'No Pressure, No Judgement',
		'short'      => 'Licensed but out of practice, or anxious behind the wheel? Calm, unhurried lessons that rebuild your confidence at whatever pace suits you.',
		'long'       => 'Not everyone learning with us is a teenager going for a first licence. Some people have held a licence for years but stopped driving after a move, a break or a collision. Others are new to Canada and getting used to unfamiliar roads and their first winter. And some have simply never felt comfortable behind the wheel, whatever their licence says. These lessons are deliberately unhurried. We go at your pace, go back over whatever feels shaky, and treat nerves as completely normal rather than something you have to push through.',
		'features'   => array(
			'Returning after a break from driving',
			'Anxious and nervous drivers welcome',
			'New to Canada and adjusting to BC roads',
			'Rebuilding specific skills at your own pace',
			'Lessons available in English and Farsi',
		),
		'faqs'       => array(
			array(
				'q' => 'I haven\'t driven in years. Where do we start?',
				'a' => 'With a quiet street and no agenda. The first session is mostly about finding out what\'s stayed with you, which is usually more than people expect, so we can spend the rest of the time on whatever actually feels rusty.',
			),
			array(
				'q' => 'I get anxious behind the wheel. Will that be a problem?',
				'a' => 'No. Nerves are common, and we treat them as normal rather than something to push through. Lessons stay unhurried, we stop when you want to stop, and nothing happens before you\'re ready for it.',
			),
			array(
				'q' => 'I\'m new to Canada. Do I need lessons to drive here?',
				'a' => 'It depends on the licence you already hold; some can be exchanged directly. Our guide to exchanging a foreign licence explains the process, and lessons help most with the things that differ: winter conditions, road markings and local habits.',
			),
		),
		'seo_title'  => 'Refresher &amp; Nervous Driver Lessons | BuckleUp',
		'seo_desc'   => 'Patient refresher and nervous-driver lessons in Coquitlam, Port Moody and North Vancouver, for drivers returning after a break or new to Canada.',
	),

);
