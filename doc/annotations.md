Annotations in Ajap
-------------------

An annotation is a piece of metadata that is attached to a specific language construct. Since PHP doesn't have a specific syntax for them, annotations reside within doc comments just before the target they annotate.

In Ajap, annotations are used to manage how PHP classes are transformed into JavaScript services, how and where to enable RPC and much more. They can be applied to classes, methods and properties.

# Format of an annotation

The general format of annotations is as follows: `@[A-Z][a-zA-Z0-9]* .*`.

Here are some examples:

```php
/**
 * @Ajap
 * @JS path/to/my/file.js
 * @JS "path/to/my/file2.js"
 * @JS [ "!my/file3.js", "!my/file4.js" ]
 */
```

Thus we have the character `@`, followed by the name of the annotation (always capitalized) then the eventual value expression associated with the annotation. Please note that **the name is case sensitive** and can only contain letters and digits.

If the value expression is proper JSON then it will be parsed and the result will be the value associated to, *of*, the annotation. If it is not proper JSON, the value of the annotation is a string containing the value expression itself.

If we refer to the examples above, the values of the three `@JS` annotations will be, respectively:
```php
- "path/to/my/file.js"
- "path/to/my/files2.js"
- array( "!my/file3.js", "!my/file4.js" )
```

Please note that annotations must be in a *doc comment* (a multiline comment with a double star at the beginning). Annotations in any other type of comment will be ignored. The doc comment has to be just before the target to be annotated or else the wrong target may be annotated. For instance, of all 3 declarations below, only the first correctly annotates the class:

```php
/**
 * @Ajap
 */
class Service1 {}

/*
 * @Ajap
 */
class Service2 {}

// @Ajap
class Service3 {}
```

# Types of Annotations

Each annotation is of a certain *type*. This type determines the acceptable format of the value associated to the annotation, if any.

## Flag

The annotation can only be used once per target. No value expression is to be provided.

Consider each `flag` annotation as a boolean that would be `false` by default, `true` if the annotation is present.

In the following example, only the first class is an Ajap service because the `@Ajap` annotation is missing from the second one (so the `@Ajap` boolean is `false` for this particular class):

```php
/**
 * @Ajap
 */
class Service1 {}

/**
 * 
 */
class Service2 {}
```

## Object

The annotation can only be used once per target. The value expression, if given, should be a properly JSON-formatted object: don't forget the double quotes around field names! If no value expression is given, the empty object, `{}`, is assumed.

`Object` annotations can have any number of default field values. You must explicitely provide them into the value expression if you want to override this default value.

For instance, `@Template` has a `normalizeSpace` field that defaults to `true`, so, if we take the following example:

```php
/**
 * @Ajap
 */
class Service {
	/**
	 * @Template
	 */
	public function template() {
		// ...
	}

	/**
	 * @Template {}
	 */
	public function templateEmptyObject() {
		// ...
	}

	/**
	 * @Template { "normalizeSpace": true }
	 */
	public function templateExplicit() {
		// ...
	}

	/**
	 * @Template { "normalizeSpace": false }
	 */
	public function templateNotNormalized() {
		// ...
	}
}
```

`normalizeSpace` is:
* implicitely `true` for `template` and `templateEmptyObject`,
* explicitely `true` for `templateExplicit`,
* explicitely `false` for `templateNotNormalized`.

## Services

The annotation can be used multiple times per target. The value expression must be a properly formed service name or an array of service names.

The final value of the annotation is an array containing all the service names in the order they were provided.

So the following declaration:
```php
/**
 * @Ajap
 * @DependsOn My.First.Service
 * @DependsOn "My.Other.Service"
 * @DependsOn [ "Another.Service", "YetAnotherService" ]
 */
class Service {}
```

will annotate `Service` with a `@DependsOn` annotation which final value is:
```php
array(
	"My.First.Service",
 	"My.Other.Service",
	"Another.Service",
	"YetAnotherService",
);
```

## URL

The annotation can only be used once per target. The value expression is a single URL, provided as a properly JSON-formatted string or not.

If the annotation supports it, the URL can point to a local file. In that case, the first letter of the URL must be an exclamation point (`!`).

The expression can point to a method of the class currently inspected which will then be called to provide the actual URL. This is particularly useful to provide a URL that depends on some configuration that cannot be known at compile time.

In the following example, the final URL will be what is stored in `$OPTIONS[ "jsonpService" ]`

```php
/**
 * @Ajap
 */
class Service {
	/**
	 * RemoteJSONP method:getURL 
	 */
	public function request() {
		// ...
	}
	
	public function getURL() {
		global $OPTIONS;
		return $OPTIONS[ "jsonpService" ];
	}
}
```

Please not that methods pointed to that way are not present in the final JavaScript stub so we have the following behaviour client-side:

```js
console.log( Service.getURL );
// => undefined
```

## URLs

The annotation can be used multiple times per target. The value expression can be a single URL, provided as a properly JSON-formatted string or not, as described in the preceeding section, or an array of URLs.

