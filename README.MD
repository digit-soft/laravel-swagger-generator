# Swagger generator
This package is made to automate API documentation for Swagger (Open Auth 3.0)

## Publish config
```bash
php artisan vendor:publish --provider="DigitSoft\Swagger\SwaggerGeneratorServiceProvider" --tag="config"
```

## Usage
To generate doc file (YML) run following command:
```bash
php artisan swagger:generate
```
To see problems with generation use `diagnose` mode, where file will not be generated, only helpful information will be printed.
```bash
php artisan swagger:generate --diagnose
```

## Describing your code
### Annotations list

| Name                  | Description                           | Places to use |
|-----------------------|---------------------------------------|---------------|
| @OA\Response          | Describes raw response                | Controller method |
| @OA\ResponseClass     | Describes response as class object    | Controller method |
| @OA\ResponseError     | Describes error response (shortcut)   | Controller method |
| @OA\RequestBody       | Describes request body                | `FormRequest` class |
| @OA\RequestBodyJson   | Describes request body with `application\json` content type | `FormRequest` class |
| @OA\RequestParam      | Describes request body parameter      | Used as argument in `@OA\RequestBody` annotation |
| @OA\RequestParamArray | Describes request body parameter. Shortcut for array type parameter | Used as argument in `@OA\RequestBody` annotation |
| @OA\Parameter         | Describes route parameter             | Controller method, Controller class |
| @OA\Property          | Describes class property              | Class used for response |
| @OA\PropertyIgnore    | Mark class property as ignored        | Class used for response |
| @OA\Secured           | Describes route as secured            | Controller method |
| @OA\Tag               | Describes route tags                  | Controller method, Controller class |
| @OA\Symlink           | Describes symlink to another class    | Class used for response |

### Responses
Responses are parsed only if explicitly documented by `@Annotation`. It must be placed in PHPDoc of **controller method** that route use.
RAW response:
```php
/**
 * Controller method PHPDoc
 *
 * @OA\Response(true,contentType="application/json",description="Boolean response")
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
```
JSON RAW response:
```php
/**
 * Controller method PHPDoc
 *
 * @OA\ResponseJson({"key":"value"},contentType="application/json",status=201,description="User data response")
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
```
Response from class properties:
```php
/**
 * Controller method PHPDoc
 *
 * @OA\ResponseClass("App\User",description="User model response")
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
```
In example above response data will be parsed from `App\User` PHPDoc.
1. `@property` descriptions (property name, type and description)
2. `@property-read` descriptions (if set `with` property in `ResponseClass` annotation)
3. `@OA\Property` annotations (property name, type, description, example etc.)

`@OA\ResponseClass` use cases, 
first is standard use but with additional properties
```php
/**
 * @OA\ResponseClass("App\User",with={"profile"},status=201)
 */
```
As items list
```php
/**
 * @OA\ResponseClass("App\User",asList=true)
 */
```
As paged items list
```php
/**
 * @OA\ResponseClass("App\User",asPagedList=true)
 */
```

Error responses
```php
/**
 * @OA\ResponseError(403) // Forbidden
 * @OA\ResponseError(404) // Not found
 * @OA\ResponseError(422) // Validation error
 */
```
### Request bodies
Request data is parsed from `::rules()` method of `FormRequest` class, that used in controller method for the route and it's annotations (`@OA\RequestBody`, `@OA\RequestBodyJson`,  `@OA\RequestParam`).
From `::rules()` method we can obtain only name and type of parameter and suggest some example, 
but if you want fully describe parameters of request body you must place appropriate annotations in `FormRequest` class for route.
#### Examples
```php
/**
 * @OA\RequestBodyJson({
 *   @OA\RequestParam("first_name",type="string",description="User name"),
 *   @OA\RequestParam("email",type="string",description="User email"),
 *   @OA\RequestParamArray("phones",items="string",description="User phones array"),
 * })
 */
```
### Tags
Tags can be defined in Controller class or method that route uses.
Do not use space ` ` in tag names, link with such tag name will be broken in Swagger UI, so better idea to use dash `-` or underscore `_`, or even just a `CamelCased` tag names.
Tags defined in controller will be applied to ALL controller methods.
```php
/**
 * @OA\Tag("Tag-name")
 */
```
### Secured
This annotation is used to mark route as `secured`, and tells to swagger, that you must provide valid user credentials to access this route.
Place it in controller method.
```php
/**
 * @OA\Secured()
 */
```
### Property
`@OA\Property` annotation is used to describe class properties as an alternative or addition to PHPDoc `@property`.
You can place example of property (if property is an associative array for example) 
or fully describe property if you dont want to place `@property` declaration for it.
```php
/**
 * @OA\Property("notification_settings",type="object",example={"marketing":false,"user_actions":true},description="User notification settings")
 */
```
### PropertyIgnore
`@OA\PropertyIgnore` annotation is used to remove given property from object description.
```php
/**
 * @OA\PropertyIgnore("property_name")
 */
```
### Symlink
This annotation can be used to describe symlink to another class (e.g. for response). All data in class PHPDoc in which it appears will be ignored.

You must use full namespace of annotations, e.g. `OA\Property`.

Besides, you can import a namespace for better code completion as in example beyond.
```php
namespace App\Models;

use OA;

/**
 * Test model class
 *
 * @OA\Property("id",type="integer",description="Primary key")
 *
 * @property string $name String name
 */
class TestModel {}
```
