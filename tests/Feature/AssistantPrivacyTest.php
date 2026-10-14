<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\AvailabilityAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_question_is_not_sent_to_the_ai_provider(): void
    {
        config(['assistant.api_key' => 'test-api-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Proyektor tersedia.']],
            ]),
        ]);

        $category = Category::create(['name' => 'Elektronik']);
        Item::create([
            'category_id' => $category->id,
            'item_code' => 'PRO-001',
            'name' => 'Proyektor',
            'total_quantity' => 2,
            'available_quantity' => 2,
            'condition' => 'baik',
            'location' => 'Gudang',
            'is_active' => true,
        ]);

        app(AvailabilityAssistant::class)->ask(
            User::factory()->create(),
            'Proyektor tersedia hari ini? PRIVATE-QUESTION-CANARY'
        );

        Http::assertSent(function (HttpRequest $request) {
            return ! str_contains($request->body(), 'PRIVATE-QUESTION-CANARY')
                && str_contains($request->body(), 'Proyektor (PRO-001)');
        });
    }
}
