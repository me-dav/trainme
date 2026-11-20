<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthAndImportTest extends TestCase
{
    use RefreshDatabase;

    private Course $board;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->board = Course::factory()->create(['slug' => 'linux', 'title' => 'Linux']);
    }

    private function fixture(): string
    {
        return File::get(base_path('tests/Fixtures/mini-module.md'));
    }

    private function fixtureUpload(?string $content = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('mini-module.md', $content ?? $this->fixture());
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'nope'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_create_user_command_creates_an_account(): void
    {
        $this->artisan('dojo:user', ['email' => 'me@example.com', '--password' => 'a-long-enough-password'])->assertSuccessful();
        $this->artisan('dojo:user', ['email' => 'short@example.com', '--password' => 'short'])->assertFailed();

        $this->assertDatabaseHas('users', ['email' => 'me@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'short@example.com']);
    }

    public function test_upload_into_a_board_previews_then_imports_as_draft(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('import.store', $this->board), ['file' => $this->fixtureUpload()]);
        $response->assertRedirect();
        $previewUrl = $response->headers->get('Location');

        // The fixture says `course: test-course` and `status: verified`; the board wins and it becomes draft.
        $this->get($previewUrl)->assertOk()
            ->assertSee('Mini Module')
            ->assertSee('lolos self-test')
            ->assertSee('board Linux')
            ->assertSee('File menyebut course')
            ->assertSee('disimpan sebagai draft');
        $this->assertSame(0, Module::query()->count());

        $this->post($previewUrl)->assertRedirect(route('modules.show', 'mini-module'));

        $module = Module::query()->sole();
        $this->assertSame($this->board->id, $module->course_id);
        $this->assertSame('draft', $module->status);
        $this->assertSame(1, Course::query()->count());
        $this->assertSame([], Storage::disk('local')->files('imports'));
    }

    public function test_a_file_without_course_can_be_uploaded_into_a_board(): void
    {
        $withoutCourse = preg_replace('/^course:\n(  .*\n)+/m', '', $this->fixture());
        $this->assertStringNotContainsString('test-course', $withoutCourse);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('import.store', $this->board), ['content' => $withoutCourse]);

        $response->assertRedirect();
        $this->post($response->headers->get('Location'))->assertRedirect(route('modules.show', 'mini-module'));
        $this->assertSame('linux', Module::query()->sole()->course->slug);
    }

    public function test_invalid_upload_reopens_the_modal_with_copyable_errors(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('import.store', $this->board), ['file' => $this->fixtureUpload("---\nformat: 2\n---\n")])
            ->assertRedirect(route('boards.show', $this->board));

        $this->get(route('boards.show', $this->board))
            ->assertOk()
            ->assertSee('File ditolak')
            ->assertSee('data-open-on-load', false)
            ->assertSee('Salin error untuk AI')
            ->assertSee('`format` harus bernilai 1.');

        $this->assertSame([], Storage::disk('local')->files('imports'));
        $this->assertSame(0, Module::query()->count());
    }

    public function test_moving_a_module_to_another_board_is_announced(): void
    {
        $user = User::factory()->create();
        $other = Course::factory()->create(['slug' => 'docker', 'title' => 'Docker']);

        $first = $this->actingAs($user)->post(route('import.store', $this->board), ['content' => $this->fixture()]);
        $this->post($first->headers->get('Location'));

        $second = $this->post(route('import.store', $other), ['content' => $this->fixture()]);
        $this->get($second->headers->get('Location'))->assertSee('dipindah ke board “Docker”', false)->assertSee('Terapkan perubahan');

        $this->post($second->headers->get('Location'));
        $this->assertSame($other->id, Module::query()->sole()->course_id);
    }

    public function test_another_session_cannot_confirm_a_pending_import(): void
    {
        $response = $this->actingAs(User::factory()->create())->post(route('import.store', $this->board), ['file' => $this->fixtureUpload()]);
        $previewUrl = $response->headers->get('Location');

        $this->flushSession();

        $this->actingAs(User::factory()->create())
            ->post($previewUrl)
            ->assertRedirect(route('dashboard'));
        $this->assertSame(0, Module::query()->count());
    }

    public function test_upload_requires_a_markdown_file(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('import.store', $this->board), ['file' => UploadedFile::fake()->create('module.pdf', 10)])
            ->assertSessionHasErrors('file', null, 'import');
    }

    public function test_the_downloadable_template_is_a_valid_module_for_the_board(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('boards.template', $this->board));

        $response->assertOk()->assertDownload('template-modul-linux.md');
        $template = $response->getContent();
        $this->assertStringContainsString('id: linux-contoh-modul', $template);

        $import = $this->post(route('import.store', $this->board), ['content' => $template]);
        $import->assertRedirect();
        $this->assertStringContainsString('/import/', $import->headers->get('Location'));
    }

    public function test_module_can_be_marked_verified_and_back(): void
    {
        $module = Module::factory()->create(['course_id' => $this->board->id, 'status' => 'draft']);

        $this->actingAs(User::factory()->create())
            ->patch(route('modules.status', $module), ['status' => 'verified'])
            ->assertRedirect(route('modules.show', $module));
        $this->assertTrue($module->fresh()->isVerified());

        $this->patch(route('modules.status', $module), ['status' => 'draft']);
        $this->assertFalse($module->fresh()->isVerified());
    }
}
