<?php
/**
 * Plugin Name: TabulaMea
 * Plugin URI: https://github.com/deckerweb/tabulamea
 * Description: Privacy-friendly pageview statistics, popular content and referrers, with access controls and optional country and technology reports.
 * Version: 0.9.1
 * Requires at least: 7.1.2
 * Requires PHP: 8.2
 * Author: David Decker – DECKERWEB
 * Author URI: https://github.com/deckerweb
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tabulamea
 * Domain Path: /languages/
 * Update URI: https://github.com/deckerweb/tabulamea
 * GitHub Plugin URI: https://github.com/deckerweb/tabulamea
 *
 * Copyright © 2026 David Decker – DECKERWEB.
 * SPDX-License-Identifier: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
define('TBM_FILE', __FILE__);
define('TBM_VERSION', '0.9.1');
require_once __DIR__ . '/includes/deckerweb-plugin-library/bootstrap.php';
deckerweb_library_register_v2(__FILE__, [], __DIR__.'/includes/deckerweb-plugin-library');
require_once __DIR__ . '/includes/UpdateIntegration.php';
add_action('init',['Deckerweb\\TabulaMea\\UpdateIntegration','boot'],20);
require_once __DIR__ . '/includes/Plugin.php';
require_once __DIR__ . '/includes/CoreStore.php';
require_once __DIR__ . '/includes/DataExports.php';
require_once __DIR__ . '/includes/StatifyImport.php';
require_once __DIR__ . '/includes/DataCare.php';
require_once __DIR__ . '/includes/RankingEnhancements.php';
require_once __DIR__ . '/includes/Insights.php';
require_once __DIR__ . '/includes/WordPressIntegration.php';
require_once __DIR__ . '/includes/MeasurementPolicy.php';
require_once __DIR__ . '/includes/SearchStats.php';
require_once __DIR__ . '/includes/ContentColumns.php';
require_once __DIR__ . '/includes/NetworkSettings.php';
require_once __DIR__ . '/includes/OutputBlocks.php';
require_once __DIR__ . '/includes/Stats.php';
require_once __DIR__ . '/includes/Measurement.php';
require_once __DIR__ . '/includes/StatisticsAdmin.php';
require_once __DIR__ . '/includes/StatisticsImport.php';
require_once __DIR__ . '/includes/Rankings.php';
require_once __DIR__ . '/includes/Tracking.php';
require_once __DIR__ . '/includes/Admin.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/Navigation.php';
require_once __DIR__ . '/includes/ShortcodeTools.php';

require_once __DIR__ . '/includes/InterfaceTools.php';
require_once __DIR__ . '/includes/ReportTools.php';
require_once __DIR__ . '/includes/AccessTools.php';
require_once __DIR__ . '/includes/DashboardTools.php';
require_once __DIR__ . '/includes/DimensionReports.php';
require_once __DIR__ . '/includes/OverviewTools.php';
require_once __DIR__ . '/includes/ConfigurationTools.php';
require_once __DIR__ . '/includes/ToolsActions.php';
require_once __DIR__ . '/includes/RankingAdmin.php';
add_action('plugins_loaded', ['Deckerweb\\TabulaMea\\Plugin', 'boot']);
register_activation_hook(__FILE__, ['Deckerweb\\TabulaMea\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Deckerweb\\TabulaMea\\Plugin', 'deactivate']);
