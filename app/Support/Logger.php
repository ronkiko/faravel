<?php // v0.4.108
/* app/Support/Logger.php
Purpose: Simple debug logger for Faravel. When app.debug is true, it writes
         tagged messages to `storage/logs/debug.log`. Each log entry is
         prepended with a bracketed tag (e.g. [AUTH.PROVIDER.REGISTER]).
FIX: Updated tab separator to use a real tab character (instead of the literal
     "\t") for readability, set default permissions (0775 for directory,
     0664 for file) to allow www-data write access, and bumped version to
     v0.4.108 to reflect the overall project patch.
*/

namespace App\Support;

/**
 * Class Logger
 *
 * Writes diagnostic messages to storage/logs/debug.log if debugging is
 * enabled via config('app.debug'). Messages are simple and do not contain
 * JSON or timestamps; tags and free text should be provided by caller.
 */
final class Logger
{
    /**
     * Write a debug message if debugging is enabled.
     *
     * @param string $tag     A short category/tag for the message (no spaces).
     * @param string $message Free‑form human readable message.
     *
     * Side effects: creates directories/files under storage/logs.
     * Does nothing if app.debug is false.
     *
     * @return void
     */
    public static function log(string $tag, string $message): void
    {
        try {
            $debug = false;
            // Check configuration via global helper if available
            if (\function_exists('config')) {
                $debug = (bool) \config('app.debug', false);
            }
            if (!$debug) {
                return;
            }
            // Build path to storage/logs
            $dir = __DIR__ . '/../../storage/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
                // Attempt to set safer permissions: owner and group can read/write, others read
                @chmod($dir, 0775);
            }
            $file = $dir . '/debug.log';
            // Format the log line: tag enclosed in [] followed by a tab and the message.
            // Use a real tab character instead of the literal "\t" (which was previously
            // printed as two characters). This improves readability: the tab clearly
            // separates the tag from the message without the eye having to find where the
            // tag ends and the text begins.
            $line = '[' . $tag . "]\t" . $message . PHP_EOL;
            @file_put_contents($file, $line, FILE_APPEND);
            // Ensure file has group-writable permissions (0664) so web server (www-data)
            // can write to it even if group differs.
            if (!file_exists($file) || !is_writable($file)) {
                // attempt to set mode only if file exists; ignore errors silently
                @chmod($file, 0664);
            }
        } catch (\Throwable $e) {
            // Suppress any logging errors
        }
    }
}