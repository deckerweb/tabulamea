<?php
/** Return runtime updater messages translated through the TabulaMea domain. */
defined( 'ABSPATH' ) || exit;
/**
 * Translate an updater message through the host domain.
 * @param string $message English component message.
 * @return string Translated message or unchanged fallback.
 */
return static function ( string $message ): string {
    // Literal calls let the host's normal translation extractor collect every source string.
    switch ( $message ) {
        case 'Private mode must be boolean.':
            return __( 'Private mode must be boolean.', 'tabulamea' );
        case 'Invalid authentication provider.':
            return __( 'Invalid authentication provider.', 'tabulamea' );
        case 'The plugin must be installed in a stable slug directory.':
            return __( 'The plugin must be installed in a stable slug directory.', 'tabulamea' );
        case 'Invalid GitHub repository URL.':
            return __( 'Invalid GitHub repository URL.', 'tabulamea' );
        case 'The private update could not be authorized. Check the repository credentials and refresh updates.':
            return __( 'The private update could not be authorized. Check the repository credentials and refresh updates.', 'tabulamea' );
        case 'Could not create the update download file.':
            return __( 'Could not create the update download file.', 'tabulamea' );
        case 'The private update download failed. Check credentials and try again.':
            return __( 'The private update download failed. Check credentials and try again.', 'tabulamea' );
        case 'Could not access the update filesystem.':
            return __( 'Could not access the update filesystem.', 'tabulamea' );
        case 'GitHub release does not contain the plugin main file.':
            return __( 'GitHub release does not contain the plugin main file.', 'tabulamea' );
        case 'Could not prepare the GitHub release package.':
            return __( 'Could not prepare the GitHub release package.', 'tabulamea' );
        case 'See the release on GitHub.':
            return __( 'See the release on GitHub.', 'tabulamea' );
        default:
            return $message;
    }
};
