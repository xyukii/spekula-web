<?php

namespace Twig\Tests\Extension;

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Extension\SandboxExtension;
use Twig\Extension\StringLoaderExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedPropertyError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityPolicy;
use Twig\Source;

class SandboxTest extends TestCase
{
    protected static $params;
    protected static $templates;

    protected function setUp(): void
    {
        self::$params = [
            'name' => 'Fabien',
            'obj' => new FooObject(),
            'arr' => ['obj' => new FooObject()],
            'child_obj' => new ChildClass(),
            'some_array' => [5, 6, 7, new FooObject()],
            'array_like' => new ArrayLikeObject(),
            'magic' => new MagicObject(),
            'recursion' => [4],
        ];
        self::$params['recursion'][] = &self::$params['recursion'];
        self::$params['recursion'][] = new FooObject();

        self::$templates = [
            '1_basic1' => '{{ obj.foo }}',
            '1_basic2' => '{{ name|upper }}',
            '1_basic3' => '{% if name %}foo{% endif %}',
            '1_basic4' => '{{ obj.bar }}',
            '1_basic5' => '{{ obj }}',
            '1_basic7' => '{{ cycle(["foo","bar"], 1) }}',
            '1_basic8' => '{{ obj.getfoobar }}{{ obj.getFooBar }}',
            '1_basic9' => '{{ obj.foobar }}{{ obj.fooBar }}',
            '1_basic' => '{% if obj.foo %}{{ obj.foo|upper }}{% endif %}',
            '1_layout' => '{% block content %}{% endblock %}',
            '1_child' => "{% extends \"1_layout\" %}\n{% block content %}\n{{ \"a\"|json_encode }}\n{% endblock %}",
            '1_include' => '{{ include("1_basic1", sandboxed=true) }}',
            '1_basic2_include_template_from_string_sandboxed' => '{{ include(template_from_string("{{ name|upper }}"), sandboxed=true) }}',
            '1_basic2_include_template_from_string' => '{{ include(template_from_string("{{ name|upper }}")) }}',
            '1_range_operator' => '{{ (1..2)[0] }}',
            '1_syntax_error_wrapper' => '{% sandbox %}{% include "1_syntax_error" %}{% endsandbox %}',
            '1_syntax_error' => '{% syntax error }}',
            '1_childobj_parentmethod' => '{{ child_obj.ParentMethod() }}',
            '1_childobj_childmethod' => '{{ child_obj.ChildMethod() }}',
            '1_array_like' => '{{ array_like["foo"] }}',
        ];
    }

    public function testSandboxWithInheritance()
    {
        $this->expectException(SecurityError::class);
        $this->expectExceptionMessage('Filter "json_encode" is not allowed in "1_child" at line 3.');

        $twig = $this->getEnvironment(true, [], self::$templates, ['block']);
        $twig->load('1_child')->render([]);
    }

    public function testSandboxGloballySet()
    {
        $twig = $this->getEnvironment(false, [], self::$templates);
        $this->assertEquals('FOO', $twig->load('1_basic')->render(self::$params), 'Sandbox does nothing if it is disabled globally');
    }

    public function testSandboxUnallowedPropertyAccessor()
    {
        $twig = $this->getEnvironment(true, [], self::$templates);
        try {
            $twig->load('1_basic1')->render(['obj' => new MagicObject()]);
            $this->fail('Sandbox throws a SecurityError exception if an unallowed method is called');
        } catch (SecurityNotAllowedPropertyError $e) {
            $this->assertEquals('Twig\Tests\Extension\MagicObject', $e->getClassName(), 'Exception should be raised on the "Twig\Tests\Extension\MagicObject" class');
            $this->assertEquals('foo', $e->getPropertyName(), 'Exception should be raised on the "foo" property');
        }
    }

    public function testSandboxUnallowedArrayIndexAccessor()
    {
        $twig = $this->getEnvironment(true, [], self::$templates);

        // ArrayObject and other internal array-like classes are exempted from sandbox restrictions
        $this->assertSame('bar', $twig->load('1_array_like')->render(['array_like' => new \ArrayObject(['foo' => 'bar'])]));

        try {
            $twig->load('1_array_like')->render(self::$params);
            $this->fail('Sandbox throws a SecurityError exception if an unallowed method is called');
        } catch (SecurityNotAllowedPropertyError $e) {
            $this->assertEquals('Twig\Tests\Extension\ArrayLikeObject', $e->getClassName(), 'Exception should be raised on the "Twig\Tests\Extension\ArrayLikeObject" class');
            $this->assertEquals('foo', $e->getPropertyName(), 'Exception should be raised on the "foo" property');
        }
    }

