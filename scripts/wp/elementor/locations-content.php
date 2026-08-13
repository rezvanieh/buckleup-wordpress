<?php
/**
 * BuckleUp — Location landing-page CONTENT (single source of truth).
 *
 * Pure data file: returns an associative array keyed by the 5 `location` CPT
 * slugs. Consumed by the Elementor location builder (the visible page body) and
 * mirrored by the SEO mu-plugin (`10-buckleup-seo.php`) for per-location JSON-LD
 * (LocalBusiness name/areaServed/geo + a location-specific FAQPage) and by
 * `seo-config.php` for the per-location Rank Math title/description.
 *
 * KEEPING ONE SOURCE: the `faqs` here are the same Q&A the FAQPage schema emits
 * on each location page, so the visible FAQ and the structured data never drift.
 *
 * Brand facts are verbatim from the live site (inc/site.php, home-hero.php,
 * 10-buckleup-seo.php): ICBC-certified; 4.98★ on 200+ reviews;
 * lead instructor Farhad Sanaeifar; (604) 441-3677; WhatsApp 16044413677;
 * Class 7L / 7N / 5 / 4 training in modern Toyota vehicles; founded 2014
 * (→ "10+ years" is conservative/accurate as of 2026). NO fabricated reviews,
 * addresses, or exact ICBC test-centre street numbers — the Tri-Cities are
 * served by the Coquitlam ICBC Driver Licensing office; North Vancouver has its
 * own North Shore office (named by area, not an unverified street address).
 *
 * @package BuckleUp_SEO
 */

