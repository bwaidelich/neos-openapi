# OpenApi

Describe an HTTP API as ordinary PHP methods, get an [OpenAPI 3.1](https://www.openapis.org/) document out of it,
and serve those methods over PSR-7.

Built on [neos/jsonschema](https://github.com/neos/jsonschema) for the schemas and, optionally,
[neos/schematic](https://github.com/neos/schematic) for turning PHP types into them and request data back into
typed arguments.

## Requirements

PHP 8.4 or higher.

## Installation

```bash
composer require neos/openapi
```

## An API, from PHP methods

Annotate methods, register the classes they live on, compile:

```php
use Neos\OpenApi\Compilation\ApiCompiler;
use Neos\OpenApi\ApiDefinition;
use Neos\OpenApi\Attributes\Operation;
use Neos\OpenApi\Attributes\RequestBody;
use Neos\OpenApi\Spec\InfoObject;
use Neos\OpenApi\Schematic\SchematicTypeBindingProvider;
use Neos\OpenApi\Support\HttpMethod;
use Neos\OpenApi\Support\RelativePath;
use Neos\Schematic\Attributes\ReflectionMiddleware;
use Neos\Schematic\Attributes\StringBased;
use Neos\Schematic\Schematic;

#[StringBased(minLength: 1, maxLength: 100, pattern: '^[a-z0-9-]+$')]
final readonly class Slug
{
    private function __construct(public string $value) {}
}

final class PostApi
{
    #[Operation(path: '/posts/{slug}', method: 'GET', summary: 'Fetch one post')]
    public function getPost(Slug $slug): Slug
    {
        return $slug;
    }

    #[Operation(path: '/posts', method: 'POST')]
    public function createPost(#[RequestBody] Slug $slug): Slug
    {
        return $slug;
    }
}

$api = ApiDefinition::create(info: new InfoObject(title: 'Blog', version: '1.0.0'))
    ->withOperationsFrom(PostApi::class, tag: 'Posts');

$provider = new SchematicTypeBindingProvider(Schematic::create(new ReflectionMiddleware()));
$compiler = new ApiCompiler($provider);
$compiled = $compiler->compile($api);
```

Compiling yields **both halves at once**: the document to publish, and a table to serve from. The spec objects
carry nothing but the specification — the runtime data lives beside them, not smuggled inside them:

```php
// ...
$document = json_encode($compiled->document, JSON_UNESCAPED_SLASHES);
assert(str_contains((string) $document, '"operationId":"getPost"'));
assert(str_contains((string) $document, '"schema":{"$ref":"#/components/schemas/Slug"}'));
// an operation that takes input can reject it, so a 400 is described with the payload the runtime emits
assert(str_contains((string) $document, '"application/problem+json"'));

$entry = $compiled->dispatchTable->find(RelativePath::fromString('/posts/{slug}'), HttpMethod::GET);
assert($entry?->methodName === 'getPost');
assert($entry->arguments[0]->source->value === 'path');
```

Everything the document needs beyond the methods themselves — `info`, servers, security schemes, a global security
requirement — lives on the `ApiDefinition`. An Api Class holds none of it, and is registered by class-string:
generating a document never constructs the classes it describes.

## Arguments: where each one comes from

Nothing is inferred from position. An argument's source is decided by what it carries, in this order:

| An argument | is filled from |
| --- | --- |
| marked `#[AuthContext]` | the caller, not the request at all — see [Security](#security) |
| marked `#[RequestBody]` | the decoded request body |
| marked `#[Parameter(in: …)]` | that location, under `name:` if it differs from the argument's own |
| named in the path template | the path |
| anything else | the query string — except on `POST`/`PUT`/`PATCH`, where it is a compile error |

An argument with a default is an optional parameter, and stays optional all the way through: if the request omits
it, it is left out of the call and the method's own default applies.

```php
// ...
use Neos\OpenApi\Attributes\Parameter;
use Neos\OpenApi\Support\ParameterLocation;
use Neos\Schematic\Attributes\IntegerBased;

#[IntegerBased(minimum: 1, maximum: 100)]
final readonly class Limit
{
    private function __construct(public int $value) {}
}

final class SearchApi
{
    #[Operation(path: '/posts/{slug}/comments', method: 'GET')]
    public function comments(
        Slug $slug,                 // named in the path template
        Limit|null $limit = null,   // anything else on a GET
        #[Parameter(in: ParameterLocation::header, name: 'X-Client-Id', description: 'Who is asking')]
        string|null $client = null,
    ): Slug {
        return $slug;
    }
}

$searchDocument = json_decode((string) json_encode($compiler->compile(
    ApiDefinition::create(info: new InfoObject(title: 'Blog', version: '1.0.0'))->withOperationsFrom(SearchApi::class),
)->document, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
$parameters = $searchDocument['paths']['/posts/{slug}/comments']['get']['parameters'];

assert(array_column($parameters, 'name') === ['slug', 'limit', 'X-Client-Id']);
assert(array_column($parameters, 'in') === ['path', 'query', 'header']);
// a path parameter is required by definition; the two with defaults are not, so they say nothing at all
assert(array_column($parameters, 'required') === [true]);
```

`#[RequestBody]` marks the one argument the body is decoded into — always explicitly, because the predecessor
inferred it positionally and reordering a signature silently changed the published API. It takes a `description:`
and a `contentType:` (`application/json` by default):

```php
// ...
$posts = json_decode((string) json_encode($compiled->document, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
$requestBody = $posts['paths']['/posts']['post']['requestBody'];

assert($requestBody['required'] === true);
assert($requestBody['content']['application/json']['schema'] === ['$ref' => '#/components/schemas/Slug']);
```

## Responses

A method's return type is the list of responses it can produce. An `ApiResponse` fixes its own status — statically,
because the document is generated without ever constructing one — and a union declares the whole set:

| A return type | becomes |
| --- | --- |
| an ordinary type | `200`, described `OK`, with that type's schema |
| an `ApiResponse` | its own status and description, plus its body schema if it declares one |
| a union of them | one response per branch (at most one may be an ordinary type) |
| `void` or `null` | `204 No Content` — a bodyless response is still a response |

On top of that, an operation taking any input is given a `400`, and one requiring authentication a `401`, both
carrying the schema of the `ProblemDocument` the handler really emits.

```php
// ...
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;
use Neos\OpenApi\Binding\TypeReference;

final readonly class PostNotFound implements ApiResponse
{
    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(404);
    }

    public static function description(): string
    {
        return 'No post has that slug';
    }

    public static function bodyType(): TypeReference|null
    {
        return null; // a TypeReference here would document a body, and `body()` would render it
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

final class LookupApi
{
    #[Operation(path: '/lookup/{slug}', method: 'GET')]
    public function lookup(Slug $slug): Slug|PostNotFound
    {
        return $slug->value === 'missing' ? new PostNotFound() : $slug;
    }

    #[Operation(path: '/lookup/{slug}', method: 'DELETE')]
    public function forget(Slug $slug): void {}
}

$lookup = json_decode((string) json_encode($compiler->compile(
    ApiDefinition::create(info: new InfoObject(title: 'Blog', version: '1.0.0'))->withOperationsFrom(LookupApi::class),
)->document, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

// one response per branch, plus the automatic 400 — ordered by status code, never by discovery order
// (PHP normalises the numeric keys of a decoded JSON object to integers)
assert(array_keys($lookup['paths']['/lookup/{slug}']['get']['responses']) === [200, 400, 404]);
assert($lookup['paths']['/lookup/{slug}']['get']['responses'][404]['description'] === 'No post has that slug');
assert(array_keys($lookup['paths']['/lookup/{slug}']['delete']['responses']) === [204, 400]);
```

The predecessor could give a non-200 response a description but never a body. `bodyType()` closes that: return a
`TypeReference` and the response documents a schema, which `body()` then renders through the very same binding —
so an error body is described exactly the way a success body is.

### Headers a response carries

A `201` that does not say where it put the thing is half an answer, and a header nobody documented is a header no
client can rely on. `ApiResponseWithHeaders` adds both halves at once, split exactly the way `ApiResponse` already
splits: static `headerTypes()` is what the generator reads while compiling, instance `headers()` is what the
handler writes. Each header names a `TypeReference`, so its schema and its value come from the same binding — a
`Location` typed as a value object is documented as that value object:

```php
// ...
use Neos\OpenApi\Response\ApiResponseWithHeaders;
use Neos\OpenApi\Binding\BuiltinType;
use Neos\OpenApi\Response\ResponseHeader;
use Neos\OpenApi\Response\ResponseHeaders;

final readonly class DraftCreated implements ApiResponseWithHeaders
{
    public function __construct(private Slug $slug) {}

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(201);
    }

    public static function description(): string
    {
        return 'The draft was created';
    }

    public static function bodyType(): TypeReference
    {
        return TypeReference::of(Slug::class);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('application/json');
    }

    public static function headerTypes(): ResponseHeaders
    {
        return ResponseHeaders::create(
            ResponseHeader::create('Location', TypeReference::builtin(BuiltinType::string), description: 'Where it lives'),
            // not every draft is rate limited, so this one may simply not be sent
            ResponseHeader::create('X-Rate-Limit-Remaining', TypeReference::builtin(BuiltinType::int), required: false),
        );
    }

    public function body(): Slug
    {
        return $this->slug;
    }

    public function headers(): array
    {
        return ['Location' => '/drafts/' . $this->slug->value];
    }
}

final class DraftApi
{
    #[Operation(path: '/drafts/{slug}', method: 'PUT')]
    public function createDraft(Slug $slug): DraftCreated
    {
        return new DraftCreated($slug);
    }
}

$drafts = json_decode((string) json_encode($compiler->compile(
    ApiDefinition::create(info: new InfoObject(title: 'Blog', version: '1.0.0'))->withOperationsFrom(DraftApi::class),
)->document, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
$headers = $drafts['paths']['/drafts/{slug}']['put']['responses'][201]['headers'];

assert(array_keys($headers) === ['Location', 'X-Rate-Limit-Remaining']);
assert($headers['Location']['description'] === 'Where it lives');
assert($headers['Location']['required'] === true);
// an optional header says nothing at all rather than `required: false`, as everywhere else
assert(!isset($headers['X-Rate-Limit-Remaining']['required']));
```

A value that serializes to a list becomes the same header once per element, which is what a repeated header is.
`Content-Type` is not declarable — the specification ignores one, and `contentType()` is what sets it — and a
response that returns a header it never declared, or leaves out one it declared as required, raises rather than
reaching a client: a document and a response disagreeing is a bug in the API, not in the request.

## Security

Security schemes are declared once, on the `ApiDefinition`; an operation names the one it needs. The caller comes
from an `AuthContextProvider` — this package authenticates nobody, it hands over the requirement the document
declares and passes whatever comes back to the `#[AuthContext]` argument, as your own type:

```php
// ...
use Neos\OpenApi\Attributes\AuthContext;
use Neos\OpenApi\Http\AuthContextProvider;
use Neos\OpenApi\Spec\SecurityRequirementObject;
use Neos\OpenApi\Spec\SecuritySchemeObject;
use Neos\OpenApi\Spec\SecuritySchemeOrReferenceObjectMap;
use Psr\Http\Message\ServerRequestInterface;

final readonly class Caller
{
    public function __construct(public string $name) {}
}

final class AccountApi
{
    #[Operation(path: '/me', method: 'GET', security: 'bearerAuth')]
    public function me(#[AuthContext] Caller $caller): Caller
    {
        return $caller;
    }
}

$callers = new class implements AuthContextProvider {
    public function authContextFor(ServerRequestInterface $request, SecurityRequirementObject $requirement): object|null
    {
        return $request->getHeaderLine('Authorization') === 'Bearer secret' ? new Caller('ada') : null;
    }
};

$schemes = SecuritySchemeOrReferenceObjectMap::create()->with('bearerAuth', SecuritySchemeObject::bearer());
$account = json_encode($compiler->compile(
    ApiDefinition::create(info: new InfoObject(title: 'Blog', version: '1.0.0'), securitySchemes: $schemes)
        ->withOperationsFrom(AccountApi::class),
)->document, JSON_UNESCAPED_SLASHES);

assert(str_contains((string) $account, '"security":[{"bearerAuth":[]}]'));
assert(str_contains((string) $account, '"bearerAuth":{"type":"http","scheme":"bearer","bearerFormat":"JWT"}'));
// the caller's identity is not part of the request's public shape, so the operation has no parameters at all
assert(!str_contains((string) $account, '"parameters"'));
// but the 401 it can produce is described, with the payload the handler emits
assert(str_contains((string) $account, '"401":{"description":"The request was not authenticated"'));
```

`security:` takes a scheme name, or an array mapping scheme names to the scopes they must grant
(`['oauth2' => ['read:posts']]`), all of which apply together. `allowAnonymous: true` adds "unauthenticated is
also acceptable" — and then the `#[AuthContext]` argument has to be nullable, since `null` is what it will be
handed. The same requirement can be set once on the `ApiDefinition`, where it covers every operation.

Every type the specification defines has a named constructor, and only the members that apply to it: `apiKey()`,
`http()`, `mutualTLS()`, `oauth2()`, `openIdConnect()`, plus `bearer()` and `basic()` for the two HTTP schemes
almost everyone wants. The combinations the specification forbids are not expressible — a `bearerFormat` on a
scheme that is not `bearer` is rejected rather than published as a member that means nothing.

```php
// ...
$staff = SecuritySchemeOrReferenceObjectMap::create()->with('staffAuth', SecuritySchemeObject::basic('Staff only'));

assert(str_contains((string) json_encode($staff), '"staffAuth":{"type":"http","description":"Staff only","scheme":"basic"}'));

try {
    SecuritySchemeObject::http('basic', bearerFormat: 'JWT');
    assert(false, 'a bearerFormat belongs to the bearer scheme alone');
} catch (\InvalidArgumentException $e) {
    assert(str_contains($e->getMessage(), 'applies to the "bearer" scheme only'));
}
```

The `WWW-Authenticate` challenge on a `401` follows from the scheme the document declares: `Bearer` for a bearer,
OAuth2 or OpenID Connect scheme, and `Basic realm="…"` for a basic one — with the realm taken from the document's
`info.title`, since a Security Scheme Object has nowhere to carry one and the protection space is the API the
document describes.

## One API, many classes

Nothing global lives on an Api Class — which is exactly what lets one API span as many of them as you like, each
contributing its own tag, so a generated UI stays navigable:

```php
// ...
$blog = ApiDefinition::create(info: new InfoObject(title: 'Blog', version: '1.0.0'), securitySchemes: $schemes)
    ->withOperationsFrom(PostApi::class, tag: 'Posts', tagDescription: 'Reading and writing posts')
    ->withOperationsFrom(LookupApi::class, tag: 'Lookup')
    ->withOperationsFrom(DraftApi::class, tag: 'Drafts')
    ->withOperationsFrom(AccountApi::class); // no tag given, so the class's short name is used

$blogDocument = json_decode((string) json_encode($compiler->compile($blog)->document, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

assert($blogDocument['tags'] === [
    ['name' => 'Posts', 'description' => 'Reading and writing posts'],
    ['name' => 'Lookup'],
    ['name' => 'Drafts'],
    ['name' => 'AccountApi'],
]);
assert($blogDocument['paths']['/posts/{slug}']['get']['tags'] === ['Posts']);
```

Every operation is checked across the whole definition, not per class: a duplicated `operationId` or two operations
claiming one path and method are caught here, no matter which classes they came from.

## What is checked, and when

Compilation is the one place reflection happens, and it fails loudly rather than guessing:

| It rejects | Because |
| --- | --- |
| a duplicated `operationId` across any two classes | client generators turn them into method names |
| two operations claiming one path and method | one of them would be unreachable |
| a `POST`/`PUT`/`PATCH` argument that is neither `#[Parameter]`, `#[RequestBody]`, nor named in the path | the predecessor inferred the body positionally, so reordering a signature changed the API |
| `#[AuthContext]` on an operation no security requirement covers | there would be no caller to hand over |
| `#[AuthContext]` that is not nullable where anonymous access is allowed | `null` is what it would be handed |
| an optional path parameter, or a missing return type | neither can be described |

```php
// ...
use Neos\OpenApi\Exception\InvalidApiDefinitionException;

final class Ambiguous
{
    #[Operation(path: '/posts', method: 'POST')]
    public function create(Slug $slug): Slug
    {
        return $slug;
    }
}

try {
    $compiler->compile(ApiDefinition::create(info: new InfoObject(title: 'x', version: '1.0.0'))
        ->withOperationsFrom(Ambiguous::class));
    assert(false, 'an unaccounted POST argument must not be inferred');
} catch (InvalidApiDefinitionException $e) {
    assert(str_contains($e->getMessage(), 'is not accounted for'));
}
```

## Serving it

The other half of a compilation is a Dispatch Table, and `RequestHandler` is what consumes it — a PSR-15 handler
over any PSR-7/PSR-17 implementation. Give it the compilation, the *same* `TypeBindingProvider` the document was
generated with, and a PSR-11 container its Api Classes can be read out of:

```php
// ...
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Neos\OpenApi\Support\FixedContainer;
use Neos\OpenApi\Http\RequestHandler;

$factory = new HttpFactory(); // any PSR-17 response + stream factory
$handler = new RequestHandler(
    $compiler->compile($blog),
    $provider,
    new FixedContainer(new PostApi(), new LookupApi(), new DraftApi(), new AccountApi()),
    $factory,
    $factory,
    $callers,
);

$response = $handler->handle(new ServerRequest('GET', '/posts/hello-world'));
assert($response->getStatusCode() === 200);
assert((string) $response->getBody() === '"hello-world"');
```

**No reflection happens on this path.** The document routes the request to a path template, the Dispatch Table
says which method answers it and where each argument comes from, and the provider — the same one — coerces the
values and serializes the result. That shared provider is why a response cannot contradict the document that
advertised it: the schema is the single source of truth for both, so returned objects are never
`json_encode`d raw.

Which means every status the handler produces is one the document already described:

```php
// ...
// an ApiResponse branch brings its own status; a void operation is a bodyless 204
assert($handler->handle(new ServerRequest('GET', '/lookup/missing'))->getStatusCode() === 404);
assert($handler->handle(new ServerRequest('DELETE', '/lookup/anything'))->getStatusCode() === 204);

// a value the schema refuses is a 400 saying which one, and why
$rejected = $handler->handle(new ServerRequest('GET', '/posts/NOT%20A%20SLUG'));
assert($rejected->getStatusCode() === 400);
assert($rejected->getHeaderLine('Content-Type') === 'application/problem+json');
assert(str_contains((string) $rejected->getBody(), '"pointer":"/path/slug"'));

// no credentials where the document requires them
$challenged = $handler->handle(new ServerRequest('GET', '/me'));
assert($challenged->getStatusCode() === 401);
assert($challenged->getHeaderLine('WWW-Authenticate') === 'Bearer');

// and with them, the caller reaches the method as its own type
$me = $handler->handle(new ServerRequest('GET', '/me', ['Authorization' => 'Bearer secret']));
assert((string) $me->getBody() === '{"name":"ada"}');

// the headers a response declared are on the wire, written through the bindings that described them
$created = $handler->handle(new ServerRequest('PUT', '/drafts/hello-world'));
assert($created->getStatusCode() === 201);
assert($created->getHeaderLine('Location') === '/drafts/hello-world');
assert(!$created->hasHeader('X-Rate-Limit-Remaining')); // declared optional, and not sent

// the path is known, the method is not
$notAllowed = $handler->handle(new ServerRequest('PUT', '/posts/hello-world'));
assert($notAllowed->getStatusCode() === 405);
assert($notAllowed->getHeaderLine('Allow') === 'GET');
```

| The handler answers | When |
| --- | --- |
| `404` | no path template matches |
| `405`, with an `Allow` header | the path is known but does not answer that method |
| `400`, listing every rejected value at once | a parameter or body could not be turned into its type |
| `401`, with a `WWW-Authenticate` challenge | the operation requires authentication and the request has none |

All four are [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) `application/problem+json` documents, and the last
two are responses the compiler put in the document — so what a client reads is what it gets. Everything an
operation *throws* travels on untouched: turning a domain exception into a status code is an application's
decision, and a middleware around this handler is where it belongs.

Api Class instances are read out of a PSR-11 container per request, so they may be request-scoped — pass your
framework's container, or `FixedContainer`, whose entries are fixed at construction and which is all the wiring
an application without a container needs.

> Query parameters are read from `getQueryParams()` and cookies from `getCookieParams()` — what PSR-7 says a
> *server* request carries, not what its URI happens to spell. Every real PSR-7 server fills them in (as does
> `ServerRequest::fromGlobals()`); a request built by hand, as above, needs `withQueryParams()`.

## The specification, as PHP

Every object in the OpenAPI 3.1 specification has a value object here, so a document is something you build and
type-check rather than an array you hope is right:

```php
use Neos\OpenApi\Spec\InfoObject;
use Neos\OpenApi\Spec\OpenApiObject;
use Neos\OpenApi\Spec\OperationObject;
use Neos\OpenApi\Spec\PathObject;
use Neos\OpenApi\Spec\PathsObject;
use Neos\OpenApi\Spec\ResponseObject;
use Neos\OpenApi\Spec\ResponsesObject;
use Neos\OpenApi\Support\HttpMethod;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\RelativePath;

$document = new OpenApiObject(
    info: new InfoObject(title: 'Blog', version: '1.0.0'),
    paths: PathsObject::create()->with(
        RelativePath::fromString('/posts'),
        PathObject::create()->withOperation(HttpMethod::GET, new OperationObject(
            operationId: 'listPosts',
            responses: ResponsesObject::create()->with(HttpStatusCode::fromInteger(200), new ResponseObject('OK')),
        )),
    ),
);

$expected = '{"openapi":"3.1.1","info":{"title":"Blog","version":"1.0.0"},'
    . '"paths":{"/posts":{"get":{"operationId":"listPosts","responses":{"200":{"description":"OK"}}}}}}';

assert(json_encode($document, JSON_UNESCAPED_SLASHES) === $expected);
```

Absent members are omitted rather than rendered as `null`, and members appear in the order the specification lists
them.

Constraints the specification states in prose are constructor invariants, so an invalid document is hard to build:
a path parameter must be required, a License Object cannot have both `identifier` and `url`, a Security Scheme's
members must match its `type`, and two paths differing only in what they call their variables are rejected because
they would match the same requests.

```php
// ...
use Neos\OpenApi\Spec\ParameterObject;
use Neos\OpenApi\Support\ParameterLocation;

try {
    new ParameterObject(name: 'id', in: ParameterLocation::path);
    assert(false, 'a path parameter cannot be optional');
} catch (\InvalidArgumentException $e) {
    assert(str_contains($e->getMessage(), 'must be required'));
}
```

A schema anywhere in the document is a `Neos\JsonSchema\Schema` — not a replica of one. That is what targeting
3.1 buys: a Schema Object *is* a JSON Schema 2020-12 schema, so it renders straight through.

## Describing PHP types

The document above was built by hand. To *derive* schemas from PHP types, this package talks to one small port —
`TypeBindingProvider` — and `neos/schematic` is what sits behind it:

```php
use Neos\OpenApi\Compilation\SchemaComponents;
use Neos\OpenApi\Schematic\SchematicTypeBindingProvider;
use Neos\OpenApi\Binding\TypeReference;
use Neos\Schematic\Attributes\ReflectionMiddleware;
use Neos\Schematic\Attributes\StringBased;
use Neos\Schematic\Schematic;

#[StringBased(minLength: 1, maxLength: 200)]
final readonly class AuthorName
{
    private function __construct(public string $value) {}
}

final readonly class Author
{
    private function __construct(
        public AuthorName $name,
        public AuthorName|null $pseudonym = null,
    ) {}
}

$provider = new SchematicTypeBindingProvider(Schematic::create(new ReflectionMiddleware()));
$binding = $provider->for(TypeReference::of(Author::class));
```

A binding answers everything this package ever needs to know about a type. Asking it for a schema **hoists** every
named type into `#/components/schemas` and hands back a `$ref` to put at the use site — so a type used by three
operations is one entry pointed at three times, and the document says "these are the same type":

```php
// ...
$components = SchemaComponents::create();
$atUseSite = $binding->jsonSchema($components);

assert(json_encode($atUseSite) === '{"$ref":"#\/components\/schemas\/Author"}');

$schemas = json_encode($components->toSchemaObjectMap(), JSON_UNESCAPED_SLASHES);
// AuthorName became its own component, and Author references it — twice, nullable the second time
assert(str_contains((string) $schemas, '"AuthorName":{"type":"string","minLength":1,"maxLength":200}'));
assert(str_contains((string) $schemas, '"name":{"$ref":"#/components/schemas/AuthorName"}'));
assert(str_contains((string) $schemas, '"pseudonym":{"anyOf":[{"$ref":"#/components/schemas/AuthorName"},{"type":"null"}]}'));
```

Nullability sits at the use site, never inside the component: `AuthorName` is one type whether or not a given
property may omit it.

The *same* binding coerces incoming data into instances and reads instances back out. That is the whole reason it
is one port rather than two — the schema a document advertises and the schema a request is checked against come
from the same object, so they cannot drift apart:

```php
// ...
$author = $binding->coerce(['name' => 'Ada Lovelace'])->value();
assert($author instanceof Author);
assert($author->name->value === 'Ada Lovelace');

assert($binding->serialize($author) === ['name' => 'Ada Lovelace', 'pseudonym' => null]);

$rejected = $binding->coerce(['name' => '']);
assert($rejected->success === false);
assert($rejected->issues?->toArray()[0]->pathAsString() === 'name');
```

Two classes with the same short name would both want the component name `Address`, so that fails loudly rather
than letting whichever was visited first win a name in a public contract.

`neos/schematic` is a **suggested** dependency: `Neos\OpenApi\Schematic\*` is the only namespace that names it,
and an architecture test enforces that.

## Only 3.1

This package emits OpenAPI 3.1.1 and nothing else:

```php
use Neos\OpenApi\Spec\SpecVersion;

assert(SpecVersion::VALUE === '3.1.1');
assert(SpecVersion::JSON_SCHEMA_DIALECT === 'https://json-schema.org/draft/2020-12/schema');
```

3.1.x *is* JSON Schema 2020-12, so a `neos/jsonschema` schema drops into a document unchanged. 3.0.x uses a
divergent dialect that would need a lossy translation layer maintained forever, which would forfeit the main
reason for building on `neos/jsonschema` at all.

## Architecture

The design decisions and their trade-offs:

- target OpenAPI 3.1 only
- one package, with `neos/schematic` behind a port
- a pure spec model plus a separate Dispatch Table
- the spec model renders, but does not parse
- response bodies are serialized by schema, not `json_encode`
- the request body is declared, never inferred

[CONTEXT.md](CONTEXT.md) is the glossary — the vocabulary this codebase holds itself to.

`neos/schematic` is a **suggested** dependency, not a required one: everything in `Neos\OpenApi\*` talks to a
`TypeBinding` port, and `Neos\OpenApi\Schematic\*` is its only implementation. An architecture test enforces that
seam, so extracting a separate `neos/schematic-openapi` package later stays a manifest change rather than a
refactor.

## Contribution

Run the checks with `composer test` (PHPStan at level max, PHP-CS-Fixer, PHPUnit). Every PHP block in this README
is executed as a test, so examples cannot drift away from the code.

[docs/PLAN.md](docs/PLAN.md) records how the package was built, phase by phase — including what was left out on
purpose, and the traps that cost time on the way.

## License

[MIT](LICENSE)