    public function testIfSandBoxIsDisabledAfterSyntaxError()
    {
        $twig = $this->getEnvironment(false, [], self::$templates);
        try {
            $twig->load('1_syntax_error_wrapper')->render(self::$params);
        } catch (SyntaxError $e) {
            /** @var SandboxExtension $sandbox */
            $sandbox = $twig->getExtension(SandboxExtension::class);
            $this->assertFalse($sandbox->isSandboxed());
        }
    }

    public function testSandboxGloballyFalseUnallowedFilterWithIncludeTemplateFromStringSandboxed()
    {
        $twig = $this->getEnvironment(false, [], self::$templates);
        $twig->addExtension(new StringLoaderExtension());
        try {
            $twig->load('1_basic2_include_template_from_string_sandboxed')->render(self::$params);
            $this->fail('Sandbox throws a SecurityError exception if an unallowed filter is called');
        } catch (SecurityNotAllowedFilterError $e) {
            $this->assertEquals('upper', $e->getFilterName(), 'Exception should be raised on the "upper" filter');
        }
    }

    public function testSandboxGloballyTrueUnallowedFilterWithIncludeTemplateFromStringSandboxed()
    {
        $twig = $this->getEnvironment(true, [], self::$templates, [], [], [], [], ['include', 'template_from_string']);
        $twig->addExtension(new StringLoaderExtension());
        try {
            $twig->load('1_basic2_include_template_from_string_sandboxed')->render(self::$params);
            $this->fail('Sandbox throws a SecurityError exception if an unallowed filter is called');
        } catch (SecurityNotAllowedFilterError $e) {
            $this->assertEquals('upper', $e->getFilterName(), 'Exception should be raised on the "upper" filter');
        }
    }

    public function testSandboxGloballyFalseUnallowedFilterWithIncludeTemplateFromStringNotSandboxed()
    {
        $twig = $this->getEnvironment(false, [], self::$templates);
        $twig->addExtension(new StringLoaderExtension());
        $this->assertSame('FABIEN', $twig->load('1_basic2_include_template_from_string')->render(self::$params));
    }

    public function testSandboxGloballyTrueUnallowedFilterWithIncludeTemplateFromStringNotSandboxed()
    {
        $twig = $this->getEnvironment(true, [], self::$templates, [], [], [], [], ['include', 'template_from_string']);
        $twig->addExtension(new StringLoaderExtension());
        try {
            $twig->load('1_basic2_include_template_from_string')->render(self::$params);
            $this->fail('Sandbox throws a SecurityError exception if an unallowed filter is called');
        } catch (SecurityNotAllowedFilterError $e) {
            $this->assertEquals('upper', $e->getFilterName(), 'Exception should be raised on the "upper" filter');
        }
    }

    public function testSandboxUnallowedFilter()
    {
        $twig = $this->getEnvironment(true, [], self::$templates);
        try {
            $twig->load('1_basic2')->render(self::$params);
            $this->fail('Sandbox throws a SecurityError exception if an unallowed filter is called');
        } catch (SecurityNotAllowedFilterError $e) {
            $this->assertEquals('upper', $e->getFilterName(), 'Exception should be raised on the "upper" filter');
        }
    }

    public function testSandboxUnallowedTag()
    {
        $twig = $this->getEnvironment(true, [], self::$templates);
        try {
            $twig->load('1_basic3')->render(self::$params);
            $this->fail('Sandbox throws a SecurityError exception if an unallowed tag is used in the template');
        } catch (SecurityNotAllowedTagError $e) {
            $this->assertEquals('if', $e->getTagName(), 'Exception should be raised on the "if" tag');
        }
    }

