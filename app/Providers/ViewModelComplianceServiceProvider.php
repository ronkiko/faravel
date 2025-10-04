<?php // v0.4.2
/* app/Providers/ViewModelComplianceServiceProvider.php
Purpose: Validate all ViewModel classes under App\Http\ViewModels against the
         ArrayBuildable contract at boot time. Fails fast with descriptive errors.
FIX: Added deep debug logging: START/END, scanned files count, per-file FQCN mapping,
     autoload presence, interface detection, per-class OK/FAIL, and exception traces.
*/

namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

use App\Support\Logger;

/**
 * Провайдер проверяет соответствие ViewModel-ов контракту ArrayBuildable.
 *
 * Сканирует app/Http/ViewModels, находит классы (PSR-4: App\Http\ViewModels\...),
 * и валидирует сигнатуры методов. При несоответствии валит загрузку с понятной
 * ошибкой — это быстрее, чем отлавливать фаталы в рантайме на первом вызове.
 */
final class ViewModelComplianceServiceProvider extends ServiceProvider
{
    /**
     * Nothing to register; validation runs in boot().
     *
     * @return void
     */
    public function register(): void
    {
        // Debug: provider register
        Logger::log('PROVIDER.REGISTER', static::class . ' register');
        // no-op
    }

    /**
     * Scan the VM directory and validate every class implementing ArrayBuildable.
     *
     * @return void
     *
     * @throws \RuntimeException Если найдено несоответствие сигнатур.
     */
    public function boot(): void
    {
        Logger::log('VMC.BOOT', 'START');

        $dir = dirname(__DIR__) . '/Http/ViewModels';
        if (!is_dir($dir)) {
            Logger::log('VMC.SKIP', 'directory_missing ' . $dir);
            Logger::log('VMC.BOOT', 'END');
            return;
        }

        $files = $this->gatherPhpFiles($dir);
        Logger::log('VMC.SCAN.FILES', 'count=' . count($files));

        $checked = 0;
        $ok = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $fqcn = $this->fqcnFromPath($file, $dir);
            Logger::log('VMC.MAP', $file . ' -> ' . ($fqcn ?? 'null'));

            if ($fqcn === null) {
                $skipped++;
                continue;
            }

            // Try to autoload class; if not — require_once and re-check
            $exists = class_exists($fqcn, true);
            if (!$exists) {
                @require_once $file;
                $exists = class_exists($fqcn, false);
            }
            Logger::log('VMC.AUTOLOAD', $fqcn . ' exists=' . ($exists ? '1' : '0'));

            if (!$exists) {
                // Not a class (interface/trait/empty) — skip
                $skipped++;
                continue;
            }

            $rc = new ReflectionClass($fqcn);

            // Validate only classes implementing ArrayBuildable
            if (!in_array(\App\Contracts\ViewModel\ArrayBuildable::class, $rc->getInterfaceNames(), true)) {
                $skipped++;
                continue;
            }

            $checked++;
            try {
                $this->validateFromArray($rc, $file);
                $this->validateToArray($rc, $file);
                Logger::log('VMC.OK', $fqcn);
                $ok++;
            } catch (\Throwable $e) {
                // Log rich context before failing fast
                Logger::exception('VMC.FAIL', $e, [
                    'class' => $fqcn,
                    'file'  => $file,
                ]);
                throw $e; // fail fast with the same descriptive message
            }
        }

        Logger::log('VMC.SUMMARY', "checked={$checked} ok={$ok} skipped={$skipped}");
        Logger::log('VMC.BOOT', 'END');
    }

    /**
     * @param ReflectionClass $rc
     * @param string          $file
     *
     * @return void
     */
    private function validateFromArray(ReflectionClass $rc, string $file): void
    {
        if (!$rc->hasMethod('fromArray')) {
            throw new \RuntimeException(
                $this->fmt($file, $rc->getName(),
                    "Method fromArray(array \$data): static is required by the contract.")
            );
        }

        $m = $rc->getMethod('fromArray');

        // Must be static
        if (!$m->isStatic()) {
            throw new \RuntimeException(
                $this->fmt($file, $rc->getName(),
                    "fromArray() must be declared static.")
            );
        }

        // Params: exactly 1 param typed as array
        $params = $m->getParameters();
        if (count($params) !== 1) {
            throw new \RuntimeException(
                $this->fmt($file, $rc->getName(),
                    "fromArray() must accept exactly one parameter: array \$data.")
            );
        }
        $p0 = $params[0]->getType();
        if (!$p0 instanceof ReflectionNamedType || $p0->getName() !== 'array') {
            throw new \RuntimeException(
                $this->fmt($file, $rc->getName(),
                    "fromArray() first parameter must be typed as 'array'.")
            );
        }

        // Return type must be 'static'
        $ret = $m->getReturnType();
        if (!$ret instanceof ReflectionNamedType || $ret->getName() !== 'static') {
            throw new \RuntimeException(
                $this->fmt($file, $rc->getName(),
                    "fromArray() return type must be 'static' per the contract.")
            );
        }
    }

    /**
     * @param ReflectionClass $rc
     * @param string          $file
     *
     * @return void
     */
    private function validateToArray(ReflectionClass $rc, string $file): void
    {
        if (!$rc->hasMethod('toArray')) {
            throw new \RuntimeException(
                $this->fmt($file, $rc->getName(),
                    "Method toArray(): array is required for view serialization.")
            );
        }

        $m = $rc->getMethod('toArray');
        if ($m->isStatic()) {
            throw new \RuntimeException(
                $this->fmt($file, $rc->getName(),
                    "toArray() must be an instance method, not static.")
            );
        }

        $ret = $m->getReturnType();
        if (!$ret instanceof ReflectionNamedType || $ret->getName() !== 'array') {
            throw new \RuntimeException(
                $this->fmt($file, $rc->getName(),
                    "toArray() return type must be 'array'.")
            );
        }
    }

    /**
     * Gather all PHP files under a directory recursively.
     *
     * @param string $dir
     * @return array<int,string>
     */
    private function gatherPhpFiles(string $dir): array
    {
        $out = [];
        $it  = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        /** @var \SplFileInfo $f */
        foreach ($it as $f) {
            if (strtolower($f->getExtension()) === 'php') {
                $out[] = $f->getPathname();
            }
        }
        sort($out);
        return $out;
    }

    /**
     * Convert a file path to FQCN using PSR-4 mapping for App\ namespace.
     *
     * @param string $file
     * @param string $rootDir app/Http/ViewModels
     * @return string|null FQCN or null when mapping is not possible.
     */
    private function fqcnFromPath(string $file, string $rootDir): ?string
    {
        $rel = substr($file, strlen($rootDir));
        if ($rel === false) {
            return null;
        }
        $rel = ltrim($rel, DIRECTORY_SEPARATOR);
        $rel = str_replace(DIRECTORY_SEPARATOR, '\\', $rel);
        $rel = preg_replace('/\.php$/i', '', $rel);

        if (!is_string($rel) || $rel === '') {
            return null;
        }

        return 'App\\Http\\ViewModels\\' . $rel;
    }

    /**
     * Format error messages consistently.
     *
     * @param string $file
     * @param string $class
     * @param string $msg
     * @return string
     */
    private function fmt(string $file, string $class, string $msg): string
    {
        return "[VM Compliance] {$msg} File: {$file}; Class: {$class}";
    }
}
