<?php

namespace Tests\Feature;

use App\Exceptions\ApiExceptionRenderer;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    use DatabaseTransactions;

    private function protectedTestRoute(): void
    {
        // Registered only inside this isolated test application, never production.
        Route::middleware(['api', 'auth:sanctum'])->get('/api/v1/_test/protected', function () {
            return ApiResponse::success(data: ['authenticated' => true]);
        });
    }

    public function test_public_health_has_the_exact_contract_without_database_queries_or_cookies(): void
    {
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();

        $this->get('/api/v1/health', ['Accept' => 'text/html'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeaderMissing('Set-Cookie')
            ->assertExactJson([
                'success' => true,
                'message' => 'API is running',
                'data' => ['status' => 'ok', 'api_version' => 'v1'],
            ]);

        $this->assertSame([], DB::connection()->getQueryLog());
    }

    public function test_missing_api_route_returns_json_without_an_accept_header(): void
    {
        $this->get('/api/v1/not-a-real-endpoint')->assertNotFound()->assertExactJson([
            'success' => false, 'message' => 'Resource not found',
        ]);
    }

    public function test_guest_protected_request_returns_401_without_a_redirect(): void
    {
        $this->protectedTestRoute();
        $this->get('/api/v1/_test/protected', ['Accept' => 'text/html'])
            ->assertUnauthorized()->assertHeaderMissing('Location')->assertExactJson([
                'success' => false, 'message' => 'Unauthenticated',
            ]);
    }

    public function test_website_session_does_not_authorize_a_mobile_route(): void
    {
        $this->protectedTestRoute();
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $this->assertTrue(Auth::guard('web')->check());
        $this->assertTrue(Hash::check('password', $user->password));
        $this->get('/api/v1/_test/protected')->assertUnauthorized();
        $this->assertTrue(Auth::guard('web')->check());
    }

    public function test_real_sanctum_bearer_token_is_hashed_and_authenticates(): void
    {
        $this->protectedTestRoute();
        $user = User::factory()->create();
        $token = $user->createToken('isolated-foundation-test', ['foundation:read'], now()->addMinutes(5));
        [, $secret] = explode('|', $token->plainTextToken, 2);
        $this->assertTrue(hash_equals(hash('sha256', $secret), $token->accessToken->token));

        $this->withToken($token->plainTextToken)->get('/api/v1/_test/protected')
            ->assertOk()->assertExactJson([
                'success' => true, 'message' => 'Request successful',
                'data' => ['authenticated' => true],
            ]);
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->protectedTestRoute();
        $token = User::factory()->create()->createToken('expired-test', ['foundation:read'], now()->subMinute());
        $this->withToken($token->plainTextToken)->get('/api/v1/_test/protected')->assertUnauthorized();
    }

    public function test_revoked_token_is_rejected(): void
    {
        $this->protectedTestRoute();
        $token = User::factory()->create()->createToken('revoked-test', ['foundation:read']);
        $token->accessToken->delete();
        $this->withToken($token->plainTextToken)->get('/api/v1/_test/protected')->assertUnauthorized();
    }

    public function test_validation_failure_uses_the_api_envelope(): void
    {
        Route::middleware('api')->post('/api/v1/_test/validation', function (Request $request) {
            $request->validate(['name' => 'required|string']);
        });

        $this->post('/api/v1/_test/validation')->assertUnprocessable()->assertJson([
            'success' => false, 'message' => 'Validation failed',
        ])->assertJsonValidationErrors('name');
    }

    public function test_api_server_error_hides_exception_details_even_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        Route::middleware('api')->get('/api/v1/_test/error', function () {
            throw new \RuntimeException('synthetic-sensitive-error-detail');
        });

        $this->get('/api/v1/_test/error')->assertStatus(500)->assertExactJson([
            'success' => false, 'message' => 'Server error',
        ]);
    }

    public function test_method_not_allowed_preserves_the_allow_header(): void
    {
        $response = $this->post('/api/v1/health')->assertStatus(405)->assertExactJson([
            'success' => false, 'message' => 'Method not allowed',
        ]);
        $this->assertStringContainsString('GET', $response->headers->get('Allow'));
    }

    public function test_throttling_error_preserves_retry_after_without_exception_details(): void
    {
        Route::middleware('api')->get('/api/v1/_test/throttled', function () {
            throw new TooManyRequestsHttpException(60, 'synthetic-private-detail');
        });
        $this->get('/api/v1/_test/throttled')->assertStatus(429)->assertHeader('Retry-After', '60')
            ->assertExactJson(['success' => false, 'message' => 'Too many requests']);
    }

    public function test_forbidden_response_does_not_echo_private_exception_messages(): void
    {
        Route::middleware('api')->get('/api/v1/_test/forbidden', fn () => abort(403, 'synthetic-private-detail'));
        $this->get('/api/v1/_test/forbidden')->assertForbidden()->assertExactJson([
            'success' => false, 'message' => 'Forbidden',
        ]);
    }

    public function test_default_success_data_is_an_object(): void
    {
        $response = ApiResponse::success();
        $this->assertInstanceOf(\stdClass::class, json_decode($response->getContent())->data);
    }

    public function test_existing_web_and_admin_route_inventory_is_unchanged(): void
    {
        $routes = [];
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                $routes[] = [
                    'uri' => $route->uri(), 'methods' => $route->methods(),
                    'name' => $route->getName(), 'action' => $route->getActionName(),
                    'middleware' => $route->gatherMiddleware(),
                ];
            }
        }
        usort($routes, fn ($a, $b) => strcmp($a['uri'].implode(',', $a['methods']), $b['uri'].implode(',', $b['methods'])));
        $expected = json_decode(file_get_contents(base_path('tests/Fixtures/web-routes-before-api.json')), true);
        $this->assertSame($expected, $routes);
        $this->assertSame('web', config('auth.defaults.guard'));
        $this->assertSame(['driver' => 'session', 'provider' => 'users'], config('auth.guards.web'));
        $this->assertSame([], config('sanctum.guard'));
        $this->assertFalse(config('sanctum.routes'));
        $this->assertFalse(app('router')->has('sanctum.csrf-cookie'));
        $webMiddleware = app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'];
        $this->assertContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $webMiddleware);
        $apiRoutes = collect(app('router')->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'api/'));
        $this->assertSame([
            'api/v1/categories', 'api/v1/cities', 'api/v1/countries', 'api/v1/currencies',
            'api/v1/health', 'api/v1/home', 'api/v1/listings', 'api/v1/listings/{id}',
        ], $apiRoutes->map(fn ($route) => $route->uri())->sort()->values()->all());
    }

    public function test_non_api_errors_keep_existing_web_rendering(): void
    {
        $this->assertNull(ApiExceptionRenderer::render(new \RuntimeException('synthetic'), Request::create('/web-path')));
        $response = $this->get('/not-a-real-web-page')->assertNotFound();
        $this->assertStringStartsWith('text/html', $response->headers->get('Content-Type'));
    }
}