    public function testSandboxUnallowedProperty()
    {
        $twig = $this->getEnvironment(true, [], self::$templates);
        try {
            $twig->load('1_basic4')->render(self::$params);
            $this->fail('Sandbox throws a SecurityError exception if an unallowed property is called in the template');
        } catch (SecurityNotAllowedPropertyError $e) {
            $this->assertEquals('Twig\Tests\Extension\FooObject', $e->getClassName(), 'Exception should be raised on the "Twig\Tests\Extension\FooObject" class');
            $this->assertEquals('bar', $e->getPropertyName(), 'Exception should be raised on the "bar" property');
        }
    }

    /**
     * @dataProvider getSandboxUnallowedToStringTests
     */
    public function testSandboxUnallowedToString($template)
    {
        $twig = $this->getEnvironment(true, [], ['index' => $template], [], ['upper', 'join', 'replace'], ['Twig\Tests\Extension\FooObject' => 'getAnotherFooObject'], [], ['random']);
        try {
            $twig->load('index')->render(self::$params);
            $this->fail('Sandbox throws a SecurityError exception if an unallowed method "__toString()" method is called in the template');
        } catch (SecurityNotAllowedMethodError $e) {
            $this->assertEquals('Twig\Tests\Extension\FooObject', $e->getClassName(), 'Exception should be raised on the "Twig\Tests\Extension\FooObject" class');
            $this->assertEquals('__tostring', $e->getMethodName(), 'Exception should be raised on the "__toString" method');
        }
    }

    public function getSandboxUnallowedToStringTests()
    {
        return [
            'simple' => ['{{ obj }}'],
            'object_from_array' => ['{{ arr.obj }}'],
            'object_chain' => ['{{ obj.anotherFooObject }}'],
            'filter' => ['{{ obj|upper }}'],
            'filter_from_array' => ['{{ arr.obj|upper }}'],
            'function' => ['{{ random(obj) }}'],
            'function_from_array' => ['{{ random(arr.obj) }}'],
            'function_and_filter' => ['{{ random(obj|upper) }}'],
            'function_and_filter_from_array' => ['{{ random(arr.obj|upper) }}'],
            'object_chain_and_filter' => ['{{ obj.anotherFooObject|upper }}'],
            'object_chain_and_function' => ['{{ random(obj.anotherFooObject) }}'],
            'concat' => ['{{ obj ~ "" }}'],
            'concat_again' => ['{{ "" ~ obj }}'],
            'object_in_arguments' => ['{{ "__toString"|replace({"__toString": obj}) }}'],
            'object_in_array' => ['{{ [12, "foo", obj]|join(", ") }}'],
            'object_in_array_var' => ['{{ some_array|join(", ") }}'],
            'object_in_array_nested' => ['{{ [12, "foo", [12, "foo", obj]]|join(", ") }}'],
            'object_in_array_var_nested' => ['{{ [12, "foo", some_array]|join(", ") }}'],
            'object_in_array_dynamic_key' => ['{{ {(obj): "foo"}|join(", ") }}'],
            'object_in_array_dynamic_key_nested' => ['{{ {"foo": { (obj): "foo" }}|join(", ") }}'],
            'context' => ['{{ _context|join(", ") }}'],
            'spread_array_operator' => ['{{ [1, 2, ...[5, 6, 7, obj]]|join(",") }}'],
            'spread_array_operator_var' => ['{{ [1, 2, ...some_array]|join(",") }}'],
            'recursion' => ['{{ recursion|join(", ") }}'],
        ];
    }

    /**
     * @dataProvider getSandboxAllowedToStringTests
     */
    public function testSandboxAllowedToString($template, $output)
    {
        $twig = $this->getEnvironment(true, [], ['index' => $template], ['set'], [], ['Twig\Tests\Extension\FooObject' => ['foo', 'getAnotherFooObject']]);
        $this->assertEquals($output, $twig->load('index')->render(self::$params));
    }

    public function getSandboxAllowedToStringTests()
    {
        return [
            'constant_test' => ['{{ obj is constant("PHP_INT_MAX") }}', ''],
            'set_object' => ['{% set a = obj.anotherFooObject %}{{ a.foo }}', 'foo'],
            'is_defined1' => ['{{ obj.anotherFooObject is defined }}', '1'],
            'is_defined2' => ['{{ magic.foo is defined }}', ''],
            'is_null' => ['{{ obj is null }}', ''],
            'is_sameas' => ['{{ obj is same as(obj) }}', '1'],
            'is_sameas_no_brackets' => ['{{ obj is same as obj }}', '1'],
            'is_sameas_from_array' => ['{{ arr.obj is same as(arr.obj) }}', '1'],
            'is_sameas_from_array_no_brackets' => ['{{ arr.obj is same as arr.obj }}', '1'],
            'is_sameas_from_another_method' => ['{{ obj.anotherFooObject is same as(obj.anotherFooObject) }}', ''],
            'is_sameas_from_another_method_no_brackets' => ['{{ obj.anotherFooObject is same as obj.anotherFooObject }}', ''],
        ];
    }

