# Laravel Given-When-Then plugin

> A Behavior Driven Testing (GWT) plugin for your Laravel Tests

### Install
```shell
composer require wolfpack-it/laravel-gwt-plugin --dev
```

### Usage / Example
Basically you extend your Test with the TestCase provided by this package,
which allows you to use the package methods. See an example below:

```php
use App\Mails\ConfirmationMail;
use App\Models\Language;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use WolfpackIT\LaravelGWTPlugin\TestCase;

// Extend the Laravel GWT Plugin TestCase
class StorePostTest extends TestCase
{
    public function test_user_can_store_post(): void
    {
        $this
            // Act as a random User
            ->as(User::factory()->create())
            // Fake sending the email
            ->fake(Mail::class)
            // Given is the post data, stored in a param called `post`
            ->given(fn (): array => [
                'title' => 'My first post',
                'content' => 'Lorem ipsum dolor amet sum it',
            ], 'post')
            // Given is the current language
            ->given(fn(): Language => Language::current())
            // The $post and $language are automatically injected based on the results of the `given` methods
            ->when(fn (array $post, Language $language): TestResponse =>
                $this->postJson(route('api.posts.store'), $post)
            )
            // Instead of using a closure you can call a protected method as a callable with `(...)`
            ->then($this->responseContainsPostId(...))
            // Add as much `then` methods as needed
            ->then($this->confirmationMailIsSent(...));
    }

    // The $response is automatically injected based on the TestResponse result of the `when` method
    protected function responseContainsPostId(TestResponse $response): void
    {
        $response
            ->assertCreated() // Status code 201
            ->assertJson(fn (AssertableJson $json) =>
                $json->has('id')
                // .. add other assertions  
            );
    }

    protected function confirmationMailIsSent(Authenticatable $user): void
    {
        Mail::assertSent(ConfirmationMail::class, function (Mailable $mail) use ($user) {
            return $mail->hasTo($user->email);
            // .. add other assertions  
        });
    }
}
```

### Methods

#### The `as` method
While testing, the Sanctum::actingAs or Passport::actingAs method may be used to authenticate a user.
To use this Authenticated user in the Given-When-Then methods you can use the 
`as(Authenticatable $user, ?string $injectAs = 'user', string $authProvider = self::DEFAULT_AUTH_PROVIDER)` method.
This Authenticatable is then added to the auto injection for the other methods.
You can also overwrite the Authentication Provider by giving the ClassName (string) as the second parameter.

```php
$this->as(User::factory()->create());
```

#### The `fake` method
While testing, you might need to mock some services like Mails or Events.
In order to do this within the plugins syntax you can use the `fake(string $facade)` method.
Any facade which contains the `fake` method can be used.

```php
$this->fake(Mail::class);
```

#### The `throws` method
The throws method indicates that you're expecting a when action to throw an exception.
The methods expect an exception class name (string) and optionally a exception message.

#### The `given` method
Given describes the preconditions and initial state before the start of a test and allows for any pre-test setup that may occur.
The method expects a Closure / Callable as first parameter and uses the outcome (Return Type) to populate the pre-conditions in the scenarios parameters.
The callable can use conditions from previous given methods in a chain via auto injection.
The second argument is to help define as which key the param can be retrieved by the auto-injection.
When using two of the same types, this `as` params can be very useful. Example

```php
    $this
        ->given(fn(): User => User::factory()->student()->create(), 'student')
        ->given(fn(): User => User::factory()->teacher()->create(), 'teacher');
```

#### The `when` method
When describes actions taken during a test. This method expects the same arguments as the given method.
Where the second param has a default value of `'response'`. The callable can use all conditions from the previous given methods as needed.

```php
$this->when(fn(array $postData): TestResponse => $this->postJson('/posts', $postData));
```

#### The `then` method
Then describes the outcome resulting from actions taken in the when clause.
This method only expects a Closure / Callable which can make the assertions necessary.
Via auto-injection the method can use both the result of the previous when methods and the previous conditions of the given methods.
This might be useful when trying to compare given data to the given response.

```php
$this->then(fn(TestResponse $response) => $response->assertOk());
```

### Good to know
> Make sure your closures / callables implement return types,
> so that the auto-injection is able to identify which condition or response to map to which argument.


This package was created by Pascal van Gemert @ **[WolfpackIT](https://wolfpackit.com)**.
It got open-sourced and is now licensed under the **[MIT license](https://opensource.org/licenses/MIT)**.
