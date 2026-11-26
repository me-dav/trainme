<?php

namespace Tests\Feature;

use App\Models\AiImport;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Module;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_a_board_can_be_created_from_the_dashboard(): void
    {
        $this->actingAs($this->user)
            ->post(route('boards.store'), ['title' => 'Docker & Container', 'description' => 'Image, volume, network.'])
            ->assertRedirect(route('boards.show', 'docker-container'));

        $this->get(route('dashboard'))->assertOk()->assertSee('Docker &amp; Container', false)->assertSee('0 modul');
        $this->get(route('boards.show', 'docker-container'))->assertOk()
            ->assertSee('Unduh template')
            ->assertSee('Salin prompt')
            ->assertSee('id` modul diawali `docker-container-`');
    }

    public function test_duplicate_or_empty_board_names_are_rejected(): void
    {
        Course::factory()->create(['slug' => 'linux', 'title' => 'Linux']);

        $this->actingAs($this->user)->from(route('dashboard'))
            ->post(route('boards.store'), ['title' => 'LINUX'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertSee('data-open-on-load', false)->assertSee('Board dengan nama itu sudah ada');

        $this->from(route('dashboard'))->post(route('boards.store'), ['title' => '!!!'])->assertSessionHasErrors('title', null, 'board');

        $this->assertSame(1, Course::query()->count());
    }

    public function test_a_board_can_be_renamed_but_keeps_its_slug(): void
    {
        $board = Course::factory()->create(['slug' => 'linux', 'title' => 'Linux']);

        $this->actingAs($this->user)
            ->patch(route('boards.update', $board), ['title' => 'Linux Fundamentals', 'description' => null])
            ->assertRedirect(route('boards.show', 'linux'));

        $this->assertSame('Linux Fundamentals', $board->fresh()->title);
        $this->assertSame('linux', $board->fresh()->slug);
    }

    public function test_an_empty_board_is_deleted_without_typing_its_slug(): void
    {
        $empty = Course::factory()->create();

        $this->actingAs($this->user)->delete(route('boards.destroy', $empty))->assertRedirect(route('dashboard'));

        $this->assertModelMissing($empty);
    }

    public function test_a_board_with_modules_is_deleted_only_after_typing_its_slug(): void
    {
        $board = Course::factory()->create(['slug' => 'linux', 'title' => 'Linux']);
        Module::factory()->create(['course_id' => $board->id]);

        $this->actingAs($this->user)
            ->followingRedirects()
            ->delete(route('boards.destroy', $board), ['confirm' => 'Linux'])
            ->assertOk()
            ->assertSee('Ketik `linux` untuk mengonfirmasi penghapusan.')
            ->assertSee('<dialog id="board-edit" aria-labelledby="board-edit-title"  data-open-on-load', false);

        $this->assertModelExists($board);
        $this->assertSame(1, $board->modules()->count());
    }

    public function test_deleting_a_board_deletes_its_modules_questions_history_and_ai_imports(): void
    {
        Storage::fake('local');
        $board = Course::factory()->create(['slug' => 'linux', 'title' => 'Linux']);
        $modules = Module::factory()->count(2)->create(['course_id' => $board->id]);
        $question = Question::factory()->create(['module_id' => $modules[0]->id]);
        $attempt = Attempt::factory()->completed(80)->create(['module_id' => $modules[0]->id, 'user_id' => $this->user->id]);
        $aiImport = AiImport::factory()->for($this->user)->for($board, 'course')->create();
        Storage::disk('local')->put($aiImport->source_path, '%PDF-1.4');
        $otherModule = Module::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('boards.destroy', $board), ['confirm' => 'linux'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Board "Linux" beserta 2 modul dihapus.');

        $this->assertModelMissing($board);
        $modules->each(fn (Module $module) => $this->assertModelMissing($module));
        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
        $this->assertModelMissing($attempt);
        $this->assertModelMissing($aiImport);
        Storage::disk('local')->assertMissing($aiImport->source_path);
        $this->assertModelExists($otherModule);
    }

    public function test_cli_import_does_not_overwrite_a_renamed_board(): void
    {
        $board = Course::factory()->create(['slug' => 'test-course', 'title' => 'Judul dari aplikasi']);

        $this->artisan('dojo:import', ['path' => base_path('tests/Fixtures/mini-module.md')])->assertSuccessful();

        $this->assertSame('Judul dari aplikasi', $board->fresh()->title);
        $this->assertSame($board->id, Module::query()->sole()->course_id);
    }
}