    public function testSandboxAllowMethodToString()
    {
        $twig = $this->getEnvironment(true, [], self::$templates, [], [], ['Twig\Tests\Extension\FooObject' => '__toString']);
        FooObject::reset();
        $this->assertEquals('foo', $twig->load('1_basic5')->render(self::$params), 'Sandbox allow some methods');
        $this->assertEquals(1, FooObject::$called['__toString'], 'Sandbox only calls method once');
    }

    public function testSandboxAllowMethodToStringDisabled()
    {
        $twig = $this->getEnvironment(false, [], self::$templates);
        FooObject::reset();
        $this->assertEquals('foo', $twig->load('1_basic5')->render(self::$params), 'Sandbox allows __toString when sandbox disabled');
        $this->assertEquals(1, FooObject::$called['__toString'], 'Sandbox only calls method once');
    }

    public function testSandboxUnallowedFunction()
    {
        $twig = $this->getEnvironment(true, [], self::$templates);
        try {
            $twig->load('1_basic7')->render(self::$params);
            $this->fail('Sandbox throws a SecurityError exception if an unallowed function is called in the template');
        } catch (SecurityNotAllowedFunctionError $e) {
            $this->assertEquals('cycle', $e->getFunctionName(), 'Exception should be raised on the "cycle" function');
        }
    }

    public function testSandboxUnallowedRangeOperator()
    {
        $twig = $this->getEnvironment(true, [], self::$templates);
        try {
            $twig->load('1_range_operator')->render(self::$params);
            $this->fail('Sandbox throws a SecurityError exception if the unallowed range operator is called');
        } catch (SecurityNotAllowedFunctionError $e) {
            $this->assertEquals('range', $e->getFunctionName(), 'Exception should be raised on the "range" function');
        }
    }

    public function testSandboxAllowMethodFoo()
    {
        $twig = $this->getEnvironment(true, [], self::$templates, [], [], ['Twig\Tests\Extension\FooObject' => 'foo']);
        FooObject::reset();
        $this->assertEquals('foo', $twig->load('1_basic1')->render(self::$params), 'Sandbox allow some methods');
        $this->assertEquals(1, FooObject::$called['foo'], 'Sandbox only calls method once');
    }

    public function testSandboxAllowFilter()
    {
        $twig = $this->getEnvironment(true, [], self::$templates, [], ['upper']);
        $this->assertEquals('FABIEN', $twig->load('1_basic2')->render(self::$params), 'Sandbox allow some filters');
    }

    public function testSandboxAllowTag()
    {
        $twig = $this->getEnvironment(true, [], self::$templates, ['if']);
        $this->assertEquals('foo', $twig->load('1_basic3')->render(self::$params), 'Sandbox allow some tags');
    }

    public function testSandboxAllowProperty()
    {
        $twig = $this->getEnvironment(true, [], self::$templates, [], [], [], ['Twig\Tests\Extension\FooObject' => 'bar']);
        $this->assertEquals('bar', $twig->load('1_basic4')->render(self::$params), 'Sandbox allow some properties');
    }

    public function testSandboxAllowFunction()
    {
        $twig = $this->getEnvironment(true, [], self::$templates, [], [], [], [], ['cycle']);
        $this->assertEquals('bar', $twig->load('1_basic7')->render(self::$params), 'Sandbox allow some functions');
    }

    public function testSandboxAllowRangeOperator()
    {
        $twig = $this->getEnvironment(true, [], self::$templates, [], [], [], [], ['range']);
        $this->assertEquals('1', $twig->load('1_range_operator')->render(self::$params), 'Sandbox allow the range operator');
    }