return array(

	/* =====================================================================
	 * COQUITLAM — landmark: Lafarge Lake / Town Centre Park
	 * ================================================================== */
	'coquitlam' => array(
		'city'           => 'Coquitlam',
		'landmark'       => 'Lafarge Lake',
		'hero_eyebrow'   => 'ICBC-Certified Driving School in Coquitlam',
		'hero_title'     => 'Driving Lessons in',
		'hero_highlight' => 'Coquitlam',
		'hero_subtitle'  => 'From the Town Centre core by Lafarge Lake to the climbs of Westwood Plateau, BuckleUp\'s ICBC-certified instructors know every Coquitlam road test route. Learn in a modern Toyota and pass with confidence.',
		'hero_stats'     => array(
			array( 'value' => '4.98★', 'label' => '200+ Google reviews' ),
			array( 'value' => '10+',   'label' => 'years on local roads' ),
		),
		'intro_heading'  => 'Learn to Drive in Coquitlam with Confidence',
		'intro_body'     => array(
			'Coquitlam is one of the most varied places in Metro Vancouver to learn to drive, and that is exactly why local students choose BuckleUp. In a single lesson you can move from the busy Town Centre grid around Lafarge Lake and Coquitlam Centre to the steep, winding residential streets of Westwood Plateau, then onto the high-speed merges of the Lougheed and Barnet Highways. Mastering that range is what makes a Coquitlam-trained driver test-ready anywhere in the Lower Mainland.',
			'Our instructors live and teach on these roads every day. We tailor each lesson to where you are — total beginners start with vehicle control and parking in quiet Burquitlam and Maillardville side streets, while road-test candidates drill the exact intersections, lane changes, and hill starts the ICBC examiner is likely to use. Lessons run in calm, reliable Toyota training vehicles with a patient, ICBC-certified instructor beside you.',
			'With a 4.98★ rating from 200+ Google reviews, BuckleUp has helped Coquitlam learners earn their Class 7 and Class 5 licences for over a decade. Whether you are a nervous new driver, a teen working toward your L or N, or a newcomer converting a licence, we build a plan around your goals.',
		),
		'why_heading'    => 'Why Coquitlam Drivers Choose BuckleUp',
		'why_items'      => array(
			array( 'icon' => 'fas fa-map-location-dot', 'title' => 'We Know Coquitlam Roads',      'desc' => 'From Lougheed Highway merges to the Westwood Plateau hills and the Town Centre one-way grid, we train on the real routes you\'ll be tested on.' ),
			array( 'icon' => 'fas fa-award',            'title' => 'ICBC-Certified Instructors',   'desc' => 'Led by senior instructor Farhad Sanaeifar, our team is fully ICBC-certified and teaches these roads every day.' ),
			array( 'icon' => 'fas fa-car-side',         'title' => 'Modern Toyota Vehicles',       'desc' => 'Calm, reliable, dual-control Toyotas that are easy to handle — ideal for first-time and nervous drivers.' ),
			array( 'icon' => 'fas fa-language',         'title' => 'English & Farsi Lessons',      'desc' => 'Clear instruction in English and Farsi so nothing gets lost — a real advantage for Coquitlam\'s diverse community.' ),
		),
		'neighborhoods_heading' => 'Neighbourhoods We Serve in Coquitlam',
		'neighborhoods'  => array( 'Town Centre', 'Burquitlam', 'Maillardville', 'Westwood Plateau', 'Eagle Ridge', 'Coquitlam Centre', 'Austin Heights', 'Ranch Park', 'River Springs', 'Como Lake', 'Harbour Chines', 'Cape Horn' ),
		'icbc'           => array(
			'heading' => 'ICBC Road Test Prep in Coquitlam',
			'centre'  => 'Coquitlam ICBC Driver Licensing office (the main test centre serving the Tri-Cities)',
			'body'    => array(
				'Most Coquitlam road tests are booked out of the Coquitlam ICBC Driver Licensing office, which also serves Port Coquitlam and Port Moody. Its routes weave through the Town Centre core and surrounding residential streets, so examiners frequently test multi-lane changes near Pinetree Way and Lougheed Highway, uphill and downhill starts toward Westwood Plateau, and parallel and hill parking on tight side streets.',
			),
			'tips'    => array(
				'Practise lane changes and merges along Lougheed Highway and Barnet Highway — examiners watch your shoulder checks closely here.',
				'Get comfortable with hill starts and downhill control on the Westwood Plateau and Eagle Ridge inclines.',
				'Rehearse parallel and hill parking on the narrow residential streets around the Town Centre test-start area.',
				'Know the school, playground, and pedestrian zones near Town Centre Park and Lafarge Lake — speed control there is heavily scored.',
			),
		),
		'faqs'           => array(
			array( 'q' => 'Where do you offer driving lessons in Coquitlam?', 'a' => 'We offer lessons across all of Coquitlam, including Town Centre, Burquitlam, Maillardville, Westwood Plateau, Eagle Ridge, and Austin Heights. We can pick you up from home, work, school, or a SkyTrain station.' ),
			array( 'q' => 'Do you prepare students for the Coquitlam ICBC road test?', 'a' => 'Yes. We train directly on the routes used by the Coquitlam ICBC Driver Licensing office — the same multi-lane changes, hill starts, and parking manoeuvres the examiner will ask for — so you walk in already familiar with the area.' ),
			array( 'q' => 'How many lessons will I need to pass in Coquitlam?', 'a' => 'Most beginners need around six to ten lessons depending on prior experience. After a free assessment, your instructor will recommend a realistic plan for the Coquitlam test routes.' ),
			array( 'q' => 'Do you offer driving lessons in Farsi in Coquitlam?', 'a' => 'Yes. Lessons are available in both English and Farsi, which many students in Coquitlam\'s diverse community find makes learning faster and less stressful.' ),
			array( 'q' => 'What licence classes can I train for in Coquitlam?', 'a' => 'We provide training for Class 7L (learner), Class 7N (novice), Class 5 (full licence), and Class 4 (commercial), all with ICBC-certified instructors and modern Toyota vehicles.' ),
		),
		'cta_heading'    => 'Ready to Start Driving in Coquitlam?',
		'cta_body'       => 'Book your first lesson today and train with ICBC-certified instructors who know every Coquitlam test route.',
		// ---- SEO ----
		'seo_title'      => 'Driving Lessons in Coquitlam | BuckleUp Driving School',
		'seo_description' => 'Driving lessons in Coquitlam with ICBC-certified instructors rated 4.98★. Master test routes from Town Centre to Westwood Plateau. Book today!',
		'geo'            => array( 'lat' => 49.2838, 'lng' => -122.7932 ),
		'area_served'    => array( 'Coquitlam', 'Burquitlam', 'Westwood Plateau', 'Maillardville', 'Eagle Ridge' ),
		'keywords'       => array( 'driving lessons coquitlam', 'driving school coquitlam', 'icbc road test coquitlam', 'driving instructor coquitlam' ),
	),

	/* =====================================================================
	 * NORTH VANCOUVER — landmark: Lonsdale Quay & the Shipyards waterfront
	 * ================================================================== */
	'north-vancouver' => array(
		'city'           => 'North Vancouver',
		'landmark'       => 'Lonsdale Quay & the Shipyards',
		'hero_eyebrow'   => 'ICBC-Certified Driving School in North Vancouver',
		'hero_title'     => 'Driving Lessons in',
		'hero_highlight' => 'North Vancouver',
		'hero_subtitle'  => 'From the Lonsdale waterfront to the steep climbs toward Grouse Mountain and the Upper Levels Highway, BuckleUp prepares you for the North Shore\'s hills, bridges, and weather with ICBC-certified instructors.',
		'hero_stats'     => array(
			array( 'value' => '4.98★', 'label' => '200+ Google reviews' ),
			array( 'value' => '10+',   'label' => 'years on local roads' ),
		),
		'intro_heading'  => 'Learn to Drive on the North Shore with Confidence',
		'intro_body'     => array(
			'Driving in North Vancouver is unlike anywhere else in Metro Vancouver. The North Shore rises sharply from the Lonsdale Quay and Shipyards waterfront up toward Grouse Mountain, so almost every route involves real hills, steep starts, and changing grades. Add the Upper Levels Highway, the bridge approaches to the Lions Gate and Ironworkers, and the wet, sometimes icy mountain weather, and you have a place where genuine local training makes all the difference.',
			'BuckleUp\'s ICBC-certified instructors specialise in exactly these conditions. We teach confident hill starts and downhill braking on the Lonsdale and Lynn Valley grades, smooth merging onto the Upper Levels Highway, and calm, decisive handling of bridge traffic. Beginners build their foundations on quieter streets in Central and Lower Lonsdale before progressing to the busier corridors and the test routes themselves.',
			'With a 4.98★ rating from 200+ Google reviews, we have helped North Vancouver students earn their Class 7 and Class 5 licences for more than a decade. Lessons run in modern, dual-control Toyota vehicles, and instruction is available in both English and Farsi.',
		),
		'why_heading'    => 'Why North Vancouver Drivers Choose BuckleUp',
		'why_items'      => array(
			array( 'icon' => 'fas fa-mountain', 'title' => 'Hill & Mountain Expertise',  'desc' => 'We drill hill starts, downhill control, and steep-grade confidence on the real Lonsdale and Lynn Valley inclines.' ),
			array( 'icon' => 'fas fa-bridge',   'title' => 'Bridge & Highway Ready',     'desc' => 'Master the Upper Levels Highway merges and the Lions Gate / Ironworkers bridge approaches that define North Shore driving.' ),
			array( 'icon' => 'fas fa-cloud-rain', 'title' => 'Wet-Weather Skills',       'desc' => 'The North Shore gets the region\'s heaviest rain — we teach safe braking, following distance, and visibility habits for it.' ),
			array( 'icon' => 'fas fa-award',    'title' => 'ICBC-Certified Instructors', 'desc' => 'Fully ICBC-certified, led by senior instructor Farhad Sanaeifar.' ),
		),
		'neighborhoods_heading' => 'Neighbourhoods We Serve in North Vancouver',
		'neighborhoods'  => array( 'Lower Lonsdale', 'Central Lonsdale', 'Lynn Valley', 'Lynn Creek', 'Deep Cove', 'Capilano', 'Edgemont Village', 'Pemberton Heights', 'Norgate', 'Seymour', 'Grand Boulevard', 'Blueridge' ),
		'icbc'           => array(
			'heading' => 'ICBC Road Test Prep in North Vancouver',
			'centre'  => 'North Vancouver ICBC Driver Licensing office (the North Shore test centre)',
			'body'    => array(
				'North Vancouver road tests run out of the North Shore\'s own ICBC Driver Licensing office, and the routes reflect the area\'s terrain. Expect hill starts and parking on graded streets, merging and lane changes on busy arterials like Lonsdale Avenue and Marine Drive, and careful speed control through the many school and pedestrian zones around Central Lonsdale and Lynn Valley.',
			),
			'tips'    => array(
				'Practise hill parking and hill starts repeatedly — North Van examiners almost always test them given the terrain.',
				'Get smooth at merging onto Marine Drive and the Upper Levels Highway with confident shoulder checks.',
				'Rehearse the Lonsdale Avenue corridor, where lane changes and bus traffic require precise timing.',
				'Practise in wet conditions when you can — gentle braking and extra following distance are habits examiners reward.',
			),
		),
		'faqs'           => array(
			array( 'q' => 'Where do you offer driving lessons in North Vancouver?', 'a' => 'We cover all of North Vancouver, including Lower and Central Lonsdale, Lynn Valley, Deep Cove, Capilano, and Edgemont Village, with pickup from home, work, school, or the SeaBus terminal.' ),
			array( 'q' => 'Do you teach hill starts and steep-grade driving?', 'a' => 'Yes — and it is a core part of every North Shore lesson. We practise hill starts, downhill braking, and hill parking on the real Lonsdale and Lynn Valley grades the ICBC examiner uses.' ),
			array( 'q' => 'Can you prepare me for the North Vancouver ICBC road test?', 'a' => 'Absolutely. We train on the routes used by the North Vancouver ICBC Driver Licensing office so you are already familiar with the hills, arterials, and parking spots before test day.' ),
			array( 'q' => 'Do you offer driving lessons in Farsi in North Vancouver?', 'a' => 'Yes. Lessons are available in both English and Farsi for clearer, more comfortable learning.' ),
			array( 'q' => 'How do you handle North Shore wet weather in lessons?', 'a' => 'We deliberately build wet-weather skills — safe braking distances, smooth steering, and visibility management — because the North Shore sees the region\'s heaviest rainfall and the road test happens rain or shine.' ),
		),
		'cta_heading'    => 'Ready to Conquer the North Shore?',
		'cta_body'       => 'Book your first North Vancouver lesson and train on the hills, bridges, and routes that matter most.',
		// ---- SEO ----
		'seo_title'      => 'Driving Lessons in North Vancouver | BuckleUp School',
		'seo_description' => 'Master North Shore hills, bridges & test routes with ICBC-certified instructors rated 4.98★. Driving lessons in North Vancouver. Book today!',
		'geo'            => array( 'lat' => 49.3200, 'lng' => -123.0724 ),
		'area_served'    => array( 'North Vancouver', 'Lonsdale', 'Lynn Valley', 'Deep Cove', 'Capilano' ),
		'keywords'       => array( 'driving lessons north vancouver', 'driving school north vancouver', 'icbc road test north vancouver', 'north shore driving instructor' ),
	),

	/* =====================================================================
	 * PORT COQUITLAM — landmark: Coast Meridian Overpass (cable-stayed bridge)
	 * ================================================================== */
	'port-coquitlam' => array(
		'city'           => 'Port Coquitlam',
		'landmark'       => 'Coast Meridian Overpass',
		'hero_eyebrow'   => 'ICBC-Certified Driving School in Port Coquitlam',
		'hero_title'     => 'Driving Lessons in',
		'hero_highlight' => 'Port Coquitlam',
		'hero_subtitle'  => 'From the Coast Meridian Overpass to the rail crossings and the Traboulay PoCo Trail neighbourhoods, BuckleUp\'s ICBC-certified instructors know every Port Coquitlam route. Learn in a modern Toyota and pass first time.',
		'hero_stats'     => array(
			array( 'value' => '4.98★', 'label' => '200+ Google reviews' ),
			array( 'value' => '10+',   'label' => 'years on local roads' ),
		),
		'intro_heading'  => 'Learn to Drive in Port Coquitlam with Confidence',
		'intro_body'     => array(
			'Port Coquitlam has a driving character all its own. The white cable-stayed Coast Meridian Overpass connects the city across the rail yards, and railway crossings, the Lougheed Highway corridor, and tight residential grids around Downtown PoCo and the Traboulay PoCo Trail all feature in everyday driving here. Learning these specifics with a local instructor is what gets PoCo students confident and test-ready.',
			'At BuckleUp we tailor every lesson to where you are. New drivers begin with vehicle control and parking on the quieter streets of Citadel Heights, Riverwood, and Mary Hill, then progress to the busier Shaughnessy Street and Lougheed Highway corridors and the rail crossings that demand careful, deliberate handling. Road-test candidates rehearse the exact manoeuvres and intersections examiners favour.',
			'Backed by a 4.98★ rating from 200+ Google reviews, BuckleUp has guided Port Coquitlam learners through their Class 7 and Class 5 tests for over a decade. Lessons run in calm, dual-control Toyota vehicles with ICBC-certified instructors, and instruction is available in English and Farsi.',
		),
		'why_heading'    => 'Why Port Coquitlam Drivers Choose BuckleUp',
		'why_items'      => array(
			array( 'icon' => 'fas fa-train',            'title' => 'Rail-Crossing Confidence',    'desc' => 'PoCo has more level rail crossings than most cities — we teach the exact stopping and scanning habits examiners look for.' ),
			array( 'icon' => 'fas fa-map-location-dot',  'title' => 'We Know PoCo Routes',         'desc' => 'From the Coast Meridian Overpass to Shaughnessy Street and the Lougheed corridor, we train on the roads you\'ll be tested on.' ),
			array( 'icon' => 'fas fa-car-side',          'title' => 'Modern Toyota Vehicles',      'desc' => 'Reliable, dual-control Toyotas that are forgiving and easy to learn in for first-time and nervous drivers.' ),
			array( 'icon' => 'fas fa-award',             'title' => 'ICBC-Certified Instructors',  'desc' => 'Fully ICBC-certified, led by senior instructor Farhad Sanaeifar.' ),
		),
		'neighborhoods_heading' => 'Neighbourhoods We Serve in Port Coquitlam',
		'neighborhoods'  => array( 'Downtown PoCo', 'Citadel Heights', 'Mary Hill', 'Riverwood', 'Birchland Manor', 'Lincoln Park', 'Central PoCo', 'Oxford Heights', 'Glenwood', 'Woodland Acres', 'Sun Valley', 'Riverwood' ),
		'icbc'           => array(
			'heading' => 'ICBC Road Test Prep in Port Coquitlam',
			'centre'  => 'Coquitlam ICBC Driver Licensing office (serves Port Coquitlam and the wider Tri-Cities)',
			'body'    => array(
				'Port Coquitlam road tests are booked through the Coquitlam ICBC Driver Licensing office, which serves the whole Tri-Cities area. For PoCo drivers that means routes featuring level rail crossings, the Shaughnessy Street and Lougheed Highway corridors, and parking and lane-change work on the residential grids near Downtown PoCo — all of which we practise directly with you.',
			),
			'tips'    => array(
				'Master railway crossings: come to a controlled stop where required, scan both ways, and never stop on the tracks.',
				'Practise lane changes and turns along Shaughnessy Street and the Lougheed Highway corridor.',
				'Rehearse parallel and stall parking on the residential streets around Downtown PoCo.',
				'Watch your speed through the school and trail-crossing zones near the Traboulay PoCo Trail and local parks.',
			),
		),
		'faqs'           => array(
			array( 'q' => 'Where do you offer driving lessons in Port Coquitlam?', 'a' => 'We serve all of Port Coquitlam, including Downtown PoCo, Citadel Heights, Mary Hill, Riverwood, and Birchland Manor, with pickup from home, work, or school.' ),
			array( 'q' => 'Do you teach how to handle PoCo\'s rail crossings?', 'a' => 'Yes. Port Coquitlam has many level rail crossings, and we make them a focus — proper stopping, scanning, and never stopping on the tracks are exactly what examiners watch for.' ),
			array( 'q' => 'Where will I take my Port Coquitlam road test?', 'a' => 'Most PoCo road tests are booked at the Coquitlam ICBC Driver Licensing office, which serves the Tri-Cities. We train on those routes so you arrive familiar and confident.' ),
			array( 'q' => 'How many lessons do I need to pass in Port Coquitlam?', 'a' => 'Most beginners need around six to ten lessons. After a quick assessment, your instructor will give you an honest plan for the PoCo test routes.' ),
			array( 'q' => 'Do you offer lessons in Farsi in Port Coquitlam?', 'a' => 'Yes. Lessons are available in both English and Farsi for clearer communication and faster learning.' ),
		),
		'cta_heading'    => 'Ready to Start Driving in Port Coquitlam?',
		'cta_body'       => 'Book your first lesson today and train with instructors who know every PoCo route and rail crossing.',
		// ---- SEO ----
		'seo_title'      => 'Driving Lessons in Port Coquitlam | BuckleUp School',
		'seo_description' => 'Driving lessons in Port Coquitlam with ICBC-certified instructors rated 4.98★. Master PoCo rail crossings and local test routes. Book today!',
		'geo'            => array( 'lat' => 49.2620, 'lng' => -122.7811 ),
		'area_served'    => array( 'Port Coquitlam', 'Citadel Heights', 'Mary Hill', 'Riverwood', 'Birchland Manor' ),
		'keywords'       => array( 'driving lessons port coquitlam', 'driving school port coquitlam', 'icbc road test port coquitlam', 'poco driving instructor' ),
	),

	/* =====================================================================
	 * PORT MOODY — landmark: Rocky Point Park & Burrard Inlet (HOME BASE)
	 * ================================================================== */
	'port-moody' => array(
		'city'           => 'Port Moody',
		'landmark'       => 'Rocky Point Park & Burrard Inlet',
		'hero_eyebrow'   => 'Your Local ICBC-Certified Driving School in Port Moody',
		'hero_title'     => 'Driving Lessons in',
		'hero_highlight' => 'Port Moody',
		'hero_subtitle'  => 'Port Moody is our home. From Rocky Point Park and the Burrard Inlet shoreline to the Newport Village SkyTrain area and the Heritage Mountain climbs, BuckleUp\'s ICBC-certified instructors know every local route inside out.',
		'hero_stats'     => array(
			array( 'value' => '4.98★', 'label' => '200+ Google reviews' ),
			array( 'value' => '10+',   'label' => 'years on local roads' ),
		),
		'intro_heading'  => 'Learn to Drive in Port Moody with Confidence',
		'intro_body'     => array(
			'Port Moody is where BuckleUp is based, and no one knows these streets better. The city wraps around the eastern end of Burrard Inlet, from the waterfront at Rocky Point Park up the steep grades of Heritage Mountain and College Park, with the busy St. Johns Street and Barnet Highway corridors carrying traffic through the middle. Add the Evergreen SkyTrain stations at Inlet Centre and Moody Centre, and you have a compact but genuinely challenging place to learn.',
			'Because Port Moody is our home base, our instructors teach on these exact roads every single day. We start nervous and first-time drivers with vehicle control and parking on the quieter streets of Glenayre, College Park, and Heritage Woods, then build up to the Heritage Mountain hill starts, the St. Johns Street lane changes, and the Barnet and Ioco Road corridors. Road-test candidates rehearse the precise manoeuvres Port Moody examiners look for.',
			'With a 4.98★ rating from 200+ Google reviews and over a decade serving the community, BuckleUp is the trusted local choice for Class 7 and Class 5 training. Lessons run in modern, dual-control Toyota vehicles with ICBC-certified instructors, and we teach in both English and Farsi.',
		),
		'why_heading'    => 'Why Port Moody Drivers Choose BuckleUp',
		'why_items'      => array(
			array( 'icon' => 'fas fa-house-chimney',     'title' => 'Your Local School',          'desc' => 'BuckleUp is based right here in Port Moody — we know every test route, hill, and tricky intersection in the city.' ),
			array( 'icon' => 'fas fa-mountain',          'title' => 'Heritage Mountain Hills',    'desc' => 'We drill hill starts and downhill control on the real Heritage Mountain and College Park grades the examiner uses.' ),
			array( 'icon' => 'fas fa-car-side',          'title' => 'Modern Toyota Vehicles',     'desc' => 'Calm, reliable, dual-control Toyotas that build confidence for first-time and nervous drivers.' ),
			array( 'icon' => 'fas fa-award',             'title' => 'ICBC-Certified Instructors', 'desc' => 'Fully ICBC-certified, led by senior instructor Farhad Sanaeifar.' ),
		),
		'neighborhoods_heading' => 'Neighbourhoods We Serve in Port Moody',
		'neighborhoods'  => array( 'Moody Centre', 'Inlet Centre', 'Newport Village', 'Heritage Mountain', 'Heritage Woods', 'College Park', 'Glenayre', 'Pleasantside', 'Anmore (nearby)', 'Ioco', 'Seaview', 'Mountain Meadows' ),
		'icbc'           => array(
			'heading' => 'ICBC Road Test Prep in Port Moody',
			'centre'  => 'Coquitlam ICBC Driver Licensing office (the nearest test centre, serving Port Moody and the Tri-Cities)',
			'body'    => array(
				'Port Moody road tests are booked through the nearby Coquitlam ICBC Driver Licensing office, which serves the whole Tri-Cities. For Port Moody drivers, preparation means confident hill starts on the Heritage Mountain and College Park grades, smooth lane changes along St. Johns Street and the Barnet Highway, and precise parking on the residential streets near Moody Centre — all of which we practise with you on home turf.',
			),
			'tips'    => array(
				'Practise hill starts and hill parking on the Heritage Mountain and College Park inclines — they are common on local routes.',
				'Get smooth with lane changes and timing along the busy St. Johns Street corridor.',
				'Rehearse merging onto the Barnet Highway with confident, well-timed shoulder checks.',
				'Mind your speed and pedestrians around Rocky Point Park, Newport Village, and the SkyTrain stations.',
			),
		),
		'faqs'           => array(
			array( 'q' => 'Where do you offer driving lessons in Port Moody?', 'a' => 'Port Moody is our home base, so we cover the entire city — Moody Centre, Inlet Centre, Newport Village, Heritage Mountain, and College Park — with pickup from home, work, school, or a SkyTrain station.' ),
			array( 'q' => 'Is BuckleUp actually located in Port Moody?', 'a' => 'Yes. BuckleUp Driving School is based in Port Moody, which means our instructors teach on these exact streets, hills, and test routes every day.' ),
			array( 'q' => 'Do you teach the Heritage Mountain hill starts?', 'a' => 'Definitely. Hill starts and downhill control on the Heritage Mountain and College Park grades are a core part of Port Moody lessons because examiners frequently test them.' ),
			array( 'q' => 'Where will I take my Port Moody road test?', 'a' => 'Most Port Moody road tests are booked at the nearby Coquitlam ICBC Driver Licensing office, which serves the Tri-Cities. We train you directly on those routes.' ),
			array( 'q' => 'Do you offer driving lessons in Farsi in Port Moody?', 'a' => 'Yes. Lessons are available in both English and Farsi for clearer, more comfortable learning.' ),
		),
		'cta_heading'    => 'Ready to Start Driving in Port Moody?',
		'cta_body'       => 'Book your first lesson today and learn from your local Port Moody driving school.',
		// ---- SEO ----
		'seo_title'      => 'Driving Lessons in Port Moody | BuckleUp Driving School',
		'seo_description' => 'Your local Port Moody driving school. ICBC-certified instructors rated 4.98★, and expert prep for Heritage Mountain hills and test routes. Book today!',
		'geo'            => array( 'lat' => 49.2838, 'lng' => -122.8556 ),
		'area_served'    => array( 'Port Moody', 'Moody Centre', 'Inlet Centre', 'Heritage Mountain', 'Newport Village' ),
		'keywords'       => array( 'driving lessons port moody', 'driving school port moody', 'icbc road test port moody', 'port moody driving instructor' ),
	),

	/* =====================================================================
	 * TRI-CITIES — regional umbrella (Coquitlam + PoCo + Port Moody)
	 * ================================================================== */
	'tri-cities' => array(
		'city'           => 'Tri-Cities',
		'landmark'       => 'the Tri-Cities mountain backdrop',
		'hero_eyebrow'   => 'ICBC-Certified Driving School Across the Tri-Cities',
		'hero_title'     => 'Driving Lessons in the',
		'hero_highlight' => 'Tri-Cities',
		'hero_subtitle'  => 'One trusted school for Coquitlam, Port Coquitlam, and Port Moody. With the mountains at your back and every local test route covered, BuckleUp\'s ICBC-certified instructors get Tri-Cities drivers ready for anything.',
		'hero_stats'     => array(
			array( 'value' => '4.98★', 'label' => '200+ Google reviews' ),
			array( 'value' => '10+',   'label' => 'years on local roads' ),
		),
		'intro_heading'  => 'Driving Lessons Across the Tri-Cities',
		'intro_body'     => array(
			'The Tri-Cities — Coquitlam, Port Coquitlam, and Port Moody — sit together against a dramatic mountain backdrop at the eastern edge of Metro Vancouver, and together they offer just about every driving challenge a new BC driver can face. Steep climbs toward Westwood Plateau and Heritage Mountain, the high-speed Lougheed and Barnet Highway corridors, Port Coquitlam\'s level rail crossings, and the busy Town Centre and Newport Village cores all sit within a short drive of one another.',
			'BuckleUp is the local school that ties the whole region together. Based in Port Moody and active across all three cities, our ICBC-certified instructors train you on the specific routes that matter wherever you live or test. Beginners build their foundations on quiet residential streets, then progress through hill starts, highway merges, rail crossings, and the exact manoeuvres examiners look for — all in calm, dual-control Toyota vehicles.',
			'With a 4.98★ rating from 200+ Google reviews and over a decade serving the Tri-Cities, we have helped thousands of local students earn their Class 7 and Class 5 licences. Lessons are available in English and Farsi, and we pick you up anywhere across Coquitlam, Port Coquitlam, and Port Moody.',
		),
		'why_heading'    => 'Why Tri-Cities Drivers Choose BuckleUp',
		'why_items'      => array(
			array( 'icon' => 'fas fa-map-location-dot',  'title' => 'Every Tri-Cities Route',     'desc' => 'From Westwood Plateau and Heritage Mountain hills to PoCo rail crossings and the Lougheed corridor, we train on them all.' ),
			array( 'icon' => 'fas fa-location-dot',       'title' => 'One School, Three Cities',   'desc' => 'Coquitlam, Port Coquitlam, and Port Moody — one trusted, local school with pickup across the whole region.' ),
			array( 'icon' => 'fas fa-award',              'title' => 'ICBC-Certified Instructors', 'desc' => 'Fully ICBC-certified, led by senior instructor Farhad Sanaeifar.' ),
			array( 'icon' => 'fas fa-language',           'title' => 'English & Farsi Lessons',    'desc' => 'Clear instruction in English and Farsi to suit the Tri-Cities\' diverse community.' ),
		),
		'neighborhoods_heading' => 'Areas We Serve Across the Tri-Cities',
		'neighborhoods'  => array( 'Coquitlam', 'Port Coquitlam', 'Port Moody', 'Town Centre', 'Westwood Plateau', 'Burquitlam', 'Downtown PoCo', 'Citadel Heights', 'Moody Centre', 'Heritage Mountain', 'Newport Village', 'Maillardville' ),
		'icbc'           => array(
			'heading' => 'ICBC Road Test Prep in the Tri-Cities',
			'centre'  => 'Coquitlam ICBC Driver Licensing office (the main test centre for Coquitlam, Port Coquitlam, and Port Moody)',
			'body'    => array(
				'Almost all Tri-Cities road tests are booked through the Coquitlam ICBC Driver Licensing office, which serves Coquitlam, Port Coquitlam, and Port Moody. Its routes combine multi-lane changes near the Town Centre, hill starts toward Westwood Plateau and Heritage Mountain, level rail crossings on the PoCo side, and varied residential parking — so the broadest, most well-rounded preparation pays off, and that is exactly what we provide.',
			),
			'tips'    => array(
				'Be equally confident on hills (Westwood Plateau, Heritage Mountain) and on level rail crossings (Port Coquitlam) — Tri-Cities routes mix both.',
				'Practise multi-lane changes and merges on the Lougheed and Barnet Highway corridors.',
				'Rehearse a full range of parking — parallel, stall, and hill — since residential test streets vary across the three cities.',
				'Know the school and pedestrian zones around Town Centre, Newport Village, and Downtown PoCo where speed control is heavily scored.',
			),
		),
		'faqs'           => array(
			array( 'q' => 'Which cities do you cover in the Tri-Cities?', 'a' => 'We provide driving lessons across all three Tri-Cities — Coquitlam, Port Coquitlam, and Port Moody — with pickup from home, work, school, or a SkyTrain station anywhere in the region.' ),
			array( 'q' => 'Where will I take my Tri-Cities road test?', 'a' => 'Most Tri-Cities road tests are booked at the Coquitlam ICBC Driver Licensing office, which serves Coquitlam, Port Coquitlam, and Port Moody. We train you on those exact routes regardless of which city you live in.' ),
			array( 'q' => 'Do you cover both the hills and the rail crossings?', 'a' => 'Yes. The Tri-Cities mix steep grades (Westwood Plateau, Heritage Mountain) with Port Coquitlam\'s level rail crossings, and our lessons prepare you confidently for both.' ),
			array( 'q' => 'How many lessons will I need to pass in the Tri-Cities?', 'a' => 'Most beginners need around six to ten lessons. After a free assessment, your instructor will recommend a plan based on the test routes you\'ll be driving.' ),
			array( 'q' => 'Do you offer driving lessons in Farsi across the Tri-Cities?', 'a' => 'Yes. Lessons are available in both English and Farsi throughout Coquitlam, Port Coquitlam, and Port Moody.' ),
		),
		'cta_heading'    => 'Ready to Start Driving in the Tri-Cities?',
		'cta_body'       => 'Book your first lesson today with the Tri-Cities\' trusted, ICBC-certified driving school.',
		// ---- SEO ----
		'seo_title'      => 'Driving Lessons in the Tri-Cities | BuckleUp School',
		'seo_description' => 'ICBC-certified driving lessons across Coquitlam, Port Coquitlam & Port Moody. Rated 4.98★ with expert local route prep. Book a lesson today!',
		'geo'            => array( 'lat' => 49.2780, 'lng' => -122.7930 ),
		'area_served'    => array( 'Tri-Cities', 'Coquitlam', 'Port Coquitlam', 'Port Moody' ),
		'keywords'       => array( 'driving lessons tri-cities', 'driving school tri-cities', 'icbc road test tri-cities', 'tri cities driving instructor' ),
	),

);
