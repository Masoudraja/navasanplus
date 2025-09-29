<?php
namespace MNS\NavasanPlus\CLI;

use MNS\NavasanPlus\Tools\Migrator;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

    /**
     * WP-CLI: مهcharge از «نوسان» به «نوسان پلاس»
     *
     * Examples:
     *   wp mnsnp migrate --dry-run
     *   wp mnsnp migrate --deactivate-old --delete-old-options
     *   wp mnsnp migrate --aggregate-formula-vars
     *   wp mnsnp migrate --old-plugin="mns-woocommerce-rate-based-products/mns-woocommerce-rate-based-products.php"
     */
    final class MigratorCommand {

        /**
         * Run migration.
         *
         * ## OPTIONS
         *
         * [--dry-run]
         * : Do a dry run (no writes).
         *
         * [--deactivate-old]
         * : Deactivate old plugin after migration.
         *
         * [--delete-old-options]
         * : Delete old options (mnswmc_*) after copying.
         *
         * [--aggregate-formula-vars]
         * : Aggregate old per-variable values into the new unified array meta.
         *
         * [--old-plugin=<basename>]
         * : Override old plugin basename (e.g. navasan/navasan.php).
         *
         * ## EXAMPLES
         *   wp mnsnp migrate --dry-run
         *   wp mnsnp migrate --deactivate-old --delete-old-options
         */
        public function migrate( $args, $assoc ) {
            $dry    = isset( $assoc['dry-run'] );
            $deact  = isset( $assoc['deactivate-old'] );
            $delopt = isset( $assoc['delete-old-options'] );
            $agg    = isset( $assoc['aggregate-formula-vars'] );

            // Optional: override old plugin basename via flag
            if ( ! empty( $assoc['old-plugin'] ) ) {
                $override = (string) $assoc['old-plugin'];
                add_filter( 'mnsnp/migrator/old_plugin', static function() use ( $override ) {
                    return $override;
                } );
            }

            $report = Migrator::run([
                'dry'                       => $dry,
                'deactivate_old'            => $deact,
                'delete_old_opts'           => $delopt,
                'aggregate_formula_vars'    => $agg, // در صورت پشتیبانی در Migrator
            ]);

            \WP_CLI::log( '--- Migration Report ---' );
            foreach ( $report as $k => $v ) {
                if ( is_array( $v ) ) continue;
                \WP_CLI::log( sprintf( '%s: %s', $k, $v ) );
            }

            // Print errors (if any)
            if ( ! empty( $report['errors'] ) && is_array( $report['errors'] ) ) {
                foreach ( $report['errors'] as $err ) {
                    \WP_CLI::warning( is_scalar( $err ) ? (string) $err : json_encode( $err, JSON_UNESCAPED_UNICODE ) );
                }
            }

            // Exit status depending on errors (only when not dry-run)
            if ( ! $dry && ! empty( $report['errors'] ) ) {
                \WP_CLI::error( 'Migration finished with errors.' );
                return;
            }

            \WP_CLI::success( $dry ? 'Dry-run completed.' : 'Migration completed.' );
        }
    }

    // حفظ Name دستور مطابق مستنداتت: `wp mnsnp migrate`
    \WP_CLI::add_command( 'mnsnp migrate', [ MigratorCommand::class, 'migrate' ] );
}