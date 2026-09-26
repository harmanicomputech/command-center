<?php

namespace Tests\Feature;

use App\Services\LgaMap;
use FilesystemIterator;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Lessons from Election Shield, checked on every run.
 */
class GuardTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function files(string $directory, string $extension): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (str_ends_with($file->getFilename(), $extension)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_no_blade_directive_is_glued_to_a_word(): void
    {
        // Blade skips "@if" right after a letter or digit (it looks like an
        // email address), leaving an unmatched @endif that breaks the page.
        foreach ($this->files(resource_path('views'), '.blade.php') as $path) {
            preg_match_all('/[A-Za-z0-9]@(if|else|elseif|endif|foreach|endforeach|forelse|empty|endforelse|isset|endisset|unless|endunless|php|endphp|error|enderror)\b/', file_get_contents($path), $matches);
            $this->assertSame([], $matches[0], $path);
        }
    }

    public function test_no_directive_follows_a_closing_slot_on_the_same_line(): void
    {
        // </x-slot> compiles to @endslot, so "</x-slot>@endif" becomes the
        // uncompiled "@endslot@endif".
        foreach ($this->files(resource_path('views'), '.blade.php') as $path) {
            $this->assertDoesNotMatchRegularExpression('/<\/x-slot[^>]*>@/', file_get_contents($path), $path);
        }
    }

    public function test_no_inline_php_directive_comes_before_a_php_block(): void
    {
        // Blade matches "@php … @endphp" from the first @php, so a one-line
        // @php(...) before a block swallows the template between them.
        foreach ($this->files(resource_path('views'), '.blade.php') as $path) {
            $view = file_get_contents($path);
            preg_match_all('/(?<!@)@php\s*\(/', $view, $inline, PREG_OFFSET_CAPTURE);
            preg_match_all('/(?<!@)@php(?!\s*\()/', $view, $blocks, PREG_OFFSET_CAPTURE);

            $this->addToAssertionCount(1);

            if ($inline[0] !== [] && $blocks[0] !== []) {
                $this->assertLessThan($inline[0][0][1], end($blocks[0])[1], $path.': move the @php block above the one-line @php(...).');
            }
        }
    }

    public function test_scripts_read_form_urls_with_get_attribute(): void
    {
        // A form field named "action" replaces form.action with the field,
        // which posted to "/[object HTMLInputElement]" in Election Shield.
        foreach ([...$this->files(resource_path('js'), '.js'), public_path('sw.js')] as $path) {
            $this->assertDoesNotMatchRegularExpression('/\bform\.action\b/', file_get_contents($path), $path);
        }
    }

    public function test_no_real_contact_details_are_committed(): void
    {
        // The repo is public: example.com addresses and fake 0800 numbers only.
        $roots = [app_path(), resource_path(), base_path('tests'), base_path('database'), base_path('docs'), base_path('deploy'), config_path()];

        foreach ($roots as $root) {
            foreach ([...$this->files($root, '.php'), ...$this->files($root, '.md'), ...$this->files($root, '.template')] as $path) {
                $text = file_get_contents($path);
                preg_match_all('/[A-Za-z0-9._%+-]+@(?!example\.(com|org|net)\b)[A-Za-z0-9-]+\.(com|ng|org|net|io)\b/i', $text, $emails);
                $this->assertSame([], $emails[0], "{$path} contains an email address");
            }
        }
    }

    public function test_every_lga_has_a_map_tile(): void
    {
        $this->assertEqualsCanonicalizing(config('campaign.lgas'), array_keys(LgaMap::LAYOUT));
    }

    /**
     * MySQL caps index and constraint names at 64 characters (SQLite, used
     * in tests, doesn't), so a long generated name only fails on the host.
     */
    public function test_database_identifiers_fit_mysql(): void
    {
        $this->artisan('migrate:fresh');
        $long = [];

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            foreach (Schema::getIndexes($name) as $index) {
                strlen($index['name']) > 64 && $long[] = $index['name'];
            }
            foreach (Schema::getForeignKeys($name) as $key) {
                $generated = $name.'_'.implode('_', $key['columns']).'_foreign';
                strlen($generated) > 64 && $long[] = $generated;
            }
            strlen($name) > 64 && $long[] = $name;
        }

        $this->assertSame([], $long, 'Give these an explicit shorter name in the migration.');
    }
}
