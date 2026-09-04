<?php

namespace Pastell\Service\SimpleTwigRenderer;

use Flow\JSONPath\JSONPath;
use SimpleXMLElement;
use Twig\Sandbox\SecurityPolicy;
use Twig\Sandbox\SecurityPolicyInterface;

/**
 * Standard Twig security policy, relaxed for the objects returned by the xpath() and jsonpath() functions: templates
 * navigate them with `node.child`, and reading a child element or a key has no side effect. The Twig policy can only
 * allow properties by name, hence this decorator.
 *
 * Twig also routes the `object.key` and `object['key']` accesses of an ArrayAccess object it does not know through
 * checkPropertyAllowed(), which is why the JSONPath instances (returned for any JSON object or array) are listed here.
 *
 * Their methods stay forbidden (SimpleXMLElement::asXML() writes a file), except the string conversion done by
 * `{{ node }}` or `|join`, which is declared in the allowed methods of the decorated policy.
 */
final class PastellSecurityPolicy implements SecurityPolicyInterface
{
    public function __construct(private readonly SecurityPolicy $securityPolicy)
    {
    }

    public function checkSecurity($tags, $filters, $functions, array $tests = []): void
    {
        $this->securityPolicy->checkSecurity($tags, $filters, $functions, $tests);
    }

    public function checkMethodAllowed($obj, $method): void
    {
        $this->securityPolicy->checkMethodAllowed($obj, $method);
    }

    public function checkPropertyAllowed($obj, $property): void
    {
        if ($obj instanceof SimpleXMLElement || $obj instanceof JSONPath) {
            return;
        }
        $this->securityPolicy->checkPropertyAllowed($obj, $property);
    }
}