    public function testSandboxAllowMethodsCaseInsensitive()
    {
        foreach (['getfoobar', 'getFoobar', 'getFooBar'] as $name) {
            $twig = $this->getEnvironment(true, [], self::$templates, [], [], ['Twig\Tests\Extension\FooObject' => $name]);
            FooObject::reset();
            $this->assertEquals('foobarfoobar', $twig->load('1_basic8')->render(self::$params), 'Sandbox allow methods in a case-insensitive way');
            $this->assertEquals(2, FooObject::$called['getFooBar'], 'Sandbox only calls method once');

            $this->assertEquals('foobarfoobar', $twig->load('1_basic9')->render(self::$params), 'Sandbox allow methods via shortcut names (ie. without get/set)');
        }
    }

    public function testSandboxLocallySetForAnInclude()
    {
        self::$templates = [
            '2_basic' => '{{ obj.foo }}{% include "2_included" %}{{ obj.foo }}',
            '2_included' => '{% if obj.foo %}{{ obj.foo|upper }}{% endif %}',
        ];

        $twig = $this->getEnvironment(false, [], self::$templates);
        $this->assertEquals('fooFOOfoo', $twig->load('2_basic')->render(self::$params), 'Sandbox does nothing if disabled globally and sandboxed not used for the include');

        self::$templates = [
            '3_basic' => '{{ obj.foo }}{% sandbox %}{% include "3_included" %}{% endsandbox %}{{ obj.foo }}',
            '3_included' => '{% if obj.foo %}{{ obj.foo|upper }}{% endif %}',
        ];

        $twig = $this->getEnvironment(true, [], self::$templates);
        try {
            $twig->load('3_basic')->render(self::$params);
            $this->fail('Sandbox throws a SecurityError exception when the included file is sandboxed');
        } catch (SecurityNotAllowedTagError $e) {
            $this->assertEquals('sandbox', $e->getTagName());
        }
    }

    public function testMacrosInASandbox()
    {
        $twig = $this->getEnvironment(true, ['autoescape' => 'html'], ['index' => <<<EOF
{%- import _self as macros %}

{%- macro test(text) %}<p>{{ text }}</p>{% endmacro %}

{{- macros.test('username') }}
EOF
        ], ['macro', 'import'], ['escape']);

        $this->assertEquals('<p>username</p>', $twig->load('index')->render([]));
    }

    public function testSandboxDisabledAfterIncludeFunctionError()
    {
        $twig = $this->getEnvironment(false, [], self::$templates);

        $e = null;
        try {
            $twig->load('1_include')->render(self::$params);
        } catch (\Throwable $e) {
        }
        if (null === $e) {
            $this->fail('An exception should be thrown for this test to be valid.');
        }

        $this->assertFalse($twig->getExtension(SandboxExtension::class)->isSandboxed(), 'Sandboxed include() function call should not leave Sandbox enabled when an error occurs.');
    }

    public function testSandboxWithNoClosureFilter()
    {
        $this->expectException('\Twig\Error\RuntimeError');
        $this->expectExceptionMessage('The callable passed to the "filter" filter must be a Closure in sandbox mode in "index" at line 1.');

        $twig = $this->getEnvironment(true, ['autoescape' => 'html'], ['index' => <<<EOF
{{ ["foo", "bar", ""]|filter("trim")|join(", ") }}
EOF
        ], [], ['escape', 'filter', 'join']);

        $twig->load('index')->render([]);
    }

    public function testSandboxWithClosureFilter()
    {
        $twig = $this->getEnvironment(true, ['autoescape' => 'html'], ['index' => <<<EOF
{{ ["foo", "bar", ""]|filter(v => v != "")|join(", ") }}
EOF
        ], [], ['escape', 'filter', 'join']);

        $this->assertSame('foo, bar', $twig->load('index')->render([]));
    }

