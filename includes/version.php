<?php
/**
 * Single source of truth for the AssetTrack version (semver).
 * Keep in sync with package.json ("npm run check:version" verifies this).
 */
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '4.2.1');
}