The final value of the annotation is an array containing all the URLs in the order they were provided.

So the following declaration:
```php
/**
 * @Ajap
 * @JS path/to/my/file.js
 * @JS "path/to/my/file2.js"
 * @JS [ "!my/file3.js", "!my/file4.js" ]
 */
class Service {}
```

will annotate `Service` with a `@JS` annotation which final value is:
```php
array(
	"path/to/my/file.js",
	"path/to/my/file2.js",
	"!my/file3.js",
	"!my/file4.js",
);
```

Since this type of annotations is generally used to list resources, the `method:methodName` format presented in the previous section comes with a twist. If the method pointed to is annotated with an annotation of the same name, then the returned string is considered as actual content, not a URL.

Here is an example:

```php
/**
 * @Ajap
 * @JS method:getURL
 * @JS method:getCode
 */
class Service {

	function getURL() {
		return "path/to/a/javascript/file";
	}
	
	/**
	 * @JS 
	 */
	function getCode() {
		return '
		console.log( "this is actual code, not a URL" );
		';
	}
}
```

This can be useful to create content that depend on some configuration outside of the services.

Note that this disrupts the normal behaviour, if any, of the annotation on the method. So it is to be used when absolutely necessary.

The method will still not be generated in JavaScript. So the previous example yields the following behaviour:

```js
console.log( Service.getURL );
// => undefined

console.log( Service.getCode );
// => undefined
```

# List of Annotations

Annotations are listed by target of the annotation since some annotations have different semantics and types depending on said target.

## Class

There is an exact correspondance between classes and services in AJAP. So we will use the terms `class` and `service` interchangeably in this section.

### Abstract (Flag)

Flags the service as "abstract". While it can still be used as a base class which another service can extend, no JavaScript stub will be generated and it is impossible to remotely call any of its methods.

This flag is extremely useful if the underlying PHP class is abstract itself since Ajap will never instantiate an `@Abstract` class.

Another use-case is when the service has value as-is but implements logic client-side and/or server-side that is the base of several other services.

So, the following PHP classes:

```php
/**
 * @Ajap
 * @Abstract
 */
class Log {

	// The message to be logged
	public $message;
	
	// Constructor
	public function __construct( $message ) {
		$this->message = $message;
	}
	
	/*
	 * Logs the message
	 * @JS
	 */
	public function log() {
		return '
		console.log( this.$message );
		';
	}
}

/**
 * @Ajap
 */
class LogHello extends Log {
	
	public __construct() {
		parent::__construct( "hello" );
	}
}
```

Will yield the following behaviour in JavaScript:

```js
console.log( window.Log );
// => undefined

console.log( window.LogHello );
// => [object Object]

LogHello.log();
// => "hello"
```

### Ajap (Flag)

This is a mandatory flag for any service. If it's missing, Ajap will not recognize the class as a service, will not generate a JavaScript stub for it and will not inspect its inheritance chain and dependencies.

The flag acts as a security protection and makes it possible for services to inherit from non-service classes.

### Alias (Services)

Creates an alias for the service client-side.

So, the following PHP class:

```php
/**
 * @Ajap
 * @Alias AnotherName
 */
class OriginalName {
}
```

Will yield the following behaviour in JavaScript:

```js
console.log( window.OriginalName === window.AnotherName );
// => true
```

### CSS (URLs)

Lists CSS resources to be loaded before the service is initiated. For local URLs, the actual content of the targeted file is embedded within the service itself: beware of relative URLs within the CSS file!

The following declaration:

```php
/**
 * @Ajap
 * @CSS path/to/my/file.css
 * @CSS "path/to/my/file2.css"
 * @CSS [ "!my/file3.css", "!my/file4.css" ]
 */
class Service {}
```

generates a JavaScript stub that will create (in that order):
* a `<link/>` node linking to `path/to/my/file.css`,
* a `<link/>` node linking to `path/to/my/file2.css`,
* a `<style/>` node which text content is the content of `my/file3.css`,
* a `<style/>` node which text content is the content of `my/file4.css`.

### DependsOn (Services)

Lists services the current service depends on.

So, the following declaration:

```php
/**
 * @Ajap
 * @DependsOn My.Other.Service
 * @DependsOn Another.Service
 */
class Service {}
```

ensures that, everytime Ajap is asked for `Service`, `My.Other.Service` and `Another.Service` are served first.

### JS (URLs)

Lists JS resources to be executed before the service is initiated. For local URLs, the actual content of the targeted file is embedded within the service itself: beware of global var declarations that wouldn't be applied properly!

The following declaration:

```php
/**
 * @Ajap
 * @JS path/to/my/file.js
 * @JS "path/to/my/file2.js"
 * @JS [ "!my/file3.js", "!my/file4.js" ]
 */
class Service {}
```

generates a JavaScript stub that:
* will load and execute `path/to/my/file.js`,
* **then** will load and execute `path/to/my/file2.js`,
* **then** will execute the code embedded from `my/file3.js`,
* **then** will execute the code embedded from `my/file4.js`,
* **then and only then** will initiate the service itself.

