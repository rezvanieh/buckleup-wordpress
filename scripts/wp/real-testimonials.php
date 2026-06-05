<?php
/**
 * Canonical list of the REAL Google reviews for Buckle Up Driving School,
 * extracted 2026-06-05 from the Google Maps business listing
 * (https://maps.app.goo.gl/ share links, place CID 0xdda9f6057f7b0fdb).
 *
 * Single source of truth: required by scripts/wp/seed-content.php (so `make
 * reset` reproduces the same testimonials) AND by the one-off prod applier.
 *
 * - Review TEXT is verbatim (original English; Google's auto-translation was
 *   bypassed via hl=en). Do not paraphrase — these are real people's words.
 * - Display NAMES are lightly title-cased for polish (client choice); the two
 *   non-name Google handles were cleaned: "Cineplex_customer" -> "Verified
 *   Customer", "Hossein TTm" -> "Hossein T.".
 * - role is the card subtitle. Client chose "Google Review" for every card.
 * - menu_order = display order (newest first).
 *
 * @return array<int,array{slug:string,name:string,role:string,rating:int,quote:string}>
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

return array(
	array( 'slug' => 'rev-muge-tiritoglu',      'name' => 'Mügé Tiritoğlu',        'role' => 'Google Review', 'rating' => 5, 'quote' => 'Farhad is an excellent driving instructor, he taught me a lot of good driving skills and made sure I pass my road tests.' ),
	array( 'slug' => 'rev-mahsa-zare',          'name' => 'Mahsa Zare',            'role' => 'Google Review', 'rating' => 5, 'quote' => 'Farhad is an amazing driving instructor. professional, patient, and very knowledgeable. He explains everything clearly, pays attention to details, and teaches in a way that actually helps you become a better and safer driver. I learned a lot from him and would highly recommend him to anyone looking for a great driving instructor. Passed my first road test after only 10 hours of practice with him 🙌' ),
	array( 'slug' => 'rev-roya-khazaee',        'name' => 'Roya Khazaee',          'role' => 'Google Review', 'rating' => 5, 'quote' => 'Farhad is honestly one of the best teachers I’ve ever had. His calm personality, great techniques, and the confidence he gives you while driving make such a huge difference. He really helped reduce my anxiety behind the wheel and made me feel comfortable and confident while learning. Thanks to his guidance, I passed my test today. Thank you so much, Farhad — highly recommended!' ),
	array( 'slug' => 'rev-verified-customer',   'name' => 'Verified Customer',     'role' => 'Google Review', 'rating' => 5, 'quote' => 'Farhad is a very good experienced driving instructor and especially for my successful class 4 commercial vehicle road test.' ),
	array( 'slug' => 'rev-rojina-alipour',      'name' => 'Rojina Alipour',        'role' => 'Google Review', 'rating' => 5, 'quote' => 'Amazing instructor! I got my N on the first try. Definitely recommend him.' ),
	array( 'slug' => 'rev-elham-hosseini',      'name' => 'Elham Hosseini',        'role' => 'Google Review', 'rating' => 5, 'quote' => 'I am deeply grateful to my instructor for their wonderful teaching in Class 4 and 5. Thanks to their exceptional professionalism, kindness, and experience, I was able to pass successfully. I highly recommend him to anyone, looking to get these driving licenses.' ),
	array( 'slug' => 'rev-arman',               'name' => 'Arman',                 'role' => 'Google Review', 'rating' => 5, 'quote' => 'I took 4 sessions with Farhad and within 2 driving lessons he helped perfect my turnings and parallel parking. He is a very nice and extremely intelligent driver who can everybody learn to drive.' ),
	array( 'slug' => 'rev-sana-mok',            'name' => 'Sana Mok',              'role' => 'Google Review', 'rating' => 5, 'quote' => 'Farhad is a great instructor. He is patient and explains everything clearly. Thanks to his lessons, I passed my driving test on the first try. Highly recommend!' ),
	array( 'slug' => 'rev-sam-tavanaei',        'name' => 'Sam Tavanaei Tabrizi',  'role' => 'Google Review', 'rating' => 5, 'quote' => 'For the first time, I took my driving test, and with the training from this group, I passed. I had never driven in Canada before, but I only had two sessions with them and one warm-up session before the exam. I even used their car for the test. Mr. Farhad was incredibly patient and supportive, and he gave me very helpful tips that made a big difference. I passed on my first attempt, and I highly recommend him.' ),
	array( 'slug' => 'rev-mozhgan-ghanbari',    'name' => 'Mozhgan Ghanbari',      'role' => 'Google Review', 'rating' => 5, 'quote' => 'I had a great experience learning with my driving instructor, Farhad. He was very patient, professional, and explained everything clearly. He helped me feel confident and comfortable while driving. I really appreciate his support and teaching style. I highly recommend him to anyone who wants to learn driving properly. Thank you Farhad.' ),
	array( 'slug' => 'rev-ouldouze-m',          'name' => 'Ouldouze Mokhtarzadeh', 'role' => 'Google Review', 'rating' => 5, 'quote' => 'Learning to drive with Farhad was an amazing experience. He is calm, patient, and provides comprehensive explanations of everything. He gave me constant advice on how to get better at driving and made me feel more comfortable behind the wheel. His competent and encouraging teaching style made learning much simpler and less stressful. To anyone who wishes to learn how to drive safely and confidently, I heartily recommend Farhad.' ),
	array( 'slug' => 'rev-bahram-hedayati',     'name' => 'Bahram Hedayati',       'role' => 'Google Review', 'rating' => 5, 'quote' => 'A very professional and patient instructor. He explains things clearly and helps build confidence quickly. I passed my test thanks to his guidance. Highly recommended!' ),
	array( 'slug' => 'rev-elnaz-fotoohi',       'name' => 'Elnaz Fotoohi',         'role' => 'Google Review', 'rating' => 5, 'quote' => 'I had one lesson with Mr. Farhad before my road test and it was very helpful. He explained everything clearly and helped me feel more confident. I passed my test after that lesson. Highly recommended!' ),
	array( 'slug' => 'rev-tabassom-asghari',    'name' => 'Tabassom Asghari Zola', 'role' => 'Google Review', 'rating' => 5, 'quote' => 'I had a great experience with Farhad teacher. He was very calm, patient, and explained everything clearly. I highly recommend them to anyone looking for a supportive and professional driving teacher. Thanks again.' ),
	array( 'slug' => 'rev-hossein-t',           'name' => 'Hossein T.',            'role' => 'Google Review', 'rating' => 5, 'quote' => 'Very supportive and patience and precise about what are on the road test' ),
	array( 'slug' => 'rev-maziar-sabouri',      'name' => 'Maziar Sabouri',        'role' => 'Google Review', 'rating' => 5, 'quote' => 'I had a very good experience with Mr. Farhad Sanaiefar. Highly recommended!' ),
	array( 'slug' => 'rev-rezvan-talebi',       'name' => 'Rezvan Talebi',         'role' => 'Google Review', 'rating' => 5, 'quote' => 'Excellent instructor! Very patient, clear in explanations, and always encouraging. I gained confidence quickly and passed my test thanks to their great teaching. Highly recommended!' ),
);
