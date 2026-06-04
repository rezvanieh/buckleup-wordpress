<?php
/**
 * BuckleUp — PRODUCTION wp-config.php constants for Bluehost.
 * ---------------------------------------------------------------------------
 * Bluehost is NOT Docker, so the WORDPRESS_CONFIG_EXTRA block from
 * docker-compose.yml does not exist there. Paste the constants below into the
 * site's wp-config.php, ABOVE the line:   /* That's all, stop editing! *​/
 *
 * Leave the Bluehost-generated DB credentials and the unique salts ALONE.
 * Only ADD the defines below (and confirm $table_prefix — see the bottom note).
 *
 * This file is documentation, not something to upload. Copy the defines out.
 */

/* --- Canonical site URL (CHANGE if the final domain differs) --------------- */
define( 'WP_HOME',    'https://www.buckleupdriving.ca' );
define( 'WP_SITEURL', 'https://www.buckleupdriving.ca' );

/* --- Environment + debug (production) -------------------------------------- */
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'WP_DEBUG',         false );
define( 'WP_DEBUG_LOG',     false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG',     false );

/* --- Security / filesystem ------------------------------------------------- */
define( 'DISALLOW_FILE_EDIT', true );   // disable the wp-admin theme/plugin editor
define( 'FS_METHOD', 'direct' );        // let WP write uploads without FTP creds

/* --- Cron: disable the unreliable pseudo-cron; a real cPanel cron hits ----- */
/* --- wp-cron.php instead (see deploy/bluehost/README.md, "Cron") ----------- */
define( 'DISABLE_WP_CRON', true );

/*
 * ===========================================================================
 * DIFFERENCES FROM THE DOCKER BUILD — read these, they matter:
 * ===========================================================================
 *
 * 1) DO NOT set define('WP_CACHE', true) and DO NOT install Cache Enabler.
 *    The Docker build used Cache Enabler (WP_CACHE + advanced-cache.php). On
 *    Bluehost, use the built-in server-level page cache instead. Running both is
 *    the "caching a cache" anti-pattern (stale pages, sometimes slower).
 *
 * 2) DO NOT set WPMS_ON / SMTP_HOST / SMTP_PORT. Those drove the DEV-ONLY
 *    Mailpit mu-plugin (00-mailpit-smtp.php), which is NOT deployed. Configure a
 *    real SMTP plugin instead — see deploy/bluehost/smtp-and-dns.md.
 *
 * 3) DO NOT carry over AUTOMATIC_UPDATER_DISABLED / WP_AUTO_UPDATE_CORE=false.
 *    Leave WordPress's default minor/security auto-updates ON in production
 *    (low-maintenance marketing site). Omitting them = on by default.
 *
 * 4) $table_prefix MUST match the imported database. The dev build uses 'wp_':
 *
 *        $table_prefix = 'wp_';
 *
 *    Bluehost's 1-click WordPress installer sometimes RANDOMIZES this (e.g.
 *    wpab12_). If the prefix in wp-config.php and in the imported SQL dump
 *    disagree, the plugins' dbDelta will build a second, EMPTY set of bu_*
 *    tables and the site will look wiped. Make them agree (easiest: set the
 *    prefix to 'wp_' to match the dump).
 */
