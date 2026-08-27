<?php

declare(strict_types=1);

namespace Neos\OpenApiTest;

require_once dirname(__DIR__, 1) . '/vendor/autoload.php';

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Neos\OpenApi\ApiDefinition;
use Neos\OpenApi\Attributes\AuthContext;
use Neos\OpenApi\Attributes\Operation;
use Neos\OpenApi\Attributes\RequestBody;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Compilation\ApiCompiler;
use Neos\OpenApi\Http\AuthContextProvider;
use Neos\OpenApi\Support\FixedContainer;
use Neos\OpenApi\Http\RequestHandler;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Schematic\SchematicTypeBindingProvider;
use Neos\OpenApi\Spec\InfoObject;
use Neos\OpenApi\Spec\SecurityRequirementObject;
use Neos\OpenApi\Spec\SecuritySchemeObject;
use Neos\OpenApi\Spec\SecuritySchemeOrReferenceObjectMap;
use Neos\OpenApi\Spec\ServerObject;
use Neos\OpenApi\Spec\ServerObjects;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;
use Neos\Schematic\Attributes\ReflectionMiddleware;
use Neos\Schematic\Attributes\StringBased;
use Neos\Schematic\Schematic;
use Psr\Http\Message\ServerRequestInterface;

#[StringBased(minLength: 1, maxLength: 100, pattern: '^[a-z0-9-]+$')]
final readonly class Slug
{
    private function __construct(public string $value) {}
}

final readonly class Post {

    public function __construct(
        public Slug $slug,
        public string $author,
        public string $text,
    )
    {
    }
}

final readonly class SomeResponse implements ApiResponse
{
    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(305);
    }

    public static function description(): string
    {
        return 'No such post';
    }

    public static function bodyType(): TypeReference|null
    {
        return null;
    }

    public static function contentType(): MediaTypeRange|null
    {
        return null;
    }

    public function body(): null
    {
        return null;
    }
}


final class PostApi
{
    #[Operation(path: '/posts/{slug}', method: 'GET', summary: 'Fetch one post')]
    public function getPost(Slug $slug, string|null $foo = null): Post|SomeResponse
    {
        return new Post($slug, 'John Doe', 'Lorem ispum');
    }

    #[Operation(path: '/posts', method: 'POST')]
    public function createPost(#[RequestBody] Slug $slug): Slug
    {
        return $slug;
    }
}

final class AccountApi
{
    #[Operation(path: '/me', method: 'GET', security: 'basicAuth')]
    public function me(#[AuthContext] Caller $caller): Caller
    {
        return $caller;
    }
}

final readonly class Caller
{
    public function __construct(public string $name) {}
}

$callers = new class implements AuthContextProvider {
    public function authContextFor(ServerRequestInterface $request, SecurityRequirementObject $requirement): object|null
    {
        $authorizationHeader = $request->getHeaderLine('Authorization');
        if ($authorizationHeader === 'Basic dXNlcjpwYXNzd29yZA==') {
            return new Caller('user');
        }
        return null;
    }
};

$api = ApiDefinition::create(
        info: new InfoObject(title: 'Blog', version: '1.0.0'),
        servers: new ServerObjects(
            new ServerObject('http://localhost:8080'),
        ),
        securitySchemes: SecuritySchemeOrReferenceObjectMap::create()
            ->with('bearerAuth', SecuritySchemeObject::bearer())
            ->with('basicAuth', SecuritySchemeObject::basic())
    )
    ->withOperationsFrom(PostApi::class, tag: 'Posts')
    ->withOperationsFrom(AccountApi::class, tag: 'Accounts');

$provider = new SchematicTypeBindingProvider(Schematic::create(new ReflectionMiddleware()));
$compiledApi = (new ApiCompiler($provider))->compile($api);

$factory = new HttpFactory(); // any PSR-17 response + stream factory
$handler = new RequestHandler(
    $compiledApi,
    $provider,
    new FixedContainer(new PostApi(), new AccountApi()),
    $factory,
    $factory,
    $callers,
);

$request = ServerRequest::fromGlobals();
if ($request->getUri()->getPath() === '/') {
    header('Content-Type: application/vnd.oai.openapi+json');
    echo json_encode($compiledApi->document);
    exit;
}

$response = $handler->handle($request);

http_response_code($response->getStatusCode());

foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}

echo $response->getBody();