### Volatile (Flag)

Tells Ajap not to generate the global variable corresponding to the service.

So, the following PHP class:

```php
/**
 * @Ajap
 * @Volatile
 */
class Service {
}
```

Will yield the following behaviour in JavaScript:

```js
console.log( window.Volatile );
// => undefined
```

## Method

### Cached (Flag)

Tells Ajap to cache the result of remote executions of the method for a given set of arguments.

For instance, the following declaration:

```php
/**
 * @Ajap
 */
class Service {
	/**
	 * @Cached 
	 */
	public function cached( $arg1, $arg2 ) {
		return rand();
	}
}
```

Will yield the following behaviour in JavaScript:

```js
$.when( Service.cached( "param1" ), Service.cached( "param1" ) ).done(function( v1, v2 ) {
	console.log( v1 === v2 );
	// => true
});

$.when( Service.cached( "param2" ), Service.cached( "param2" ) ).done(function( v1, v2 ) {
	console.log( v1 === v2 );
	// => true
});
```

### CrossDomain (Flag)

*Alias of XDomain*

Flags the method as suitable for cross-domain access. Web pages from another domain will be able to request this method using JSONP.

### CSS (Flag)

When the method is pointed to by a `@CSS` annotation on the declaring class, tells Ajap to consider the returned string as actual CSS code, not a URL.

### Dynamic (Flag)

*only useful for methods that generate code or URLs*

Tells Ajap to re-generate the method everytime the service is requested.

### Init (Flag)

Marks a method as generating initialization code. The method will not be generated in the JavaScript stub.

For instance, the following declaration:

```php
/**
 * @Ajap
 */
class Service {
	/**
	 * @Init 
	 */
	public function init() {
		this.testInit = true;
	}
}
```

Will yield the following behaviour in JavaScript:

```js
console.log( Service.init );
// => undefined

console.log( Service.testInit );
// => true
```

### JS (Flag)

When the method is pointed to by a `@JS` annotation on the declaring class, tells Ajap to consider the returned string as actual JavaScript code, not a URL.

### Local (Flag)

Marks the method as "local" which will make it behave as if it was private: it will not be defined in the JavaScript stub and will not be executable remotely.

### NonBlocking (Flag)

Sessions in PHP are blocking: a client cannot execute more than one script concurrently because of that. In Ajap terms, it means no more than one method can be executed remotely at a given time. For optimization reasons or when data consistency is not an issue, use `@NonBlocking` so that the session is closed before executing the method thus not blocking the execution of another method in parallel.

### Post (Flag)

Generates a remotely executable method that accepts a `<form/>` element client-side and an array representing the form's serialization server-side.

For instance, the following service:

```php
/**
 * @Ajap
 */
class Service {

	/**
	 * @Post 
	 */
	public function post( $data ) {
		return $data[ "a" ] + $data[ "b" ];
	}
}
```

and the following HTML:

```html
<form id="my-form">
	<input name="a" value="5">
	<input name="b" value="6">
</form>
```

will yield the following behaviour in JavaScript:

```js
Service.post( document.getElementById( "my-form" ) ).done(function( result ) {
	console.log( result );
	// => 11
});
```

### RemoteJSONP (URL)

### Template (Object)

Marks a method as generating text data based on a template.

The template format is akin to PHP and can interchangeably use <? ?>, <# #> or <@ @> opening and closing tags.

For instance, the following declaration:

```php
/**
 * @Ajap
 */
class Service {

	public $messages = array(
		"Hello",
		"Have a good day",
	);

	/**
	 * @Template
	 */
	public function template( $name ) {
		return '
		<# this.$messages.forEach(function( message ) { #>
			<#= message #>, <#= $name #>.
		<# }) #>
		';
	}
}
```

will yield the following behaviour in JavaScript:

```js
console.log( Service.template( "John" ) );
// => "Hello, John. Have a good day, John."
```

By default, whitespaces are trimmed and normalized. You can preserve whitespacing by setting the `normalizeSpace` field to `false`. So, the following service:

```php
/**
 * @Ajap
 */
class Service {

	/**
	 * @Template
	 */
	public function templateNormalized() {
		return ' hello  world!  ';
	}

	/**
	 * @Template { "normalizeSpace": false }
	 */
	public function templateNotNormalized() {
		return ' hello  world!  ';
	}
}
```

will yield the following behaviour in JavaScript:

```js
console.log( Service.templateNormalized() );
// => "hello world!"

console.log( Service.templateNotNormalized() );
// => " hello  world!  "
```


### XDomain (Flag)

*Alias of CrossDomain*

Flags the method as suitable for cross-domain access. Web pages from another domain will be able to request this method using the JSONP protocol.

## Property

### Dynamic (Flag)

Tells Ajap to re-generate the property everytime the service is requested.

### Implicit (Flag)

### Local (Flag)

Marks the property as "local" which will make it behave as if it was private: it will not be defined in the JavaScript stub.