    public function testMultipleClassMatchesViaInheritanceInAllowedMethods()
    {
        $twig_child_first = $this->getEnvironment(true, [], self::$templates, [], [], [
            'Twig\Tests\Extension\ChildClass' => ['ChildMethod'],
            'Twig\Tests\Extension\ParentClass' => ['ParentMethod'],
        ]);
        $twig_parent_first = $this->getEnvironment(true, [], self::$templates, [], [], [
            'Twig\Tests\Extension\ParentClass' => ['ParentMethod'],
            'Twig\Tests\Extension\ChildClass' => ['ChildMethod'],
        ]);

        try {
            $twig_child_first->load('1_childobj_childmethod')->render(self::$params);
        } catch (SecurityError $e) {
            $this->fail('This test case is malfunctioning as even the child class method which comes first is not being allowed.');
        }

        try {
            $twig_parent_first->load('1_childobj_parentmethod')->render(self::$params);
        } catch (SecurityError $e) {
            $this->fail('This test case is malfunctioning as even the parent class method which comes first is not being allowed.');
        }

        try {
            $twig_parent_first->load('1_childobj_childmethod')->render(self::$params);
        } catch (SecurityError $e) {
            $this->fail('checkMethodAllowed is exiting prematurely after matching a parent class and not seeing a method allowed on a child class later in the list');
        }

        try {
            $twig_child_first->load('1_childobj_parentmethod')->render(self::$params);
        } catch (SecurityError $e) {
            $this->fail('checkMethodAllowed is exiting prematurely after matching a child class and not seeing a method allowed on its parent class later in the list');
        }

        $this->expectNotToPerformAssertions();
    }

    protected function getEnvironment($sandboxed, $options, $templates, $tags = [], $filters = [], $methods = [], $properties = [], $functions = [], $sourcePolicy = null)
    {
        $loader = new ArrayLoader($templates);
        $twig = new Environment($loader, array_merge(['debug' => true, 'cache' => false, 'autoescape' => false], $options));
        $policy = new SecurityPolicy($tags, $filters, $methods, $properties, $functions);
        $twig->addExtension(new SandboxExtension($policy, $sandboxed, $sourcePolicy));

        return $twig;
    }

    public function testSandboxSourcePolicyEnableReturningFalse()
    {
        $twig = $this->getEnvironment(false, [], self::$templates, [], [], [], [], [], new class() implements \Twig\Sandbox\SourcePolicyInterface {
            public function enableSandbox(Source $source): bool
            {
                return '1_basic' != $source->getName();
            }
        });
        $this->assertEquals('FOO', $twig->load('1_basic')->render(self::$params));
    }

    public function testSandboxSourcePolicyEnableReturningTrue()
    {
        $twig = $this->getEnvironment(false, [], self::$templates, [], [], [], [], [], new class() implements \Twig\Sandbox\SourcePolicyInterface {
            public function enableSandbox(Source $source): bool
            {
                return '1_basic' === $source->getName();
            }
        });
        $this->expectException(SecurityError::class);
        $twig->load('1_basic')->render([]);
    }

    public function testSandboxSourcePolicyFalseDoesntOverrideOtherEnables()
    {
        $twig = $this->getEnvironment(true, [], self::$templates, [], [], [], [], [], new class() implements \Twig\Sandbox\SourcePolicyInterface {
            public function enableSandbox(Source $source): bool
            {
                return false;
            }
        });
        $this->expectException(SecurityError::class);
        $twig->load('1_basic')->render([]);
    }
}

class ParentClass
{
    public function ParentMethod()
    {
    }
}
class ChildClass extends ParentClass
{
    public function ChildMethod()
    {
    }
}

class FooObject
{
    public static $called = ['__toString' => 0, 'foo' => 0, 'getFooBar' => 0];

    public $bar = 'bar';

    public static function reset()
    {
        self::$called = ['__toString' => 0, 'foo' => 0, 'getFooBar' => 0];
    }

    public function __toString()
    {
        ++self::$called['__toString'];

        return 'foo';
    }

    public function foo()
    {
        ++self::$called['foo'];

        return 'foo';
    }

    public function getFooBar()
    {
        ++self::$called['getFooBar'];

        return 'foobar';
    }

    public function getAnotherFooObject()
    {
        return new self();
    }
}

class ArrayLikeObject extends \ArrayObject
{
    public function offsetExists($offset): bool
    {
        throw new \BadMethodCallException('Should not be called.');
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        throw new \BadMethodCallException('Should not be called.');
    }

    public function offsetSet($offset, $value): void
    {
    }

    public function offsetUnset($offset): void
    {
    }
}

class MagicObject
{
    #[\ReturnTypeWillChange]
    public function __get($name)
    {
        throw new \BadMethodCallException('Should not be called.');
    }

    public function __isset($name): bool
    {
        throw new \BadMethodCallException('Should not be called.');
    }
}
