<?php

namespace Tests\Unit;

use App\Services\Grading\AnswerNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AnswerNormalizerTest extends TestCase
{
    private AnswerNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new AnswerNormalizer;
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function equivalentCommands(): array
    {
        return [
            'extra whitespace' => ['  chmod   u+x    deploy.sh ', 'chmod u+x deploy.sh'],
            'shell prompt' => ['$ chmod u+x deploy.sh', 'chmod u+x deploy.sh'],
            'bare sudo' => ['sudo chmod u+x deploy.sh', 'chmod u+x deploy.sh'],
            'single quotes' => ["chmod 'u+x' deploy.sh", 'chmod u+x deploy.sh'],
            'double quotes' => ['find . -name "*.sh"', 'find . -name *.sh'],
            'escaped semicolon' => ['find . -exec rm {} \;', 'find . -exec rm {} ;'],
            'quoted braces' => ["find . -exec rm '{}' ';'", 'find . -exec rm {} ;'],
            'spaced redirection' => ['find / -perm -4000 2> /dev/null', 'find / -perm -4000 2>/dev/null'],
            'line continuation' => ["chown -R deploy:www-data \\\n  /srv/app", 'chown -R deploy:www-data /srv/app'],
        ];
    }

    #[DataProvider('equivalentCommands')]
    public function test_equivalent_commands_share_a_canonical_form(string $typed, string $canonical): void
    {
        $this->assertSame($canonical, $this->normalizer->command($typed));
    }

    public function test_sudo_with_options_is_kept_because_the_user_matters(): void
    {
        $this->assertSame(
            'sudo -u www-data touch /srv/app/.probe',
            $this->normalizer->command('sudo -u www-data touch /srv/app/.probe'),
        );
    }

    public function test_flags_canonical_ignores_option_order_and_bundling(): void
    {
        $expected = $this->normalizer->flagsCanonical($this->normalizer->commandTokens('ls -ld /var/www'));

        $this->assertSame($expected, $this->normalizer->flagsCanonical($this->normalizer->commandTokens('ls -dl /var/www')));
        $this->assertSame($expected, $this->normalizer->flagsCanonical($this->normalizer->commandTokens('ls -l -d /var/www')));
        $this->assertNotSame($expected, $this->normalizer->flagsCanonical($this->normalizer->commandTokens('ls -la /var/www')));
    }

    public function test_flags_canonical_keeps_positional_order(): void
    {
        $this->assertNotSame(
            $this->normalizer->flagsCanonical($this->normalizer->commandTokens('cp -r a b')),
            $this->normalizer->flagsCanonical($this->normalizer->commandTokens('cp -r b a')),
        );
    }

    public function test_text_answers_ignore_case_quotes_and_trailing_dot(): void
    {
        $this->assertSame('sticky bit', $this->normalizer->text('  "Sticky   Bit." '));
        $this->assertSame('750', $this->normalizer->text('`750`'));
    }

    public function test_scenario_answers_are_trimmed_per_line_with_prompts_removed(): void
    {
        $this->assertSame(
            "namei -l /srv/app\nchgrp www-data /srv/app",
            $this->normalizer->scenario("\$ namei   -l /srv/app\r\n\r\n   sudo chgrp www-data /srv/app  "),
        );
    }
}
